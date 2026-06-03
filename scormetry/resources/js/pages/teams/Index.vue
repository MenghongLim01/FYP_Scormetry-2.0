<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Users, FileText, BookOpen, Calendar, Star } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import { result as teamResult } from '@/actions/App/Http/Controllers/TeamController';

defineProps<{
    teams: Array<{
        id: number;
        name: string;
        defense_date: string | null;
        defense_time: string | null;
        defense_room: string | null;
        results_released_at: string | null;
        subject: { id: number; title: string };
        members: Array<{ id: number; name: string; email: string }>;
        papers: Array<{ id: number; final_score: number | null; visibility_status: string }>;
    }>;
}>();

function formatDate(val: string | null): string {
    if (!val) return '—';
    return new Date(val).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

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
    <Head title="My Team" />

    <div class="flex flex-col gap-6 p-6">
        <div class="rounded-2xl border border-[#212e70]/15 bg-[#212e70]/[0.04] p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#212e70]">Team Workspace</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#212e70]">My Team</h1>
            <p class="text-sm text-slate-600">Teams you are a member of</p>
        </div>

        <div v-if="teams.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#212e70]/25 bg-[#212e70]/[0.05] py-16 text-center">
            <Users class="mb-3 h-10 w-10 text-[#212e70]/60" />
            <p class="font-medium text-slate-600">You are not in any teams yet.</p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="team in teams"
                :key="team.id"
                class="overflow-hidden border-[#212e70]/15 shadow-sm transition hover:-translate-y-0.5 hover:border-[#212e70]/30 hover:shadow-md"
            >
                <CardHeader class="border-b-0 bg-gradient-to-br from-[#212e70] via-[#283a88] to-[#3452b8] p-5 text-white">
                    <div class="flex items-start justify-between gap-3">
                        <CardTitle class="flex items-center gap-3 text-base text-white">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 text-white shadow-sm ring-1 ring-white/20">
                                <Users class="h-5 w-5" />
                            </span>
                            <span>{{ team.name }}</span>
                        </CardTitle>
                        <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white/90 ring-1 ring-white/20">Defense Team</span>
                    </div>
                    <Link :href="subjectShow.url(team.subject.id)" class="ml-14 flex items-center gap-1 text-xs text-white/80 transition hover:text-white hover:underline">
                        <BookOpen class="h-3 w-3" />
                        {{ team.subject.title }}
                    </Link>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wide">Members</p>
                        <div class="flex flex-wrap gap-1.5">
                            <div
                                v-for="member in team.members"
                                :key="member.id"
                                class="flex items-center gap-1.5 rounded-full border border-[#212e70]/15 bg-[#212e70]/[0.05] px-2.5 py-1 text-xs text-[#212e70]"
                            >
                                <div class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-[10px] font-bold text-[#212e70] shadow-sm">
                                    {{ member.name.charAt(0).toUpperCase() }}
                                </div>
                                {{ member.name }}
                            </div>
                        </div>
                    </div>

                    <!-- Defense schedule -->
                    <div v-if="team.defense_date" class="flex items-center gap-2 rounded-lg border border-[#212e70]/20 bg-[#212e70]/[0.05] px-3 py-2 text-xs text-[#212e70]">
                        <Calendar class="h-3.5 w-3.5 shrink-0 text-[#212e70]" />
                        <span>
                            <span class="font-medium">{{ formatDate(team.defense_date) }}</span>
                            <span v-if="team.defense_time" class="text-slate-600"> at {{ team.defense_time }}</span>
                            <span v-if="team.defense_room" class="text-slate-600"> · {{ team.defense_room }}</span>
                        </span>
                    </div>

                    <div v-if="team.papers.length > 0">
                        <p class="mb-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wide">Papers</p>
                        <div class="flex flex-col gap-1.5">
                            <div v-for="paper in team.papers" :key="paper.id" class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2">
                                <Link :href="paperShow.url(paper.id)" class="flex items-center gap-1 text-sm hover:text-[#212e70] hover:underline">
                                    <FileText class="h-3.5 w-3.5 text-slate-500" />
                                    <span v-if="paper.final_score !== null" class="font-semibold">{{ paper.final_score }} / 100</span>
                                    <span v-else class="text-slate-500">No score yet</span>
                                </Link>
                                <Badge :variant="paperBadgeVariant(paper.visibility_status)" class="text-xs">
                                    {{ statusLabel(paper.visibility_status) }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Result link -->
                    <Link
                        :href="teamResult.url(team.id)"
                        class="flex items-center gap-1.5 rounded-lg border border-[#212e70]/25 bg-[#212e70]/[0.06] px-3 py-2 text-xs font-semibold text-[#212e70] transition-colors hover:bg-[#212e70]/10"
                    >
                        <Star class="h-3.5 w-3.5" />
                        {{ team.results_released_at ? 'View Defense Results' : 'Results Pending' }}
                    </Link>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
