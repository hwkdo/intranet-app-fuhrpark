<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Database\Factories\VehicleCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleCategory extends Model
{
    /** @use HasFactory<VehicleCategoryFactory> */
    use HasFactory;

    protected $table = 'intranet_app_fuhrpark_vehicle_categories';

    protected $fillable = [
        'name',
        'requires_license',
        'is_electric',
        'electric_range_avg_km',
        'electric_charge_minutes_avg',
    ];

    protected function casts(): array
    {
        return [
            'requires_license' => 'boolean',
            'is_electric' => 'boolean',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_category_id');
    }

    public function averageElectricRangeKm(): ?int
    {
        if (! $this->is_electric) {
            return null;
        }

        if ($this->electric_range_avg_km) {
            return (int) floor($this->electric_range_avg_km * 0.8);
        }

        $vehicles = $this->vehicles()
            ->where('fuel_type', 'electric')
            ->where('active', true)
            ->get();

        if ($vehicles->isEmpty()) {
            return null;
        }

        $totalRange = $vehicles->sum(
            fn (Vehicle $vehicle): int => $vehicle->effectiveElectricRangeKm() ?? 0,
        );

        return $totalRange > 0 ? (int) ceil($totalRange / $vehicles->count()) : null;
    }

    protected static function newFactory(): VehicleCategoryFactory
    {
        return VehicleCategoryFactory::new();
    }
}
