<x-mail::message>
# Review Completed

Great news! The review for your paper in **{{ $paper->subject->title }}** has been completed.

@if($paper->final_score !== null)
**Final Score:** {{ $paper->final_score }} / 100
@endif

You can now view the paper and its reviews.

<x-mail::button :url="route('papers.show', $paper)">
View Paper
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
