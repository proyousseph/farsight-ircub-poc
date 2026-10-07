<?php

namespace App\Services;

use App\Models\WaterBill;
use Illuminate\Support\Facades\Storage;

class BillPdfService
{
    public function generate(WaterBill $bill): string
    {
        $bill->loadMissing([
            'payer:id,tin,full_name,phone,email,address',
            'waterAccount:id,account_no,meter_no,tariff_class,location',
        ]);

        $html = view('bills.water', ['bill' => $bill])->render();
        $dir = 'bills/'.$bill->period;

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $relative = $dir.'/'.$bill->bill_number.'.pdf';
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            Storage::disk('local')->put($relative, $pdf->output());

            return $relative;
        }

        $relative = $dir.'/'.$bill->bill_number.'.html';
        Storage::disk('local')->put($relative, $html);

        return $relative;
    }
}
