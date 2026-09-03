<?php

use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Hwkdo\IntranetAppFuhrpark\Services\HandoutReturnService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, on, state, title, updated};

title('Fuhrpark - Zentrale');

state([
    'embedded' => false,
    'deskStandortId' => null,
    'showHandoutModal' => false,
    'showReturnModal' => false,
    'selectedBookingId' => null,
    'handoutDriverId' => null,
    'returnKmEnd' => null,
    'returnHasDamage' => false,
    'returnDamageNote' => '',
    'returnChecklist' => [
        'key' => false,
        'license' => false,
        'fuel_card' => false,
    ],
    'signatureData' => null,
    'returnSignatureData' => null,
    'showAllVehiclesOnRoad' => false,
]);

mount(function (): void {
    $this->deskStandortId = FuhrparkModels::vehicleStandortIdFor(Auth::user()?->standort_id);
});

$deskVehicleStandorte = computed(fn () => FuhrparkModels::vehicleStandorte());

$expectedHandouts = computed(function () {
    $resolver = app(BookingStatusResolver::class);

    $query = Booking::query()
        ->whereDate('starts_at', today())
        ->whereDoesntHave('handout')
        ->whereNotIn('purpose', [BookingPurpose::Lock, BookingPurpose::ChargeLock])
        ->with(['vehicle', 'driver'])
        ->orderBy('starts_at');

    if ($this->deskStandortId) {
        $query->whereHas('vehicle', fn ($q) => $q->where('standort_id', $this->deskStandortId));
    }

    return $query
        ->get()
        ->filter(fn (Booking $booking): bool => $resolver->isAwaitingHandoutToday($booking));
});

$expectedReturns = computed(function () {
    $resolver = app(BookingStatusResolver::class);

    $query = Booking::query()
        ->with(['vehicle', 'driver', 'handout.returnRecord'])
        ->whereHas('handout', fn ($q) => $q->whereDoesntHave('returnRecord'))
        ->whereNotIn('purpose', [BookingPurpose::Lock, BookingPurpose::ChargeLock])
        ->orderBy('ends_at');

    if ($this->deskStandortId) {
        $query->whereHas('vehicle', fn ($q) => $q->where('standort_id', $this->deskStandortId));
    }

    return $query
        ->get()
        ->filter(function (Booking $booking) use ($resolver): bool {
            if ($this->showAllVehiclesOnRoad) {
                return $resolver->isCurrentlyHandedOut($booking);
            }

            return $resolver->isAwaitingReturnToday($booking);
        });
});

$selectedBooking = computed(fn () => $this->selectedBookingId
    ? Booking::query()->with(['vehicle', 'driver', 'handout'])->find($this->selectedBookingId)
    : null);

$handoutDrivers = computed(fn () => FuhrparkModels::userQuery()
    ->where('active', true)
    ->orderBy('nachname')
    ->orderBy('vorname')
    ->get(['id', 'vorname', 'nachname']));

$handoutPredecessor = computed(function () {
    $booking = $this->selectedBooking;

    if (! $booking) {
        return null;
    }

    return app(HandoutReturnService::class)->predecessorForHandout($booking);
});

$handoutBlockedByPredecessor = computed(fn () => $this->handoutPredecessor !== null);

$handoutPredecessorStatus = computed(function () {
    $predecessor = $this->handoutPredecessor;

    if (! $predecessor) {
        return null;
    }

    return app(BookingStatusResolver::class)->resolve($predecessor);
});

$selectedHandoutDriverName = computed(function () {
    if (! $this->handoutDriverId) {
        return '';
    }

    $user = FuhrparkModels::userQuery()
        ->whereKey($this->handoutDriverId)
        ->first(['id', 'vorname', 'nachname']);

    return $user?->name ?? '';
});

updated([
    'handoutDriverId' => function (): void {
        $this->signatureData = null;
    },
]);

$openHandout = function (int $bookingId): void {
    $booking = Booking::query()->with(['vehicle', 'driver', 'handout'])->findOrFail($bookingId);
    $this->authorize('handout', $booking);

    $this->selectedBookingId = $bookingId;
    $this->handoutDriverId = $booking->driver_id;
    $this->signatureData = null;
    $this->showHandoutModal = true;
};

$openReturn = function (int $bookingId): void {
    $booking = Booking::query()->findOrFail($bookingId);
    $this->authorize('returnVehicle', $booking);
    $this->selectedBookingId = $bookingId;
    $this->returnKmEnd = $booking->km_start;
    $this->returnSignatureData = null;
    $this->showReturnModal = true;
};

$confirmHandout = function (): void {
    $booking = $this->selectedBooking;
    if (! $booking) {
        return;
    }

    $this->authorize('handout', $booking);

    if ($this->handoutBlockedByPredecessor) {
        return;
    }

    $this->validate(
        [
            'handoutDriverId' => 'required|integer|exists:users,id',
            'signatureData' => 'required|string',
        ],
        [
            'handoutDriverId.required' => 'Bitte wählen Sie einen Fahrer aus.',
            'signatureData.required' => 'Bitte erfassen Sie zuerst eine Unterschrift.',
        ],
    );

    app(HandoutReturnService::class)->handout(
        $booking,
        Auth::user(),
        (int) $this->handoutDriverId,
        ['data' => $this->signatureData],
    );

    $this->showHandoutModal = false;
    $this->signatureData = null;
};

$confirmReturn = function (): void {
    $booking = $this->selectedBooking;
    $handout = $booking?->handout;
    if (! $handout) {
        return;
    }

    $this->validate(
        [
            'returnKmEnd' => 'required|integer|min:0',
            'returnSignatureData' => 'required|string',
        ],
        [
            'returnSignatureData.required' => 'Bitte erfassen Sie zuerst eine Unterschrift.',
        ],
    );

    app(HandoutReturnService::class)->returnVehicle(
        $handout,
        Auth::user(),
        (int) $booking->driver_id,
        (int) $this->returnKmEnd,
        $this->returnChecklist,
        $this->returnHasDamage,
        $this->returnDamageNote ?: null,
        ['data' => $this->returnSignatureData],
    );

    $this->showReturnModal = false;
    $this->returnSignatureData = null;
};

on(['signature-confirmed' => function (string $img_src, string $base64, array $checkboxes) {
    if ($this->showReturnModal) {
        $this->returnSignatureData = $base64;

        return;
    }

    $this->signatureData = $base64;
}]);

?>

<div>
    @if ($embedded)
        @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.partials.desk-content')
    @else
        <x-intranet-app-fuhrpark::fuhrpark-layout heading="Zentrale" subheading="Ausgabe und Rückgabe">
            @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.partials.desk-content')
        </x-intranet-app-fuhrpark::fuhrpark-layout>
    @endif
</div>
