<script setup>
defineProps({
    title: String,
    subtitle: String,
});

const letters = 'QUORIDOR'.split('');
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
        <!-- Animated wordmark -->
        <router-link to="/" class="mb-3 select-none rise" style="--d: 0s">
            <h1 class="font-display text-4xl sm:text-6xl font-black tracking-[0.18em] flex">
                <span
                    v-for="(letter, i) in letters"
                    :key="i"
                    class="title-gradient rise inline-block"
                    :style="{ '--d': `${0.05 * i}s` }"
                >{{ letter }}</span>
            </h1>
        </router-link>

        <p class="font-mono text-[11px] uppercase tracking-[0.5em] text-dim mb-10 rise" style="--d: 0.4s">
            real-time strategy arena
        </p>

        <!-- Glass card with rotating border -->
        <div class="ring-spin glass rounded-3xl w-full max-w-md p-8 sm:p-10 rise" style="--d: 0.25s">
            <h2 class="font-display text-xl font-semibold mb-1">{{ title }}</h2>
            <p class="text-dim text-sm mb-8">{{ subtitle }}</p>
            <slot />
        </div>

        <div class="mt-8 text-dim text-sm rise" style="--d: 0.5s">
            <slot name="footer" />
        </div>
    </div>
</template>
