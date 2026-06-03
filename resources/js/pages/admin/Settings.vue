<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import AdminSettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as adminSettingsEdit } from '@/routes/admin/settings';
import { index as adminUsersIndex } from '@/routes/admin/users';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: adminUsersIndex(),
            },
            {
                title: 'Settings',
                href: adminSettingsEdit(),
            },
        ],
    },
});

defineProps<{
    schoolEmailDomain: string;
    status?: string;
}>();
</script>

<template>
    <Head title="Admin Settings" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Admin Settings</h1>
            <Link :href="adminUsersIndex()">
                <Button variant="outline">
                    <Users class="mr-2 h-4 w-4" />
                    User Management
                </Button>
            </Link>
        </div>

        <div class="space-y-6">
            <Heading
                variant="small"
                title="Registration Settings"
                description="Control how new users can register and access the system"
            />

            <Form
                :action="AdminSettingsController.update.url()"
                method="patch"
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="school_email_domain">School email domain</Label>
                    <Input
                        id="school_email_domain"
                        name="school_email_domain"
                        type="text"
                        placeholder="school.edu"
                        :value="schoolEmailDomain"
                        class="max-w-sm"
                    />
                    <p class="text-xs text-muted-foreground">
                        Users registering with this email domain will be automatically
                        approved. Leave empty to require manual approval for all users.
                    </p>
                    <InputError :message="errors.school_email_domain" />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing">Save settings</Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </Form>
        </div>
    </div>
</template>
