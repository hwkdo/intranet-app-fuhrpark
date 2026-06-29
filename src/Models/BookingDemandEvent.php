<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Database\Factories\BookingDemandEventFactory;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDemandEvent extends Model
{
    /** @use HasFactory<BookingDemandEventFactory> */
    use HasFactory;

    protected $table = 'intranet_app_fuhrpark_booking_demand_events';

    protected $fillable = [
        'user_id',
        'standort_id',
        'vehicle_category_id',
        'vehicle_id',
        'driver_id',
        'starts_at',
        'ends_at',
        'reason',
        'source',
        'had_alternative_category',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reason' => BookingDemandReason::class,
            'source' => BookingDemandSource::class,
            'had_alternative_category' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'user_id');
    }

    public function standort(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::standort(), 'standort_id');
    }

    public function vehicleCategory(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'driver_id');
    }

    protected static function newFactory(): BookingDemandEventFactory
    {
        return BookingDemandEventFactory::new();
    }
}
