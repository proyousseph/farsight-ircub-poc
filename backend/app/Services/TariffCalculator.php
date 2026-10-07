<?php

namespace App\Services;

use App\Models\WaterTariff;
use InvalidArgumentException;

class TariffCalculator
{
    /**
     * @return array{tariff_amount: float, fixed_charge: float, breakdown: array<int, array<string, float|int|null>>}
     */
    public function calculate(string $tariffClass, float $consumption, ?string $asOf = null): array
    {
        $tariff = WaterTariff::activeFor($tariffClass, $asOf);

        if (! $tariff) {
            throw new InvalidArgumentException("No active tariff configured for class {$tariffClass}.");
        }

        $remaining = max(0, $consumption);
        $amount = 0.0;
        $breakdown = [];

        foreach ($tariff->tiers as $tier) {
            $from = (float) ($tier['from'] ?? 0);
            $to = isset($tier['to']) && $tier['to'] !== null ? (float) $tier['to'] : null;
            $rate = (float) ($tier['rate_per_m3'] ?? 0);

            if ($remaining <= 0) {
                break;
            }

            if ($to === null) {
                $units = $remaining;
            } else {
                $bandSize = max(0, $to - $from);
                $units = min($remaining, $bandSize);
            }

            $line = round($units * $rate, 2);
            $amount += $line;
            $breakdown[] = [
                'from' => $from,
                'to' => $to,
                'units' => round($units, 3),
                'rate_per_m3' => $rate,
                'amount' => $line,
            ];
            $remaining = round($remaining - $units, 3);
        }

        return [
            'tariff_amount' => round($amount, 2),
            'fixed_charge' => round((float) $tariff->fixed_charge, 2),
            'breakdown' => $breakdown,
            'tariff_id' => $tariff->id,
        ];
    }
}
