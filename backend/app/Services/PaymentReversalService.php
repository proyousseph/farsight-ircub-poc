<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterBill;
use Illuminate\Support\Facades\DB;

class PaymentReversalService
{
    public function request(Payment $payment, User $actor, string $reason): Payment
    {
        if ($payment->status !== 'SUCCESS') {
            throw new \RuntimeException('Only SUCCESS payments can be requested for reversal.');
        }
        if ($payment->reversal_status === 'PENDING') {
            throw new \RuntimeException('A reversal request is already pending for this payment.');
        }
        if ($payment->fmis_status === 'POSTED' && $payment->fmis_journal_line_id) {
            throw new \RuntimeException('Payment is posted to FMIS. Reverse the FMIS batch before reversing the payment.');
        }

        $before = $payment->toArray();
        $payment->reversal_status = 'PENDING';
        $payment->reversal_reason = $reason;
        $payment->reversal_requested_by = $actor->id;
        $payment->reversal_requested_at = now();
        $payment->reversal_reviewed_by = null;
        $payment->reversal_reviewed_at = null;
        $payment->save();

        AuditLog::record('Payment', $payment->id, 'REVERSAL_REQUESTED', $before, $payment->fresh()->toArray(), $actor->id);

        return $payment->fresh(['payer:id,tin,full_name', 'assessment:id,control_number,status', 'creator:id,name']);
    }

    public function approve(Payment $payment, User $actor, ?string $notes = null): Payment
    {
        if ($payment->reversal_status !== 'PENDING') {
            throw new \RuntimeException('No pending reversal request to approve.');
        }
        if ((int) $payment->reversal_requested_by === (int) $actor->id) {
            throw new \RuntimeException('Segregation of duties: the user who requested the reversal cannot approve it.');
        }

        return DB::transaction(function () use ($payment, $actor, $notes) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $before = $payment->toArray();

            if ($payment->assessment_id) {
                $assessment = Assessment::query()->lockForUpdate()->findOrFail($payment->assessment_id);
                $aBefore = $assessment->toArray();
                $assessment->amount_paid = max(0, (float) $assessment->amount_paid - (float) $payment->amount);
                $assessment->save();
                $assessment->refreshStatus();
                AuditLog::record('Assessment', $assessment->id, 'UPDATED', $aBefore, $assessment->fresh()->toArray(), $actor->id);
            }

            if ($payment->water_bill_id) {
                $bill = WaterBill::query()->lockForUpdate()->findOrFail($payment->water_bill_id);
                $bBefore = $bill->toArray();
                $bill->amount_paid = max(0, (float) $bill->amount_paid - (float) $payment->amount);
                $bill->save();
                $bill->refreshStatus();
                AuditLog::record('WaterBill', $bill->id, 'UPDATED', $bBefore, $bill->fresh()->toArray(), $actor->id);
            }

            $payment->status = 'REVERSED';
            $payment->reversal_status = 'APPROVED';
            $payment->reversal_reviewed_by = $actor->id;
            $payment->reversal_reviewed_at = now();
            if ($notes) {
                $payment->notes = trim(($payment->notes ? $payment->notes."\n" : '').'Reversal: '.$notes);
            }
            $payment->save();

            AuditLog::record('Payment', $payment->id, 'REVERSED', $before, $payment->fresh()->toArray(), $actor->id);

            return $payment->fresh(['payer:id,tin,full_name', 'assessment:id,control_number,status', 'creator:id,name']);
        });
    }

    public function reject(Payment $payment, User $actor, ?string $notes = null): Payment
    {
        if ($payment->reversal_status !== 'PENDING') {
            throw new \RuntimeException('No pending reversal request to reject.');
        }
        if ((int) $payment->reversal_requested_by === (int) $actor->id) {
            throw new \RuntimeException('Segregation of duties: the requester cannot reject their own reversal request.');
        }

        $before = $payment->toArray();
        $payment->reversal_status = 'REJECTED';
        $payment->reversal_reviewed_by = $actor->id;
        $payment->reversal_reviewed_at = now();
        if ($notes) {
            $payment->reversal_reason = trim($payment->reversal_reason.' | Rejected: '.$notes);
        }
        $payment->save();

        AuditLog::record('Payment', $payment->id, 'REVERSAL_REJECTED', $before, $payment->fresh()->toArray(), $actor->id);

        return $payment->fresh(['payer:id,tin,full_name', 'assessment:id,control_number,status', 'creator:id,name']);
    }
}
