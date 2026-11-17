<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'branches';

    protected $fillable = [
        'name', 'code', 'address', 'latitude', 'longitude', 'geofence_radius_meters', 'timezone', 'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_meters' => 'integer',
        ];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }
}
