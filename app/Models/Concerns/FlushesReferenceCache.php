<?php

namespace App\Models\Concerns;

use App\Support\ReferenceCache;

/**
 * Bumps the reference-cache version whenever the model is written so stale
 * entries are never served. Add `use FlushesReferenceCache` and a
 * `REFERENCE_CACHE_KEY` constant to the model.
 */
trait FlushesReferenceCache
{
    protected static function bootFlushesReferenceCache(): void
    {
        $flush = fn () => ReferenceCache::flush(static::REFERENCE_CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }
}
