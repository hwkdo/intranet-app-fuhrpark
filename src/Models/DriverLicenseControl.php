<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Models;

use Hwkdo\IntranetAppFuhrpark\Database\Factories\DriverLicenseControlFactory;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLicenseControl extends Model
{
    /** @use HasFactory<DriverLicenseControlFactory> */
    use HasFactory;
    protected $table = 'intranet_app_fuhrpark_driver_license_controls';

    protected $fillable = [
        'driver_license_id',
        'inspected_by_user_id',
        'note',
        'file_path',
        'file_name',
        'legacy_id',
    ];

    public function driverLicense(): BelongsTo
    {
        return $this->belongsTo(DriverLicense::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(FuhrparkModels::user(), 'inspected_by_user_id');
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null && $this->file_path !== '';
    }
}
