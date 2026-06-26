@component('mail::message')
# Fahrzeugschaden gemeldet

Fahrzeug **{{ $booking->vehicle->license_plate }}**  
Buchung #{{ $booking->id }}  
Gemeldet von: {{ $reporter->name ?? 'Unbekannt' }}

@component('mail::button', ['url' => route('apps.fuhrpark.meine')])
Zum Fuhrpark
@endcomponent
@endcomponent
