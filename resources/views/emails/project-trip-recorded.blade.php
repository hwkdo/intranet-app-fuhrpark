@component('mail::message')
# Projektfahrt erfasst

**Fahrer:** {{ $entry->user->name ?? '-' }}  
**Projekt:** {{ $project?->name ?? '-' }}  
**KM Projekt:** {{ $entry->km_project }}  
**Route:** {{ $entry->route }}
@endcomponent
