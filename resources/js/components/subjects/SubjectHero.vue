<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BookOpen, LogOut, Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { destroy as subjectDestroy, edit as subjectEdit, leave as subjectLeave } from '@/actions/App/Http/Controllers/SubjectController';

const props = defineProps<{
    subject: {
        id: number;
        title: string;
        description?: string | null;
        teacher: { name: string };
        passing_score: number;
        require_approval: boolean;
    };
    isOwnerOrAdmin: boolean;
    isSubjectOwner: boolean;
    canLeave: boolean;
}>();

const showDeleteDialog = ref(false);
const showLeaveDialog = ref(false);

function deleteSubject() {
    router.delete(subjectDestroy.url(props.subject.id), {
        onSuccess: () => { showDeleteDialog.value = false; },
    });
}

function leaveSubject() {
    router.delete(subjectLeave.url(props.subject.id), {
        onSuccess: () => { showLeaveDialog.value = false; },
    });
}
</script>

<template>
    <!-- Subject hero banner -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#3157f4] via-[#3345e5] to-[#4631cf] px-6 py-5 text-white shadow-sm">
        <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/10" />
        <div class="pointer-events-none absolute -bottom-10 right-28 h-28 w-28 rounded-full bg-white/10" />
        <div class="pointer-events-none absolute bottom-0 right-0 h-32 w-44 rounded-tl-full bg-white/5" />

        <div class="relative flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20 shadow-inner ring-2 ring-white/25">
                    <BookOpen class="h-6 w-6 text-white" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ subject.title }}</h1>
                    <p v-if="subject.description" class="mt-1 max-w-3xl text-sm text-white/75">{{ subject.description }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-white/80">
                        <span>Taught by <strong class="text-white">{{ subject.teacher.name }}</strong></span>
                        <span class="h-1 w-1 rounded-full bg-white/40" />
                        <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold text-white">Pass: {{ subject.passing_score }}%</span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="subject.require_approval
                                ? 'bg-amber-300 text-amber-950'
                                : 'bg-emerald-400 text-emerald-950'"
                        >
                            {{ subject.require_approval ? 'Approval Required' : 'Auto Join' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <Button v-if="isOwnerOrAdmin" size="sm" class="gap-1.5 bg-white/20 text-white hover:bg-white/30" as-child>
                    <Link :href="subjectEdit.url(subject.id)">
                        <Pencil class="h-3.5 w-3.5" />
                        Edit
                    </Link>
                </Button>

                <Dialog v-if="isSubjectOwner" v-model:open="showDeleteDialog">
                    <DialogTrigger as-child>
                        <Button size="sm" class="gap-1.5 bg-rose-500 text-white hover:bg-rose-600">
                            <Trash2 class="h-3.5 w-3.5" />
                            Delete
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Subject</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete <strong>"{{ subject.title }}"</strong>?
                                This will remove all teams, documents, and reviews permanently.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child><Button variant="outline">Cancel</Button></DialogClose>
                            <Button variant="destructive" @click="deleteSubject">Delete Subject</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog v-if="canLeave" v-model:open="showLeaveDialog">
                    <DialogTrigger as-child>
                        <Button size="sm" class="gap-1.5 bg-white/20 text-white hover:bg-white/30">
                            <LogOut class="h-3.5 w-3.5" />
                            Leave
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Leave Subject</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to leave <strong>"{{ subject.title }}"</strong>?
                                You will lose access to all documents and teams in this subject.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child><Button variant="outline">Cancel</Button></DialogClose>
                            <Button variant="destructive" @click="leaveSubject">Leave Subject</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </div>
</template>
