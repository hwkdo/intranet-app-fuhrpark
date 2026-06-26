<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Livewire\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppFuhrpark\Data\CategoryBookingOptionData;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Services\BookingAvailabilityService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAvailabilityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class VehicleLockConflictReschedule extends Component
{
    use AuthorizesRequests;

    public int $bookingId;

    public int $excludeVehicleId;

    public ?int $categoryId = null;

    public bool $checked = false;

    public ?int $targetVehicleId = null;

    public bool $notReschedulable = false;

    public bool $resolved = false;

    public string $resolution = '';

    public function mount(int $bookingId, int $excludeVehicleId): void
    {
        $this->bookingId = $bookingId;
        $this->excludeVehicleId = $excludeVehicleId;
    }

    #[Computed]
    public function booking(): Booking
    {
        return Booking::query()
            ->with(['vehicle.category', 'driver'])
            ->findOrFail($this->bookingId);
    }

    /**
     * @return Collection<int, CategoryBookingOptionData>
     */
    #[Computed]
    public function categoryBookingOptions(): Collection
    {
        $booking = $this->booking;

        return app(VehicleAvailabilityService::class)->categoryBookingOptions(
            $booking->starts_at,
            $booking->ends_at,
            $booking->vehicle->standort_id,
            excludeBookingIds: app(BookingAvailabilityService::class)->excludeBookingIds($booking),
            electricRouteKm: $booking->electric_route_km,
            excludeVehicleId: $this->excludeVehicleId,
        );
    }

    #[Computed]
    public function hasNoReschedulableCategories(): bool
    {
        $options = $this->categoryBookingOptions;

        if ($options->isEmpty()) {
            return true;
        }

        return ! $options->contains(fn (CategoryBookingOptionData $option): bool => $option->isAvailable);
    }

    public function updatedCategoryId(): void
    {
        $this->resetCheckState();
    }

    #[Computed]
    public function targetVehicle(): ?Vehicle
    {
        if ($this->targetVehicleId === null) {
            return null;
        }

        return Vehicle::query()->find($this->targetVehicleId);
    }

    public function checkReschedule(): void
    {
        $this->authorize('update', $this->booking);

        $this->validate([
            'categoryId' => 'required|integer',
        ], [
            'categoryId.required' => 'Bitte eine Kategorie wählen.',
        ]);

        $selectedOption = $this->categoryBookingOptions
            ->firstWhere(fn (CategoryBookingOptionData $option): bool => $option->category->id === (int) $this->categoryId);

        if ($selectedOption === null || ! $selectedOption->isAvailable) {
            $this->checked = true;
            $this->notReschedulable = true;
            $this->targetVehicleId = null;

            return;
        }

        $this->resetCheckState();

        $vehicle = app(BookingAvailabilityService::class)->findBestAlternativeVehicle(
            $this->booking,
            (int) $this->categoryId,
            $this->excludeVehicleId,
        );

        $this->checked = true;

        if ($vehicle === null) {
            $this->notReschedulable = true;

            return;
        }

        $this->targetVehicleId = $vehicle->id;
    }

    public function confirmReschedule(): void
    {
        $this->authorize('update', $this->booking);

        if ($this->targetVehicleId === null) {
            return;
        }

        app(BookingService::class)->reschedule(
            $this->booking,
            $this->booking->starts_at,
            $this->booking->ends_at,
            $this->targetVehicleId,
        );

        $this->markResolved('rescheduled');

        Flux::toast(
            text: 'Buchung '.$this->bookingId.' wurde auf '.$this->targetVehicle?->license_plate.' umgebucht.',
            variant: 'success',
        );
    }

    public function deleteBooking(): void
    {
        $this->authorize('delete', $this->booking);

        app(BookingService::class)->cancel(
            $this->booking,
            'Gelöscht wegen Fahrzeugsperre (keine Umbuchung möglich).',
            Auth::user(),
            force: true,
        );

        $this->markResolved('deleted');

        Flux::toast(
            text: 'Buchung '.$this->bookingId.' wurde gelöscht.',
            variant: 'success',
        );
    }

    public function render(): View
    {
        return view('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.vehicle-lock-conflict-reschedule');
    }

    private function resetCheckState(): void
    {
        $this->checked = false;
        $this->notReschedulable = false;
        $this->targetVehicleId = null;
    }

    private function markResolved(string $resolution): void
    {
        $this->resolved = true;
        $this->resolution = $resolution;
        $this->dispatch('lock-conflict-resolved', bookingId: $this->bookingId);
    }
}
