<x-mail::message>
# You have been added as a reviewer

Hi **{{ $reviewer->name }}**,

You have been added as a reviewer for **{{ $reviewerSubject->title }}** with the role of **{{ str_replace('_', ' ', ucwords($committeeRole, '_')) }}** by **{{ $reviewerSubject->teacher->name }}**.

You can now access the subject's papers and submit your reviews.

<x-mail::button :url="route('subjects.show', $reviewerSubject)">
View Subject
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
