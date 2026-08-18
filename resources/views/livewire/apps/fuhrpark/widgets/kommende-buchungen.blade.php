<?php

use Hwkdo\IntranetAppFuhrpark\Dashboard\FuhrparkDashboardWidgetProvider;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->authorize('see-app-fuhrpark');
    }

    private function itemLimit(): int
    {
        $counts = auth()->user()?->settings->dashboard->personalGrid?->widgetItemCounts ?? [];
        $widgetKey = FuhrparkDashboardWidgetProvider::KEY_KOMMENDE_BUCHUNGEN;

        $value = $counts['fuhrpark.'.$widgetKey]
            ?? $counts[$widgetKey]
            ?? 5;

        return min(max((int) $value, 1), 30);
    }

    #[Computed]
    public function bookings(): Collection
    {
        return $this->upcomingBookingsQuery()
            ->with('vehicle')
            ->limit($this->itemLimit())
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->totalCount() > $this->itemLimit();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->upcomingBookingsQuery()->count();
    }

    /**
     * @return Builder<Booking>
     */
    private function upcomingBookingsQuery(): Builder
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        return Booking::query()
            ->whereBelongsTo($user, 'driver')
            ->upcoming();
    }
};
?>

@placeholder
    <flux:card class="h-full">
        <div class="mb-3 space-y-2">
            <flux:skeleton class="h-4 w-44" />
            <flux:skeleton class="h-3 w-64" />
        </div>
        <div class="space-y-2">
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
        </div>
    </flux:card>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    :title="'Kommende Buchungen ('.$this->totalCount().')'"
    :description="'Ihre nächsten Fahrzeugbuchungen (max. '.$this->itemLimit().')'"
>
    @forelse($this->bookings as $booking)
        <a
            href="{{ route('apps.fuhrpark.meine') }}"
            wire:key="fuhrpark-upcoming-booking-{{ $booking->id }}"
            wire:navigate
            class="group block cursor-pointer rounded-md border border-zinc-200 px-3 py-2 transition-colors duration-150 hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800/40 dark:hover:bg-white/15"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="font-medium group-hover:text-zinc-900 dark:group-hover:text-white">
                    {{ $booking->starts_at->format('d.m.Y H:i') }}
                </div>
                <div class="shrink-0 text-sm font-medium text-zinc-700 dark:text-white">
                    {{ $booking->vehicle?->license_plate ?? '—' }}
                </div>
            </div>
            <div class="text-xs text-zinc-500 dark:text-white">
                {{ filled($booking->description) ? $booking->description : $booking->purpose->label() }}
            </div>
        </a>
    @empty
        <flux:text class="text-zinc-500 dark:text-white">Keine kommenden Buchungen.</flux:text>
    @endforelse

    @if($this->hasMore)
        <div class="pt-1">
            <flux:button variant="ghost" size="sm" :href="route('apps.fuhrpark.meine')" wire:navigate>
                Weitere anzeigen
            </flux:button>
        </div>
    @endif
</x-intranet-app-base::dashboard.widget-card>
