<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useRanksStore } from '@/stores/ranks';
import EloDisplay from '@/components/ui/EloDisplay.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';

const route = useRoute();
const ranks = useRanksStore();

const profile = ref(null);
const failed = ref(false);

const user = computed(() => profile.value?.user);
const rank = computed(() => (user.value ? ranks.rankFor(user.value.elo) : null));

const winRate = computed(() => {
    if (!user.value?.games_played) return '—';
    return `${Math.round((user.value.games_won / user.value.games_played) * 100)}%`;
});

async function load() {
    profile.value = null;
    failed.value = false;
    try {
        profile.value = (await axios.get(`/api/users/${route.params.id}`)).data;
    } catch {
        failed.value = true;
    }
}

function fmtDate(iso, withTime = false) {
    return new Date(iso).toLocaleDateString([], {
        day: '2-digit', month: 'short', year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}

watch(() => route.params.id, () => route.name === 'profile' && load());

onMounted(() => {
    ranks.fetch();
    load();
});
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <header class="flex items-center justify-between px-5 sm:px-10 py-5 rise" style="--d: 0s">
            <router-link to="/lobby" class="font-display font-black text-lg tracking-[0.2em] title-gradient select-none">
                QUORIDOR
            </router-link>
            <router-link to="/lobby" class="btn-ghost rounded-full px-4 py-1.5 text-xs uppercase tracking-widest">
                ← Lobby
            </router-link>
        </header>

        <LoadingSpinner v-if="!profile && !failed" class="flex-1" />

        <div v-else-if="failed" class="flex-1 grid place-items-center text-dim">Player not found.</div>

        <main v-else class="flex-1 w-full max-w-4xl mx-auto px-5 sm:px-10 pb-14 space-y-6">
            <!-- identity card -->
            <section class="glass rounded-3xl p-8 sm:p-10 flex flex-col sm:flex-row items-center gap-8 rise" style="--d: 0.08s">
                <div class="flex flex-col items-center gap-4">
                    <div class="w-24 h-24 rounded-full grid place-items-center font-display font-black text-4xl bg-p1/15 text-p1-bright shadow-glow-p1">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="text-center">
                        <div class="font-display font-bold text-xl flex items-center gap-2 justify-center">
                            {{ user.name }}
                            <span v-if="user.is_admin" class="font-mono text-[9px] uppercase tracking-widest text-gold border border-gold/40 rounded px-1.5 py-0.5">admin</span>
                        </div>
                        <div v-if="rank" class="font-mono text-[11px] uppercase tracking-[0.3em] mt-1.5" :style="{ color: rank.color }">
                            {{ rank.name }}
                        </div>
                        <div class="font-mono text-[10px] text-dim mt-1.5">strategist since {{ fmtDate(user.created_at) }}</div>
                    </div>
                </div>

                <div class="flex-1 flex flex-col items-center gap-6">
                    <EloDisplay :elo="user.elo" :size="150" />
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 w-full max-w-sm min-w-0">
                        <div class="glass rounded-2xl py-3.5 px-1 text-center min-w-0">
                            <div class="font-display font-bold text-xl tabular-nums">{{ user.games_played }}</div>
                            <div class="font-mono text-[9px] uppercase tracking-[0.15em] text-dim mt-1 truncate">battles</div>
                        </div>
                        <div class="glass rounded-2xl py-3.5 px-1 text-center min-w-0">
                            <div class="font-display font-bold text-xl tabular-nums text-mint">{{ user.games_won }}</div>
                            <div class="font-mono text-[9px] uppercase tracking-[0.15em] text-dim mt-1 truncate">wins</div>
                        </div>
                        <div class="glass rounded-2xl py-3.5 px-1 text-center min-w-0">
                            <div class="font-display font-bold text-xl tabular-nums text-gold">{{ winRate }}</div>
                            <div class="font-mono text-[9px] uppercase tracking-[0.15em] text-dim mt-1 truncate">winrate</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- match history -->
            <section class="glass rounded-3xl p-7 rise" style="--d: 0.18s">
                <h2 class="font-display font-semibold text-base tracking-wide mb-5">Recent battles</h2>

                <div class="space-y-1.5">
                    <div v-for="match in profile.recent_games" :key="match.id"
                         class="flex items-center gap-4 rounded-xl px-4 py-3 hover:bg-white/[0.025] transition-colors">
                        <span class="font-mono text-[10px] uppercase tracking-widest w-12 text-center rounded-md py-1"
                              :class="match.voided ? 'text-dim bg-white/5' : match.won ? 'text-mint bg-mint/10' : 'text-p2-bright bg-p2/10'">
                            {{ match.voided ? 'void' : match.won ? 'win' : 'loss' }}
                        </span>
                        <span class="flex-1 text-sm truncate">
                            vs
                            <router-link :to="{ name: 'profile', params: { id: match.opponent.id } }"
                                         class="font-medium text-ink hover:text-p1-bright transition-colors">
                                {{ match.opponent.name }}
                            </router-link>
                        </span>
                        <span class="font-mono text-xs font-semibold tabular-nums"
                              :class="match.elo_change > 0 ? 'text-mint' : match.elo_change < 0 ? 'text-p2-bright' : 'text-dim'">
                            {{ match.elo_change > 0 ? '+' : '' }}{{ match.elo_change }}
                        </span>
                        <span class="font-mono text-[10px] text-dim hidden sm:inline">{{ fmtDate(match.played_at, true) }}</span>
                    </div>
                </div>

                <p v-if="!profile.recent_games.length" class="text-dim text-sm text-center py-8">
                    No finished battles yet.
                </p>
            </section>
        </main>
    </div>
</template>
