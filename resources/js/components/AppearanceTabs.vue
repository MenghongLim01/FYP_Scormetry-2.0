<script setup lang="ts">
import { Check, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    {
        value: 'light',
        Icon: Sun,
        label: 'Light',
        description: 'Clean and bright interface',
    },
    {
        value: 'dark',
        Icon: Moon,
        label: 'Dark',
        description: 'Easy on the eyes at night',
    },
] as const;
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <button
            v-for="{ value, Icon, label, description } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'group relative flex flex-col items-center gap-3 rounded-xl border-2 p-5 text-left transition-all duration-200',
                appearance === value
                    ? 'border-primary bg-primary/5 shadow-md dark:bg-primary/10'
                    : 'border-border hover:border-primary/40 hover:bg-muted/50',
            ]"
        >
            <!-- Selected checkmark -->
            <span
                v-if="appearance === value"
                class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <Check class="h-3 w-3" />
            </span>

            <!-- Preview mockup -->
            <div class="w-full overflow-hidden rounded-lg border border-border shadow-sm">
                <!-- Light preview -->
                <template v-if="value === 'light'">
                    <div class="bg-white">
                        <div class="flex items-center gap-1.5 border-b border-gray-200 bg-gray-50 px-3 py-2">
                            <div class="h-2 w-2 rounded-full bg-gray-300"></div>
                            <div class="h-2 w-12 rounded-full bg-gray-200"></div>
                        </div>
                        <div class="flex gap-2 p-3">
                            <div class="flex w-14 flex-col gap-1.5">
                                <div class="h-2 rounded-full bg-gray-200"></div>
                                <div class="h-2 rounded-full bg-blue-200"></div>
                                <div class="h-2 w-3/4 rounded-full bg-gray-200"></div>
                            </div>
                            <div class="flex-1 space-y-1.5">
                                <div class="h-2 rounded-full bg-gray-100"></div>
                                <div class="h-2 w-5/6 rounded-full bg-gray-100"></div>
                                <div class="h-2 w-4/6 rounded-full bg-gray-100"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Dark preview -->
                <template v-else>
                    <div class="bg-gray-900">
                        <div class="flex items-center gap-1.5 border-b border-gray-700 bg-gray-800 px-3 py-2">
                            <div class="h-2 w-2 rounded-full bg-gray-600"></div>
                            <div class="h-2 w-12 rounded-full bg-gray-700"></div>
                        </div>
                        <div class="flex gap-2 p-3">
                            <div class="flex w-14 flex-col gap-1.5">
                                <div class="h-2 rounded-full bg-gray-700"></div>
                                <div class="h-2 rounded-full bg-blue-800"></div>
                                <div class="h-2 w-3/4 rounded-full bg-gray-700"></div>
                            </div>
                            <div class="flex-1 space-y-1.5">
                                <div class="h-2 rounded-full bg-gray-800"></div>
                                <div class="h-2 w-5/6 rounded-full bg-gray-800"></div>
                                <div class="h-2 w-4/6 rounded-full bg-gray-800"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Label + icon row -->
            <div class="flex w-full items-center gap-2">
                <span
                    :class="[
                        'flex h-8 w-8 items-center justify-center rounded-lg transition-colors',
                        appearance === value
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary',
                    ]"
                >
                    <component :is="Icon" class="h-4 w-4" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-foreground">{{ label }}</p>
                    <p class="text-xs text-muted-foreground">{{ description }}</p>
                </div>
            </div>
        </button>
    </div>
</template>
