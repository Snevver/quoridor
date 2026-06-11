<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useMatchmakingStore } from '@/stores/matchmaking';
import { useRanksStore } from '@/stores/ranks';
import EloDisplay from '@/components/ui/EloDisplay.vue';

const auth = useAuthStore();
const matchmaking = useMatchmakingStore();
const ranks = useRanksStore();
const router = useRouter();

const leaderboard = ref([]);
const activeGameSlug = ref(null);
const queueTimer = ref(0);
let ticker = null;

const winRate = computed(() => {
    const played = auth.user?.games_played ?? 0;
    if (!played) return '—';
    return `${Math.round(((auth.user?.games_won ?? 0) / played) * 100)}%`;
});

const queueClock = computed(() => {
    const s = queueTimer.value;
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
});

async function joinQueue() {
    await matchmaking.join();
    queueTimer.value = 0;
    ticker = setInterval(() => queueTimer.value++, 1000);
}

async function leaveQueue() {
    clearInterval(ticker);
    await matchmaking.leave();
}

async function logout() {
    if (matchmaking.inQueue) await leaveQueue();
    matchmaking.stopListening();
    await auth.logout();
    router.push({ name: 'login' });
}

onMounted(async () => {
    matchmaking.listenForMatches();
    ranks.fetch();
    auth.fetchUser(); // fresh ELO + win counts after returning from a game

    axios.get('/api/leaderboard')
        .then(({ data }) => (leaderboard.value = data.players))
        .catch(() => {});

    // Offer to resume if a live game already exists (e.g. after refresh).
    try {
        const { data } = await axios.get('/api/matchmaking/status');
        activeGameSlug.value = data.active_game_slug;
        if (data.in_queue) {
            matchmaking.inQueue = true;
            queueTimer.value = data.waiting_seconds;
            matchmaking.startPolling();
            ticker = setInterval(() => queueTimer.value++, 1000);
        }
    } catch {
        /* fine */
    }
});

onUnmounted(() => clearInterval(ticker));

