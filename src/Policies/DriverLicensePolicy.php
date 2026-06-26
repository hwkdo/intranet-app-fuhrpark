<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Policies;

use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicenseControl;
use Illuminate\Contracts\Auth\Authenticatable;

class DriverLicensePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('manage-app-fuhrpark-driver-licenses');
    }

    public function manage(Authenticatable $user): bool
    {
        return $user->can('manage-app-fuhrpark-driver-licenses');
    }

    public function downloadControl(Authenticatable $user, DriverLicenseControl $control): bool
    {
        return $user->can('manage-app-fuhrpark-driver-licenses');
    }

    public function view(Authenticatable $user, DriverLicense $driverLicense): bool
    {
        return $user->can('manage-app-fuhrpark-driver-licenses');
    }
}
