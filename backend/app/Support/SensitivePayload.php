<?php

namespace App\Support;

class SensitivePayload
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitive = [
            'password', 'remember_token', 'token', 'secret', 'signature', 'authorization',
            'initiate_payload', 'callback_payload', 'two_factor_secret', 'national_id',
            'status_history',
        ];

        foreach ($payload as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $payload[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }

    /**
     * Safe public representation of a channel payment (no provider blobs).
     *
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public static function channelPaymentPublic(array $payment): array
    {
        unset(
            $payment['initiate_payload'],
            $payment['callback_payload'],
            $payment['status_history'],
        );

        return $payment;
    }
}
