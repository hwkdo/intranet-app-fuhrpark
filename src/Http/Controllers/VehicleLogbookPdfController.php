<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Http\Controllers;

use App\Services\PdfService;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Services\LogbookService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Psr\Http\Message\ResponseInterface;

class VehicleLogbookPdfController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Vehicle $vehicle, LogbookService $logbookService, PdfService $pdfService): ResponseInterface
    {
        $this->authorize('viewLogbook', $vehicle);

        $vehicle->load('category');

        $html = view('intranet-app-fuhrpark::pdf.vehicle-logbook', [
            'vehicle' => $vehicle,
            'entries' => $logbookService->entriesForVehicle($vehicle),
        ])->render();

        $filename = 'fahrtenbuch-'.str_replace(' ', '-', $vehicle->license_plate).'.pdf';

        return $pdfService
            ->inlineFromHtml($html)
            ->withHeader('Content-Disposition', 'inline; filename="'.$filename.'"');
    }
}
