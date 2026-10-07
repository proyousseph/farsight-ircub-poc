<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelPayment;
use App\Models\SupervisorNotification;
use App\Services\ChannelPaymentService;
use App\Services\MockFxRateClient;
use App\Support\OwnsPayerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChannelPaymentController extends Controller
{
    public function __construct(private ChannelPaymentService $channels)
    {
    }

    public function rates(Request $request, MockFxRateClient $fx): JsonResponse
    {
        $data = $request->validate([
            'quote' => ['nullable', 'string', 'max:10'],
            'base' => ['nullable', 'string', 'max:10'],
        ]);

        $rate = $fx->fetch(
            strtoupper($data['quote'] ?? config('channels.local_currency', 'SOS')),
            strtoupper($data['base'] ?? 'USD')
        );

        return response()->json(['rate' => $rate]);
    }

    public function index(Request $request): JsonResponse
    {
        $items = ChannelPayment::query()
            ->with(['payer:id,tin,full_name', 'payment:id,external_ref,status', 'creator:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($builder) use ($term) {
                    $builder->where('external_ref', 'ilike', $term)
                        ->orWhere('provider_txn_id', 'ilike', $term)
                        ->orWhereHas('payer', function ($payer) use ($term) {
                            $payer->where('tin', 'ilike', $term)->orWhere('full_name', 'ilike', $term);
                        });
                });
            })
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(\App\Support\Pagination::perPage($request));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'payer_id' => ['required', 'exists:payers,id'],
            'assessment_id' => ['nullable', 'exists:assessments,id'],
            'water_bill_id' => ['nullable', 'exists:water_bills,id'],
            'revenue_code' => ['required', 'string', Rule::exists('revenue_types', 'revenue_code')->where('is_active', true)],
            'channel' => ['required', Rule::in(['BANK', 'MOBILE_MONEY'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'local_currency' => ['nullable', 'string', 'max:10'],
            'external_ref' => ['nullable', 'string', 'max:100', 'unique:channel_payments,external_ref'],
        ];

        if (config('channels.allow_simulate')) {
            $rules['simulate'] = ['nullable', Rule::in(['SUCCESS', 'FAILED', 'PENDING'])];
        }

        $data = $request->validate($rules);

        if (! OwnsPayerScope::canAccessPayer($request->user(), (int) $data['payer_id'], 'payments.view', 'payments.view_own')) {
            return response()->json(['message' => 'You do not have access to initiate payments for this payer.'], 403);
        }

        try {
            $payment = $this->channels->initiate($data, $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Unable to initiate channel payment.',
            ], 422);
        }

        return response()->json([
            'message' => 'Channel payment initiated.',
            'channel_payment' => $payment,
        ], 201);
    }

    public function show(ChannelPayment $channelPayment): JsonResponse
    {
        $channelPayment->load([
            'payer:id,tin,full_name',
            'assessment:id,control_number,status,payer_id',
            'waterBill:id,bill_number,status,payer_id',
            'payment:id,external_ref,status,amount',
            'exchangeRate',
            'creator:id,name',
        ]);

        $payload = $channelPayment->toArray();
        unset($payload['initiate_payload'], $payload['callback_payload'], $payload['status_history']);

        return response()->json(['channel_payment' => $payload]);
    }

    public function check(Request $request, ChannelPayment $channelPayment): JsonResponse
    {
        try {
            $payment = $this->channels->checkStatus($channelPayment, $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Status check completed.',
            'channel_payment' => $payment,
        ]);
    }

    public function retryDue(Request $request): JsonResponse
    {
        $sync = $request->boolean('sync', false);

        if ($sync || config('queue.default') === 'sync') {
            $result = $this->channels->processDueRetries($request->user()?->id);

            return response()->json([
                'message' => 'Due retries processed.',
                'queued' => false,
                'processed' => $result['processed'],
                'items' => $result['items'],
            ]);
        }

        \App\Jobs\ProcessChannelRetriesJob::dispatch($request->user()?->id);

        return response()->json([
            'message' => 'Due channel retries queued on Redis.',
            'queued' => true,
            'queue' => 'channels',
        ], 202);
    }

    public function callback(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON payload.'], 422);
        }

        $signature = $request->header('X-Channel-Signature');

        try {
            $payment = $this->channels->handleCallback($payload, $signature, $rawBody);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Channel payment not found.'], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Unable to process callback.',
            ], 422);
        }

        return response()->json([
            'message' => 'Callback processed.',
            'channel_payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'external_ref' => $payment->external_ref,
                'payment_id' => $payment->payment_id,
            ],
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = SupervisorNotification::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest()
            ->paginate(\App\Support\Pagination::perPage($request, 20));

        return response()->json($items);
    }
}
