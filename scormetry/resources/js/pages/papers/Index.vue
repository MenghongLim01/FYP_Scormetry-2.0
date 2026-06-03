<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Filter } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuth } from '@/composables/useAuth';
import { computed, ref } from 'vue';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { show as paperShow } from '@/actions/App/Http/Controllers/PaperController';

const props = defineProps<{
    papers: Array<{
        id: number;
        file_path: string;
        final_score: number | null;
        visibility_status: string;
        team?: { id: number; name: string; members: Array<{ id: number; name: string }> };
        subject: { id: number; title: string };
        reviews?: Array<{
            id: number;
            is_submitted: boolean;
            reviewer: { id: number; name: string };
        }>;
    }>;
    reviewerTeamIds: number[];
}>();

const { isStudent, isTeacherOrAdmin } = useAuth();

const filterMyTeamsOnly = ref(false);

const isJudgeWithTeams = computed(
    () => isTeacherOrAdmin.value && props.reviewerTeamIds.length > 0,
);

const visiblePapers = computed(() => {
    if (filterMyTeamsOnly.value && props.reviewerTeamIds.length > 0) {
        return props.papers.filter((p) => p.team && props.reviewerTeamIds.includes(p.team.id));
    }
    return props.papers;
});

function paperBadgeVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'published') return 'default';
    if (status === 'submitted') return 'secondary';
    return 'outline';
}

function statusLabel(status: string): string {
    if (status === 'published') return 'Review Completed';
    return status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>

<template>
    <Head title="Papers" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between rounded-2xl border border-[#212e70]/15 bg-[#212e70]/[0.04] p-5 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#212e70]">Document Center</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#212e70]">Papers</h1>
                <p class="text-sm text-slate-600">
                    {{ isStudent ? 'Your submitted academic papers' : 'All submitted papers across subjects' }}
                </p>
            </div>

            <button
                v-if="isJudgeWithTeams"
                class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                :class="filterMyTeamsOnly
                    ? 'border-[#212e70] bg-[#212e70]/10 text-[#212e70]'
                    : 'border-input text-muted-foreground hover:text-foreground'"
                @click="filterMyTeamsOnly = !filterMyTeamsOnly"
            >
                <Filter class="h-3.5 w-3.5" />
                {{ filterMyTeamsOnly ? 'My teams only' : 'All teams' }}
            </button>
        </div>

        <Card class="overflow-hidden border-[#212e70]/15 shadow-sm">
            <CardHeader class="border-b bg-[#212e70] px-6 py-4">
                <CardTitle class="flex items-center gap-3 text-sm font-semibold text-white">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                        <FileText class="h-4 w-4 text-white" />
                    </span>
                    <span>{{ visiblePapers.length }} Paper{{ visiblePapers.length !== 1 ? 's' : '' }}</span>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="visiblePapers.length === 0" class="flex flex-col items-center justify-center py-14 text-center">
                    <FileText class="mb-3 h-9 w-9 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No papers found.</p>
                </div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-[#212e70] text-left text-xs font-semibold uppercase tracking-wider text-white">
                            <th class="px-6 py-3">Subject</th>
                            <th class="px-6 py-3">Team</th>
                            <th class="px-6 py-3">Final Score</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Reviews</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="paper in visiblePapers" :key="paper.id" class="transition-colors hover:bg-muted/70">
                            <td class="px-6 py-3">
                                <Link :href="subjectShow.url(paper.subject.id)" class="font-medium hover:text-[#212e70] hover:underline">
                                    {{ paper.subject.title }}
                                </Link>
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                {{ paper.team?.name ?? '—' }}
                            </td>
                            <td class="px-6 py-3">
                                <span v-if="paper.final_score !== null" class="font-semibold">{{ paper.final_score }} / 100</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-6 py-3">
                                <Badge :variant="paperBadgeVariant(paper.visibility_status)">
                                    {{ statusLabel(paper.visibility_status) }}
                                </Badge>
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                {{ paper.reviews?.filter((r) => r.is_submitted).length ?? 0 }}/{{ paper.reviews?.length ?? 0 }} submitted
                            </td>
                            <td class="px-6 py-3">
                                <Link :href="paperShow.url(paper.id)" class="text-[#212e70] hover:underline">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
