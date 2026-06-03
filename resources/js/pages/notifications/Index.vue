<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Award,
    Bell,
    Calendar,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Clock,
    FileText,
    Inbox,
    ShieldCheck,
    Star,
} from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { read, readAll } from '@/routes/notifications';
import { formatDateTimeWithAmPm } from '@/lib/utils';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Notifications', href: '#' },
        ],
    },
});

type InboxItem = {
    id: string;
    title: string;
    body: string;
    url: string | null;
    category: string;
    priority: 'danger' | 'warning' | 'info' | 'success' | string;
    status: string;
    source: 'task' | 'notification';
    action_label: string | null;
    read_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    actionItems: InboxItem[];
    notifications: {
        data: InboxItem[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number | null;
        to: number | null;
    };
}>();

const activeFilter = ref('all');

const categoryIcon: Record<string, typeof Bell> = {
    paper: FileText,
    review: Star,
    result: Award,
    reviewer: ShieldCheck,
    schedule: Calendar,
    deadline: Clock,
    system: Bell,
};

const categoryTint: Record<string, string> = {
    paper: 'bg-indigo-50 text-indigo-700 ring-indigo-100 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-400/20',
    review: 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/20',
    result: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-400/20',
    reviewer: 'bg-violet-50 text-violet-700 ring-violet-100 dark:bg-violet-500/15 dark:text-violet-200 dark:ring-violet-400/20',
    schedule: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-500/15 dark:text-blue-200 dark:ring-blue-400/20',
    deadline: 'bg-red-50 text-red-700 ring-red-100 dark:bg-red-500/15 dark:text-red-200 dark:ring-red-400/20',
    system: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-700/50 dark:text-slate-200 dark:ring-slate-600',
};

const priorityBorder: Record<string, string> = {
    danger: 'border-l-red-500',
    warning: 'border-l-amber-500',
    info: 'border-l-indigo-500',
    success: 'border-l-emerald-500',
};

const statusTint: Record<string, string> = {
    Overdue: 'bg-red-50 text-red-700 ring-red-100 dark:bg-red-500/15 dark:text-red-200 dark:ring-red-400/20',
    'Due soon': 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/20',
    'Needs action': 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/20',
    Ready: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-500/15 dark:text-blue-200 dark:ring-blue-400/20',
    New: 'bg-indigo-50 text-indigo-700 ring-indigo-100 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-400/20',
    Released: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-400/20',
    Scheduled: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-500/15 dark:text-blue-200 dark:ring-blue-400/20',
    Waiting: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-700/50 dark:text-slate-200 dark:ring-slate-600',
    Rejected: 'bg-red-50 text-red-700 ring-red-100 dark:bg-red-500/15 dark:text-red-200 dark:ring-red-400/20',
    Read: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
};

const inboxItems = computed(() => [...props.actionItems, ...props.notifications.data]);

const filters = computed(() => [
    { key: 'all', label: 'All', count: inboxItems.value.length },
    { key: 'action', label: 'Needs action', count: props.actionItems.length },
    { key: 'schedule', label: 'Schedule', count: inboxItems.value.filter((item) => item.category === 'schedule' || item.category === 'deadline').length },
    { key: 'paper', label: 'Documents', count: inboxItems.value.filter((item) => item.category === 'paper').length },
    { key: 'review', label: 'Reviews', count: inboxItems.value.filter((item) => item.category === 'review' || item.category === 'reviewer').length },
    { key: 'result', label: 'Results', count: inboxItems.value.filter((item) => item.category === 'result').length },
]);

const filteredItems = computed(() => {
    if (activeFilter.value === 'all') {
        return inboxItems.value;
    }

    if (activeFilter.value === 'action') {
        return props.actionItems;
    }

    if (activeFilter.value === 'schedule') {
        return inboxItems.value.filter((item) => item.category === 'schedule' || item.category === 'deadline');
    }

    if (activeFilter.value === 'review') {
        return inboxItems.value.filter((item) => item.category === 'review' || item.category === 'reviewer');
    }

    return inboxItems.value.filter((item) => item.category === activeFilter.value);
});

const urgentCount = computed(() => props.actionItems.filter((item) => item.priority === 'danger').length);
const dueSoonCount = computed(() => props.actionItems.filter((item) => item.priority === 'warning').length);
const unreadCount = computed(() => props.notifications.data.filter((item) => item.source === 'notification' && !item.read_at).length);

function formatDateTime(iso: string | null): string {
    return formatDateTimeWithAmPm(
        iso,
        {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        },
        '',
    );
}

function openItem(item: InboxItem) {
    if (item.source === 'notification') {
        router.post(read(item.id).url, {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (item.url) {
                    router.visit(item.url);
                }
            },
        });

        return;
    }

    if (item.url) {
        router.visit(item.url);
    }
}

