<x-mail::message>
# Review Auto-submitted

Your saved draft review for **{{ $review->paper->team->name }}** in **{{ $review->paper->subject->title }}** was complete when the score deadline passed, so Scormetry submitted and locked it automatically.

Feedback was optional, so only the required rubric scores were checked before auto-submission.

<x-mail::button :url="route('reviews.show', $review)">
View Submitted Review
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
