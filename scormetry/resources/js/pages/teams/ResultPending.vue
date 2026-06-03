<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';

defineProps<{
    team: { id: number; name: string };
    subject: { id: number; title: string };
}>();
</script>

<template>
    <Head title="Results Pending" />

    <div class="flex flex-col items-center justify-center gap-6 p-6 py-24 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950">
            <Clock class="h-8 w-8 text-amber-600 dark:text-amber-400" />
        </div>
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Results Not Yet Released</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Results for <strong>{{ team.name }}</strong> in <strong>{{ subject.title }}</strong> have not been released yet.
            </p>
            <p class="mt-0.5 text-sm text-muted-foreground">
                Your instructor will notify you when scores are available.
            </p>
        </div>
        <Button variant="outline" as-child>
            <Link :href="subjectShow.url(subject.id)">
                <ArrowLeft class="mr-1.5 h-4 w-4" />
                Back to Subject
            </Link>
        </Button>
    </div>
</template>
