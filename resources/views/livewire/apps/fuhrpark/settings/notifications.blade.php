<?php

use function Livewire\Volt\title;

title('Benachrichtigungen - Fuhrpark');

?>

<div>
    <x-intranet-app-fuhrpark::fuhrpark-layout heading="Benachrichtigungen" subheading="Benachrichtigungseinstellungen für den Fuhrpark">
        @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'fuhrpark'])
    </x-intranet-app-fuhrpark::fuhrpark-layout>
</div>
