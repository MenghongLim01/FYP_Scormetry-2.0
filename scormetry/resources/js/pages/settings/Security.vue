<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck, ShieldOff, ShieldAlert, KeyRound, Lock } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Security settings', href: edit() }],
    },
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
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

        <!-- ── Two-Factor Authentication ────────────────────────────────────── -->
        <section v-if="canManageTwoFactor" class="space-y-5">

            <!-- Section header -->
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg"
                    :class="twoFactorEnabled
                        ? 'bg-emerald-50 dark:bg-emerald-950/40'
                        : 'bg-slate-100 dark:bg-slate-800'"
                >
                    <component
                        :is="twoFactorEnabled ? ShieldCheck : ShieldOff"
                        class="h-4 w-4"
                        :class="twoFactorEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                    />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-foreground">Two-factor authentication</h2>
                    <p class="text-sm text-muted-foreground">
                        Add an extra layer of security to your account.
                    </p>
                </div>
            </div>

            <!-- 2FA Status card -->
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">

                <!-- Status banner -->
                <div
                    class="flex items-center gap-3 border-b px-5 py-3"
                    :class="twoFactorEnabled
                        ? 'bg-emerald-50/60 border-emerald-100 dark:bg-emerald-950/20 dark:border-emerald-900'
                        : 'bg-slate-50 border-border dark:bg-slate-900/20'"
                >
                    <span
                        class="flex h-2 w-2 rounded-full"
                        :class="twoFactorEnabled ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
                    />
                    <span
                        class="text-sm font-semibold"
                        :class="twoFactorEnabled ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'"
                    >
                        {{ twoFactorEnabled ? '2FA is Enabled' : '2FA is Disabled' }}
                    </span>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-4">
                    <!-- Disabled state -->
                    <template v-if="!twoFactorEnabled">
                        <p class="text-sm text-muted-foreground">
                            When you enable two-factor authentication, you will be prompted for a secure
                            one-time PIN during login. This PIN is generated by a TOTP-compatible app
                            (such as Google Authenticator or Authy) on your phone.
                        </p>
                        <div class="flex items-center gap-3">
                            <Button v-if="hasSetupData" @click="showSetupModal = true" class="gap-2">
                                <ShieldAlert class="h-4 w-4" />
                                Continue setup
                            </Button>
                            <Form
                                v-else
                                :action="enable.url()"
                                method="post"
                                @success="showSetupModal = true"
                                #default="{ processing }"
                            >
                                <Button type="submit" :disabled="processing" class="gap-2">
                                    <ShieldCheck class="h-4 w-4" />
                                    Enable two-factor authentication
                                </Button>
                            </Form>
                        </div>
                    </template>

                    <!-- Enabled state -->
                    <template v-else>
                        <p class="text-sm text-muted-foreground">
                            Your account is protected with two-factor authentication. You will be prompted
                            for a one-time PIN from your authenticator app each time you log in.
                        </p>

                        <!-- Recovery codes -->
                        <TwoFactorRecoveryCodes />

                        <!-- Disable button -->
                        <div class="border-t border-border pt-4">
                            <p class="mb-3 text-xs text-muted-foreground">
                                Disabling 2FA will remove the extra protection from your account.
                            </p>
                            <Form :action="disable.url()" method="delete" #default="{ processing }">
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    :disabled="processing"
                                    class="gap-2"
                                >
                                    <ShieldOff class="h-4 w-4" />
                                    Disable two-factor authentication
                                </Button>
                            </Form>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Setup modal -->
            <TwoFactorSetupModal
                v-model:isOpen="showSetupModal"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </section>

        <!-- 2FA not available notice -->
        <section v-else class="rounded-xl border border-border bg-slate-50 dark:bg-slate-900/20 p-5">
            <div class="flex items-center gap-3">
                <Lock class="h-5 w-5 shrink-0 text-muted-foreground" />
                <div>
                    <p class="text-sm font-medium text-foreground">Two-factor authentication</p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Two-factor authentication is not available for your account type.
                    </p>
                </div>
            </div>
        </section>

    </div>
</template>
