@component('mail::message')
# Projektfahrt geändert

**Fahrer:** {{ $entry->user->name ?? '-' }}  
**Projekt:** {{ $project?->name ?? '-' }}  
**KM Projekt:** {{ $oldKmProject }} → {{ $entry->km_project }}  
**Route:** {{ $entry->route }}
@endcomponent
