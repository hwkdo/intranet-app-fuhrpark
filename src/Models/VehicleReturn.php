<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleReturn extends Model
{
    protected $table = 'intranet_app_fuhrpark_returns';

    protected $fillable = [
        'handout_id',
        'driver_id',
        'processed_by_user_id',
        'km_end',
        'checklist',
        'has_damage',
        'damage_note',
        'signature_data',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'has_damage' => 'boolean',
            'signature_data' => 'array',
        ];
    }

    public function handout(): BelongsTo
    {
        return $this->belongsTo(Handout::class, 'handout_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'driver_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'processed_by_user_id');
    }
}
