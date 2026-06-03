<x-mail::message>
# Review Still Needed

The score deadline has passed for **{{ $paper->team->name }}** in **{{ $paper->subject->title }}**, but your review is not complete yet.

Scormetry only auto-submits completed drafts. Please open the review page and fill in all required rubric scores.

**Team:** {{ $paper->team->name }}  
**Round:** {{ $paper->defenseAttempt?->period?->name ?? 'Defense round' }}  
**Deadline:** {{ optional($paper->defenseAttempt?->score_deadline_at)->format('d M Y, h:i A') ?? 'Passed' }}

<x-mail::button :url="route('reviews.create', $paper)">
Complete Review
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
