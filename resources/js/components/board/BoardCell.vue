<script setup>
import { computed } from 'vue';
import { useGameStore } from '@/stores/game';

const props = defineProps({
    x: { type: Number, required: true },
    y: { type: Number, required: true },
});

const game = useGameStore();

const isLegal = computed(() =>
    !game.wallMode && game.isMyTurn && game.legalMoves.some((m) => m.x === props.x && m.y === props.y)
);

const isLastMove = computed(() =>
    game.lastMove?.move_type === 'pawn'
    && game.lastMove.payload?.to?.[0] === props.x
    && game.lastMove.payload?.to?.[1] === props.y
);

const legalColor = computed(() =>
    game.myRole === 'p2' ? 'rgba(251,77,109,0.55)' : 'rgba(109,124,255,0.55)'
);

function onClick() {
    if (isLegal.value) game.submitPawnMove(props.x, props.y);
}
</script>

<template>
    <div
        class="qcell"
        :class="{
            'is-goal-p1': y === 8,
            'is-goal-p2': y === 0,
            'is-legal': isLegal,
            'last-move': isLastMove,
        }"
        :style="{
            gridColumn: 2 * x + 1,
            gridRow: 2 * y + 1,
            '--legal-color': legalColor,
        }"
        @click="onClick"
    ></div>
</template>
