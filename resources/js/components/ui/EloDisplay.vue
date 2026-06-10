<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRanksStore } from '@/stores/ranks';

const props = defineProps({
    elo: { type: Number, required: true },
    size: { type: Number, default: 168 },
});

const ranksStore = useRanksStore();

const rank = computed(() => ranksStore.rankFor(props.elo));
const nextRank = computed(() => ranksStore.nextRankFor(props.elo));

// Ring fills with progress toward the next rank threshold.
const progress = computed(() => {
    if (!rank.value) return 0.04;
    if (!nextRank.value) return 1;
    const span = nextRank.value.min_elo - rank.value.min_elo;
    return Math.min(1, Math.max(0.04, (props.elo - rank.value.min_elo) / span));
});

const radius = computed(() => (props.size - 14) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);

const drawn = ref(false);
onMounted(async () => {
    await ranksStore.fetch();
    requestAnimationFrame(() => (drawn.value = true));
});

const dashOffset = computed(() =>
    drawn.value ? circumference.value * (1 - progress.value) : circumference.value
);
</script>

<template>
    <div class="relative grid place-items-center" :style="{ width: `${size}px`, height: `${size}px` }">
        <svg :width="size" :height="size" class="-rotate-90">
            <defs>
                <linearGradient id="eloGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#2fc39b" />
                    <stop offset="100%" stop-color="#e0763c" />
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
                <div v-if="rank" class="font-mono text-[10px] uppercase tracking-[0.3em] mt-1"
                     :style="{ color: rank.color }">
                    {{ rank.name }}
                </div>
                <div v-if="nextRank" class="font-mono text-[9px] text-dim mt-1 tabular-nums">
                    {{ nextRank.min_elo - elo }} to {{ nextRank.name }}
                </div>
            </div>
        </div>
    </div>
</template>
