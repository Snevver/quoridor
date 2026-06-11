<script setup>
import { computed } from 'vue';
import { useBoardView } from '@/composables/useBoardView';

const props = defineProps({
    wall: { type: Object, required: true },      // { x, y, orientation } in server space
    preview: { type: Boolean, default: false },
    valid: { type: Boolean, default: true },
});

const { anchorCoord } = useBoardView();

const style = computed(() => {
    const x = anchorCoord(props.wall.x);
    const y = anchorCoord(props.wall.y);
    return props.wall.orientation === 'H'
        ? { gridColumn: `${2 * x + 1} / span 3`, gridRow: `${2 * y + 2}` }
        : { gridColumn: `${2 * x + 2}`, gridRow: `${2 * y + 1} / span 3` };
});
</script>

<template>
    <div
        :class="preview ? ['qwall-preview', valid ? 'valid' : 'invalid'] : 'qwall'"
        :style="style"
    ></div>
</template>
