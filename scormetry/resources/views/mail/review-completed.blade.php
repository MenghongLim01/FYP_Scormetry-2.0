<x-mail::message>
# Review Completed

**{{ $review->reviewer->name }}** has submitted a review for a paper in **{{ $review->paper->subject->title }}**.

**Role:** {{ str_replace('_', ' ', ucwords($review->committee_role, '_')) }}

<x-mail::button :url="route('papers.show', $review->paper)">
View Paper & Reviews
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
