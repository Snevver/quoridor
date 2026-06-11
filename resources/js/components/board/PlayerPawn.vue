<script setup>
import { computed } from 'vue';

const props = defineProps({
    role: { type: String, required: true }, // 'p1' | 'p2'
    pos: { type: Object, required: true },  // { x, y }
    active: Boolean,
    defeated: Boolean,
});

// Pawns live above the grid and glide between cells via transform.
const style = computed(() => ({
    transform: `translate(
        calc(${props.pos.x} * (var(--cell) + var(--slot))),
        calc(${props.pos.y} * (var(--cell) + var(--slot)))
    )`,
    '--ring-color': props.role === 'p1' ? 'rgb(var(--c-p1) / 0.8)' : 'rgb(var(--c-p2) / 0.8)',
}));
</script>

<template>
    <div class="pawn" :class="[`pawn-${role}`, { 'is-active': active, 'is-defeated': defeated }]" :style="style">
        <div class="pawn-gem"></div>
    </div>
</template>
