<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Database\Factories\DriverLicenseFactory;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DriverLicense extends Model
{
    /** @use HasFactory<DriverLicenseFactory> */
    use HasFactory;

    protected $table = 'intranet_app_fuhrpark_driver_licenses';

    protected $fillable = [
        'user_id',
        'valid_until',
        'restricted_until',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'restricted_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'user_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(DriverLicenseControl::class);
    }

    public function latestControl(): HasOne
    {
        return $this->hasOne(DriverLicenseControl::class)->latestOfMany();
    }
}
