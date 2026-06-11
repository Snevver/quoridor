<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/stores/game';
import { isMuted, toggleMute } from '@/lib/sound';
import QuoridorBoard from '@/components/board/QuoridorBoard.vue';
import TurnIndicator from '@/components/ui/TurnIndicator.vue';
import WallCounter from '@/components/ui/WallCounter.vue';
import WallTray from '@/components/ui/WallTray.vue';
import GameResult from '@/components/ui/GameResult.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';

const game = useGameStore();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const failed = ref(false);
const failMessage = ref('');
const muted = ref(isMuted());
const confirmingResign = ref(false);

const myElo = computed(() => {
    if (!game.eloResult) return { before: null, after: null };
    return game.myRole === 'p1'
        ? { before: game.eloResult.p1_before, after: game.eloResult.p1_after }
        : { before: game.eloResult.p2_before, after: game.eloResult.p2_after };
});

function onKeydown(event) {
    if (event.key === 'Escape') game.clearPreview();
}

function soundToggle() {
    muted.value = toggleMute();
}

async function resign() {
    if (!confirmingResign.value) {
        confirmingResign.value = true;
        setTimeout(() => (confirmingResign.value = false), 3000);
        return;
    }
    confirmingResign.value = false;
    try {
        await game.resign();
    } catch {
        /* already finished */
    }
}

function backToLobby() {
    router.push({ name: 'lobby' });
}

// Joining right after matchmaking can hit transient failures (brief network
// blips, server hiccups); retry before giving up so the opponent isn't left
// staring at "opponent disconnected".
async function loadGame() {
    loading.value = true;
    failed.value = false;

    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            await game.joinGame(route.params.slug);
            loading.value = false;
            return;
        } catch (error) {
            const status = error.response?.status;
            failMessage.value = status
                ? `The server replied with error ${status}${error.response.data?.message ? ` — ${error.response.data.message}` : ''}.`
                : 'Network error — check your connection.';
            if ([403, 404].includes(status)) break; // not transient, retrying won't help
            if (attempt < 3) await new Promise((r) => setTimeout(r, attempt * 1200));
        }
    }

    loading.value = false;
    failed.value = true;
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    loadGame();
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    game.leaveGame();
});

const playerCards = computed(() => {
    if (!game.game) return [];
    return ['p1', 'p2'].map((role) => ({
        role,
        info: game.game.players[role],
        isMe: role === game.myRole,
        wallsLeft: game.boardState?.walls_left?.[role] ?? 10,
        active: game.boardState?.status === 'active' && game.boardState?.current_turn === role,
    }));
});
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <GameResult
            v-if="game.isFinished && game.eloResult"
            :won="game.iWon"
            :voided="!game.boardState?.winner"
            :elo-before="myElo.before"
            :elo-after="myElo.after"
            :opponent-name="game.opponent?.name ?? 'Your rival'"
            @back="backToLobby"
        />

        <!-- top bar -->
        <header class="flex items-center justify-between px-5 sm:px-10 py-4 rise" style="--d: 0s">
            <button @click="backToLobby" class="font-display font-black text-base tracking-[0.2em] title-gradient select-none">
                QUORIDOR
            </button>
            <div class="flex items-center gap-3">
                <button @click="soundToggle" class="btn-ghost rounded-full w-9 h-9 grid place-items-center text-sm" :title="muted ? 'Unmute' : 'Mute'">
                    {{ muted ? '🔇' : '🔊' }}
                </button>
                <button
                    v-if="!game.isFinished"
                    @click="resign"
                    class="btn-ghost rounded-full px-4 py-1.5 text-xs uppercase tracking-widest"
                    :class="confirmingResign ? '!text-p2 !border-p2/60' : ''"
                >
                    {{ confirmingResign ? 'Confirm?' : 'Resign' }}
                </button>
            </div>
        </header>

        <LoadingSpinner v-if="loading" class="flex-1" />

        <div v-else-if="failed" class="flex-1 grid place-items-center">
            <div class="text-center px-4">
                <p class="text-dim mb-2">This match could not be loaded.</p>
                <p class="font-mono text-xs text-dim mb-4">{{ failMessage }}</p>
                <div class="flex items-center justify-center gap-3">
                    <button @click="loadGame" class="btn-hero rounded-xl px-8 py-3 text-sm text-white">
                        <span class="relative z-10">Retry</span>
                    </button>
                    <button @click="backToLobby" class="btn-ghost rounded-xl px-8 py-3 text-sm">
                        Back to lobby
                    </button>
                </div>
            </div>
        </div>

        <main v-else class="flex-1 w-full max-w-6xl 2xl:max-w-[88rem] mx-auto px-4 sm:px-8 pb-10
                            flex flex-col lg:flex-row items-center lg:items-start justify-center gap-8">
            <!-- side panel -->
            <aside class="w-full lg:w-[300px] space-y-4 order-2 lg:order-1 rise" style="--d: 0.1s">
                <div
                    v-for="card in playerCards"
                    :key="card.role"
                    class="glass rounded-2xl p-4 flex items-center gap-3.5 transition-all duration-300"
                    :class="card.active ? (card.role === 'p1' ? 'shadow-glow-p1 ring-1 ring-p1/40' : 'shadow-glow-p2 ring-1 ring-p2/40') : 'opacity-80'"
                >
                    <div class="w-11 h-11 rounded-full grid place-items-center font-display font-bold shrink-0"
                         :class="card.role === 'p1' ? 'bg-p1/20 text-p1-bright' : 'bg-p2/20 text-p2-bright'">
                        {{ (card.info?.name ?? '?').charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm truncate">{{ card.info?.name }}</span>
                            <span v-if="card.isMe" class="font-mono text-[9px] uppercase tracking-widest text-mint shrink-0">you</span>
                            <span
                                v-else
                                class="w-1.5 h-1.5 rounded-full shrink-0"
                                :class="game.opponentOnline ? 'bg-mint shadow-[0_0_8px_rgb(var(--c-success)/0.9)]' : 'bg-dim'"
                                :title="game.opponentOnline ? 'Online' : 'Offline'"
                            ></span>
                        </div>
                        <div class="font-mono text-[11px] text-dim tabular-nums mb-1.5">{{ card.info?.elo }} ELO</div>
                        <WallCounter :left="card.wallsLeft" :color="card.role" :draggable="card.isMe" />
                    </div>
                </div>

                <!-- opponent connection banner -->
                <transition name="fade">
                    <div v-if="!game.opponentOnline && !game.isFinished"
                         class="glass rounded-2xl px-4 py-3 text-center border !border-p2/30">
                        <span class="font-mono text-[11px] uppercase tracking-[0.2em] text-p2-bright animate-pulse">
                            opponent disconnected
                        </span>
                    </div>
                </transition>

                <p class="text-dim text-xs text-center leading-relaxed px-2">
                    Tap a glowing cell to move. Click a groove between cells (or drag a wall
                    from your stack) — green means legal, red means blocked. Reach the far side to win.
                </p>
            </aside>

            <!-- board -->
            <section class="order-1 lg:order-2 flex flex-col items-center gap-6 rise" style="--d: 0.2s">
                <TurnIndicator :is-my-turn="game.isMyTurn" :finished="game.isFinished" />
                <QuoridorBoard />
                <WallTray />
            </section>
        </main>
    </div>
</template>
