<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MeterReading;
use App\Models\WaterAccount;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MeterReadingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $readings = MeterReading::query()
            ->with([
                'waterAccount:id,account_no,meter_no,tariff_class,payer_id',
                'waterAccount.payer:id,tin,full_name',
                'creator:id,name',
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->whereHas('waterAccount', function ($account) use ($term) {
                    $account->where('account_no', 'ilike', $term)
                        ->orWhere('meter_no', 'ilike', $term)
                        ->orWhereHas('payer', function ($payer) use ($term) {
                            $payer->where('tin', 'ilike', $term)->orWhere('full_name', 'ilike', $term);
                        });
                });
            })
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('water_account_id'), fn ($q) => $q->where('water_account_id', $request->integer('water_account_id')))
            ->latest('reading_date')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($readings);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'water_account_id' => ['required', 'exists:water_accounts,id'],
            'reading_date' => ['required', 'date'],
            'reading_value' => ['required', 'numeric', 'min:0'],
            'is_rollover' => ['sometimes', 'boolean'],
            'is_meter_replacement' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $reading = $this->capture($data, $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Meter reading captured.',
            'reading' => $reading->load(['waterAccount.payer:id,tin,full_name', 'creator:id,name']),
        ], 201);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);
        $required = ['meter_no', 'reading_date', 'reading_value'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);

                return response()->json([
                    'message' => 'CSV must include columns: '.implode(', ', $required),
                ], 422);
            }
        }

        $accepted = [];
        $rejected = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($header as $index => $column) {
                $data[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
            }

            $validator = Validator::make($data, [
                'meter_no' => ['required', 'string'],
                'reading_date' => ['required', 'date'],
                'reading_value' => ['required', 'numeric', 'min:0'],
                'is_rollover' => ['nullable'],
                'is_meter_replacement' => ['nullable'],
                'notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $rejected[] = ['row' => $rowNumber, 'reason' => $validator->errors()->first(), 'data' => $data];
                continue;
            }

            $account = WaterAccount::query()->where('meter_no', strtoupper($data['meter_no']))->first();
            if (! $account) {
                $rejected[] = ['row' => $rowNumber, 'reason' => 'Meter number not found.', 'data' => $data];
                continue;
            }

            try {
                $reading = $this->capture([
                    'water_account_id' => $account->id,
                    'reading_date' => $data['reading_date'],
                    'reading_value' => $data['reading_value'],
                    'is_rollover' => filter_var($data['is_rollover'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_meter_replacement' => filter_var($data['is_meter_replacement'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'notes' => $data['notes'] ?? null,
                ], $request->user()?->id);

                $accepted[] = [
                    'row' => $rowNumber,
                    'reading_id' => $reading->id,
                    'meter_no' => $account->meter_no,
                    'consumption' => $reading->consumption,
                ];
            } catch (\Throwable $e) {
                $rejected[] = ['row' => $rowNumber, 'reason' => $e->getMessage(), 'data' => $data];
            }
        }

        fclose($handle);

        return response()->json([
            'message' => 'Meter reading upload processed.',
            'summary' => [
                'total_rows' => count($accepted) + count($rejected),
                'accepted' => count($accepted),
                'rejected' => count($rejected),
            ],
            'accepted' => $accepted,
            'rejected' => $rejected,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function capture(array $data, ?int $userId): MeterReading
    {
        return DB::transaction(function () use ($data, $userId) {
            $account = WaterAccount::query()->findOrFail($data['water_account_id']);
            if ($account->status !== 'ACTIVE') {
                throw new \InvalidArgumentException('Water account is not active.');
            }

            $previous = MeterReading::query()
                ->where('water_account_id', $account->id)
                ->where('status', 'ACCEPTED')
                ->orderByDesc('reading_date')
                ->orderByDesc('id')
                ->first();

            $readingValue = (float) $data['reading_value'];
            $previousValue = $previous ? (float) $previous->reading_value : 0.0;
            $isRollover = (bool) ($data['is_rollover'] ?? false);
            $isReplacement = (bool) ($data['is_meter_replacement'] ?? false);

            if ($previous && $readingValue < $previousValue && ! $isRollover && ! $isReplacement) {
                throw new \InvalidArgumentException(
                    'Reading is lower than previous reading. Flag as rollover or meter replacement to accept.'
                );
            }

            if ($isRollover || $isReplacement) {
                $consumption = max(0, $readingValue);
            } else {
                $consumption = max(0, $readingValue - $previousValue);
            }

            $readingDate = Carbon::parse($data['reading_date']);

            $reading = MeterReading::query()->create([
                'water_account_id' => $account->id,
                'reading_date' => $readingDate->toDateString(),
                'reading_value' => $readingValue,
                'previous_reading' => $previousValue,
                'consumption' => $consumption,
                'is_rollover' => $isRollover,
                'is_meter_replacement' => $isReplacement,
                'status' => 'ACCEPTED',
                'period' => $readingDate->format('Y-m'),
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            AuditLog::record('MeterReading', $reading->id, 'CREATED', null, $reading->toArray(), $userId);

            return $reading;
        });
    }
}
