import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function addMinutesToClockTime(time: string, minutes: number): string {
    const [hours = 0, rawMinutes = 0] = time.split(':').map(Number);
    const totalMinutes = hours * 60 + rawMinutes + minutes;
    const normalizedMinutes = ((totalMinutes % 1440) + 1440) % 1440;

    return `${String(Math.floor(normalizedMinutes / 60)).padStart(2, '0')}:${String(normalizedMinutes % 60).padStart(2, '0')}`;
}

export function formatClockTime(value: string | null | undefined, fallback = '—'): string {
    if (!value) {
        return fallback;
    }

    const match = value.match(/(?:^|T|\s)(\d{1,2}):(\d{2})/);

    if (!match) {
        return value;
    }

    const hours = Number(match[1]);
    const minutes = match[2];
    const period = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours % 12 || 12;

    return `${hour12}:${minutes} ${period}`;
}

export function formatDateTimeWithAmPm(
    value: string | null | undefined,
    options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    },
    fallback = '—',
): string {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-US', { ...options, hour12: true });
}

export function formatClockTimeRange(time: string | null | undefined, durationMinutes: number | null | undefined): string {
    if (!time) {
        return 'Time not set';
    }

    if (!durationMinutes) {
        return formatClockTime(time);
    }

    return `${formatClockTime(time)} - ${formatClockTime(addMinutesToClockTime(time, durationMinutes))}`;
}
