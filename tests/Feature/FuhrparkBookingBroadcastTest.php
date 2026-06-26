<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Events\FuhrparkBookingChanged;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

test('booking service dispatches fuhrpark booking changed broadcast event on create', function (): void {
    Event::fake([FuhrparkBookingChanged::class]);

    $booking = Booking::factory()->create();

    FuhrparkBookingChanged::dispatch($booking, 'created');

    Event::assertDispatched(FuhrparkBookingChanged::class, function (FuhrparkBookingChanged $event) use ($booking): bool {
        return $event->booking->is($booking)
            && $event->action === 'created';
    });
});

test('fuhrpark booking changed broadcasts on private fuhrpark channel', function (): void {
    $booking = Booking::factory()->create();

    $event = new FuhrparkBookingChanged($booking, 'rescheduled');

    expect($event->broadcastOn())->toEqual([new PrivateChannel('fuhrpark-channel')])
        ->and($event->broadcastAs())->toBe('buchung-changed')
        ->and($event->broadcastWith())->toBe([
            'booking_id' => $booking->id,
            'action' => 'rescheduled',
        ]);
});

test('fuhrpark channel requires see-app-fuhrpark permission', function (): void {
    Permission::findOrCreate('see-app-fuhrpark', 'web');

    $user = User::factory()->create();
    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo('see-app-fuhrpark');

    $callback = app(BroadcastManager::class)->driver()->getChannels()['fuhrpark-channel'];

    expect($callback($user))->toBeFalse()
        ->and($callback($authorizedUser))->toBeTrue();
});
