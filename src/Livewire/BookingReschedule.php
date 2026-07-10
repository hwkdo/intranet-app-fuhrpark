<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Livewire;

use Carbon\Carbon;
use Flux\Flux;
use Hwkdo\IntranetAppFuhrpark\Data\AvailabilityResult;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingAvailabilityService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingDemandEventService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class BookingReschedule extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public ?int $bookingId = null;

    public string $rescheduleStartDate = '';

    public string $rescheduleEndDate = '';

    public string $rescheduleStartTime = '08:00';

    public string $rescheduleEndTime = '12:00';

    public ?int $rescheduleVehicleId = null;

    public ?int $rescheduleOtherCategoryId = null;

    public ?int $rescheduleCategoryId = null;

    public bool $reschedulePreferSameVehicle = true;

    public bool $rescheduleChecked = false;

    #[On('open-booking-reschedule')]
    public function open(int $bookingId): void
    {
        $booking = Booking::query()
            ->with(['vehicle.category', 'handout.returnRecord', 'logbookEntry'])
            ->findOrFail($bookingId);

        $this->authorize('update', $booking);

        $this->bookingId = $bookingId;
        $this->rescheduleStartDate = $booking->starts_at->format('Y-m-d');
        $this->rescheduleEndDate = $booking->ends_at->format('Y-m-d');
        $this->rescheduleStartTime = $booking->starts_at->format('H:i');
        $this->rescheduleEndTime = $booking->ends_at->format('H:i');
        $this->rescheduleVehicleId = null;
        $this->rescheduleOtherCategoryId = null;
        $this->rescheduleCategoryId = null;
        $this->reschedulePreferSameVehicle = true;
        $this->rescheduleChecked = false;
        $this->showModal = true;
    }

    public function checkRescheduleAvailability(): void
    {
        $booking = $this->selectedBooking;
        if (! $booking) {
            return;
        }

        $this->authorize('update', $booking);

        $this->validateRescheduleTimes();

        [$start, $end] = $this->validatedReschedulePeriod();

        $result = app(BookingAvailabilityService::class)->findAlternatives($booking, $start, $end);

        $this->rescheduleChecked = true;
        $this->rescheduleVehicleId = null;
        $this->rescheduleOtherCategoryId = null;
        $this->rescheduleCategoryId = null;

        if ($this->canSelectRescheduleVehicle) {
            $this->preselectRescheduleVehicle($result, $booking);
        } elseif ($result->hasSameCategoryAlternatives()) {
            $this->rescheduleCategoryId = $booking->vehicle->vehicle_category_id;
        }

        if ($result->noneAvailable) {
            app(BookingDemandEventService::class)->record(
                userId: (int) Auth::id(),
                startsAt: $start,
                endsAt: $end,
                reason: BookingDemandReason::RescheduleUnavailable,
                source: BookingDemandSource::Reschedule,
                standortId: $booking->vehicle->standort_id,
                vehicleCategoryId: $booking->vehicle->vehicle_category_id,
                driverId: (int) $booking->driver_id,
            );
        }
    }

    public function updatedRescheduleStartDate(): void
    {
        $this->resetRescheduleCheck();

        if ($this->rescheduleEndDate !== '' && $this->rescheduleEndDate < $this->rescheduleStartDate) {
            $this->rescheduleEndDate = $this->rescheduleStartDate;
        }
    }

    public function updatedRescheduleEndDate(): void
    {
        $this->resetRescheduleCheck();
    }

    public function updatedRescheduleStartTime(): void
    {
        $this->resetRescheduleCheck();
    }

    public function updatedRescheduleEndTime(): void
    {
        $this->resetRescheduleCheck();
    }

    public function updatedReschedulePreferSameVehicle(): void
    {
        if (! $this->rescheduleChecked) {
            return;
        }

        $booking = $this->selectedBooking;
        if (! $booking) {
            return;
        }

        if ($this->reschedulePreferSameVehicle) {
            $this->preselectRescheduleVehicle($this->rescheduleAvailability, $booking);
        } else {
            $this->rescheduleVehicleId = null;
        }
    }

    public function selectRescheduleOtherCategory(int $categoryId): void
    {
        if ($this->canSelectRescheduleVehicle) {
            $this->rescheduleOtherCategoryId = $categoryId;
            $this->rescheduleCategoryId = $categoryId;
            $this->rescheduleVehicleId = null;

            return;
        }

        $this->rescheduleByCategoryForUser($categoryId);
    }

    public function selectRescheduleVehicle(int $vehicleId): void
    {
        $this->rescheduleVehicleId = $vehicleId;

        if ($this->rescheduleAvailability->sameCategory->contains('id', $vehicleId)) {
            $this->rescheduleOtherCategoryId = null;
        }
    }

    public function confirmReschedule(): void
    {
        $booking = $this->selectedBooking;
        if (! $booking || ! $this->rescheduleChecked) {
            return;
        }

        if (! $this->canSelectRescheduleVehicle) {
            if (! $this->rescheduleCategoryId) {
                return;
            }

            $this->rescheduleByCategoryForUser((int) $this->rescheduleCategoryId);

            return;
        }

        if (! $this->rescheduleVehicleId) {
            return;
        }

        $this->authorize('update', $booking);

        $this->validateRescheduleTimes();

        [$start, $end] = $this->validatedReschedulePeriod();

        if (! $this->isRescheduleVehicleAllowed((int) $this->rescheduleVehicleId)) {
            throw ValidationException::withMessages([
                'rescheduleVehicleId' => ['Das gewählte Fahrzeug ist im Zeitraum nicht verfügbar.'],
            ]);
        }

        app(BookingService::class)->reschedule(
            $booking,
            $start,
            $end,
            (int) $this->rescheduleVehicleId,
        );

        $this->finishReschedule();
        Flux::toast(text: 'Buchung wurde erfolgreich umgebucht.', variant: 'success');
    }

    #[Computed]
    public function selectedBooking(): ?Booking
    {
        if (! $this->bookingId) {
            return null;
        }

        return Booking::query()
            ->with(['vehicle.category', 'driver', 'handout.returnRecord', 'logbookEntry'])
            ->find($this->bookingId);
    }

    #[Computed]
    public function rescheduleAvailability(): AvailabilityResult
    {
        if (! $this->showModal || ! $this->bookingId || ! $this->rescheduleChecked) {
            return new AvailabilityResult(collect(), [], true);
        }

        $booking = $this->selectedBooking;
        $start = $this->reschedulePeriodStart();
        $end = $this->reschedulePeriodEnd();

        if (! $booking || ! $start || ! $end || $end->lte($start)) {
            return new AvailabilityResult(collect(), [], true);
        }

        return app(BookingAvailabilityService::class)->findAlternatives($booking, $start, $end);
    }

    #[Computed]
    public function canSelectRescheduleVehicle(): bool
    {
        return Auth::user()?->can('manage-app-fuhrpark') ?? false;
    }

    #[Computed]
    public function rescheduleOtherCategoryVehicles(): Collection
    {
        if (! $this->rescheduleOtherCategoryId) {
            return collect();
        }

        foreach ($this->rescheduleAvailability->otherCategories as $group) {
            if ($group->category->id === $this->rescheduleOtherCategoryId) {
                return $group->vehicles;
            }
        }

        return collect();
    }

    public function render(): View
    {
        return view('intranet-app-fuhrpark::livewire.booking-reschedule');
    }

    private function rescheduleByCategoryForUser(int $categoryId): void
    {
        $booking = $this->selectedBooking;
        if (! $booking || ! $this->rescheduleChecked) {
            return;
        }

        $this->authorize('update', $booking);

        try {
            $this->validateRescheduleTimes();

            if (! $this->isRescheduleCategoryAllowed($categoryId)) {
                Flux::toast(text: 'Diese Kategorie ist im gewählten Zeitraum nicht verfügbar.', variant: 'danger');

                return;
            }

            [$start, $end] = $this->validatedReschedulePeriod();

            app(BookingService::class)->rescheduleByCategory($booking, $start, $end, $categoryId);

            $this->finishReschedule();
            Flux::toast(text: 'Buchung wurde erfolgreich umgebucht.', variant: 'success');
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Umbuchen fehlgeschlagen.';

            Flux::toast(text: $message, variant: 'danger');
        }
    }

    private function finishReschedule(): void
    {
        $this->showModal = false;
        $this->dispatch('fuhrpark-calendar-refresh');
        $this->dispatch('fuhrpark-booking-rescheduled');
    }

    private function validateRescheduleTimes(): void
    {
        $this->validate([
            'rescheduleStartDate' => 'required|date',
            'rescheduleEndDate' => 'required|date|after_or_equal:rescheduleStartDate',
            'rescheduleStartTime' => 'required',
            'rescheduleEndTime' => 'required',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function validatedReschedulePeriod(): array
    {
        return $this->validatedPeriod(
            $this->reschedulePeriodStart(),
            $this->reschedulePeriodEnd(),
            'rescheduleEndTime',
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function validatedPeriod(?Carbon $start, ?Carbon $end, string $errorField): array
    {
        if (! $start || ! $end || $end->lte($start)) {
            throw ValidationException::withMessages([
                $errorField => ['Das Ende muss nach dem Beginn liegen.'],
            ]);
        }

        return [$start, $end];
    }

    private function reschedulePeriodStart(): ?Carbon
    {
        if ($this->rescheduleStartDate === '' || $this->rescheduleStartTime === '') {
            return null;
        }

        return Carbon::parse($this->rescheduleStartDate.' '.$this->rescheduleStartTime);
    }

    private function reschedulePeriodEnd(): ?Carbon
    {
        if ($this->rescheduleEndDate === '' || $this->rescheduleEndTime === '') {
            return null;
        }

        return Carbon::parse($this->rescheduleEndDate.' '.$this->rescheduleEndTime);
    }

    private function resetRescheduleCheck(): void
    {
        $this->rescheduleChecked = false;
        $this->rescheduleVehicleId = null;
        $this->rescheduleOtherCategoryId = null;
        $this->rescheduleCategoryId = null;
    }

    private function preselectRescheduleVehicle(AvailabilityResult $result, Booking $booking): void
    {
        if (! $this->reschedulePreferSameVehicle) {
            return;
        }

        $currentVehicleId = $booking->vehicle_id;

        if ($result->hasSameCategoryAlternatives() && $result->sameCategory->contains('id', $currentVehicleId)) {
            $this->rescheduleVehicleId = $currentVehicleId;
        }
    }

    private function isRescheduleVehicleAllowed(int $vehicleId): bool
    {
        $result = $this->rescheduleAvailability;

        if ($result->sameCategory->contains('id', $vehicleId)) {
            return true;
        }

        foreach ($result->otherCategories as $group) {
            if ($group->vehicles->contains('id', $vehicleId)) {
                return true;
            }
        }

        return false;
    }

    private function isRescheduleCategoryAllowed(int $categoryId): bool
    {
        $booking = $this->selectedBooking;
        if (! $booking) {
            return false;
        }

        $result = $this->rescheduleAvailability;

        if ($result->hasSameCategoryAlternatives() && $booking->vehicle->vehicle_category_id === $categoryId) {
            return true;
        }

        foreach ($result->otherCategories as $group) {
            if ($group->category->id === $categoryId) {
                return true;
            }
        }

        return false;
    }
}
