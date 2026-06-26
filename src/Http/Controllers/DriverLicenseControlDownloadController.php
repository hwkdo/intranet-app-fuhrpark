<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Http\Controllers;

use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicenseControl;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverLicenseControlDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(DriverLicenseControl $control): StreamedResponse
    {
        $this->authorize('manage', DriverLicense::class);

        abort_unless(
            $control->hasFile() && Storage::disk('local')->exists($control->file_path),
            404,
        );

        return Storage::disk('local')->download(
            $control->file_path,
            $control->file_name ?? basename($control->file_path),
        );
    }
}
