@component('mail::message')
# Nicht angetretene Fahrt – Buchung gelöscht

Ein Fahrer hat eine überfällige Buchung gelöscht. Das Fahrzeug war dadurch unnötig blockiert.

**Fahrzeug:** {{ $booking->vehicle->license_plate }}  
**Fahrer:** {{ $booking->driver->name ?? '-' }}  
**Geplanter Zeitraum:** {{ $booking->starts_at->format('d.m.Y H:i') }} – {{ $booking->ends_at->format('d.m.Y H:i') }}  
**Begründung:** {{ $reason }}  
**Gelöscht von:** {{ $cancelledBy->name ?? '-' }}
@endcomponent
