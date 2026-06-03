<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import { Bold, Italic, Underline as UnderlineIcon, Strikethrough, List, ListOrdered, Quote, Heading2, Undo, Redo } from 'lucide-vue-next';
import { watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
        }),
        Underline,
        Placeholder.configure({
            placeholder: props.placeholder ?? 'Write your feedback…',
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm dark:prose-invert max-w-none min-h-[120px] px-3 py-2 focus:outline-none',
        },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', ed.getHTML());
    },
});

watch(
    () => props.modelValue,
    (val) => {
        if (editor.value && editor.value.getHTML() !== val) {
            editor.value.commands.setContent(val, { emitUpdate: false });
        }
    },
);

type BtnDef = { action: () => void; isActive: () => boolean; icon: typeof Bold; title: string };

const buttons: BtnDef[] = [
    { action: () => editor.value?.chain().focus().toggleBold().run(), isActive: () => editor.value?.isActive('bold') ?? false, icon: Bold, title: 'Bold' },
    { action: () => editor.value?.chain().focus().toggleItalic().run(), isActive: () => editor.value?.isActive('italic') ?? false, icon: Italic, title: 'Italic' },
    { action: () => editor.value?.chain().focus().toggleUnderline().run(), isActive: () => editor.value?.isActive('underline') ?? false, icon: UnderlineIcon, title: 'Underline' },
    { action: () => editor.value?.chain().focus().toggleStrike().run(), isActive: () => editor.value?.isActive('strike') ?? false, icon: Strikethrough, title: 'Strikethrough' },
    { action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), isActive: () => editor.value?.isActive('heading', { level: 2 }) ?? false, icon: Heading2, title: 'Heading' },
    { action: () => editor.value?.chain().focus().toggleBulletList().run(), isActive: () => editor.value?.isActive('bulletList') ?? false, icon: List, title: 'Bullet List' },
    { action: () => editor.value?.chain().focus().toggleOrderedList().run(), isActive: () => editor.value?.isActive('orderedList') ?? false, icon: ListOrdered, title: 'Ordered List' },
    { action: () => editor.value?.chain().focus().toggleBlockquote().run(), isActive: () => editor.value?.isActive('blockquote') ?? false, icon: Quote, title: 'Blockquote' },
];
</script>

<template>
    <div class="rounded-lg border bg-background">
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b px-2 py-1.5">
            <button
                v-for="btn in buttons"
                :key="btn.title"
                type="button"
                :title="btn.title"
                class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                :class="{ 'bg-accent text-foreground': btn.isActive() }"
                @click="btn.action()"
            >
                <component :is="btn.icon" class="h-3.5 w-3.5" />
            </button>
            <div class="mx-1 h-4 w-px bg-border" />
            <button
                type="button"
                title="Undo"
                class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                @click="editor?.chain().focus().undo().run()"
            >
                <Undo class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                title="Redo"
                class="flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                @click="editor?.chain().focus().redo().run()"
            >
                <Redo class="h-3.5 w-3.5" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
