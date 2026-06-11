<script setup>
import { computed, ref, watch } from 'vue';
import { useGameStore } from '@/stores/game';
import { useWallDrag } from '@/composables/useWallDrag';
import BoardCell from './BoardCell.vue';
import WallSlot from './WallSlot.vue';
import PlacedWall from './PlacedWall.vue';
import PlayerPawn from './PlayerPawn.vue';

const game = useGameStore();
const { drag } = useWallDrag();

const cells = [];
for (let y = 0; y < 9; y++) {
    for (let x = 0; x < 9; x++) cells.push({ x, y });
}

// Horizontal grooves sit under each cell (9 wide x 8 tall); vertical
// grooves sit right of each cell (8 wide x 9 tall).
const hSlots = [];
for (let cy = 0; cy < 8; cy++) {
    for (let cx = 0; cx < 9; cx++) hSlots.push({ cx, cy });
}

const vSlots = [];
for (let cy = 0; cy < 9; cy++) {
    for (let cx = 0; cx < 8; cx++) vSlots.push({ cx, cy });
}

const shaking = ref(false);
watch(() => game.shakeSignal, () => {
    shaking.value = false;
    requestAnimationFrame(() => (shaking.value = true));
    setTimeout(() => (shaking.value = false), 500);
});

const p1Active = computed(() => game.boardState?.status === 'active' && game.boardState?.current_turn === 'p1');
const p2Active = computed(() => game.boardState?.status === 'active' && game.boardState?.current_turn === 'p2');
</script>

<template>
    <div class="board-stage">
        <div class="board-tilt" :class="{ shake: shaking }">
            <div class="board-frame">
                <div v-if="game.boardState" class="qgrid" :class="{ 'qgrid-flipped': game.myRole === 'p1' }">
                    <BoardCell v-for="cell in cells" :key="`c${cell.x}-${cell.y}`" :x="cell.x" :y="cell.y" />

                    <WallSlot v-for="slot in hSlots" :key="`h${slot.cx}-${slot.cy}`"
                              :cx="slot.cx" :cy="slot.cy" orientation="H" />
                    <WallSlot v-for="slot in vSlots" :key="`v${slot.cx}-${slot.cy}`"
                              :cx="slot.cx" :cy="slot.cy" orientation="V" />

                    <PlacedWall v-for="(wall, i) in game.boardState.walls" :key="`w${i}`" :wall="wall" />

                    <PlacedWall v-if="game.pendingWall" :wall="game.pendingWall" preview :valid="game.pendingWall.valid" />

                    <PlayerPawn role="p1" :pos="game.boardState.pawns.p1" :active="p1Active"
                                :defeated="game.isFinished && game.boardState.winner !== 'p1'" />
                    <PlayerPawn role="p2" :pos="game.boardState.pawns.p2" :active="p2Active"
                                :defeated="game.isFinished && game.boardState.winner !== 'p2'" />
                </div>
            </div>
        </div>

        <!-- the wall being dragged from the stack; fades back once a groove snaps -->
        <Teleport to="body">
            <div
                v-if="drag.active"
                class="wall-ghost"
                :class="{ 'is-v': drag.orientation === 'V', 'is-snapped': !!game.pendingWall }"
                :style="{ left: `${drag.x}px`, top: `${drag.y}px` }"
            ></div>
        </Teleport>
    </div>
</template>
