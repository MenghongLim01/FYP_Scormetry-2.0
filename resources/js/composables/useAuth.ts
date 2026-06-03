import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth.user);
    const actingRole = computed(() => page.props.auth.actingRole ?? null);
    const effectiveRole = computed(() => actingRole.value ?? user.value?.role);
    const isActing = computed(() => !!actingRole.value);
    const roleViewEnabled = computed(() => !!(page.props as Record<string, unknown>).roleViewEnabled);

    // isRealAdmin always reflects the actual user role (for admin-only UI like the role switcher)
    const isRealAdmin = computed(() => user.value?.role === 'admin');
    // isAdmin uses effectiveRole so the view matches the acting role exactly
    const isAdmin = computed(() => effectiveRole.value === 'admin');
    const isTeacher = computed(() => effectiveRole.value === 'teacher');
    const isStudent = computed(() => effectiveRole.value === 'student');
    const isTeacherOrAdmin = computed(() => isTeacher.value || isAdmin.value);

    return {
        user,
        actingRole,
        effectiveRole,
        isActing,
        isRealAdmin,
        isAdmin,
        isTeacher,
        isStudent,
        isTeacherOrAdmin,
        roleViewEnabled,
    };
}
