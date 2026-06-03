<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, RefreshCw, Trash2 } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import AdminClassroomController from '@/actions/App/Http/Controllers/Admin/ClassroomController';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminClassroomsIndex } from '@/routes/admin/classrooms';
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
        created_at: string;
        memberships_count: number;
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
    router.delete(AdminClassroomController.destroy.url({ subject: pendingDelete.value.id }), {
        onFinish: () => {
            deleteOpen.value = false;
            pendingDelete.value = null;
        },
    });
}

function resetJoinCode(subjectId: number) {
    router.patch(AdminClassroomController.resetJoinCode.url({ subject: subjectId }), {}, { preserveScroll: true });
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Admin Classrooms" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Classrooms</h1>
                <p class="text-sm text-muted-foreground">All classrooms across the system</p>
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
                <CardDescription class="text-xs">Admins can reset join codes or delete classrooms.</CardDescription>
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
                            <th class="px-6 py-3">Members</th>
                            <th class="px-6 py-3">Join Code</th>
                            <th class="px-6 py-3">Created</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="subject in subjects" :key="subject.id" class="transition-colors hover:bg-muted/70">
                            <td class="px-6 py-3 font-medium">
                                {{ subject.title }}
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                {{ subject.teacher?.name ?? '—' }}
                            </td>
                            <td class="px-6 py-3">
                                <Badge variant="secondary" class="text-xs">
                                    {{ subject.memberships_count }}
                                </Badge>
                            </td>
                            <td class="px-6 py-3 font-mono text-xs">
                                {{ subject.join_code ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                {{ formatDate(subject.created_at) }}
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" class="gap-1.5" @click="resetJoinCode(subject.id)">
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        Reset Code
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
