<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Support;

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\StandortSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FuhrparkModels
{
    /**
     * @return class-string<Model>
     */
    public static function user(): string
    {
        return (string) config('intranet-app-fuhrpark.user_model', User::class);
    }

    /**
     * @return class-string<Model>
     */
    public static function standort(): string
    {
        return (string) config('intranet-app-fuhrpark.standort_model', Standort::class);
    }

    /**
     * @return Builder<Model>
     */
    public static function userQuery(): Builder
    {
        return static::user()::query();
    }

    /**
     * @return Builder<Model>
     */
    public static function standortQuery(): Builder
    {
        return static::standort()::query();
    }

    public static function vehicleStandortIdFor(?int $standortId): ?int
    {
        if (! $standortId) {
            return null;
        }

        $setting = StandortSetting::query()->where('standort_id', $standortId)->first();

        if ($setting) {
            if ($setting->is_vehicle_standort) {
                return $standortId;
            }

            return $setting->vehicle_standort_id;
        }

        return static::legacyVehicleStandortIdFor($standortId);
    }

    /**
     * @return Collection<int, Model>
     */
    public static function vehicleStandorte(): Collection
    {
        return StandortSetting::query()
            ->where('is_vehicle_standort', true)
            ->with('standort')
            ->get()
            ->map(fn (StandortSetting $setting): ?Model => $setting->standort)
            ->filter()
            ->sortBy(fn (Model $standort): string => (string) $standort->name)
            ->values();
    }

    private static function legacyVehicleStandortIdFor(int $standortId): ?int
    {
        $standort = static::standortQuery()->find($standortId);

        if (! $standort) {
            return null;
        }

        if ($standort->fahrzeugstandort_id !== null) {
            return (int) $standort->fahrzeugstandort_id;
        }

        return null;
    }
}
