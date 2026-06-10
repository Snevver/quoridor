<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: Number, default: 0 },
    accent: { type: String, default: 'ink' }, // ink | p1 | p2 | gold | mint
});

const shown = ref(0);

function animateTo(target) {
    const from = shown.value;
    const start = performance.now();
    const duration = 900;
    const step = (now) => {
        const t = Math.min(1, (now - start) / duration);
        shown.value = Math.round(from + (target - from) * (1 - Math.pow(1 - t, 3)));
        if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

onMounted(() => animateTo(props.value));
watch(() => props.value, (v) => animateTo(v));

const accentClass = {
    ink: 'text-ink',
    p1: 'text-p1-bright',
    p2: 'text-p2-bright',
    gold: 'text-gold',
    mint: 'text-mint',
};
</script>

<template>
    <div class="glass rounded-2xl px-4 py-4 text-center">
        <div class="font-display font-bold text-2xl tabular-nums" :class="accentClass[accent]">{{ shown }}</div>
        <div class="font-mono text-[9px] uppercase tracking-[0.25em] text-dim mt-1.5">{{ label }}</div>
    </div>
</template>
