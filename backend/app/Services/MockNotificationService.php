<?php

namespace App\Services;

use App\Models\WaterBill;
use Illuminate\Support\Facades\Log;

class MockNotificationService
{
    /**
     * @return array{status: string, sms: array<string, mixed>, email: array<string, mixed>}
     */
    public function notifyBill(WaterBill $bill): array
    {
        $bill->loadMissing(['payer:id,full_name,phone,email', 'waterAccount:id,account_no,meter_no']);

        $payer = $bill->payer;
        $message = sprintf(
            'IRCUB Water Bill %s for account %s: due %s USD by %s. Control period %s.',
            $bill->bill_number,
            $bill->waterAccount?->account_no,
            number_format((float) $bill->total_due, 2),
            optional($bill->due_date)->toDateString(),
            $bill->period
        );

        $sms = [
            'to' => $payer?->phone,
            'body' => $message,
            'provider' => 'mock-sms',
            'status' => $payer?->phone ? 'SENT' : 'SKIPPED',
            'sent_at' => now()->toIso8601String(),
        ];

        $email = [
            'to' => $payer?->email,
            'subject' => 'Your IRCUB water bill '.$bill->bill_number,
            'body' => $message,
            'provider' => 'mock-email',
            'status' => $payer?->email ? 'SENT' : 'SKIPPED',
            'sent_at' => now()->toIso8601String(),
        ];

        Log::info('Mock water bill notification', [
            'bill_number' => $bill->bill_number,
            'sms' => $sms,
            'email' => $email,
        ]);

        $status = ($sms['status'] === 'SENT' || $email['status'] === 'SENT') ? 'SENT' : 'SKIPPED';

        return [
            'status' => $status,
            'sms' => $sms,
            'email' => $email,
        ];
    }
}
