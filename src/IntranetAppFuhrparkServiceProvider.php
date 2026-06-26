<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark;

use Hwkdo\IntranetAppFuhrpark\Commands\ImportDriverLicensesCommand;
use Hwkdo\IntranetAppFuhrpark\Contracts\BookingCalendarSyncInterface;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Policies\BookingPolicy;
use Hwkdo\IntranetAppFuhrpark\Policies\DriverLicensePolicy;
use Hwkdo\IntranetAppFuhrpark\Policies\VehiclePolicy;
use Hwkdo\IntranetAppFuhrpark\Services\NullBookingCalendarSync;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppFuhrparkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('intranet-app-fuhrpark')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(ImportDriverLicensesCommand::class)
            ->discoversMigrations();
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(BookingCalendarSyncInterface::class, NullBookingCalendarSync::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(DriverLicense::class, DriverLicensePolicy::class);

        $this->registerFuhrparkGates();

        Livewire::addNamespace(
            namespace: 'intranet-app-fuhrpark',
            viewPath: __DIR__.'/../resources/views/livewire',
            classNamespace: 'Hwkdo\IntranetAppFuhrpark\Livewire',
            classPath: __DIR__.'/../src/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
        );

        $this->app->booted(function (): void {
            Volt::mount(__DIR__.'/../resources/views/livewire');
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/channels.php');
    }

    protected function registerFuhrparkGates(): void
    {
        Gate::define('fuhrpark.book', fn ($user): bool => $user->can('see-app-fuhrpark'));

        Gate::define('fuhrpark.view-team', function ($user): bool {
            return $user->can('see-app-fuhrpark') && ($user->ist_vorgesetzter ?? false);
        });

        Gate::define('fuhrpark.operate-desk', fn ($user): bool => $user->can('operate-app-fuhrpark-zentrale'));

        Gate::define('fuhrpark.manage', fn ($user): bool => $user->can('manage-app-fuhrpark'));

        Gate::define('fuhrpark.manage-driver-licenses', fn ($user): bool => $user->can('manage-app-fuhrpark-driver-licenses'));
    }
}
