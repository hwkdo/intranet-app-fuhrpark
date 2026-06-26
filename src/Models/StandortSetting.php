<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Database\Factories\StandortSettingFactory;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandortSetting extends Model
{
    /** @use HasFactory<StandortSettingFactory> */
    use HasFactory;

    protected $table = 'intranet_app_fuhrpark_standort_settings';

    protected $fillable = [
        'standort_id',
        'is_vehicle_standort',
        'vehicle_standort_id',
    ];

    protected function casts(): array
    {
        return [
            'is_vehicle_standort' => 'boolean',
        ];
    }

    public function standort(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::standort(), 'standort_id');
    }

    public function vehicleStandort(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::standort(), 'vehicle_standort_id');
    }

    protected static function newFactory(): StandortSettingFactory
    {
        return StandortSettingFactory::new();
    }
}
