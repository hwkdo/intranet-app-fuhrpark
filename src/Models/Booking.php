<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Database\Factories\BookingFactory;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $table = 'intranet_app_fuhrpark_bookings';

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'driver_id',
        'purpose',
        'purpose_note',
        'lock_reason',
        'lock_user_id',
        'charge_lock_for_booking_id',
        'description',
        'is_commute',
        'electric_route_km',
        'starts_at',
        'ends_at',
        'km_start',
        'km_end',
        'ms_graph_event_id',
        'sync_to_calendar',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => BookingPurpose::class,
            'is_commute' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sync_to_calendar' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'user_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'driver_id');
    }

    public function handout(): HasOne
    {
        return $this->hasOne(Handout::class, 'booking_id');
    }

    public function logbookEntry(): HasOne
    {
        return $this->hasOne(LogbookEntry::class, 'booking_id');
    }

    public function returnRecord(): HasOneThrough
    {
        return $this->hasOneThrough(
            VehicleReturn::class,
            Handout::class,
            'booking_id',
            'handout_id',
            'id',
            'id',
        );
    }

    public function chargeLockBooking(): BelongsTo
    {
        return $this->belongsTo(self::class, 'charge_lock_for_booking_id');
    }

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }
}
