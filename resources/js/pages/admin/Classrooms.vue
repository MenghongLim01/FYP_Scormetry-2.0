<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, ExternalLink, FileText, KeyRound, RefreshCw, ShieldCheck, Trash2, Users } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    control as adminClassroomControl,
    destroy as adminClassroomDestroy,
    index as adminClassroomsIndex,
    resetJoinCode as adminClassroomResetJoinCode,
    resetReviewerCode as adminClassroomResetReviewerCode,
} from '@/routes/admin/classrooms';
import { show as subjectShow } from '@/routes/subjects';
import { ref } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Classrooms', href: adminClassroomsIndex() },
        ],
    },
});

const props = defineProps<{
    subjects: Array<{
        id: number;
        title: string;
        join_code: string | null;
        reviewer_code: string | null;
        created_at: string;
        memberships_count: number;
        teams_count: number;
        papers_count: number;
        students_count: number;
        reviewers_count: number;
        pending_members_count: number;
        teacher: { id: number; name: string } | null;
    }>;
}>();

const deleteOpen = ref(false);
const pendingDelete = ref<{ id: number; title: string } | null>(null);

function requestDelete(subject: { id: number; title: string }) {
    pendingDelete.value = { id: subject.id, title: subject.title };
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!pendingDelete.value) return;
    router.delete(adminClassroomDestroy.url({ subject: pendingDelete.value.id }), {
        onFinish: () => {
            deleteOpen.value = false;
            pendingDelete.value = null;
        },
    });
}

function resetJoinCode(subjectId: number) {
    router.patch(adminClassroomResetJoinCode.url({ subject: subjectId }), {}, { preserveScroll: true });
}

function resetReviewerCode(subjectId: number) {
    router.patch(adminClassroomResetReviewerCode.url({ subject: subjectId }), {}, { preserveScroll: true });
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Admin Classrooms" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Classrooms</h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    Open a classroom control page when an admin needs to transfer the owner, add users, reset access codes, override scores, or correct submitted reviews.
                </p>
            </div>
        </div>

        <Card>
            <CardHeader class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                        <BookOpen class="h-4 w-4 text-blue-600" />
                        {{ subjects.length }} Classroom{{ subjects.length !== 1 ? 's' : '' }}
                    </CardTitle>
                </div>
                <CardDescription class="text-xs">
                    Student and reviewer join codes can be reset separately. Classroom control keeps recovery actions in one place.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="subjects.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                    No classrooms found.
                </div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-primary text-left text-xs font-semibold uppercase tracking-wider text-primary-foreground">
                            <th class="px-6 py-3">Classroom</th>
                            <th class="px-6 py-3">Owner</th>
                            <th class="px-6 py-3">Teams</th>
                            <th class="px-6 py-3">Members</th>
                            <th class="px-6 py-3">Documents</th>
                            <th class="px-6 py-3">Student Code</th>
                            <th class="px-6 py-3">Reviewer Code</th>
                            <th class="px-6 py-3">Created</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="subject in subjects" :key="subject.id" class="transition-colors hover:bg-muted/70">
                            <td class="px-6 py-4">
                                <p class="font-semibold">{{ subject.title }}</p>
                                <div v-if="subject.pending_members_count > 0" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                    {{ subject.pending_members_count }} pending approval{{ subject.pending_members_count !== 1 ? 's' : '' }}
                                </div>
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                {{ subject.teacher?.name ?? '—' }}
                            </td>
                            <td class="px-6 py-3">
                                <Badge variant="secondary" class="text-xs">
                                    {{ subject.teams_count }}
                                </Badge>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <Badge variant="secondary" class="gap-1 text-xs">
                                        <Users class="h-3 w-3" />
                                        {{ subject.students_count }} students
                                    </Badge>
                                    <Badge variant="secondary" class="gap-1 text-xs">
                                        <ShieldCheck class="h-3 w-3" />
                                        {{ subject.reviewers_count }} reviewers
                                    </Badge>
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                <Badge variant="secondary" class="gap-1 text-xs">
                                    <FileText class="h-3 w-3" />
                                    {{ subject.papers_count }}
                                </Badge>
                            </td>
                            <td class="px-6 py-3 font-mono text-xs">
                                {{ subject.join_code ?? '—' }}
                            </td>
                            <td class="px-6 py-3 font-mono text-xs">
                                {{ subject.reviewer_code ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                {{ formatDate(subject.created_at) }}
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Button as-child size="sm" class="gap-1.5 bg-[#24327a] text-white hover:bg-[#1d2863]">
                                        <Link :href="adminClassroomControl({ subject: subject.id })">
                                            <ShieldCheck class="h-3.5 w-3.5" />
                                            Control
                                        </Link>
                                    </Button>
                                    <Button as-child size="sm" variant="outline" class="gap-1.5">
                                        <Link :href="subjectShow({ subject: subject.id })">
                                            <ExternalLink class="h-3.5 w-3.5" />
                                            Open
                                        </Link>
                                    </Button>
                                    <Button size="sm" variant="outline" class="gap-1.5" @click="resetJoinCode(subject.id)">
                                        <KeyRound class="h-3.5 w-3.5" />
                                        Student
                                    </Button>
                                    <Button size="sm" variant="outline" class="gap-1.5" @click="resetReviewerCode(subject.id)">
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        Reviewer
                                    </Button>
                                    <Button size="sm" variant="destructive" class="gap-1.5" @click="requestDelete(subject)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Delete Classroom"
        :description="pendingDelete ? `Are you sure you want to permanently delete ${pendingDelete.title}? All data will be lost.` : ''"
        cancel-text="Cancel"
        confirm-text="Yes, Delete"
        @confirm="confirmDelete"
    />
</template>
