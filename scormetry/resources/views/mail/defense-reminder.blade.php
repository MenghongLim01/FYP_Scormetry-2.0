<x-mail::message>
# Defense Reminder: {{ $team->name }}

This is your {{ $reminderLabel }} reminder for the upcoming FYP defense.

**Subject:** {{ $team->subject->title }}  
**Team:** {{ $team->name }}  
**Date:** {{ optional($team->defense_date)->format('d M Y') ?? 'Not scheduled' }}  
**Time:** {{ $team->defense_time ?? 'Not scheduled' }}  
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
