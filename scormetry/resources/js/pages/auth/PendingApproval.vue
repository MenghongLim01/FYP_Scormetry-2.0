<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Clock, RefreshCw, Mail } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { ref } from 'vue';

defineOptions({
    layout: {
        title: 'Approval pending',
        description: 'Your account is awaiting approval by an administrator.',
    },
});

defineProps<{
    userEmail?: string;
}>();

const checking = ref(false);

function checkStatus() {
    checking.value = true;
    router.reload({
        onFinish: () => {
            checking.value = false;
        },
    });
}
</script>

<template>
    <Head title="Pending Approval" />

    <div class="flex flex-col items-center gap-6 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950">
            <Clock class="h-8 w-8 text-amber-600 dark:text-amber-400" />
        </div>

        <div class="space-y-2">
            <h2 class="text-lg font-semibold">Your account is pending approval</h2>
            <p class="text-sm text-muted-foreground">
                Your registration was received. An administrator will review and
                approve your account shortly.
            </p>
        </div>

        <div class="w-full rounded-lg border border-amber-200 bg-amber-50 p-4 text-left dark:border-amber-900 dark:bg-amber-950/50">
            <h3 class="mb-2 text-sm font-medium text-amber-800 dark:text-amber-300">What happens next?</h3>
            <ul class="space-y-1.5 text-xs text-amber-700 dark:text-amber-400">
                <li class="flex items-start gap-2">
                    <Mail class="mt-0.5 h-3 w-3 shrink-0" />
                    <span>You will receive an email once your account is approved.</span>
                </li>
                <li class="flex items-start gap-2">
                    <RefreshCw class="mt-0.5 h-3 w-3 shrink-0" />
                    <span>You can also check your status by clicking the button below.</span>
                </li>
            </ul>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <Button type="button" variant="secondary" :disabled="checking" @click="checkStatus">
                <RefreshCw :class="['h-4 w-4', checking && 'animate-spin']" />
                Check Status
            </Button>
            <Form :action="logout()" method="post">
                <Button type="submit" variant="outline" class="w-full">Log out</Button>
            </Form>
        </div>
    </div>
</template>
