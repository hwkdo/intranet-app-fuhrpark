<?php

use function Livewire\Volt\title;

title('Hintergrund');

?>

<div>
    <x-intranet-app-fuhrpark::fuhrpark-layout heading="Hintergrund" subheading="Geplante Tasks, Jobs, Mails und Benachrichtigungen">
        @livewire('intranet-app-base::background-activities', ['appIdentifier' => 'fuhrpark'])
    </x-intranet-app-fuhrpark::fuhrpark-layout>
</div>
