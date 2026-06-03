<x-mail::message>
# Defense Results Released

Your defense results for **{{ $team->name }}** in **{{ $team->subject->title }}** are now available.

@if($team->latestPaper()?->effectiveFinalScore() !== null)
**Final Score:** {{ $team->latestPaper()->effectiveFinalScore() }} / 100
@endif

<x-mail::button :url="route('teams.scores', $team)">
View Score Breakdown
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
