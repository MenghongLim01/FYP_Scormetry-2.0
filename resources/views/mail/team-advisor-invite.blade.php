<x-mail::message>
# You have been invited as a team advisor

Hi **{{ $advisor->name }}**,

**{{ $invitedByName }}** has invited you to be the advisor for team **{{ $team->name }}** in **{{ $forSubject->title }}**.

@if ($pendingApproval)
Your invitation is awaiting approval from the subject's FYP instructor. You'll be added to the team once it's approved.
@else
You are now listed as the advisor for this team.
@endif

<x-mail::button :url="route('subjects.show', $forSubject)">
View Subject
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
