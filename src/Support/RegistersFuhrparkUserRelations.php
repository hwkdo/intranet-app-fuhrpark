<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Support;

use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class RegistersFuhrparkUserRelations
{
    public static function register(): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = FuhrparkModels::user();

        $userClass::resolveRelationUsing(
            'driverLicense',
            fn (Model $user): HasOne => $user->hasOne(DriverLicense::class, 'user_id'),
        );

        $userClass::resolveRelationUsing(
            'fuhrparkBookingsAsBooker',
            fn (Model $user): HasMany => $user->hasMany(Booking::class, 'user_id'),
        );

        $userClass::resolveRelationUsing(
            'fuhrparkBookingsAsDriver',
            fn (Model $user): HasMany => $user->hasMany(Booking::class, 'driver_id'),
        );
    }
}
