<x-mail::message>
# Your login code

Use this one-time code to finish signing in to Scormetry:

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

This code expires in 10 minutes. If you didn't try to sign in, you can safely ignore this email — and consider changing your password.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
