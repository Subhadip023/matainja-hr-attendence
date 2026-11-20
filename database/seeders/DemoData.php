<?php

namespace Database\Seeders;

/**
 * Demo logins (password "password") and demo clients/invoices must never land
 * on a live server by accident. They are seeded only in the local and testing
 * environments, or when SEED_DEMO_DATA=true is set on purpose (e.g. a staging demo).
 */
final class DemoData
{
    public static function allowed(): bool
    {
        return app()->environment(['local', 'testing'])
            || filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function skippedMessage(string $what): string
    {
        return "Skipped {$what}: demo data is only seeded in local/testing (set SEED_DEMO_DATA=true to force).";
    }
}
