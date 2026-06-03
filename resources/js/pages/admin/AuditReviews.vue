<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Unlock, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { dashboard as adminDashboard } from '@/routes/admin';
import { formatDateTimeWithAmPm } from '@/lib/utils';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Audit · Review unlocks', href: '#' },
        ],
    },
});

type LogRow = {
    id: number;
    created_at: string | null;
    subject: string | null;
    team: string | null;
    judge: { name: string; email: string } | null;
    unlocked_by: { name: string; email: string } | null;
    reason: string | null;
};

defineProps<{
    logs: {
        data: LogRow[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number | null;
        to: number | null;
    };
}>();

function formatDateTime(s: string | null): string {
    return formatDateTimeWithAmPm(s);
}
</script>

<template>
    <Head title="Audit · Review unlocks" />

    <div class="flex flex-col">
        <!-- Hero -->
        <div class="bg-gradient-to-br from-indigo-700 to-indigo-800 px-6 pt-6 pb-20 text-white shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                    <Unlock class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Review Unlocks</h1>
                    <p class="text-sm text-white/70">Every time a locked review was reopened for editing — required for academic-integrity audits.</p>
                </div>
            </div>
        </div>

        <div class="relative z-10 -mt-12 px-6">
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr class="border-b bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
                                <th class="px-4 py-3">When</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Team</th>
                                <th class="px-4 py-3">Judge</th>
                                <th class="px-4 py-3">Unlocked by</th>
                                <th class="px-4 py-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in logs.data" :key="row.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ formatDateTime(row.created_at) }}</td>
                                <td class="px-4 py-3 font-medium">{{ row.subject ?? '—' }}</td>
                                <td class="px-4 py-3">{{ row.team ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div v-if="row.judge">
                                        <p class="text-sm">{{ row.judge.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ row.judge.email }}</p>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="row.unlocked_by">
                                        <p class="text-sm">{{ row.unlocked_by.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ row.unlocked_by.email }}</p>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3 max-w-md text-xs text-muted-foreground">{{ row.reason ?? '—' }}</td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                    No review-unlock events recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground">
                    <span>Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}</span>
                    <div class="flex items-center gap-1">
                        <Link
                            v-if="logs.prev_page_url"
                            :href="logs.prev_page_url"
                            class="inline-flex items-center gap-1 rounded-md border bg-background px-2 py-1 hover:bg-muted"
                        >
                            <ChevronLeft class="h-3 w-3" /> Prev
                        </Link>
                        <span>Page {{ logs.current_page }} / {{ logs.last_page }}</span>
                        <Link
                            v-if="logs.next_page_url"
                            :href="logs.next_page_url"
                            class="inline-flex items-center gap-1 rounded-md border bg-background px-2 py-1 hover:bg-muted"
                        >
                            Next <ChevronRight class="h-3 w-3" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
