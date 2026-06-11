<script setup>
// Read-only thumbnail of any board_state — used in the admin match inspector.
defineProps({
    boardState: { type: Object, required: true },
});

function wallStyle(wall) {
    return wall.orientation === 'H'
        ? { gridColumn: `${2 * wall.x + 1} / span 3`, gridRow: `${2 * wall.y + 2}` }
        : { gridColumn: `${2 * wall.x + 2}`, gridRow: `${2 * wall.y + 1} / span 3` };
}
</script>

<template>
    <div class="qgrid mini-board" style="--cell: 22px; --slot: 4px">
        <template v-for="y in 9">
            <div
                v-for="x in 9"
                :key="`m${x}-${y}`"
                class="rounded-[2px]"
                :class="y === 9 ? 'bg-p1/15' : y === 1 ? 'bg-p2/15' : 'bg-white/[0.05]'"
                :style="{ gridColumn: 2 * (x - 1) + 1, gridRow: 2 * (y - 1) + 1 }"
            ></div>
        </template>

        <div v-for="(wall, i) in boardState.walls" :key="`mw${i}`"
             class="rounded-[2px] bg-ink/90 shadow-[0_0_6px_rgb(var(--c-ink)/0.5)]"
             :style="wallStyle(wall)"></div>

        <div class="rounded-full bg-p1 shadow-glow-p1 m-[3px]"
             :style="{ gridColumn: 2 * boardState.pawns.p1.x + 1, gridRow: 2 * boardState.pawns.p1.y + 1 }"></div>
        <div class="rounded-full bg-p2 shadow-glow-p2 m-[3px]"
             :style="{ gridColumn: 2 * boardState.pawns.p2.x + 1, gridRow: 2 * boardState.pawns.p2.y + 1 }"></div>
    </div>
</template>
