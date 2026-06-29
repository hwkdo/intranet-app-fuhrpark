<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Database\Factories\VehicleFactory;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Enums\VehicleAdminDisplayStatus;
use Hwkdo\IntranetAppFuhrpark\Enums\VehicleAdminUnavailabilityCause;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $table = 'intranet_app_fuhrpark_vehicles';

    protected $fillable = [
        'vehicle_category_id',
        'standort_id',
        'license_plate',
        'manufacturer',
        'model',
        'vin',
        'fuel_type',
        'initial_km',
        'active',
        'is_new',
        'inactive_reason',
        'inactive_by_user_id',
        'available_from',
        'available_until',
        'electric_range_km',
        'electric_charge_minutes',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_new' => 'boolean',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function standort(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::standort(), 'standort_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'vehicle_id');
    }

    public function logbookEntries(): HasManyThrough
    {
        return $this->hasManyThrough(LogbookEntry::class, Booking::class, 'vehicle_id', 'booking_id');
    }

    public function isElectric(): bool
    {
        return $this->fuel_type === 'electric';
    }

    public function effectiveElectricRangeKm(): ?int
    {
        if (! $this->isElectric()) {
            return null;
        }

        $range = $this->electric_range_km ?? $this->category?->electric_range_avg_km;

        return $range ? (int) floor($range * 0.8) : null;
    }

    public function hasAvailabilityRestriction(): bool
    {
        return $this->available_from !== null || $this->available_until !== null;
    }

    public function availabilityLabel(): string
    {
        $parts = [];

        if ($this->available_from) {
            $parts[] = 'Ab '.$this->available_from->format('d.m.Y H:i');
        }

        if ($this->available_until) {
            $parts[] = 'Bis '.$this->available_until->format('d.m.Y H:i');
        }

        return implode(' · ', $parts);
    }

    public function adminUnavailabilityCause(): ?VehicleAdminUnavailabilityCause
    {
        $now = now();

        if (! $this->active) {
            return VehicleAdminUnavailabilityCause::Deactivated;
        }

        if ($this->available_from && $now < $this->available_from) {
            return VehicleAdminUnavailabilityCause::AvailabilityWindow;
        }

        if ($this->available_until && $now > $this->available_until) {
            return VehicleAdminUnavailabilityCause::AvailabilityWindow;
        }

        if ($this->hasActiveLockNow()) {
            return VehicleAdminUnavailabilityCause::Lock;
        }

        if ($this->hasActiveWorkshopNow()) {
            return VehicleAdminUnavailabilityCause::Workshop;
        }

        return null;
    }

    public function adminDisplayStatus(): VehicleAdminDisplayStatus
    {
        if ($this->adminUnavailabilityCause() !== null) {
            return VehicleAdminDisplayStatus::Unavailable;
        }

        if ($this->hasRegularBookingNow()) {
            return VehicleAdminDisplayStatus::Underway;
        }

        if ($this->hasAvailabilityRestriction() || (int) ($this->active_locks_count ?? 0) > 0) {
            return VehicleAdminDisplayStatus::Limited;
        }

        return VehicleAdminDisplayStatus::Available;
    }

    public function adminStatusMenuIsUrgent(string $action): bool
    {
        $cause = $this->adminUnavailabilityCause();

        if ($cause === null) {
            return false;
        }

        return $cause->menuAction() === $action;
    }

    public function hasRegularBookingNow(): bool
    {
        return $this->bookings()
            ->where('purpose', BookingPurpose::Normal)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->exists();
    }

    public function hasActiveLockNow(): bool
    {
        return $this->bookings()
            ->where('purpose', BookingPurpose::Lock)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->exists();
    }

    public function hasActiveWorkshopNow(): bool
    {
        return $this->bookings()
            ->where('purpose', BookingPurpose::Workshop)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->exists();
    }

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }
}
