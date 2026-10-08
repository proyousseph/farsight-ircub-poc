<?php

namespace App\Support;

/**
 * Fail-closed gate for in-process mock channel/FMIS adapters.
 * Live environments must set CHANNEL_ALLOW_MOCK / FMIS_ALLOW_MOCK explicitly
 * (POC only) or wire a real provider client.
 */
class MockProviderGuard
{
    public static function assertChannelAllowed(): void
    {
        if (config('channels.allow_mock_providers', false)) {
            return;
        }

        throw new \RuntimeException(
            'Mock channel provider is disabled. Set CHANNEL_ALLOW_MOCK=true only for POC, or configure a live channel adapter.'
        );
    }

    public static function assertFmisAllowed(): void
    {
        if (config('ircub.allow_mock_fmis', false)) {
            return;
        }

        throw new \RuntimeException(
            'Mock FMIS provider is disabled. Set FMIS_ALLOW_MOCK=true only for POC, or configure a live FMIS adapter.'
        );
    }
}
