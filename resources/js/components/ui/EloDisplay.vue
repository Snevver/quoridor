<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    elo: { type: Number, required: true },
    size: { type: Number, default: 168 },
});

// Ring fills relative to a 600–2000 ladder — purely decorative scale.
const progress = computed(() => Math.min(1, Math.max(0.04, (props.elo - 600) / 1400)));

const radius = computed(() => (props.size - 14) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);

const drawn = ref(false);
onMounted(() => requestAnimationFrame(() => (drawn.value = true)));

const dashOffset = computed(() =>
    drawn.value ? circumference.value * (1 - progress.value) : circumference.value
);

const tier = computed(() => {
    if (props.elo >= 1800) return 'Grandmaster';
    if (props.elo >= 1600) return 'Master';
    if (props.elo >= 1400) return 'Diamond';
    if (props.elo >= 1300) return 'Gold';
    if (props.elo >= 1150) return 'Silver';
    return 'Bronze';
});
</script>

<template>
    <div class="relative grid place-items-center" :style="{ width: `${size}px`, height: `${size}px` }">
        <svg :width="size" :height="size" class="-rotate-90">
            <defs>
                <linearGradient id="eloGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#6d7cff" />
                    <stop offset="100%" stop-color="#fb4d6d" />
                </linearGradient>
            </defs>
            <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" stroke-width="7" class="elo-ring-track" />
            <circle
                :cx="size / 2" :cy="size / 2" :r="radius" fill="none" stroke-width="7"
                class="elo-ring-fill"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
            />
        </svg>
        <div class="absolute inset-0 grid place-items-center text-center">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.35em] text-dim mb-1">rating</div>
                <div class="font-display font-black text-4xl tabular-nums">{{ elo }}</div>
                <div class="font-mono text-[10px] uppercase tracking-[0.3em] text-gold mt-1">{{ tier }}</div>
            </div>
        </div>
    </div>
</template>
