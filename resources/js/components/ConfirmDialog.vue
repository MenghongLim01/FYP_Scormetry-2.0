<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const open = defineModel<boolean>('open', { required: true });

const props = withDefaults(defineProps<{
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    confirmDisabled?: boolean;
}>(), {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    confirmDisabled: false,
});

const emit = defineEmits<{
    (e: 'confirm'): void;
}>();

function onConfirm() {
    emit('confirm');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">{{ cancelText }}</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="confirmDisabled"
                    @click="onConfirm"
                >
                    {{ confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

