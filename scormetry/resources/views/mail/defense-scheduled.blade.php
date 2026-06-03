<x-mail::message>
@if($changeType === 'cancelled')
# Defense Schedule Cancelled

The defense schedule for this attempt has been cancelled. Please remove the previous event from your calendar.
@elseif($changeType === 'updated')
# Defense Schedule Updated

The defense schedule for this attempt has changed. The attached calendar invite updates the existing event in Google Calendar.
@else
# Defense Schedule Confirmed

The defense schedule for this attempt has been set. The attached calendar invite can be added to Google Calendar.
@endif

<x-mail::panel>
**Subject:** {{ $team->subject->title }}
**Team:** {{ $team->name }}
**Round:** {{ $period?->name ?? 'Defense' }} / {{ $attempt->label }}
**Date:** {{ $startsAt?->format('d M Y') ?? 'Not set' }}
**Start Time:** {{ $startsAt?->format('g:i A') ?? 'Not set' }}
**End Time:** {{ $endsAt?->format('g:i A') ?? 'Not set' }}
**Duration:** {{ $attempt->defense_duration ? $attempt->defense_duration.' minutes' : 'Not set' }}
**Room / Venue:** {{ $attempt->defense_room ?? 'To be announced' }}
@if($scoreDeadlineAt)
**Score Deadline:** {{ $scoreDeadlineAt->format('d M Y, g:i A') }}
@endif
</x-mail::panel>

Scormetry remains the official source of defense schedules. Google Calendar is used only as a convenience through email calendar invitations, so students and judges can add the defense session to their personal calendars.

@if($changeType === 'cancelled')
Please disregard previous schedule notifications for this attempt.
@else
Please be present at the venue at least 10 minutes before the scheduled start time.
@endif

<x-mail::button :url="route('subjects.show', $team->subject)">
View Subject Page
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
