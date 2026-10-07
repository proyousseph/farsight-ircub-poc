<?php

namespace App\Jobs;

use App\Models\WaterBill;
use App\Services\BillPdfService;
use App\Services\MockNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyWaterBillJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $waterBillId)
    {
        $this->onQueue('notifications');
    }

    public function handle(MockNotificationService $notifications, BillPdfService $pdfs): void
    {
        /** @var WaterBill|null $bill */
        $bill = WaterBill::query()->find($this->waterBillId);
        if (! $bill) {
            return;
        }

        if (! $bill->pdf_path) {
            $bill->pdf_path = $pdfs->generate($bill);
        }

        $notify = $notifications->notifyBill($bill);
        $bill->notification_status = $notify['status'];
        $bill->notification_log = $notify;
        $bill->save();

        Log::info('NotifyWaterBillJob completed', [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'status' => $notify['status'],
        ]);
    }
}
