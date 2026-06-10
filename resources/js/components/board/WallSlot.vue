<script setup>
import { computed } from 'vue';
import { useGameStore } from '@/stores/game';

const props = defineProps({
    // grid coordinates of the groove segment
    cx: { type: Number, required: true },
    cy: { type: Number, required: true },
    orientation: { type: String, required: true }, // 'H' | 'V'
});

const game = useGameStore();

// A 2-cell wall is anchored at the hovered segment, clamped to the board.
const wall = computed(() => ({
    x: props.orientation === 'H' ? Math.min(props.cx, 7) : props.cx,
    y: props.orientation === 'H' ? props.cy : Math.min(props.cy, 7),
    orientation: props.orientation,
}));

const armed = computed(() => game.wallMode && game.isMyTurn && !game.isFinished);

const gridStyle = computed(() =>
    props.orientation === 'H'
        ? { gridColumn: 2 * props.cx + 1, gridRow: 2 * props.cy + 2 }
        : { gridColumn: 2 * props.cx + 2, gridRow: 2 * props.cy + 1 }
);

function hover() {
    if (armed.value) game.previewWall(wall.value.x, wall.value.y, wall.value.orientation);
}

function place() {
    if (armed.value) game.submitWallPlacement(wall.value.x, wall.value.y, wall.value.orientation);
}
</script>

<template>
    <div
        class="qslot"
        :class="{ armed }"
        :style="gridStyle"
        @mouseenter="hover"
        @mouseleave="game.clearPreview()"
        @click="place"
    ></div>
</template>
