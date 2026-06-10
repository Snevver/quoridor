<script setup>
defineProps({
    isMyTurn: Boolean,
    finished: Boolean,
});
</script>

<template>
    <transition name="turn-banner" mode="out-in">
        <div
            v-if="!finished"
            :key="isMyTurn"
            class="glass rounded-full px-6 py-2.5 flex items-center gap-3 select-none"
        >
            <span
                class="w-2.5 h-2.5 rounded-full"
                :class="isMyTurn ? 'bg-mint shadow-[0_0_12px_rgba(52,211,153,0.9)] animate-pulse' : 'bg-dim'"
            ></span>
            <span class="font-display text-xs sm:text-sm font-semibold tracking-[0.2em] uppercase"
                  :class="isMyTurn ? 'text-mint' : 'text-dim'">
                {{ isMyTurn ? 'Your move' : 'Opponent thinking…' }}
            </span>
        </div>
    </transition>
</template>
