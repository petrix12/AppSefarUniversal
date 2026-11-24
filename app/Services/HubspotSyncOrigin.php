<?php

namespace App\Services;

/** Prevents inbound HubSpot projections from being echoed back as outbound writes. */
class HubspotSyncOrigin
{
    private static int $remoteWriteDepth = 0;

    public static function fromHubspot(callable $callback): mixed
    {
        self::$remoteWriteDepth++;
        try {
            return $callback();
        } finally {
            self::$remoteWriteDepth--;
        }
    }

    public static function isRemoteWrite(): bool
    {
        return self::$remoteWriteDepth > 0;
    }
}
