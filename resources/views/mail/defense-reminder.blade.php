<x-mail::message>
@php
    $defenseTime = $team->defense_time
        ? \Illuminate\Support\Carbon::createFromFormat('H:i', substr((string) $team->defense_time, 0, 5))->format('g:i A')
        : 'Not scheduled';
@endphp

# Defense Reminder: {{ $team->name }}

This is your {{ $reminderLabel }} reminder for the upcoming FYP defense.

**Subject:** {{ $team->subject->title }}  
**Team:** {{ $team->name }}  
**Date:** {{ optional($team->defense_date)->format('d M Y') ?? 'Not scheduled' }}  
**Time:** {{ $defenseTime }}  
**Room:** {{ $team->defense_room ?? 'Not assigned' }}

@if($team->latestPaper())
<x-mail::button :url="route('papers.show', $team->latestPaper())">
View Submitted Paper
</x-mail::button>
@endif

<x-mail::button :url="route('subjects.show', $team->subject)">
Open Team Room
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
