<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{ maskedEmail: string }>();

defineOptions({
    layout: {
        title: 'Enter your login code',
        description: 'We sent a 6-digit code to your email to finish signing in.',
    },
});

const form = useForm({ code: '' });

function submit() {
    form.transform((data) => ({ ...data, code: data.code.trim() }))
        .post('/otp-challenge');
}

function resend() {
    router.post('/otp-challenge/resend', {}, { preserveState: true });
}
</script>

<template>
    <Head title="Login code" />

    <form @submit.prevent="submit" class="space-y-6">
        <div class="flex flex-col items-center gap-2 text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <ShieldCheck class="h-6 w-6" />
            </div>
            <p class="text-sm text-muted-foreground">
                Enter the 6-digit code sent to <span class="font-medium text-foreground">{{ maskedEmail }}</span>.
            </p>
        </div>

        <div class="grid gap-2">
            <Label for="code">Login code</Label>
            <Input
                id="code"
                v-model="form.code"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                placeholder="000000"
                class="text-center text-2xl font-bold tracking-[0.5em]"
                autofocus
                required
            />
            <InputError :message="form.errors.code" />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing || form.code.length < 6">
            {{ form.processing ? 'Verifying…' : 'Verify & continue' }}
        </Button>

        <div class="text-center text-sm text-muted-foreground">
            Didn't get it?
            <button type="button" class="font-medium text-primary hover:underline" @click="resend">
                Resend code
            </button>
        </div>
    </form>
</template>
