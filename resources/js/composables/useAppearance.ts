import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

// Scormetry only supports two explicit themes. It deliberately does NOT follow
// the operating system's prefers-color-scheme — the app always defaults to light
// and only switches to dark when the user explicitly chooses it.
const validAppearances = ['light', 'dark'] as const;
const DEFAULT_APPEARANCE: Appearance = 'light';

function isAppearance(value: string | null): value is Appearance {
    return validAppearances.includes(value as (typeof validAppearances)[number]);
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') return;
    document.cookie = `${name}=${value};path=/;max-age=${days * 24 * 60 * 60};SameSite=Lax`;
};

function cookieAppearance(): Appearance | null {
    if (typeof document === 'undefined') return null;

    const cookieValue = document.cookie
        .split('; ')
        .find((row) => row.startsWith('appearance='))
        ?.split('=')[1];

    const value = cookieValue ? decodeURIComponent(cookieValue) : null;

    return isAppearance(value) ? value : null;
}

// Resolve the saved preference. Anything that isn't an explicit valid theme
// (no value, legacy 'system', invalid) falls back to light.
function storedAppearance(): Appearance {
    if (typeof window === 'undefined') return DEFAULT_APPEARANCE;

    const localValue = localStorage.getItem('appearance');

    if (isAppearance(localValue)) {
        return localValue;
    }

    return cookieAppearance() ?? DEFAULT_APPEARANCE;
}

const appearance = ref<Appearance>(DEFAULT_APPEARANCE);

function applyResolvedTheme(resolved: ResolvedAppearance): void {
    if (typeof window === 'undefined') return;

    const isDark = resolved === 'dark';

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = resolved;
    document.documentElement.style.colorScheme = resolved;
}

export function resolvedTheme(value: Appearance): ResolvedAppearance {
    // Only an explicit 'dark' resolves to dark; everything else is light.
    return value === 'dark' ? 'dark' : 'light';
}

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') return;

    applyResolvedTheme(resolvedTheme(value));
}

// Keep the theme in sync across tabs/windows (e.g. the user toggles it
// elsewhere). This reads the persisted Scormetry preference only — never the OS.
function syncStoredTheme() {
    appearance.value = storedAppearance();
    updateTheme(appearance.value);
}

if (typeof window !== 'undefined') {
    window.addEventListener('storage', syncStoredTheme);
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') return;

    appearance.value = storedAppearance();
    updateTheme(appearance.value);
}

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        appearance.value = storedAppearance();
        updateTheme(appearance.value);
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => resolvedTheme(appearance.value));

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        setCookie('appearance', value);
        updateTheme(value);
    }

    return { appearance, resolvedAppearance, updateAppearance };
}
