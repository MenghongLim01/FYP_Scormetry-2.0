<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { KeyRound, Mail, ShieldCheck, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { edit } from '@/routes/security';

const props = withDefaults(defineProps<{ otpLoginEnabled?: boolean }>(), {
    otpLoginEnabled: false,
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Security settings', href: edit() }],
    },
});

const otpEnabled = ref(props.otpLoginEnabled);
const otpSaving = ref(false);

function setOtp(enabled: boolean) {
    otpSaving.value = true;
    router.patch('/settings/security/otp', { enabled }, {
        preserveScroll: true,
        onSuccess: () => { otpEnabled.value = enabled; },
        onFinish: () => { otpSaving.value = false; },
    });
}
</script>

<template>
    <Head title="Security settings" />
    <h1 class="sr-only">Security settings</h1>

    <div class="space-y-8">

        <!-- ── Update Password ──────────────────────────────────────────────── -->
        <section class="space-y-5">
            <!-- Section header -->
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950/40">
                    <KeyRound class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-foreground">Update password</h2>
                    <p class="text-sm text-muted-foreground">
                        Ensure your account is using a long, random password to stay secure.
                    </p>
                </div>
            </div>

            <Form
                :action="SecurityController.update.url()"
                method="put"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="['password', 'password_confirmation', 'current_password']"
                class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="current_password" class="text-sm font-medium">Current password</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="block w-full"
                        autocomplete="current-password"
                        placeholder="Enter current password"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password" class="text-sm font-medium">New password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        class="block w-full"
                        autocomplete="new-password"
                        placeholder="Enter new password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation" class="text-sm font-medium">Confirm new password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        class="block w-full"
                        autocomplete="new-password"
                        placeholder="Confirm new password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4 border-t border-border pt-4">
                    <Button :disabled="processing" data-test="update-password-button">
                        Save password
                    </Button>
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-show="recentlySuccessful" class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                            Password saved.
                        </p>
                    </Transition>
                </div>
            </Form>
        </section>

        <Separator />

        <!-- ── Email login code (OTP) ───────────────────────────────────────── -->
        <section class="space-y-5">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg"
                    :class="otpEnabled ? 'bg-emerald-50 dark:bg-emerald-950/40' : 'bg-slate-100 dark:bg-slate-800'"
                >
                    <ShieldCheck class="h-4 w-4" :class="otpEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-foreground">Email login code</h2>
                    <p class="text-sm text-muted-foreground">
                        Add a one-time code, sent to your email, as a second step when you sign in.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300">
                            <Mail class="h-4.5 w-4.5" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                {{ otpEnabled ? 'Email login code is ON' : 'Email login code is OFF' }}
                            </p>
                            <p class="mt-0.5 max-w-md text-xs text-muted-foreground">
                                When ON, after entering your email and password you'll be asked for a 6-digit code sent to your
                                email. This protects your account even if someone learns your password. It's optional — leave it OFF
                                to sign in with just email and password.
                            </p>
                        </div>
                    </div>
                    <Button
                        :variant="otpEnabled ? 'outline' : 'default'"
                        :disabled="otpSaving"
                        class="shrink-0 gap-2"
                        @click="setOtp(!otpEnabled)"
                    >
                        <Loader2 v-if="otpSaving" class="h-4 w-4 animate-spin" />
                        <ShieldCheck v-else class="h-4 w-4" />
                        {{ otpEnabled ? 'Turn off' : 'Turn on' }}
                    </Button>
                </div>
            </div>
        </section>

    </div>
</template>
