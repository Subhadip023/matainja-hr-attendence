<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CompanySetting;
use Illuminate\Validation\ValidationException;

/**
 * Checks that a check-in / check-out happens inside the staff member's branch
 * geofence. Only branches with latitude and longitude are fenced, and the
 * whole check can be switched off with the `attendance.geofence_enforced`
 * company setting.
 */
class GeofenceService
{
    public const SETTING_ENFORCED = 'attendance.geofence_enforced';

    public const SETTING_DEFAULT_RADIUS = 'attendance.geofence_radius_meters';

    public const FALLBACK_RADIUS_METERS = 100;

    /** GPS accuracy tolerated on top of the radius, at most this many metres. */
    public const MAX_ACCURACY_ALLOWANCE_METERS = 50;

    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * @param  array{latitude?: float|int|string|null, longitude?: float|int|string|null, accuracy_meters?: int|string|null}  $meta
     *
     * @throws ValidationException when the punch is outside the fence or has no location
     */
    public function assertWithinFence(?Branch $branch, array $meta, string $field = 'location'): void
    {
        if (! $this->isEnforcedFor($branch)) {
            return;
        }

        $latitude = $meta['latitude'] ?? null;
        $longitude = $meta['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            throw ValidationException::withMessages([
                $field => ['Your location is required to check in or out. Turn on location and try again.'],
            ]);
        }

        $distance = $this->distanceInMeters(
            (float) $branch->latitude,
            (float) $branch->longitude,
            (float) $latitude,
            (float) $longitude,
        );

        $radius = $this->radiusFor($branch);
        $allowance = min((int) ($meta['accuracy_meters'] ?? 0), self::MAX_ACCURACY_ALLOWANCE_METERS);

        if ($distance > $radius + $allowance) {
            throw ValidationException::withMessages([
                $field => [sprintf(
                    'You are %d m from %s. You must be within %d m of the office to check in or out.',
                    (int) round($distance),
                    $branch->name,
                    $radius,
                )],
            ]);
        }
    }

    public function isEnforcedFor(?Branch $branch): bool
    {
        if ($branch === null || $branch->latitude === null || $branch->longitude === null) {
            return false;
        }

        $setting = CompanySetting::where('key', self::SETTING_ENFORCED)->value('value');

        return $setting === null || filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    public function radiusFor(Branch $branch): int
    {
        if ($branch->geofence_radius_meters) {
            return (int) $branch->geofence_radius_meters;
        }

        $default = CompanySetting::where('key', self::SETTING_DEFAULT_RADIUS)->value('value');

        return is_numeric($default) && (int) $default > 0 ? (int) $default : self::FALLBACK_RADIUS_METERS;
    }

    /**
     * Great-circle distance (haversine).
     */
    public function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $deltaLatitude = deg2rad($toLatitude - $fromLatitude);
        $deltaLongitude = deg2rad($toLongitude - $fromLongitude);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($deltaLongitude / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
