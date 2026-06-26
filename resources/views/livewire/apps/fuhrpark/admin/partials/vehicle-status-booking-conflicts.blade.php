@props([
    'bookings',
    'vehicleId',
    'emptyMessage',
    'warningMessage',
])

@if ($bookings->isNotEmpty())
    <flux:callout variant="warning">
        {{ $warningMessage }}
    </flux:callout>

    <div class="space-y-3">
        @foreach ($bookings as $booking)
            @livewire(\Hwkdo\IntranetAppFuhrpark\Livewire\Admin\VehicleLockConflictReschedule::class, [
                'bookingId' => $booking->id,
                'excludeVehicleId' => $vehicleId,
            ], key('vehicle-status-conflict-'.$vehicleId.'-'.$booking->id))
        @endforeach
    </div>
@else
    <flux:callout variant="success">
        {{ $emptyMessage }}
    </flux:callout>
@endif
