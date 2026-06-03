<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpenText, ClipboardCheck, ShieldCheck, Users } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();

const features = [
    { icon: BookOpenText, label: 'Create rubrics & manage subjects', color: 'text-blue-400' },
    { icon: Users,         label: 'Assign teams and coordinate reviewers', color: 'text-violet-400' },
    { icon: ClipboardCheck,label: 'Track scores and release results', color: 'text-emerald-400' },
    { icon: ShieldCheck,   label: 'Role-based access for everyone', color: 'text-indigo-400' },
];
</script>

<template>
    <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">

        <!-- ── Left panel ── -->
        <div class="relative hidden h-full flex-col overflow-hidden p-10 lg:flex dark:border-r">

            <!-- Base: deep neutral-navy, less saturated -->
            <div class="absolute inset-0 bg-[#151b2e]" />

            <!-- Very soft color washes (barely there) -->
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-32 -left-16 h-80 w-80 rounded-full bg-blue-500/12 blur-3xl" />
                <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-violet-500/12 blur-3xl" />
            </div>

            <!-- Subtle dot grid -->
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.045]"
                style="background-image: radial-gradient(circle, #94a3b8 1px, transparent 1px); background-size: 26px 26px;"
            />

            <!-- Thin top accent line -->
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/40 to-transparent" />

            <!-- Logo -->
            <Link :href="home()" class="relative z-20 flex items-center gap-2.5 text-sm font-semibold text-white">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/8 ring-1 ring-white/12">
                    <AppLogoIcon class="size-5 fill-current text-white" />
                </div>
                {{ name }}
            </Link>

            <!-- Centre: floating mock cards -->
            <div class="relative z-20 flex flex-1 items-center justify-center">
                <div class="relative w-full max-w-xs">

                    <!-- Card 1 — team overview -->
                    <div class="rounded-2xl border border-white/8 bg-white/5 p-4 shadow-xl backdrop-blur-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="h-6 w-6 rounded-full bg-blue-500/30 ring-1 ring-blue-400/30 flex items-center justify-center">
                                    <Users class="h-3 w-3 text-blue-300" />
                                </div>
                                <span class="text-xs font-medium text-white/70">Team Alpha</span>
                            </div>
                            <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-400 ring-1 ring-emerald-500/20">
                                Active
                            </span>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-[11px] text-white/40">
                                <span>Final Score</span>
                                <span class="font-semibold text-white/70">87.4</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-white/8">
                                <div class="h-1.5 w-[72%] rounded-full bg-gradient-to-r from-blue-500 to-violet-500" />
                            </div>
                        </div>
                        <!-- Mini reviewer row -->
                        <div class="mt-3 flex items-center gap-1.5">
                            <div v-for="i in 3" :key="i"
                                class="h-5 w-5 rounded-full bg-white/10 ring-1 ring-white/15 text-[9px] flex items-center justify-center text-white/50 font-medium">
                                {{ ['R1','R2','R3'][i-1] }}
                            </div>
                            <span class="ml-1 text-[10px] text-white/35">3 reviewers</span>
                        </div>
                    </div>

                    <!-- Card 2 — score breakdown (offset) -->
                    <div class="mt-3 ml-6 rounded-2xl border border-white/8 bg-white/4 p-4 shadow-lg backdrop-blur-sm">
                        <p class="mb-2.5 text-[11px] font-medium text-white/50">Score Breakdown</p>
                        <div class="space-y-2">
                            <div v-for="item in [{label:'Methodology',pct:'82%',color:'bg-blue-500'},{label:'Presentation',pct:'90%',color:'bg-violet-500'},{label:'Innovation',pct:'75%',color:'bg-emerald-500'}]"
                                :key="item.label"
                                class="flex items-center gap-2">
                                <span class="w-20 text-[10px] text-white/40 truncate">{{ item.label }}</span>
                                <div class="flex-1 h-1 rounded-full bg-white/8">
                                    <div :class="['h-1 rounded-full', item.color]" :style="{ width: item.pct }" />
                                </div>
                                <span class="text-[10px] text-white/40">{{ item.pct }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Floating badge -->
                    <div class="absolute -top-4 -right-2 flex items-center gap-1.5 rounded-full border border-white/10 bg-white/6 px-3 py-1.5 shadow-lg backdrop-blur-sm">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                        <span class="text-[10px] font-medium text-white/60">Results released</span>
                    </div>
                </div>
            </div>

            <!-- Bottom: tagline + feature list -->
            <div class="relative z-20 space-y-5">
                <div class="space-y-1.5">
                    <p class="text-lg font-semibold text-white leading-snug">
                        Streamline your academic<br />paper review process.
                    </p>
                    <p class="text-sm text-white/40 leading-relaxed">
                        From rubrics to results — everything coordinated in one place.
                    </p>
                </div>

                <ul class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <li v-for="f in features" :key="f.label" class="flex items-center gap-2 text-[11px] text-white/45">
                        <component :is="f.icon" :class="['h-3 w-3 shrink-0', f.color]" />
                        {{ f.label }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── Right panel ── -->
        <div class="relative lg:p-8">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-blue-50/30 via-transparent to-violet-50/20 dark:from-blue-950/10 dark:to-violet-950/10" />

            <div class="relative mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                <div class="flex flex-col items-center space-y-2 text-center lg:hidden">
                    <Link :href="home()" class="mb-2">
                        <AppLogoIcon class="size-10 text-foreground" />
                    </Link>
                </div>

                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-semibold tracking-tight" v-if="title">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground" v-if="description">{{ description }}</p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
