<script setup>
import { computed } from 'vue';

const props = defineProps({
    wall: { type: Object, required: true },      // { x, y, orientation }
    preview: { type: Boolean, default: false },
    valid: { type: Boolean, default: true },
});

const style = computed(() =>
    props.wall.orientation === 'H'
        ? { gridColumn: `${2 * props.wall.x + 1} / span 3`, gridRow: `${2 * props.wall.y + 2}` }
        : { gridColumn: `${2 * props.wall.x + 2}`, gridRow: `${2 * props.wall.y + 1} / span 3` }
);
</script>

<template>
    <div
        :class="preview ? ['qwall-preview', valid ? 'valid' : 'invalid'] : 'qwall'"
        :style="style"
    ></div>
</template>
