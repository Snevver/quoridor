<script setup>
import { computed } from 'vue';

// Pure geometry: grooves no longer take clicks — walls are placed by
// dragging from the wall stack (see useWallDrag), which reads these
// elements' positions and data attributes to snap the preview.
const props = defineProps({
    // grid coordinates of the groove segment
    cx: { type: Number, required: true },
    cy: { type: Number, required: true },
    orientation: { type: String, required: true }, // 'H' | 'V'
});

const gridStyle = computed(() =>
    props.orientation === 'H'
        ? { gridColumn: 2 * props.cx + 1, gridRow: 2 * props.cy + 2 }
        : { gridColumn: 2 * props.cx + 2, gridRow: 2 * props.cy + 1 }
);
</script>

<template>
    <div
        class="qslot"
        :style="gridStyle"
        :data-cx="cx"
        :data-cy="cy"
        :data-orientation="orientation"
    ></div>
</template>
