import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const validAppearances = ['light', 'dark', 'system'] as const;
const prefersDarkQuery = '(prefers-color-scheme: dark)';

function isAppearance(value: string | null): value is Appearance {
    return validAppearances.includes(value as Appearance);
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

function osPrefersDark(): boolean {
    return typeof window !== 'undefined'
        && window.matchMedia(prefersDarkQuery).matches;
}

function storedAppearance(): Appearance {
    if (typeof window === 'undefined') return 'system';

    const localValue = localStorage.getItem('appearance');

    if (isAppearance(localValue)) {
        return localValue;
    }

    return cookieAppearance() ?? 'system';
}

const systemPrefersDark = ref(osPrefersDark());
const appearance = ref<Appearance>('system');

function applyResolvedTheme(resolved: ResolvedAppearance): void {
    if (typeof window === 'undefined') return;

    const isDark = resolved === 'dark';

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = resolved;
    document.documentElement.style.colorScheme = resolved;
}

export function resolvedTheme(value: Appearance): ResolvedAppearance {
    return value === 'system'
        ? (osPrefersDark() ? 'dark' : 'light')
        : value;
}

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') return;

    systemPrefersDark.value = osPrefersDark();
    applyResolvedTheme(resolvedTheme(value));
}

function syncSystemTheme() {
    const stored = storedAppearance();
    appearance.value = stored;
    systemPrefersDark.value = osPrefersDark();

    if (stored === 'system') {
        applyResolvedTheme(systemPrefersDark.value ? 'dark' : 'light');
    }
}

if (typeof window !== 'undefined') {
    window.matchMedia(prefersDarkQuery).addEventListener('change', syncSystemTheme);

    window.addEventListener('focus', syncSystemTheme);
    window.addEventListener('storage', syncSystemTheme);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') syncSystemTheme();
    });
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') return;

    appearance.value = storedAppearance();
    systemPrefersDark.value = osPrefersDark();
    updateTheme(appearance.value);
}

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        appearance.value = storedAppearance();
        systemPrefersDark.value = osPrefersDark();
        updateTheme(appearance.value);
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() =>
        appearance.value === 'system'
            ? (systemPrefersDark.value ? 'dark' : 'light')
            : appearance.value,
    );

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        setCookie('appearance', value);
        updateTheme(value);
    }

    return { appearance, resolvedAppearance, updateAppearance };
}