function markAllRead() {
    router.post(readAll().url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <div class="flex flex-col">
        <div class="bg-gradient-to-br from-[#24327a] via-indigo-700 to-violet-700 px-6 pt-6 pb-24 text-white shadow-md">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20">
                        <Bell class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Notifications</h1>
                        <p class="mt-1 max-w-2xl text-sm text-white/75">
                            Your action inbox for approvals, defense schedules, documents, scoring, and released results.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-1.5 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25"
                    @click="markAllRead"
                >
                    <CheckCheck class="h-4 w-4" />
                    Mark all read
                </button>
            </div>
        </div>

        <div class="relative z-10 -mt-14 px-6 pb-8">
            <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-200">
                            <AlertTriangle class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-950 dark:text-white">{{ urgentCount }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Urgent</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-200">
                            <ClipboardCheck class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-950 dark:text-white">{{ dueSoonCount }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Needs action</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200">
                            <Inbox class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-950 dark:text-white">{{ unreadCount }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Unread updates</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border bg-card shadow-sm dark:border-slate-800">
                <div class="flex flex-wrap items-center gap-2 border-b bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900">
                    <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Filter</span>
                    <button
                        v-for="filter in filters"
                        :key="filter.key"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold transition"
                        :class="activeFilter === filter.key
                            ? 'border-[#24327a] bg-[#24327a] text-white shadow-sm dark:border-indigo-400 dark:bg-indigo-500'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'"
                        @click="activeFilter = filter.key"
                    >
                        {{ filter.label }}
                        <span class="rounded-full bg-black/5 px-1.5 text-xs dark:bg-white/10">{{ filter.count }}</span>
                    </button>
                </div>

                <div v-if="filteredItems.length === 0" class="px-6 py-20 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                        <Bell class="h-6 w-6 text-slate-400" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-foreground">No notifications yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        You will see approvals, document uploads, scoring reminders, and released results here.
                    </p>
                </div>

                <div v-else class="divide-y dark:divide-slate-800">
                    <button
                        v-for="item in filteredItems"
                        :key="`${item.source}-${item.id}`"
                        type="button"
                        class="flex w-full items-start gap-4 border-l-4 px-5 py-4 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        :class="[priorityBorder[item.priority] ?? 'border-l-indigo-500', item.source === 'notification' && !item.read_at ? 'bg-indigo-50/40 dark:bg-indigo-500/10' : 'bg-white dark:bg-slate-900']"
                        @click="openItem(item)"
                    >
                        <div class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1" :class="categoryTint[item.category] ?? categoryTint.system">
                            <component :is="categoryIcon[item.category] ?? Bell" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-bold text-slate-950 dark:text-white">{{ item.title }}</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="statusTint[item.status] ?? statusTint.Read">
                                    {{ item.status }}
                                </span>
                                <span v-if="item.source === 'notification' && !item.read_at" class="h-2 w-2 rounded-full bg-indigo-500" />
                            </div>
                            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ item.body }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span class="capitalize">{{ item.category }}</span>
                                <span v-if="item.created_at">- {{ formatDateTime(item.created_at) }}</span>
                                <span v-if="item.source === 'task'" class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    Action item
                                </span>
                            </div>
                        </div>
                        <div class="hidden shrink-0 items-center gap-2 sm:flex">
                            <span
                                v-if="item.action_label"
                                class="inline-flex items-center gap-1.5 rounded-lg border bg-white px-3 py-2 text-sm font-semibold text-[#24327a] shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-indigo-200"
                            >
                                {{ item.action_label }}
                                <ArrowRight class="h-4 w-4" />
                            </span>
                            <ArrowRight v-else-if="item.url" class="h-4 w-4 text-muted-foreground/60" />
                        </div>
                    </button>
                </div>

                <div v-if="notifications.last_page > 1" class="flex items-center justify-between border-t bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground dark:border-slate-800">
                    <span>Showing {{ notifications.from }}-{{ notifications.to }} of {{ notifications.total }} saved notifications</span>
                    <div class="flex items-center gap-2">
                        <Link v-if="notifications.prev_page_url" :href="notifications.prev_page_url" class="inline-flex items-center gap-1 rounded-md border bg-background px-2 py-1 hover:bg-muted">
                            <ChevronLeft class="h-3 w-3" /> Prev
                        </Link>
                        <span>Page {{ notifications.current_page }} / {{ notifications.last_page }}</span>
                        <Link v-if="notifications.next_page_url" :href="notifications.next_page_url" class="inline-flex items-center gap-1 rounded-md border bg-background px-2 py-1 hover:bg-muted">
                            Next <ChevronRight class="h-3 w-3" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
