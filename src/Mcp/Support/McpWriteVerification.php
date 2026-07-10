<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Support;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;

class McpWriteVerification
{
    public static function assistantWriteHint(): string
    {
        return 'Schreibende Aktionen nur als erfolgreich melden, wenn das Tool `success: true` und `verified: true` zurückgibt. `buchungen_anzeigen` und `buchung_detail` ändern nichts — zum Umbuchen `buchung_umbuchen`, zum Löschen `buchung_loeschen`.';
    }

    /**
     * @return array{verified: bool, starts_at_in_database: string|null, ends_at_in_database: string|null}
     */
    public static function verifyReschedule(Booking $booking, CarbonInterface $expectedStart, CarbonInterface $expectedEnd): array
    {
        $fresh = $booking->fresh();

        if ($fresh === null) {
            return [
                'verified' => false,
                'starts_at_in_database' => null,
                'ends_at_in_database' => null,
            ];
        }

        return [
            'verified' => $fresh->starts_at->equalTo($expectedStart) && $fresh->ends_at->equalTo($expectedEnd),
            'starts_at_in_database' => $fresh->starts_at->toIso8601String(),
            'ends_at_in_database' => $fresh->ends_at->toIso8601String(),
        ];
    }

    /**
     * @return array{verified: bool, booking_still_exists: bool}
     */
    public static function verifyDeleted(int $bookingId): array
    {
        $stillExists = Booking::query()->whereKey($bookingId)->exists();

        return [
            'verified' => ! $stillExists,
            'booking_still_exists' => $stillExists,
        ];
    }
}
