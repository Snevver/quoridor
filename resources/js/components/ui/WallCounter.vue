<script setup>
import { useWallDrag } from '@/composables/useWallDrag';

defineProps({
    total: { type: Number, default: 10 },
    left: { type: Number, required: true },
    color: { type: String, default: 'p1' }, // 'p1' | 'p2'
    draggable: { type: Boolean, default: false }, // own stack: chips can be dragged onto the board
});

const { startDrag, onDragMove, endDrag, cancelDrag } = useWallDrag();
</script>

<template>
    <div
        class="flex items-center gap-[3px]"
        :class="{ 'wall-drag-source': draggable }"
        @pointerdown="draggable && startDrag($event)"
        @pointermove="draggable && onDragMove($event)"
        @pointerup="draggable && endDrag()"
        @pointercancel="draggable && cancelDrag()"
    >
        <div
            v-for="i in total"
            :key="i"
            class="wall-chip w-[5px] h-5 rounded-full"
            :class="[
                i <= left
                    ? (color === 'p1' ? 'bg-p1 shadow-glow-p1' : 'bg-p2 shadow-glow-p2')
                    : 'spent bg-dim',
            ]"
        ></div>
        <span class="font-mono text-xs text-dim ml-2 tabular-nums">{{ left }}</span>
    </div>
</template>
