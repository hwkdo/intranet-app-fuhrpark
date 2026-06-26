@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $defaultNavItems = [
        ['label' => 'Kalender', 'href' => route('apps.fuhrpark.index'), 'icon' => 'calendar', 'description' => 'Fahrzeuge buchen', 'buttonText' => 'Kalender öffnen'],
        ['label' => 'Meine Buchungen', 'href' => route('apps.fuhrpark.meine'), 'icon' => 'clipboard-document-list', 'description' => 'Eigene Buchungen verwalten', 'buttonText' => 'Buchungen anzeigen'],
        ['label' => 'Mein Team', 'href' => route('apps.fuhrpark.team'), 'icon' => 'user-group', 'description' => 'Buchungen der Mitarbeitenden', 'buttonText' => 'Team anzeigen', 'permission' => 'fuhrpark.view-team'],
        ['label' => 'Zentrale', 'href' => route('apps.fuhrpark.zentrale'), 'icon' => 'building-office', 'description' => 'Ausgabe und Rückgabe', 'buttonText' => 'Zentrale öffnen', 'permission' => 'operate-app-fuhrpark-zentrale'],
        ['label' => 'Führerscheine', 'href' => route('apps.fuhrpark.fuehrerscheine'), 'icon' => 'identification', 'description' => 'Führerscheinkontrollen verwalten', 'buttonText' => 'Führerscheine öffnen', 'permission' => 'manage-app-fuhrpark-driver-licenses'],
        ['label' => 'Meine Einstellungen', 'href' => route('apps.fuhrpark.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Persönliche Einstellungen', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'App-Info', 'href' => route('apps.fuhrpark.info'), 'icon' => 'information-circle', 'description' => 'Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Admin', 'href' => route('apps.fuhrpark.admin.index'), 'icon' => 'shield-check', 'description' => 'Stammdaten und Statistik', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-fuhrpark'],
    ];

    $navItems = ! empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('fuhrpark');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

<x-intranet-app-base::app-layout
    app-identifier="fuhrpark"
    :heading="$heading"
    :subheading="$subheading"
    :nav-items="$navItems"
    :wrap-in-card="! request()->routeIs('apps.fuhrpark.index')"
>
    {{ $slot }}
</x-intranet-app-base::app-layout>
