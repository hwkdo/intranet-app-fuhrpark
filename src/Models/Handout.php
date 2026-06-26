<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Handout extends Model
{
    protected $table = 'intranet_app_fuhrpark_handouts';

    protected $fillable = [
        'booking_id',
        'driver_id',
        'processed_by_user_id',
        'signature_data',
    ];

    protected function casts(): array
    {
        return [
            'signature_data' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'driver_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'processed_by_user_id');
    }

    public function returnRecord(): HasOne
    {
        return $this->hasOne(VehicleReturn::class, 'handout_id');
    }
}
