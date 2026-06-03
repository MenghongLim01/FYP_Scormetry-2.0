<x-mail::message>
# You have been invited as a reviewer

You have been invited to join **{{ $invitation->subject->title }}** as a **{{ str_replace('_', ' ', ucwords($invitation->committee_role, '_')) }}** reviewer by **{{ $invitation->subject->teacher->name }}**.

To accept this invitation, you will need to register for an account using this email address.

<x-mail::button :url="route('register', ['invite' => $invitation->token])">
Register & Accept Invitation
</x-mail::button>

If you already have an account, please log in — the invitation will be applied automatically once you register with this email address.

This invitation link will remain active until accepted.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
