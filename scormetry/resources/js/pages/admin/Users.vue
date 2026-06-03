<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { UserCheck, UserX, Shield } from 'lucide-vue-next';
import AdminUserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { edit as adminSettingsEdit } from '@/routes/admin/settings';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { ref } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: adminUsersIndex(),
            },
            {
                title: 'User Management',
                href: adminUsersIndex(),
            },
        ],
    },
});

defineProps<{
    users: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
        status: string;
        is_blocked: boolean;
        subjects_count: number;
        teaching_count: number;
        created_at: string;
    }>;
}>();

function approveUser(userId: number) {
    router.post(AdminUserController.approve.url({ user: userId }));
}

function changeRole(userId: number, role: string) {
    router.patch(AdminUserController.updateRole.url({ user: userId }), { role });
}

function toggleBlock(userId: number, isBlocked: boolean) {
    router.patch(
        (isBlocked ? AdminUserController.unblock : AdminUserController.block).url({ user: userId }),
        {},
        { preserveScroll: true },
    );
}

type PendingDelete = { id: number; name: string; teaching_count: number } | null;
const deleteOpen = ref(false);
const pendingDelete = ref<PendingDelete>(null);

function requestDelete(user: { id: number; name: string; teaching_count: number }) {
    pendingDelete.value = { id: user.id, name: user.name, teaching_count: user.teaching_count };
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!pendingDelete.value) return;
    router.delete(AdminUserController.destroy.url({ user: pendingDelete.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleteOpen.value = false;
            pendingDelete.value = null;
        },
    });
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    return status === 'approved' ? 'default' : 'destructive';
}
</script>

<template>
    <Head title="User Management" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">User Management</h1>
                <p class="text-sm text-muted-foreground">
                    Manage user accounts and approval requests
                </p>
            </div>
            <Link :href="adminSettingsEdit()">
                <Button variant="outline">
                    <Shield class="mr-2 h-4 w-4" />
                    Settings
                </Button>
            </Link>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>
                        All Users
                        <span class="ml-1.5 text-sm font-normal text-muted-foreground">({{ users.length }})</span>
                    </CardTitle>
                    <div class="flex items-center gap-3 text-xs text-muted-foreground">
                        <span>{{ users.filter((u) => u.status === 'pending').length }} pending approval</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="users.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    No users found.
                </div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-primary text-left text-xs font-semibold uppercase tracking-wider text-primary-foreground"
                        >
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Classrooms</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Registered</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-b transition-colors hover:bg-muted/70 last:border-0"
                        >
                            <td class="px-6 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-6 py-3 text-muted-foreground">{{ user.email }}</td>
                            <td class="px-6 py-3">
                                <select
                                    :value="user.role"
                                    class="rounded border border-input bg-transparent px-2 py-1 text-sm capitalize focus:outline-none focus:ring-2 focus:ring-ring/30"
                                    @change="changeRole(user.id, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="student">Student</option>
                                    <option value="teacher">Teacher</option>
                                </select>
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                <span class="font-medium text-foreground">{{ user.subjects_count + user.teaching_count }}</span>
                                <span class="ml-1 text-xs text-muted-foreground">(enrolled {{ user.subjects_count }}, teaching {{ user.teaching_count }})</span>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <Badge :variant="statusVariant(user.status)">
                                        {{ user.status }}
                                    </Badge>
                                    <Badge v-if="user.is_blocked" variant="destructive">blocked</Badge>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">
                                {{ new Date(user.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        v-if="user.status === 'pending'"
                                        size="sm"
                                        @click="approveUser(user.id)"
                                    >
                                        <UserCheck class="mr-1 h-3 w-3" />
                                        Approve
                                    </Button>
                                    <Button
                                        size="sm"
                                        :variant="user.is_blocked ? 'outline' : 'secondary'"
                                        @click="toggleBlock(user.id, user.is_blocked)"
                                    >
                                        {{ user.is_blocked ? 'Unblock' : 'Block' }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        @click="requestDelete({ id: user.id, name: user.name, teaching_count: user.teaching_count })"
                                    >
                                        <UserX class="mr-1 h-3 w-3" />
                                        Remove
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
        title="Delete User"
        :description="pendingDelete
            ? pendingDelete.teaching_count > 0
                ? `Are you sure you want to delete ${pendingDelete.name}? They own ${pendingDelete.teaching_count} subject${pendingDelete.teaching_count !== 1 ? 's' : ''} — all subjects, papers, teams, and student data inside them will also be permanently deleted. This cannot be undone.`
                : `Are you sure you want to delete ${pendingDelete.name}? This cannot be undone.`
            : ''"
        cancel-text="Cancel"
        confirm-text="Yes, Delete"
        @confirm="confirmDelete"
    />
</template>
