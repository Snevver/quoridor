<script setup>
import { computed } from 'vue';
import { useGameStore } from '@/stores/game';
import { useWallDrag } from '@/composables/useWallDrag';

// Mobile drag source: the side panel (with the wall stack) sits below the
// board on small screens, so a compact tray under the board keeps walls
// within thumb's reach while the board is visible.
const game = useGameStore();
const { startDrag, onDragMove, endDrag, cancelDrag } = useWallDrag();

const left = computed(() => game.boardState?.walls_left?.[game.myRole] ?? 0);
</script>

<template>
    <div
        v-if="game.myRole && !game.isFinished"
        class="lg:hidden glass rounded-2xl px-4 py-2.5 flex items-center gap-3 wall-drag-source"
        @pointerdown="startDrag"
        @pointermove="onDragMove"
        @pointerup="endDrag"
        @pointercancel="cancelDrag"
    >
        <span class="font-mono text-[9px] uppercase tracking-[0.25em] text-dim select-none">walls</span>
        <div class="flex items-center gap-1">
            <div
                v-for="i in 10"
                :key="i"
                class="wall-chip w-[7px] h-7 rounded-full"
                :class="[
                    i <= left
                        ? (game.myRole === 'p1' ? 'bg-p1 shadow-glow-p1' : 'bg-p2 shadow-glow-p2')
                        : 'spent bg-dim',
                ]"
            ></div>
        </div>
        <span class="font-mono text-xs text-dim tabular-nums select-none">{{ left }}</span>
    </div>
</template>
