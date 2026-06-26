<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Fahrtenbuch {{ $vehicle->license_plate }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { margin-bottom: 20px; }
        .meta table td { padding: 2px 12px 2px 0; vertical-align: top; }
        table.entries { width: 100%; border-collapse: collapse; }
        table.entries th, table.entries td { border: 1px solid #ccc; padding: 6px 4px; text-align: left; }
        table.entries th { background: #f4f4f5; font-weight: 600; }
        .empty { margin-top: 24px; color: #666; }
    </style>
</head>
<body>
    <h1>Fahrtenbuch {{ $vehicle->license_plate }}</h1>

    <div class="meta">
        <table>
            <tr>
                <td>Hersteller:</td>
                <td>{{ $vehicle->manufacturer ?? '–' }}</td>
            </tr>
            <tr>
                <td>Modell:</td>
                <td>{{ $vehicle->model ?? '–' }}</td>
            </tr>
            <tr>
                <td>Kraftstoff:</td>
                <td>{{ match ($vehicle->fuel_type) {
                    'electric' => 'Elektro',
                    'diesel' => 'Diesel',
                    default => 'Benzin',
                } }}</td>
            </tr>
            <tr>
                <td>Fahrgestell-Nr.:</td>
                <td>{{ $vehicle->vin ?? '–' }}</td>
            </tr>
        </table>
    </div>

    @if ($entries->isEmpty())
        <p class="empty">Bisher keine Fahrten.</p>
    @else
        <table class="entries">
            <thead>
                <tr>
                    <th>Nr.</th>
                    <th>Fahrer</th>
                    <th>Zeitraum</th>
                    <th>Zweck</th>
                    <th>Route</th>
                    <th>KM Anfang</th>
                    <th>KM Ende</th>
                    <th>Arbeitsfahrt</th>
                    <th>Projektfahrt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $entry->user->name ?? '–' }}</td>
                        <td>
                            {{ $entry->booking->starts_at->format('d.m.Y H:i') }}
                            –
                            {{ $entry->booking->ends_at->format('d.m.Y H:i') }}
                        </td>
                        <td>{{ $entry->booking->description }}</td>
                        <td>{{ $entry->route }}</td>
                        <td>{{ $entry->booking->km_start ?? '–' }}</td>
                        <td>{{ $entry->booking->km_end ?? '–' }}</td>
                        <td>{{ $entry->km_commute }}</td>
                        <td>{{ $entry->km_project }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
