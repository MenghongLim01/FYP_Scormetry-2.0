<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Users, FileText, Clock, ArrowRight } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminClassroomsIndex } from '@/routes/admin/classrooms';
import { index as adminUsersIndex } from '@/routes/admin/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Dashboard', href: adminDashboard() },
        ],
    },
});

defineProps<{
    stats: {
        total_classrooms: number;
        total_users: number;
        total_submissions: number;
        pending_approvals: number;
    };
}>();

const cards = [
    { key: 'total_classrooms', label: 'Total Classrooms', icon: BookOpen, color: 'text-indigo-700 bg-indigo-50 dark:text-white dark:bg-indigo-950/60' },
    { key: 'total_users', label: 'Total Users', icon: Users, color: 'text-indigo-700 bg-indigo-50 dark:text-white dark:bg-indigo-950/60' },
    { key: 'total_submissions', label: 'Total Submissions', icon: FileText, color: 'text-teal-700 bg-teal-50 dark:text-white dark:bg-teal-950/60' },
    { key: 'pending_approvals', label: 'Pending Approvals', icon: Clock, color: 'text-amber-700 bg-amber-50 dark:text-white dark:bg-amber-950/60' },
] as const;
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex flex-col">

        <!-- Hero panel -->
        <div class="bg-gradient-to-br from-indigo-700 to-indigo-800 px-6 pt-6 pb-20 text-white shadow-md">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Admin Panel</h1>
                    <p class="text-sm text-white/70">Global overview and controls</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child class="bg-white/20 text-white border-0 hover:bg-white/30">
                        <Link :href="adminClassroomsIndex()">
                            Classrooms
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                    <Button variant="outline" as-child class="bg-white/10 text-white border-white/30 hover:bg-white/25">
                        <Link :href="adminUsersIndex()">
                            Users
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Floating stat cards -->
        <div class="relative z-10 -mt-12 px-6 pb-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="card in cards" :key="card.key" class="rounded-2xl shadow-md transition-shadow hover:shadow-lg">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">{{ card.label }}</CardTitle>
                    <div :class="['flex h-9 w-9 items-center justify-center rounded-lg', card.color]">
                        <component :is="card.icon" class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold dark:text-white">{{ (stats as any)[card.key] }}</div>
                </CardContent>
            </Card>
        </div>
        </div><!-- /floating -->
    </div>
</template>

