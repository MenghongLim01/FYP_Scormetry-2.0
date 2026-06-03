<x-mail::message>
# New Paper Submitted

A new paper has been submitted for **{{ $paper->subject->title }}**.

**Team:** {{ $paper->team?->name ?? 'N/A' }}
**Submitted:** {{ $paper->created_at->format('d M Y, g:i A') }}

Please review it at your earliest convenience.

<x-mail::button :url="route('papers.show', $paper)">
View Paper
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
