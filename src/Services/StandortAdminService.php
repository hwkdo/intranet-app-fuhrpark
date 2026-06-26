<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Hwkdo\IntranetAppFuhrpark\Data\AdminStandortData;
use Hwkdo\IntranetAppFuhrpark\Models\StandortSetting;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StandortAdminService
{
    /**
     * @return Collection<int, AdminStandortData>
     */
    public function allForAdmin(): Collection
    {
        $settings = StandortSetting::query()->get()->keyBy('standort_id');

        return FuhrparkModels::standortQuery()
            ->orderBy('name')
            ->get()
            ->map(function (Model $standort) use ($settings): AdminStandortData {
                $setting = $settings->get($standort->getKey());

                return new AdminStandortData(
                    standort: $standort,
                    isVehicleStandort: (bool) ($setting?->is_vehicle_standort ?? false),
                    vehicleStandortId: $setting?->vehicle_standort_id,
                );
            });
    }

    /**
     * @return Collection<int, Model>
     */
    public function vehicleStandorte(): Collection
    {
        return StandortSetting::query()
            ->where('is_vehicle_standort', true)
            ->with('standort')
            ->get()
            ->map(fn (StandortSetting $setting): Model => $setting->standort)
            ->filter()
            ->sortBy(fn (Model $standort): string => (string) $standort->name)
            ->values();
    }

    public function setVehicleStandort(Model $standort, bool $isVehicleStandort): StandortSetting
    {
        if ($isVehicleStandort) {
            if (StandortSetting::query()->where('vehicle_standort_id', $standort->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'is_vehicle_standort' => ['Dieser Standort ist anderen Standorten als Fahrzeugstandort zugeordnet. Bitte zuerst die Zuordnungen entfernen.'],
                ]);
            }

            return StandortSetting::query()->updateOrCreate(
                ['standort_id' => $standort->getKey()],
                [
                    'is_vehicle_standort' => true,
                    'vehicle_standort_id' => null,
                ],
            );
        }

        if (Vehicle::query()->where('standort_id', $standort->getKey())->exists()) {
            throw ValidationException::withMessages([
                'is_vehicle_standort' => ['An diesem Standort sind noch Fahrzeuge hinterlegt.'],
            ]);
        }

        return StandortSetting::query()->updateOrCreate(
            ['standort_id' => $standort->getKey()],
            ['is_vehicle_standort' => false],
        );
    }

    public function assignVehicleStandort(Model $standort, ?int $vehicleStandortId): StandortSetting
    {
        $setting = StandortSetting::query()->firstOrNew(['standort_id' => $standort->getKey()]);

        if ($setting->is_vehicle_standort) {
            throw ValidationException::withMessages([
                'vehicle_standort_id' => ['Ein Fahrzeugstandort kann keinem anderen Fahrzeugstandort zugeordnet werden.'],
            ]);
        }

        if ($vehicleStandortId === null) {
            $setting->vehicle_standort_id = null;
            $setting->save();

            return $setting->fresh();
        }

        if ((int) $vehicleStandortId === (int) $standort->getKey()) {
            throw ValidationException::withMessages([
                'vehicle_standort_id' => ['Ein Standort kann nicht sich selbst zugeordnet werden.'],
            ]);
        }

        $isValidVehicleStandort = StandortSetting::query()
            ->where('standort_id', $vehicleStandortId)
            ->where('is_vehicle_standort', true)
            ->exists();

        if (! $isValidVehicleStandort) {
            throw ValidationException::withMessages([
                'vehicle_standort_id' => ['Bitte einen gültigen Fahrzeugstandort wählen.'],
            ]);
        }

        $setting->is_vehicle_standort = false;
        $setting->vehicle_standort_id = $vehicleStandortId;
        $setting->save();

        return $setting->fresh();
    }
}
