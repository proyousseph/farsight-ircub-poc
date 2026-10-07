<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OwnsPayerScope
{
    /**
     * If the user only has *_view_own (not full view), restrict to linked payer_id.
     */
    public static function apply(Builder $query, ?User $user, string $fullPermission, string $ownPermission, string $payerColumn = 'payer_id'): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasPermission($fullPermission)) {
            return $query;
        }

        if ($user->hasPermission($ownPermission)) {
            $payerId = $user->payer_id;
            if (! $payerId) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where($payerColumn, $payerId);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canAccessPayer(?User $user, int $payerId, string $fullPermission, string $ownPermission): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasPermission($fullPermission)) {
            return true;
        }
        if ($user->hasPermission($ownPermission) && (int) $user->payer_id === $payerId) {
            return true;
        }

        return false;
    }
}
