<?php

use function Livewire\Volt\{title};

title('Fuhrpark - Meine Einstellungen');

?>

<x-intranet-app-fuhrpark::fuhrpark-layout heading="Meine Einstellungen" subheading="Persönliche Einstellungen für die Fuhrpark App">
    @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'fuhrpark'])
</x-intranet-app-fuhrpark::fuhrpark-layout>