const medals = ['🥇', '🥈', '🥉'];
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <!-- top bar -->
        <header class="flex items-center justify-between flex-wrap gap-3 px-5 sm:px-10 py-5 rise" style="--d: 0s">
            <h1 class="font-display font-black text-lg sm:text-xl tracking-[0.2em] title-gradient select-none">
                QUORIDOR
            </h1>
            <div class="flex items-center flex-wrap gap-3 sm:gap-4">
                <router-link
                    v-if="auth.user.is_admin"
                    to="/admin"
                    class="btn-ghost rounded-full px-4 py-1.5 text-xs uppercase tracking-widest !text-gold !border-gold/40 hover:shadow-glow-gold"
                >
                    ⌖ Command deck
                </router-link>
                <router-link
                    :to="{ name: 'profile', params: { slug: auth.user.slug } }"
                    class="glass rounded-full pl-2 pr-4 py-1.5 flex items-center gap-2.5 hover:shadow-glow-p1 transition-shadow"
                    title="View your profile"
                >
                    <span class="w-7 h-7 rounded-full grid place-items-center bg-p1/25 text-p1-bright font-display font-bold text-xs">
                        {{ auth.user.name.charAt(0).toUpperCase() }}
                    </span>
                    <span class="text-sm font-medium">{{ auth.user.name }}</span>
                    <span class="font-mono text-xs text-gold tabular-nums">{{ auth.user.elo }}</span>
                </router-link>
                <button @click="logout" class="btn-ghost rounded-full px-4 py-1.5 text-xs uppercase tracking-widest">
                    Exit
                </button>
            </div>
        </header>

        <main class="flex-1 w-full max-w-6xl 2xl:max-w-[88rem] mx-auto px-5 sm:px-10 pb-14 grid lg:grid-cols-[1fr_380px] gap-8 items-start">
            <!-- left: hero / queue -->
            <section class="glass rounded-3xl p-5 sm:p-12 flex flex-col items-center text-center rise min-w-0" style="--d: 0.12s">
                <EloDisplay :elo="auth.user.elo" />

                <!-- rank ladder -->
                <div v-if="ranks.ranks.length" class="w-full max-w-md lg:max-w-2xl mt-8 min-w-0">
                    <div class="font-mono text-[9px] uppercase tracking-[0.35em] text-dim mb-3">rank ladder</div>
                    <!-- auto-fit wraps the ladder onto multiple rows on narrow screens;
                         84px columns fit long rank names like GRANDMASTER untruncated -->
                    <div class="grid gap-1.5" style="grid-template-columns: repeat(auto-fit, minmax(84px, 1fr))">
                        <div
                            v-for="rank in ranks.ranks"
                            :key="rank.id"
                            class="rank-step rounded-xl py-2.5 px-1 text-center min-w-0"
                            :class="{ 'is-current': ranks.rankFor(auth.user.elo)?.id === rank.id }"
                            :style="{ '--rank-color': rank.color }"
                        >
                            <div class="w-2.5 h-2.5 rounded-full mx-auto mb-1.5"
                                 :style="{ background: rank.color, boxShadow: `0 0 8px ${rank.color}` }"></div>
                            <div class="font-mono text-[8px] sm:text-[9px] uppercase tracking-wider truncate px-0.5"
                                 :style="{ color: rank.color }">{{ rank.name }}</div>
                            <div class="font-mono text-[8px] text-dim tabular-nums">{{ rank.min_elo }}+</div>
                            <div v-if="ranks.rankFor(auth.user.elo)?.id === rank.id"
                                 class="font-mono text-[8px] uppercase tracking-widest text-ink mt-1">▲ you</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 sm:gap-3 w-full max-w-sm mt-8 mb-10 min-w-0">
                    <div class="glass rounded-2xl py-4 px-1 min-w-0">
                        <div class="font-display font-bold text-xl sm:text-2xl tabular-nums">{{ auth.user.games_played }}</div>
                        <div class="font-mono text-[9px] sm:text-[10px] uppercase tracking-[0.15em] sm:tracking-[0.25em] text-dim mt-1 truncate">battles</div>
                    </div>
                    <div class="glass rounded-2xl py-4 px-1 min-w-0">
                        <div class="font-display font-bold text-xl sm:text-2xl tabular-nums text-mint">{{ auth.user.games_won }}</div>
                        <div class="font-mono text-[9px] sm:text-[10px] uppercase tracking-[0.15em] sm:tracking-[0.25em] text-dim mt-1 truncate">wins</div>
                    </div>
                    <div class="glass rounded-2xl py-4 px-1 min-w-0">
                        <div class="font-display font-bold text-xl sm:text-2xl tabular-nums text-gold">{{ winRate }}</div>
                        <div class="font-mono text-[9px] sm:text-[10px] uppercase tracking-[0.15em] sm:tracking-[0.25em] text-dim mt-1 truncate">winrate</div>
                    </div>
                </div>

                <!-- queue zone -->
                <div v-if="!matchmaking.inQueue" class="w-full max-w-sm space-y-3">
                    <button @click="joinQueue" class="btn-hero w-full rounded-2xl py-5 text-base text-white">
                        <span class="relative z-10">Find opponent</span>
                    </button>
                    <router-link
                        v-if="activeGameSlug"
                        :to="{ name: 'game', params: { slug: activeGameSlug } }"
                        class="btn-ghost block w-full rounded-2xl py-3.5 text-sm uppercase tracking-widest"
                    >
                        ⚔ Resume live match
                    </router-link>
                </div>

                <div v-else class="flex flex-col items-center gap-6">
                    <div class="radar grid place-items-center">
                        <span class="radar-ring"></span>
                        <span class="radar-ring"></span>
                        <span class="radar-ring"></span>
                        <span class="radar-sweep"></span>
                        <span class="font-mono text-lg text-p1-bright tabular-nums relative z-10">{{ queueClock }}</span>
                    </div>
                    <p class="font-mono text-[11px] uppercase tracking-[0.4em] text-dim animate-pulse">
                        scanning for rivals…
                    </p>
                    <button @click="leaveQueue" class="btn-ghost rounded-xl px-7 py-2.5 text-xs uppercase tracking-widest">
                        Cancel
                    </button>
                </div>
            </section>

            <!-- right: leaderboard -->
            <aside class="glass rounded-3xl p-7 rise" style="--d: 0.24s">
                <div class="flex items-baseline justify-between mb-6">
                    <h2 class="font-display font-semibold text-base tracking-wide">Hall of Strategists</h2>
                    <span class="font-mono text-[10px] uppercase tracking-[0.3em] text-dim">top 10</span>
                </div>

                <ol class="space-y-1.5">
                    <router-link
                        v-for="(player, i) in leaderboard"
                        :key="player.id"
                        :to="{ name: 'profile', params: { slug: player.slug } }"
                        custom
                        v-slot="{ navigate }"
                    >
                    <li
                        @click="navigate"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors cursor-pointer"
                        :class="[
                            player.id === auth.user.id ? 'bg-p1/10 ring-1 ring-p1/30' : 'hover:bg-white/[0.03]',
                        ]"
                        :title="`View ${player.name}'s profile`"
                    >
                        <span class="w-7 text-center font-mono text-sm" :class="i < 3 ? '' : 'text-dim'">
                            {{ medals[i] ?? i + 1 }}
                        </span>
                        <span class="flex-1 truncate text-sm font-medium">
                            {{ player.name }}
                            <span v-if="player.id === auth.user.id" class="font-mono text-[9px] uppercase tracking-widest text-mint ml-1">you</span>
                        </span>
                        <span
                            v-if="ranks.rankFor(player.elo)"
                            class="font-mono text-[9px] uppercase tracking-widest hidden sm:inline"
                            :style="{ color: ranks.rankFor(player.elo).color }"
                        >{{ ranks.rankFor(player.elo).name }}</span>
                        <span class="font-mono text-xs text-dim tabular-nums">{{ player.games_won }}W</span>
                        <span class="font-mono text-sm font-semibold text-gold tabular-nums">{{ player.elo }}</span>
                    </li>
                    </router-link>
                </ol>

                <p v-if="!leaderboard.length" class="text-dim text-sm text-center py-8">
                    No champions yet. Be the first.
                </p>
            </aside>
        </main>
    </div>
</template>
