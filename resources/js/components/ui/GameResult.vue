<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    won: Boolean,
    voided: Boolean,
    eloBefore: Number,
    eloAfter: Number,
    opponentName: String,
});

const emit = defineEmits(['back']);

const shownElo = ref(props.eloBefore ?? 0);
const delta = computed(() => (props.eloAfter ?? 0) - (props.eloBefore ?? 0));

const confetti = Array.from({ length: 70 }, (_, i) => {
    const palette = ['#2fc39b', '#e0763c', '#d4a843', '#b8e34d', '#f3ece0', '#62e2bd'];
    return {
        id: i,
        style: {
            left: `${Math.random() * 100}%`,
            width: `${5 + Math.random() * 6}px`,
            height: `${9 + Math.random() * 9}px`,
            background: palette[i % palette.length],
            '--fall-time': `${2.4 + Math.random() * 2.4}s`,
            '--fall-delay': `${Math.random() * 2.2}s`,
            '--spin': `${360 + Math.random() * 540}deg`,
        },
    };
});

onMounted(() => {
    // Count the rating up/down with an ease-out curve.
    const from = props.eloBefore ?? 0;
    const to = props.eloAfter ?? from;
    const startedAt = performance.now();
    const duration = 1600;

    const step = (now) => {
        const t = Math.min(1, (now - startedAt) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        shownElo.value = Math.round(from + (to - from) * eased);
        if (t < 1) requestAnimationFrame(step);
    };

    setTimeout(() => requestAnimationFrame(step), 650);
});
</script>

<template>
    <div class="fixed inset-0 z-50 overlay-veil grid place-items-center overflow-hidden px-4">
        <!-- confetti only rains for the victor -->
        <template v-if="won && !voided">
            <span v-for="c in confetti" :key="c.id" class="confetti" :style="c.style"></span>
        </template>

        <div class="result-card glass rounded-3xl px-8 sm:px-14 py-10 text-center max-w-md w-full relative"
             :class="won && !voided ? 'shadow-glow-gold' : ''">
            <div class="font-display font-black text-5xl sm:text-6xl mb-2 tracking-tight"
                 :class="voided ? 'text-dim' : won ? 'text-gold text-glow-p1' : 'text-dim'">
                {{ voided ? 'VOIDED' : won ? 'VICTORY' : 'DEFEAT' }}
            </div>

            <p class="text-dim text-sm mb-8">
                {{ voided
                    ? 'An admin voided this match — no ratings were changed.'
                    : won ? `${opponentName} has been outmaneuvered.` : `${opponentName} takes this one. Run it back?` }}
            </p>

            <div class="glass rounded-2xl py-5 px-6 mb-8">
                <div class="font-mono text-[10px] uppercase tracking-[0.35em] text-dim mb-2">rating</div>
                <div class="flex items-baseline justify-center gap-3">
                    <span class="font-display font-black text-4xl tabular-nums">{{ shownElo }}</span>
                    <span class="font-mono text-sm font-semibold tabular-nums"
                          :class="delta >= 0 ? 'text-mint' : 'text-p2'">
                        {{ delta >= 0 ? '+' : '' }}{{ delta }}
                    </span>
                </div>
            </div>

            <button @click="emit('back')" class="btn-hero w-full rounded-xl py-3.5 text-sm text-white">
                <span class="relative z-10">Back to lobby</span>
            </button>
        </div>
    </div>
</template>
