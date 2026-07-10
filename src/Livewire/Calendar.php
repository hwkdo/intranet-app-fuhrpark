<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Livewire;

use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Data\BookingStoreData;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingDemandEventService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseService;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAvailabilityService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Fuhrpark - Kalender')]
class Calendar extends Component
{
    use AuthorizesRequests;

    public bool $showBookModal = false;

    public bool $showDetailModal = false;

    public ?int $selectedBookingId = null;

    public string $bookStartDate = '';

    public string $bookEndDate = '';

    public string $bookStartTime = '08:00';

    public string $bookEndTime = '12:00';

    public ?int $bookCategoryId = null;

    public ?int $bookDriverId = null;

    public ?int $bookStandortId = null;

    public string $bookDescription = '';

    public bool $bookIsCommute = false;

    public bool $bookSyncToCalendar = false;

    public ?int $bookElectricRouteKm = null;

    public bool $bookElectricRangeAcknowledged = false;

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->bookDriverId = Auth::id();
        $this->bookStandortId = FuhrparkModels::vehicleStandortIdFor(Auth::user()?->standort_id);
    }

    #[Computed]
    public function bookVehicleStandortOptions(): Collection
    {
        return FuhrparkModels::vehicleStandorte();
    }

    public function updatedBookStandortId(): void
    {
        $this->bookCategoryId = null;
        $this->bookElectricRouteKm = null;
        $this->bookElectricRangeAcknowledged = false;
    }

    #[Computed]
    public function bookDriverOptions(): Collection
    {
        return FuhrparkModels::userQuery()
            ->where('active', true)
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get(['id', 'vorname', 'nachname']);
    }

    #[Computed]
    public function bookCategoryRequiresLicense(): bool
    {
        return $this->selectedBookCategory?->requires_license ?? false;
    }

    #[Computed]
    public function bookingDriverMeetsLicenseRequirement(): bool
    {
        if (! $this->bookCategoryId || ! $this->bookDriverId) {
            return true;
        }

        $driver = FuhrparkModels::user()::query()->find($this->bookDriverId);

        if (! $driver) {
            return false;
        }

        return app(DriverLicenseService::class)->canBook($driver, (int) $this->bookCategoryId);
    }

    #[Computed]
    public function isBookingForSelf(): bool
    {
        return Auth::id() !== null && (int) $this->bookDriverId === (int) Auth::id();
    }

    #[Computed]
    public function bookCategoryOptions(): Collection
    {
        $standortId = $this->bookStandortId;
        $start = $this->bookPeriodStart();
        $end = $this->bookPeriodEnd();

        if (! $standortId || ! $start || ! $end || $end->lte($start)) {
            return collect();
        }

        return app(VehicleAvailabilityService::class)->categoryBookingOptions(
            $start,
            $end,
            $standortId,
            electricRouteKm: $this->bookElectricRouteKm,
        );
    }

    #[Computed]
    public function selectedBooking(): ?Booking
    {
        if (! $this->selectedBookingId) {
            return null;
        }

        return Booking::query()
            ->with(['vehicle.category', 'driver', 'handout.returnRecord', 'logbookEntry'])
            ->find($this->selectedBookingId);
    }

    public function updatedBookStartDate(): void
    {
        $this->resetBookElectricRangeAcknowledgement();

        if ($this->bookEndDate !== '' && $this->bookEndDate < $this->bookStartDate) {
            $this->bookEndDate = $this->bookStartDate;
        }

        $this->bookCategoryId = null;
    }

    public function updatedBookEndDate(): void
    {
        $this->resetBookElectricRangeAcknowledgement();
        $this->bookCategoryId = null;
    }

    public function updatedBookStartTime(): void
    {
        $this->resetBookElectricRangeAcknowledgement();
        $this->bookCategoryId = null;
    }

    public function updatedBookEndTime(): void
    {
        $this->resetBookElectricRangeAcknowledgement();
        $this->bookCategoryId = null;
    }

    public function updatedBookCategoryId(): void
    {
        $this->bookElectricRangeAcknowledged = false;

        if (! $this->bookRequiresElectricRoute) {
            $this->bookElectricRouteKm = null;
        }
    }

    public function updatedBookElectricRouteKm(): void
    {
        $this->bookElectricRangeAcknowledged = false;
    }

    private function resetBookElectricRangeAcknowledgement(): void
    {
        $this->bookElectricRangeAcknowledged = false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function calendarEvents(string $start, string $end): array
    {
        return app(BookingService::class)->calendarEvents(
            Carbon::parse($start),
            Carbon::parse($end),
            Auth::id(),
        );
    }

    public function openBookModal(string $date): void
    {
        if (Carbon::parse($date)->startOfDay()->lt(now()->startOfDay())) {
            return;
        }

        $this->bookStartDate = $date;
        $this->bookEndDate = $date;
        $this->bookStartTime = '08:00';
        $this->bookEndTime = '12:00';
        $this->bookCategoryId = null;
        $this->bookElectricRouteKm = null;
        $this->bookElectricRangeAcknowledged = false;
        $this->bookDescription = '';
        $this->bookSyncToCalendar = false;

        if (! $this->bookStandortId) {
            $this->bookStandortId = FuhrparkModels::vehicleStandortIdFor(Auth::user()?->standort_id);
        }

        $this->showBookModal = true;
    }

    public function openDetailModal(int $bookingId): void
    {
        $this->selectedBookingId = $bookingId;
        $this->showDetailModal = true;
    }

    #[Computed]
    public function selectedBookCategory(): ?VehicleCategory
    {
        if (! $this->bookCategoryId) {
            return null;
        }

        return VehicleCategory::query()->find($this->bookCategoryId);
    }

    #[Computed]
    public function bookRequiresElectricRoute(): bool
    {
        return $this->selectedBookCategory?->is_electric ?? false;
    }

    #[Computed]
    public function electricRouteExceedsCategoryRange(): bool
    {
        if (! $this->bookRequiresElectricRoute || ! $this->bookElectricRouteKm) {
            return false;
        }

        $maxRange = $this->selectedBookCategory?->averageElectricRangeKm();

        if (! $maxRange) {
            return false;
        }

        return (int) $this->bookElectricRouteKm > $maxRange;
    }

    public function acknowledgeElectricRangeLimit(): void
    {
        if (! $this->electricRouteExceedsCategoryRange) {
            return;
        }

        $this->bookElectricRangeAcknowledged = true;
    }

    public function canShowBookForm(): bool
    {
        if ($this->electricRouteExceedsCategoryRange && ! $this->bookElectricRangeAcknowledged) {
            return false;
        }

        return true;
    }

    public function canSubmitBooking(): bool
    {
        if (! $this->canShowBookForm() || ! $this->bookStandortId) {
            return false;
        }

        return $this->bookingDriverMeetsLicenseRequirement;
    }

    public function createBooking(): void
    {
        $rules = [
            'bookStartDate' => 'required|date|after_or_equal:today',
            'bookEndDate' => 'required|date|after_or_equal:bookStartDate',
            'bookStartTime' => 'required',
            'bookEndTime' => 'required',
            'bookCategoryId' => ['required', 'integer', 'exists:intranet_app_fuhrpark_vehicle_categories,id'],
            'bookDriverId' => 'required|integer',
            'bookStandortId' => 'required|integer',
            'bookDescription' => 'required|string|max:255',
        ];

        if ($this->bookRequiresElectricRoute) {
            $rules['bookElectricRouteKm'] = 'required|integer|min:1';
        }

        $this->validate($rules);

        if ($this->electricRouteExceedsCategoryRange && ! $this->bookElectricRangeAcknowledged) {
            throw ValidationException::withMessages([
                'bookElectricRouteKm' => ['Die geplante Strecke übersteigt die Reichweite dieser Fahrzeugkategorie.'],
            ]);
        }

        [$start, $end] = $this->validatedBookPeriod();

        $standortId = $this->bookStandortId;

        if (! $standortId || ! FuhrparkModels::vehicleStandorte()->pluck('id')->contains($standortId)) {
            throw ValidationException::withMessages([
                'bookStandortId' => ['Bitte einen gültigen Fahrzeugstandort wählen.'],
            ]);
        }

        $categoryAvailable = app(VehicleAvailabilityService::class)
            ->categoryBookingOptions($start, $end, $standortId, electricRouteKm: $this->bookElectricRouteKm)
            ->contains(fn ($option): bool => $option->category->id === (int) $this->bookCategoryId && $option->isAvailable);

        if (! $categoryAvailable) {
            $hadAlternative = app(VehicleAvailabilityService::class)
                ->categoryBookingOptions($start, $end, $standortId, electricRouteKm: $this->bookElectricRouteKm)
                ->contains(fn ($option): bool => $option->isAvailable && $option->category->id !== (int) $this->bookCategoryId);

            app(BookingDemandEventService::class)->record(
                userId: (int) Auth::id(),
                startsAt: $start,
                endsAt: $end,
                reason: BookingDemandReason::NoVehicleInCategory,
                source: BookingDemandSource::Create,
                standortId: $standortId,
                vehicleCategoryId: (int) $this->bookCategoryId,
                driverId: (int) $this->bookDriverId,
                hadAlternativeCategory: $hadAlternative,
            );

            throw ValidationException::withMessages([
                'bookCategoryId' => ['Diese Kategorie ist im gewählten Zeitraum ausgebucht.'],
            ]);
        }

        app(BookingService::class)->create(
            new BookingStoreData(
                driverId: (int) $this->bookDriverId,
                description: $this->bookDescription,
                startsAt: $start,
                endsAt: $end,
                vehicleCategoryId: (int) $this->bookCategoryId,
                standortId: $standortId,
                isCommute: $this->bookIsCommute,
                electricRouteKm: $this->bookElectricRouteKm ? (int) $this->bookElectricRouteKm : null,
                syncToCalendar: $this->bookSyncToCalendar,
            ),
            Auth::user(),
        );

        $this->showBookModal = false;
        $this->dispatch('fuhrpark-calendar-refresh');
    }

    public function startReschedule(): void
    {
        $booking = $this->selectedBooking;
        if (! $booking) {
            return;
        }

        $this->authorize('update', $booking);
        $this->showDetailModal = false;
        $this->dispatch('open-booking-reschedule', bookingId: $booking->id);
    }

    public function updated(mixed $property): void
    {
        if (! in_array($property, [
            'bookStartDate',
            'bookEndDate',
            'bookStartTime',
            'bookEndTime',
            'bookStandortId',
        ], true)) {
            return;
        }

        if (! $this->showBookModal || ! $this->bookStandortId || ! $this->hasValidBookPeriod()) {
            return;
        }

        if ($this->hasAvailableBookCategory()) {
            return;
        }

        [$start, $end] = $this->validatedBookPeriod();

        app(BookingDemandEventService::class)->record(
            userId: (int) Auth::id(),
            startsAt: $start,
            endsAt: $end,
            reason: BookingDemandReason::AllCategoriesUnavailable,
            source: BookingDemandSource::Preview,
            standortId: $this->bookStandortId,
            driverId: $this->bookDriverId,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function validatedBookPeriod(): array
    {
        return $this->validatedPeriod(
            $this->bookPeriodStart(),
            $this->bookPeriodEnd(),
            'bookEndTime',
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

    private function bookPeriodStart(): ?Carbon
    {
        if ($this->bookStartDate === '' || $this->bookStartTime === '') {
            return null;
        }

        return Carbon::parse($this->bookStartDate.' '.$this->bookStartTime);
    }

    private function bookPeriodEnd(): ?Carbon
    {
        if ($this->bookEndDate === '' || $this->bookEndTime === '') {
            return null;
        }

        return Carbon::parse($this->bookEndDate.' '.$this->bookEndTime);
    }

    public function bookerStandortId(): ?int
    {
        return $this->bookStandortId;
    }

    public function hasValidBookPeriod(): bool
    {
        $start = $this->bookPeriodStart();
        $end = $this->bookPeriodEnd();

        return $start !== null && $end !== null && $end->gt($start);
    }

    public function hasAvailableBookCategory(): bool
    {
        return $this->bookCategoryOptions->contains(fn ($option): bool => $option->isAvailable);
    }

    #[Computed]
    public function canCancelSelectedBooking(): bool
    {
        $booking = $this->selectedBooking;

        if (! $booking) {
            return false;
        }

        return app(BookingStatusResolver::class)->canBeCancelledByDriver($booking);
    }

    public function cancelBooking(?string $reason = null): void
    {
        $booking = $this->selectedBooking;
        if (! $booking) {
            return;
        }

        if (! app(BookingStatusResolver::class)->canBeCancelledByDriver($booking)) {
            abort(403);
        }

        app(BookingService::class)->cancel($booking, $reason, Auth::user());

        $this->showDetailModal = false;
        $this->dispatch('fuhrpark-calendar-refresh');
    }

    public function openCancelModal(): void
    {
        $booking = $this->selectedBooking;
        if (! $booking) {
            return;
        }

        if (! app(BookingStatusResolver::class)->canBeCancelledByDriver($booking)) {
            abort(403);
        }

        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function confirmCancelBooking(): void
    {
        $booking = $this->selectedBooking;
        if (! $booking) {
            return;
        }

        if (! app(BookingStatusResolver::class)->canBeCancelledByDriver($booking)) {
            abort(403);
        }

        if (app(BookingStatusResolver::class)->requiresCancellationReason($booking)) {
            $this->validate(
                ['cancelReason' => 'required|string|min:3'],
                ['cancelReason.required' => 'Bitte geben Sie eine Begründung an.'],
            );
        }

        app(BookingService::class)->cancel($booking, $this->cancelReason ?: null, Auth::user());

        $this->showCancelModal = false;
        $this->showDetailModal = false;
        $this->cancelReason = '';
        $this->dispatch('fuhrpark-calendar-refresh');
    }

    #[Computed]
    public function cancelRequiresReason(): bool
    {
        $booking = $this->selectedBooking;

        if (! $booking) {
            return false;
        }

        return app(BookingStatusResolver::class)->requiresCancellationReason($booking);
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $user = Auth::user();

        if ($user === null || ! $user->can('see-app-fuhrpark')) {
            return [];
        }

        return [
            'echo-private:fuhrpark-channel,.buchung-changed' => 'refreshCalendarFromBroadcast',
        ];
    }

    public function refreshCalendarFromBroadcast(): void
    {
        $this->dispatch('fuhrpark-calendar-refresh');
    }

    public function render(): View
    {
        return view('intranet-app-fuhrpark::livewire.apps.fuhrpark.calendar');
    }
}
