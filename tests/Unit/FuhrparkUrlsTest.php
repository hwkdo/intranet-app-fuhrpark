<?php

declare(strict_types=1);

use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkUrls;
use Illuminate\Http\Request;

test('fuhrpark urls nutzen app url statt mcp request host', function (): void {
    config(['app.url' => 'https://v3-intranet.localhost.test']);

    app()->instance('request', Request::create('http://localhost/mcp/apps/fuhrpark', 'POST'));

    expect(FuhrparkUrls::publicBaseUrl())->toBe('https://v3-intranet.localhost.test')
        ->and(FuhrparkUrls::route('apps.fuhrpark.meine'))->toBe('https://v3-intranet.localhost.test/apps/fuhrpark/meine');
});

test('fuhrpark app links enthalten kalender und meine buchungen', function (): void {
    config(['app.url' => 'https://intranet.example.test']);

    $links = FuhrparkUrls::appLinks();

    expect($links['kalender']['url'])->toBe('https://intranet.example.test/apps/fuhrpark')
        ->and($links['meine_buchungen']['url'])->toBe('https://intranet.example.test/apps/fuhrpark/meine');
});
