<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Policies;

use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Illuminate\Contracts\Auth\Authenticatable;

class VehiclePolicy
{
    public function manage(Authenticatable $user): bool
    {
        return $user->can('manage-app-fuhrpark');
    }

    public function update(Authenticatable $user, Vehicle $vehicle): bool
    {
        return $user->can('manage-app-fuhrpark');
    }

    public function viewLogbook(Authenticatable $user, Vehicle $vehicle): bool
    {
        return $user->can('manage-app-fuhrpark');
    }
}
