<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChannelPayment;
use App\Models\Payment;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChannelReconciliationService
{
    public function __construct(private MockChannelClient $channel)
    {
    }

    public function run(string $date, string $channel, ?int $userId = null): ReconciliationRun
    {
        $channel = strtoupper($channel);
        if (! in_array($channel, ['BANK', 'MOBILE_MONEY'], true)) {
            throw new \InvalidArgumentException('Channel must be BANK or MOBILE_MONEY.');
        }

        return DB::transaction(function () use ($date, $channel, $userId) {
            ReconciliationRun::query()->where('report_date', $date)->where('channel', $channel)->delete();

            $ircubPayments = Payment::query()
                ->where('channel', $channel)
                ->where('status', 'SUCCESS')
                ->whereDate('paid_at', $date)
                ->get();

            // Build a mock channel statement from IRCUB refs, with optional intentional drift:
            // omit first IRCUB row sometimes and add one extra channel-only line for demo.
            $statementLines = $ircubPayments->map(fn (Payment $p) => [
                'external_ref' => $p->external_ref,
                'amount' => (float) $p->amount,
                'currency' => $p->currency,
            ])->values()->all();

            // Include successful channel payments that may not yet be linked oddly — already in payments.
            $statement = $this->channel->statement($date, $channel, $statementLines);

            // Inject one unmatched channel-only line for demonstration if none provided by mock.
            $channelLines = collect($statement['lines'] ?? []);
            if ($channelLines->isNotEmpty()) {
                $channelLines = $channelLines->slice(1)->values(); // drop first => IRCUB_ONLY candidate
            }
            $channelLines->push([
                'external_ref' => 'CH-STMT-ONLY-'.$date.'-'.substr($channel, 0, 1),
                'amount' => 12.5,
                'currency' => 'USD',
                'channel' => $channel,
            ]);

            $ircubByRef = $ircubPayments->keyBy('external_ref');
            $channelByRef = $channelLines->keyBy('external_ref');

            $matched = 0;
            $ircubOnly = 0;
            $channelOnly = 0;
            $items = [];

            foreach ($ircubByRef as $ref => $payment) {
                if ($channelByRef->has($ref)) {
                    $matched++;
                    $items[] = [
                        'match_status' => 'MATCHED',
                        'external_ref' => $ref,
                        'ircub_amount' => $payment->amount,
                        'channel_amount' => $channelByRef[$ref]['amount'],
                        'channel' => $channel,
                        'details' => ['payment_id' => $payment->id],
                    ];
                } else {
                    $ircubOnly++;
                    $items[] = [
                        'match_status' => 'IRCUB_ONLY',
                        'external_ref' => $ref,
                        'ircub_amount' => $payment->amount,
                        'channel_amount' => null,
                        'channel' => $channel,
                        'details' => ['payment_id' => $payment->id],
                    ];
                }
            }

            foreach ($channelByRef as $ref => $line) {
                if (! $ircubByRef->has($ref)) {
                    $channelOnly++;
                    $items[] = [
                        'match_status' => 'CHANNEL_ONLY',
                        'external_ref' => $ref,
                        'ircub_amount' => null,
                        'channel_amount' => $line['amount'],
                        'channel' => $channel,
                        'details' => $line,
                    ];
                }
            }

            $ircubTotal = round((float) $ircubPayments->sum('amount'), 2);
            $channelTotal = round((float) $channelLines->sum('amount'), 2);

            $statementPath = 'reconciliation/'.$date.'-'.$channel.'.json';
            Storage::disk('local')->put($statementPath, json_encode([
                'date' => $date,
                'channel' => $channel,
                'lines' => $channelLines,
            ], JSON_PRETTY_PRINT));

            $run = ReconciliationRun::query()->create([
                'report_date' => $date,
                'channel' => $channel,
                'status' => 'COMPLETED',
                'ircub_total' => $ircubTotal,
                'channel_total' => $channelTotal,
                'difference' => round($ircubTotal - $channelTotal, 2),
                'matched_count' => $matched,
                'ircub_only_count' => $ircubOnly,
                'channel_only_count' => $channelOnly,
                'statement_path' => $statementPath,
                'summary' => [
                    'ircub_count' => $ircubPayments->count(),
                    'channel_count' => $channelLines->count(),
                    'pending_channel_payments' => ChannelPayment::query()
                        ->where('channel', $channel)
                        ->whereDate('created_at', $date)
                        ->whereIn('status', ['PENDING', 'FAILED'])
                        ->count(),
                ],
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                ReconciliationItem::query()->create(array_merge($item, [
                    'reconciliation_run_id' => $run->id,
                ]));
            }

            AuditLog::record('ReconciliationRun', $run->id, 'COMPLETED', null, $run->toArray(), $userId);

            return $run->fresh('items');
        });
    }
}
