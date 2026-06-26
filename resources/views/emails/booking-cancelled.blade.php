@component('mail::message')
# Buchung gelöscht

Ihre Buchung für **{{ $booking->vehicle->license_plate }}** ({{ $booking->starts_at->format('d.m.Y H:i') }}) wurde gelöscht.
@endcomponent
