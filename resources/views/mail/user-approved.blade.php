<x-mail::message>
# Your Account Has Been Approved

Hi **{{ $user->name }}**,

Your account has been approved. You can now log in and start using {{ config('app.name') }}.

<x-mail::button :url="route('login')">
Log In Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
