<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Shared cache for slowly-changing reference data (departments, branches,
 * designations, shifts, leave types...).
 *
 * Keys are versioned per name so writes can invalidate cheaply without
 * relying on cache tags — this works on the file and database drivers.
 */
class ReferenceCache
{
    private const TTL = 900; // seconds (15 minutes)

    /**
     * Fetch cached data for $name, filling it via $resolver on a miss.
     * Pass $variant when the same name is cached under several shapes
     * (e.g. holidays filtered by year/branch).
     */
    public static function get(string $name, Closure $resolver, string $variant = ''): mixed
    {
        $version = (int) Cache::get("refver:$name", 0);

        return Cache::remember("ref:$name:v$version:$variant", self::TTL, $resolver);
    }

    /**
     * Invalidate every cached variant of $name (called from model events).
     */
    public static function flush(string $name): void
    {
        Cache::increment("refver:$name");
    }
}
