<?php

declare(strict_types=1);

use App\Data\UserSettings;
use Hwkdo\IntranetAppFuhrpark\Models\IntranetAppFuhrparkSettings;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkAiChatSystemPrompt;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, title};

title('Fuhrpark - KI-Chat');

$appSettings = computed(function () {
    return IntranetAppFuhrparkSettings::current()?->settings;
});

$apiKey = computed(function () {
    $user = Auth::user();

    if (! $user) {
        return '';
    }

    $settings = UserSettings::from($user->settings);

    return (string) ($settings->ai->openWebUiApiToken ?? '');
});

$model = computed(function () {
    return (string) ($this->appSettings?->openWebUiModel ?? 'intranet-app-fuhrpark');
});

$baseUrl = computed(function () {
    return (string) config('openwebui-api-laravel.base_api_url_ollama', 'https://chat.ai.hwk-do.com/api');
});

$hasApiKey = computed(fn (): bool => $this->apiKey !== '');

?>

<div>
    <x-intranet-app-fuhrpark::fuhrpark-layout heading="KI-Chat" subheading="Fahrzeugbuchungen mit KI und MCP-Server">
        @if ($this->hasApiKey)
            @livewire('prism-chat', [
                'appIdentifier' => 'fuhrpark',
                'model' => $this->model,
                'apiKey' => $this->apiKey,
                'baseUrl' => $this->baseUrl,
                'useMcpTools' => true,
                'additionalSystemPrompt' => FuhrparkAiChatSystemPrompt::additionalPrompt(),
            ])
        @else
            <flux:card class="glass-card">
                <flux:callout variant="warning" class="mb-4">
                    <flux:heading size="sm">API-Token fehlt</flux:heading>
                    <flux:text>
                        Um den KI-Chat zu nutzen, müssen Sie einen OpenWebUI API-Token in Ihren globalen Einstellungen konfigurieren.
                    </flux:text>
                </flux:callout>

                <flux:button
                    variant="primary"
                    href="{{ route('settings.all') }}"
                >
                    Zu den Einstellungen
                </flux:button>
            </flux:card>
        @endif
    </x-intranet-app-fuhrpark::fuhrpark-layout>
</div>
