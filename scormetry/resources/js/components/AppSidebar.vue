<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BookOpen, LayoutGrid, FileText, Users, Settings, UsersRound, ClipboardList } from 'lucide-vue-next';
import { store as actingRoleStore, destroy as actingRoleDestroy } from '@/actions/App/Http/Controllers/Admin/ActingRoleController';
import { index as adminDashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { index as adminClassroomsIndex } from '@/actions/App/Http/Controllers/Admin/ClassroomController';
import { edit as adminSettingsEdit } from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { index as adminUsersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { index as assignedTeamsIndex } from '@/actions/App/Http/Controllers/AssignedTeamsController';
import { index as papersIndex } from '@/actions/App/Http/Controllers/PaperController';
import { index as subjectsIndex } from '@/actions/App/Http/Controllers/SubjectController';
import { index as teamsIndex } from '@/actions/App/Http/Controllers/TeamController';
import { useAuth } from '@/composables/useAuth';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavFooter from '@/components/NavFooter.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const { isAdmin, isRealAdmin, isActing, isStudent, isTeacher, effectiveRole, roleViewEnabled } = useAuth();

const roles = ['admin', 'teacher', 'student'] as const;

function switchRole(role: string) {
    router.post(actingRoleStore.url(), { role }, { preserveScroll: true });
}

function clearActingRole() {
    router.delete(actingRoleDestroy.url(), { preserveScroll: true });
}

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const academicNavItems: NavItem[] = [
    {
        title: 'Subjects',
        href: subjectsIndex.url(),
        icon: BookOpen,
    },
    {
        title: 'Papers',
        href: papersIndex.url(),
        icon: FileText,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Admin Dashboard',
        href: adminDashboardIndex.url(),
        icon: LayoutGrid,
    },
    {
        title: 'Classroom Management',
        href: adminClassroomsIndex.url(),
        icon: BookOpen,
    },
    {
        title: 'User Management',
        href: adminUsersIndex.url(),
        icon: Users,
    },
    {
        title: 'Settings',
        href: adminSettingsEdit.url(),
        icon: Settings,
    },
];

const studentNavItems: NavItem[] = [
    {
        title: 'My Team',
        href: teamsIndex.url(),
        icon: UsersRound,
    },
];

const reviewerNavItems: NavItem[] = [
    {
        title: 'My Assigned Teams',
        href: assignedTeamsIndex.url(),
        icon: ClipboardList,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Settings',
        href: '/settings/profile',
        icon: Settings,
    },
];

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Dashboard" />
            <NavMain :items="academicNavItems" label="Academic Management" />
            <NavMain v-if="isStudent" :items="studentNavItems" label="Student Management" />
            <NavMain v-if="isTeacher" :items="reviewerNavItems" label="Review Work" />
            <NavMain v-if="isAdmin" :items="adminNavItems" label="Admin Panel" />
            <SidebarGroup v-if="isRealAdmin && roleViewEnabled">
                <SidebarGroupLabel class="flex items-center justify-between">
                    <span>Dev: Role View</span>
                    <button
                        v-if="isActing"
                        class="text-xs text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                        @click="clearActingRole"
                    >
                        Reset
                    </button>
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <div class="flex gap-1 px-2">
                        <button
                            v-for="role in roles"
                            :key="role"
                            class="flex-1 rounded-md px-2 py-1 text-xs font-medium capitalize transition-colors"
                            :class="effectiveRole === role ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
                            @click="switchRole(role)"
                        >
                            {{ role }}
                        </button>
                    </div>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <SidebarSeparator />
            <NavFooter :items="footerNavItems" />
        </SidebarFooter>

    </Sidebar>
    <slot />
</template>
