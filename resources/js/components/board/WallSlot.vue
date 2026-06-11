<script setup>
import { computed } from 'vue';
import { useGameStore } from '@/stores/game';
import { useBoardView } from '@/composables/useBoardView';

const props = defineProps({
    // logical grid coordinates of the groove segment (server space)
    cx: { type: Number, required: true },
    cy: { type: Number, required: true },
    orientation: { type: String, required: true }, // 'H' | 'V'
});

const game = useGameStore();
const { cellTrack, anchorCoord } = useBoardView();

// A 2-cell wall is anchored at this segment, clamped to the board.
const wall = computed(() => ({
    x: props.orientation === 'H' ? Math.min(props.cx, 7) : props.cx,
    y: props.orientation === 'H' ? props.cy : Math.min(props.cy, 7),
}));

const armed = computed(() => game.isMyTurn && !game.isFinished);

// Hover-capable devices (desktop) place walls by hovering a groove and
// clicking; touch devices drag from the wall tray instead, where these
// slots only serve as passive snap targets for useWallDrag.
const canHover = window.matchMedia('(hover: hover)').matches;

const gridStyle = computed(() =>
    props.orientation === 'H'
        ? { gridColumn: cellTrack(props.cx), gridRow: 2 * anchorCoord(props.cy) + 2 }
        : { gridColumn: 2 * anchorCoord(props.cx) + 2, gridRow: cellTrack(props.cy) }
);

function hover() {
    if (canHover && armed.value) game.previewWall(wall.value.x, wall.value.y, props.orientation);
}

function leave() {
    if (canHover) game.clearPreview();
}

function place() {
    if (!canHover || !armed.value) return;
    game.previewWall(wall.value.x, wall.value.y, props.orientation);
    game.submitWallPlacement(wall.value.x, wall.value.y, props.orientation);
}
</script>

<template>
    <div
        class="qslot"
        :class="{ armed: armed && canHover }"
        :style="gridStyle"
        :data-cx="cx"
        :data-cy="cy"
        :data-orientation="orientation"
        @mouseenter="hover"
        @mouseleave="leave"
        @click="place"
    ></div>
</template>
