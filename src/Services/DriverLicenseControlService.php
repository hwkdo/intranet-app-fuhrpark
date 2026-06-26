<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicenseControl;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DriverLicenseControlService
{
    public function recordInitialControl(
        Authenticatable|Model $user,
        Authenticatable|Model $inspector,
        ?Carbon $restrictedUntil = null,
        ?string $note = null,
        ?UploadedFile $file = null,
    ): DriverLicenseControl {
        return DB::transaction(function () use ($user, $inspector, $restrictedUntil, $note, $file): DriverLicenseControl {
            $license = DriverLicense::query()->create([
                'user_id' => $user->getKey(),
                'valid_until' => $this->defaultValidUntil(),
                'restricted_until' => $restrictedUntil,
            ]);

            return $this->createControl($license, $inspector, $note, $file);
        });
    }

    public function recordFollowUpControl(
        DriverLicense $license,
        Authenticatable|Model $inspector,
        ?Carbon $restrictedUntil = null,
        ?string $note = null,
        ?UploadedFile $file = null,
    ): DriverLicenseControl {
        return DB::transaction(function () use ($license, $inspector, $restrictedUntil, $note, $file): DriverLicenseControl {
            $license->update([
                'valid_until' => $this->defaultValidUntil(),
                'restricted_until' => $restrictedUntil ?? $license->restricted_until,
            ]);

            return $this->createControl($license->fresh(), $inspector, $note, $file);
        });
    }

    public function extendOneYear(
        DriverLicense $license,
        Authenticatable|Model $inspector,
    ): DriverLicenseControl {
        return $this->recordFollowUpControl(
            license: $license,
            inspector: $inspector,
            note: 'Automatische Verlängerung (+1 Jahr)',
        );
    }

    public function isExpiringSoon(Authenticatable|Model $user, int $days = 21): bool
    {
        $license = DriverLicense::query()->where('user_id', $user->getKey())->first();

        if (! $license) {
            return false;
        }

        $threshold = now()->startOfDay()->addDays($days);

        if ($license->valid_until->lte($threshold)) {
            return true;
        }

        if ($license->restricted_until && $license->restricted_until->lte($threshold)) {
            return true;
        }

        return false;
    }

    private function defaultValidUntil(): Carbon
    {
        return now()->startOfDay()->addYear();
    }

    private function createControl(
        DriverLicense $license,
        Authenticatable|Model $inspector,
        ?string $note,
        ?UploadedFile $file,
    ): DriverLicenseControl {
        $control = DriverLicenseControl::query()->create([
            'driver_license_id' => $license->id,
            'inspected_by_user_id' => $inspector->getKey(),
            'note' => $note,
        ]);

        if ($file) {
            $this->storeControlFile($control, $file);
        }

        return $control->fresh(['driverLicense.user', 'inspectedBy']);
    }

    private function storeControlFile(DriverLicenseControl $control, UploadedFile $file): void
    {
        $directory = "fuhrpark/driver-license-controls/{$control->id}";
        $fileName = $file->getClientOriginalName();
        $path = $file->storeAs($directory, $fileName, 'local');

        $control->update([
            'file_path' => $path,
            'file_name' => $fileName,
        ]);
    }
}
