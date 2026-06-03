<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { GraduationCap, BookOpen, Info } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { ref } from 'vue';

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});

const props = defineProps<{
    invitationEmail?: string | null;
    invitationSubject?: string | null;
    invitationRole?: string | null;
}>();

const selectedRole = ref<'student' | 'teacher'>('student');
</script>

<template>
    <Head title="Register" />

    <div class="flex flex-col gap-6">
        <!-- Invitation banner -->
        <div
            v-if="invitationSubject"
            class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/50"
        >
            <Info class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
            <div class="text-sm text-blue-800 dark:text-blue-300">
                <p class="font-medium">You have been invited as a reviewer</p>
                <p class="mt-0.5 text-xs text-blue-600 dark:text-blue-400">
                    Subject: {{ invitationSubject }}
                    <span v-if="invitationRole"> &middot; Role: {{ invitationRole?.replace('_', ' ') }}</span>
                </p>
            </div>
        </div>

        <template v-if="$page.props.googleLoginEnabled">
            <a
                :href="`/auth/google?role=${selectedRole}`"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2.5 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4" />
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                </svg>
                Sign up with Google
            </a>

            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <Separator class="w-full" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">Or continue with</span>
                </div>
            </div>
        </template>

        <Form
            v-bind="store.post()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label>I am a</Label>
                    <input type="hidden" name="role" :value="selectedRole" />
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium transition-colors"
                            :class="selectedRole === 'student'
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-input text-muted-foreground hover:border-foreground/50'"
                            @click="selectedRole = 'student'"
                        >
                            <GraduationCap class="h-4 w-4" />
                            Student
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium transition-colors"
                            :class="selectedRole === 'teacher'
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-input text-muted-foreground hover:border-foreground/50'"
                            @click="selectedRole = 'teacher'"
                        >
                            <BookOpen class="h-4 w-4" />
                            Teacher
                        </button>
                    </div>
                    <InputError :message="errors.role" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        :placeholder="invitationEmail ?? 'email@example.com'"
                        :default-value="invitationEmail ?? undefined"
                        :readonly="!!invitationEmail"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Create account
                </Button>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Log in</TextLink
            >
        </div>
    </div>
</template>
