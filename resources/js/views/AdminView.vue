<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import StatCard from '@/components/admin/StatCard.vue';
import MiniBoard from '@/components/admin/MiniBoard.vue';

const auth = useAuthStore();

const stats = ref(null);
const tab = ref('players');

// double-click safety on destructive actions: first click arms, second fires
const armed = ref(null);
function confirmThen(key, action) {
    if (armed.value === key) {
        armed.value = null;
        return action();
    }
    armed.value = key;
    setTimeout(() => {
        if (armed.value === key) armed.value = null;
    }, 3000);
}

async function loadStats() {
    stats.value = (await axios.get('/api/admin/stats')).data;
}

/* ---- players ------------------------------------------------------- */
const users = ref(null);
const search = ref('');
const eloEdit = reactive({ id: null, value: 0 });
let searchDebounce = null;

async function loadUsers(page = 1) {
    const { data } = await axios.get('/api/admin/users', { params: { search: search.value || undefined, page } });
    users.value = data;
}

function onSearch() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadUsers(1), 300);
}

function startEloEdit(user) {
    eloEdit.id = user.id;
    eloEdit.value = user.elo;
}

async function saveElo(user) {
    await axios.patch(`/api/admin/users/${user.id}/elo`, { elo: Number(eloEdit.value) });
    eloEdit.id = null;
    await Promise.all([loadUsers(users.value.current_page), loadStats()]);
}

async function toggleBan(user) {
    await axios.patch(`/api/admin/users/${user.id}/ban`);
    await Promise.all([loadUsers(users.value.current_page), loadStats()]);
}

async function toggleAdmin(user) {
    await axios.patch(`/api/admin/users/${user.id}/admin`);
    await loadUsers(users.value.current_page);
}

/* ---- matches ------------------------------------------------------- */
const games = ref(null);
const gameFilter = ref('');
const inspected = ref(null);

async function loadGames(page = 1) {
    const { data } = await axios.get('/api/admin/games', { params: { status: gameFilter.value || undefined, page } });
    games.value = data;
}

function setFilter(value) {
    gameFilter.value = value;
    inspected.value = null;
    loadGames(1);
}

async function inspect(game) {
    if (inspected.value?.id === game.id) {
        inspected.value = null;
        return;
    }
    inspected.value = (await axios.get(`/api/admin/games/${game.slug}`)).data;
}

async function endGame(game, result) {
    await axios.post(`/api/admin/games/${game.slug}/end`, { result });
    inspected.value = null;
    await Promise.all([loadGames(games.value.current_page), loadStats()]);
}

/* ---- queue ---------------------------------------------------------- */
const queue = ref([]);

async function loadQueue() {
    queue.value = (await axios.get('/api/admin/queue')).data.entries;
}

async function kick(entry) {
    await axios.delete(`/api/admin/queue/${entry.user_id}`);
    await Promise.all([loadQueue(), loadStats()]);
}

async function clearQueue() {
    await axios.delete('/api/admin/queue');
    await Promise.all([loadQueue(), loadStats()]);
}

/* ---- ranks ----------------------------------------------------------- */
const ranks = ref([]);
const rankDraft = reactive({ id: null, name: '', min_elo: 0, color: '#fbbf24' });
const rankError = ref('');

async function loadRanks() {
    ranks.value = (await axios.get('/api/ranks')).data;
}

function editRank(rank) {
    rankError.value = '';
    Object.assign(rankDraft, rank ?? { id: null, name: '', min_elo: 0, color: '#fbbf24' });
}

async function saveRank() {
    rankError.value = '';
    const payload = { name: rankDraft.name, min_elo: Number(rankDraft.min_elo), color: rankDraft.color };
    try {
        if (rankDraft.id) {
            await axios.patch(`/api/admin/ranks/${rankDraft.id}`, payload);
        } else {
            await axios.post('/api/admin/ranks', payload);
        }
        editRank(null);
        await loadRanks();
    } catch (error) {
        rankError.value = Object.values(error.response?.data?.errors ?? {})[0]?.[0] ?? 'Could not save rank.';
    }
}

async function deleteRank(rank) {
    await axios.delete(`/api/admin/ranks/${rank.id}`);
    await loadRanks();
}

/* --------------------------------------------------------------------- */
const tabs = [
    { id: 'players', label: 'Players' },
    { id: 'matches', label: 'Matches' },
    { id: 'queue', label: 'Queue' },
    { id: 'ranks', label: 'Ranks' },
];

function selectTab(id) {
    tab.value = id;
    if (id === 'players' && !users.value) loadUsers();
    if (id === 'matches' && !games.value) loadGames();
    if (id === 'queue') loadQueue();
    if (id === 'ranks') loadRanks();
}

const statCards = computed(() => stats.value && [
    { label: 'players', value: stats.value.users, accent: 'ink' },
    { label: 'live games', value: stats.value.games_active, accent: 'mint' },
    { label: 'games total', value: stats.value.games_total, accent: 'p1' },
    { label: 'games today', value: stats.value.games_today, accent: 'p1' },
    { label: 'moves played', value: stats.value.moves_total, accent: 'gold' },
    { label: 'in queue', value: stats.value.in_queue, accent: 'p2' },
    { label: 'banned', value: stats.value.banned, accent: 'p2' },
]);

function fmtDate(iso) {
    return new Date(iso).toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

onMounted(() => {
    loadStats();
    loadUsers();
});
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <!-- top bar -->
        <header class="flex items-center justify-between flex-wrap gap-3 px-4 sm:px-10 py-5 rise" style="--d: 0s">
            <div class="flex items-baseline gap-4 min-w-0">
                <router-link to="/lobby" class="font-display font-black text-lg tracking-[0.2em] title-gradient select-none">
                    QUORIDOR
                </router-link>
                <span class="hidden sm:inline font-display font-semibold text-xs tracking-[0.45em] uppercase text-gold text-glow-p1">
                    ⌖ command deck
                </span>
            </div>
            <router-link to="/lobby" class="btn-ghost rounded-full px-4 py-1.5 text-xs uppercase tracking-widest">
                ← Lobby
            </router-link>
        </header>

        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-10 pb-14 space-y-6">
            <!-- stats -->
            <section v-if="statCards" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 rise" style="--d: 0.08s">
                <StatCard v-for="card in statCards" :key="card.label" v-bind="card" />
            </section>

            <!-- tabs -->
            <nav class="glass rounded-2xl p-2 inline-flex flex-wrap gap-2 rise" style="--d: 0.16s">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    @click="selectTab(t.id)"
                    class="rounded-xl px-4 sm:px-6 py-2.5 text-xs font-display font-semibold uppercase tracking-[0.2em] transition-all"
                    :class="tab === t.id ? 'bg-gold/20 text-gold shadow-glow-gold' : 'text-dim hover:text-ink'"
                >
                    {{ t.label }}
                </button>
            </nav>

            <!-- PLAYERS -->
            <section v-if="tab === 'players'" class="glass rounded-3xl p-4 sm:p-6 rise" style="--d: 0.2s">
                <input
                    v-model="search"
                    @input="onSearch"
                    type="search"
                    placeholder="Search by name or email…"
                    class="input-arena max-w-sm mb-5"
                />

                <!-- phone: stacked cards, nothing to scroll sideways -->
                <div class="md:hidden space-y-2">
                    <div v-for="user in users?.data ?? []" :key="`card-${user.id}`"
                         class="rounded-xl border border-line/50 p-3.5 space-y-2"
                         :class="{ 'opacity-50': user.banned_at }">
                        <div class="flex items-center gap-2 font-medium flex-wrap">
                            <span class="truncate min-w-0">{{ user.name }}</span>
                            <span v-if="user.is_admin" class="font-mono text-[9px] uppercase tracking-widest text-gold border border-gold/40 rounded px-1.5 py-0.5 shrink-0">admin</span>
                            <span v-if="user.banned_at" class="font-mono text-[9px] uppercase tracking-widest text-p2 border border-p2/40 rounded px-1.5 py-0.5 shrink-0">banned</span>
                            <span v-if="user.id === auth.user.id" class="font-mono text-[9px] uppercase tracking-widest text-mint shrink-0">you</span>
                        </div>
                        <div class="text-dim text-xs truncate">{{ user.email }}</div>
                        <div class="flex items-center flex-wrap gap-x-4 gap-y-1 font-mono text-xs tabular-nums">
                            <template v-if="eloEdit.id === user.id">
                                <input v-model.number="eloEdit.value" type="number" min="0" max="4000"
                                       class="input-arena !py-1 !px-2 w-24 !rounded-lg"
                                       @keyup.enter="saveElo(user)" @keyup.escape="eloEdit.id = null" />
                                <button @click="saveElo(user)" class="text-mint hover:underline">save</button>
                            </template>
                            <button v-else @click="startEloEdit(user)"
                                    class="text-gold hover:underline decoration-dotted underline-offset-4"
                                    title="Tap to edit">
                                {{ user.elo }} ELO ✎
                            </button>
                            <span class="text-dim">{{ user.games_won }} / {{ user.games_played }} won</span>
                            <span class="text-dim">{{ fmtDate(user.created_at) }}</span>
                        </div>
                        <div v-if="user.id !== auth.user.id" class="flex flex-wrap gap-2 pt-1">
                            <button @click="confirmThen(`admin-${user.id}`, () => toggleAdmin(user))"
                                    class="btn-ghost rounded-lg px-3 py-1.5 text-[10px] uppercase tracking-widest"
                                    :class="armed === `admin-${user.id}` ? '!text-gold !border-gold/60' : ''">
                                {{ armed === `admin-${user.id}` ? 'Sure?' : (user.is_admin ? 'Demote' : 'Promote') }}
                            </button>
                            <button @click="confirmThen(`ban-${user.id}`, () => toggleBan(user))"
                                    class="btn-ghost rounded-lg px-3 py-1.5 text-[10px] uppercase tracking-widest"
                                    :class="armed === `ban-${user.id}` ? '!text-p2 !border-p2/60' : ''">
                                {{ armed === `ban-${user.id}` ? 'Sure?' : (user.banned_at ? 'Unban' : 'Ban') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="font-mono text-[10px] uppercase tracking-[0.25em] text-dim text-left">
                                <th class="py-2 pr-4">Player</th>
                                <th class="py-2 pr-4">ELO</th>
                                <th class="py-2 pr-4">W / P</th>
                                <th class="py-2 pr-4">Joined</th>
                                <th class="py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users?.data ?? []" :key="user.id"
                                class="border-t border-line/50 hover:bg-white/[0.025] transition-colors"
                                :class="{ 'opacity-50': user.banned_at }">
                                <td class="py-3 pr-4">
                                    <div class="flex items-center gap-2 font-medium">
                                        {{ user.name }}
                                        <span v-if="user.is_admin" class="font-mono text-[9px] uppercase tracking-widest text-gold border border-gold/40 rounded px-1.5 py-0.5">admin</span>
                                        <span v-if="user.banned_at" class="font-mono text-[9px] uppercase tracking-widest text-p2 border border-p2/40 rounded px-1.5 py-0.5">banned</span>
                                        <span v-if="user.id === auth.user.id" class="font-mono text-[9px] uppercase tracking-widest text-mint">you</span>
                                    </div>
                                    <div class="text-dim text-xs">{{ user.email }}</div>
                                </td>
                                <td class="py-3 pr-4 font-mono tabular-nums">
                                    <template v-if="eloEdit.id === user.id">
                                        <input v-model.number="eloEdit.value" type="number" min="0" max="4000"
                                               class="input-arena !py-1 !px-2 w-24 !rounded-lg"
                                               @keyup.enter="saveElo(user)" @keyup.escape="eloEdit.id = null" />
                                        <button @click="saveElo(user)" class="text-mint text-xs ml-2 hover:underline">save</button>
                                    </template>
                                    <button v-else @click="startEloEdit(user)"
                                            class="text-gold hover:underline decoration-dotted underline-offset-4"
                                            title="Click to edit">
                                        {{ user.elo }} ✎
                                    </button>
                                </td>
                                <td class="py-3 pr-4 font-mono text-xs text-dim tabular-nums">{{ user.games_won }} / {{ user.games_played }}</td>
                                <td class="py-3 pr-4 font-mono text-xs text-dim">{{ fmtDate(user.created_at) }}</td>
                                <td class="py-3 text-right whitespace-nowrap">
                                    <template v-if="user.id !== auth.user.id">
                                        <button @click="confirmThen(`admin-${user.id}`, () => toggleAdmin(user))"
                                                class="btn-ghost rounded-lg px-3 py-1.5 text-[10px] uppercase tracking-widest mr-2"
                                                :class="armed === `admin-${user.id}` ? '!text-gold !border-gold/60' : ''">
                                            {{ armed === `admin-${user.id}` ? 'Sure?' : (user.is_admin ? 'Demote' : 'Promote') }}
                                        </button>
                                        <button @click="confirmThen(`ban-${user.id}`, () => toggleBan(user))"
                                                class="btn-ghost rounded-lg px-3 py-1.5 text-[10px] uppercase tracking-widest"
                                                :class="armed === `ban-${user.id}` ? '!text-p2 !border-p2/60' : ''">
                                            {{ armed === `ban-${user.id}` ? 'Sure?' : (user.banned_at ? 'Unban' : 'Ban') }}
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="users && users.last_page > 1" class="flex items-center justify-end gap-3 mt-4 font-mono text-xs text-dim">
                    <button :disabled="users.current_page <= 1" @click="loadUsers(users.current_page - 1)"
                            class="btn-ghost rounded-lg px-3 py-1.5 disabled:opacity-30">‹ prev</button>
                    <span class="tabular-nums">{{ users.current_page }} / {{ users.last_page }}</span>
                    <button :disabled="users.current_page >= users.last_page" @click="loadUsers(users.current_page + 1)"
                            class="btn-ghost rounded-lg px-3 py-1.5 disabled:opacity-30">next ›</button>
                </div>
            </section>

            <!-- MATCHES -->
            <section v-if="tab === 'matches'" class="glass rounded-3xl p-4 sm:p-6 rise" style="--d: 0.2s">
                <div class="flex flex-wrap gap-2 mb-5">
                    <button v-for="f in [['', 'all'], ['active', 'live'], ['finished', 'finished']]" :key="f[0]"
                            @click="setFilter(f[0])"
                            class="rounded-full px-4 py-1.5 text-[10px] font-mono uppercase tracking-[0.25em] border transition-all"
                            :class="gameFilter === f[0] ? 'border-gold/60 text-gold' : 'border-line text-dim hover:text-ink'">
                        {{ f[1] }}
                    </button>
                </div>

                <div class="space-y-2">
                    <div v-for="game in games?.data ?? []" :key="game.id">
                        <button @click="inspect(game)"
                                class="w-full flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl px-4 py-3 border border-line/50 hover:bg-white/[0.025] transition-colors text-left">
                            <span class="font-mono text-xs text-dim shrink-0">#{{ game.id }}</span>
                            <span class="flex-1 min-w-[150px] text-sm">
                                <span class="text-p1-bright font-medium">{{ game.player1?.name }}</span>
                                <span class="text-dim mx-2">vs</span>
                                <span class="text-p2-bright font-medium">{{ game.player2?.name }}</span>
                            </span>
                            <span class="font-mono text-xs text-dim tabular-nums hidden sm:inline">{{ game.moves_count }} moves</span>
                            <span class="font-mono text-[10px] uppercase tracking-widest rounded-full px-3 py-1 border"
                                  :class="game.status === 'active' ? 'text-mint border-mint/40' : 'text-dim border-line'">
                                {{ game.status === 'active' ? '● live' : (game.winner ? `${game.winner.name} won` : 'void') }}
                            </span>
                        </button>

                        <!-- inspector -->
                        <div v-if="inspected?.id === game.id"
                             class="glass rounded-xl mt-1 p-4 sm:p-5 flex flex-col md:flex-row gap-6 items-start">
                            <MiniBoard :board-state="inspected.board_state" />
                            <div class="flex-1 min-w-0 space-y-4">
                                <div class="font-mono text-xs text-dim space-y-1">
                                    <div>started {{ fmtDate(inspected.created_at) }}</div>
                                    <div>walls left — <span class="text-p1-bright">{{ inspected.board_state.walls_left.p1 }}</span> / <span class="text-p2-bright">{{ inspected.board_state.walls_left.p2 }}</span></div>
                                    <div>turn — {{ inspected.board_state.current_turn }}</div>
                                </div>

                                <div v-if="game.status === 'active'" class="flex flex-wrap gap-2">
                                    <button @click="confirmThen(`end-p1-${game.id}`, () => endGame(game, 'p1'))"
                                            class="btn-ghost rounded-lg px-4 py-2 text-[10px] uppercase tracking-widest"
                                            :class="armed === `end-p1-${game.id}` ? '!text-p1-bright !border-p1/60' : ''">
                                        {{ armed === `end-p1-${game.id}` ? 'Sure?' : `${game.player1?.name} wins` }}
                                    </button>
                                    <button @click="confirmThen(`end-p2-${game.id}`, () => endGame(game, 'p2'))"
                                            class="btn-ghost rounded-lg px-4 py-2 text-[10px] uppercase tracking-widest"
                                            :class="armed === `end-p2-${game.id}` ? '!text-p2-bright !border-p2/60' : ''">
                                        {{ armed === `end-p2-${game.id}` ? 'Sure?' : `${game.player2?.name} wins` }}
                                    </button>
                                    <button @click="confirmThen(`end-void-${game.id}`, () => endGame(game, 'void'))"
                                            class="btn-ghost rounded-lg px-4 py-2 text-[10px] uppercase tracking-widest"
                                            :class="armed === `end-void-${game.id}` ? '!text-gold !border-gold/60' : ''">
                                        {{ armed === `end-void-${game.id}` ? 'Sure?' : 'Void (no ELO)' }}
                                    </button>
                                </div>

                                <div v-if="inspected.moves?.length" class="max-h-40 overflow-y-auto font-mono text-xs text-dim space-y-0.5 pr-2">
                                    <div v-for="move in inspected.moves" :key="move.id" class="flex gap-3">
                                        <span class="w-8 text-right tabular-nums opacity-60 shrink-0">{{ move.move_number }}.</span>
                                        <span class="w-20 sm:w-28 truncate shrink-0">{{ move.player?.name }}</span>
                                        <span v-if="move.move_type === 'pawn'">→ ({{ move.payload.to[0] }},{{ move.payload.to[1] }})</span>
                                        <span v-else>▦ wall {{ move.payload.orientation }} ({{ move.payload.x }},{{ move.payload.y }})</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="games && !games.data.length" class="text-dim text-sm text-center py-8">No matches found.</p>
                </div>

                <div v-if="games && games.last_page > 1" class="flex items-center justify-end gap-3 mt-4 font-mono text-xs text-dim">
                    <button :disabled="games.current_page <= 1" @click="loadGames(games.current_page - 1)"
                            class="btn-ghost rounded-lg px-3 py-1.5 disabled:opacity-30">‹ prev</button>
                    <span class="tabular-nums">{{ games.current_page }} / {{ games.last_page }}</span>
                    <button :disabled="games.current_page >= games.last_page" @click="loadGames(games.current_page + 1)"
                            class="btn-ghost rounded-lg px-3 py-1.5 disabled:opacity-30">next ›</button>
                </div>
            </section>

            <!-- QUEUE -->
            <section v-if="tab === 'queue'" class="glass rounded-3xl p-4 sm:p-6 rise" style="--d: 0.2s">
                <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                    <h2 class="font-display font-semibold text-sm tracking-wide">Matchmaking queue</h2>
                    <button v-if="queue.length" @click="confirmThen('clear-queue', clearQueue)"
                            class="btn-ghost rounded-lg px-4 py-2 text-[10px] uppercase tracking-widest"
                            :class="armed === 'clear-queue' ? '!text-p2 !border-p2/60' : ''">
                        {{ armed === 'clear-queue' ? 'Sure?' : 'Clear all' }}
                    </button>
                </div>

                <div class="space-y-2">
                    <div v-for="entry in queue" :key="entry.id"
                         class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl px-4 py-3 border border-line/50">
                        <span class="w-2 h-2 rounded-full bg-mint animate-pulse shrink-0"></span>
                        <span class="flex-1 min-w-[110px] text-sm font-medium truncate">{{ entry.user?.name }}</span>
                        <span class="font-mono text-xs text-gold tabular-nums shrink-0">{{ entry.elo_at_join }} ELO</span>
                        <span class="font-mono text-xs text-dim">since {{ fmtDate(entry.created_at) }}</span>
                        <button @click="kick(entry)" class="btn-ghost rounded-lg px-3 py-1.5 text-[10px] uppercase tracking-widest">
                            Kick
                        </button>
                    </div>
                    <p v-if="!queue.length" class="text-dim text-sm text-center py-8">Queue is empty.</p>
                </div>
            </section>

            <!-- RANKS -->
            <section v-if="tab === 'ranks'" class="glass rounded-3xl p-4 sm:p-6 rise" style="--d: 0.2s">
                <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                    <h2 class="font-display font-semibold text-sm tracking-wide">Rank ladder · MMR thresholds</h2>
                    <button @click="editRank(null); rankDraft.min_elo = (ranks.at(-1)?.min_elo ?? 0) + 200"
                            class="btn-ghost rounded-lg px-4 py-2 text-[10px] uppercase tracking-widest !text-gold !border-gold/40">
                        + New rank
                    </button>
                </div>

                <div class="space-y-2 mb-6">
                    <div v-for="(rank, i) in ranks" :key="rank.id"
                         class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl px-4 py-3 border border-line/50 hover:bg-white/[0.025] transition-colors">
                        <span class="w-3.5 h-3.5 rounded-full shrink-0"
                              :style="{ background: rank.color, boxShadow: `0 0 10px ${rank.color}` }"></span>
                        <span class="flex-1 min-w-[100px] font-display font-semibold text-sm truncate" :style="{ color: rank.color }">{{ rank.name }}</span>
                        <span class="font-mono text-xs text-dim tabular-nums">
                            {{ rank.min_elo }}{{ ranks[i + 1] ? ` – ${ranks[i + 1].min_elo - 1}` : '+' }} MMR
                        </span>
                        <button @click="editRank(rank)" class="btn-ghost rounded-lg px-3 py-1.5 text-[10px] uppercase tracking-widest">Edit</button>
                        <button @click="confirmThen(`rank-del-${rank.id}`, () => deleteRank(rank))"
                                class="btn-ghost rounded-lg px-3 py-1.5 text-[10px] uppercase tracking-widest"
                                :class="armed === `rank-del-${rank.id}` ? '!text-p2 !border-p2/60' : ''">
                            {{ armed === `rank-del-${rank.id}` ? 'Sure?' : 'Delete' }}
                        </button>
                    </div>
                </div>

                <!-- editor -->
                <div class="glass rounded-2xl p-5 max-w-xl">
                    <div class="font-mono text-[10px] uppercase tracking-[0.3em] text-dim mb-4">
                        {{ rankDraft.id ? `editing — ${rankDraft.name}` : 'new rank' }}
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[140px]">
                            <label class="block font-mono text-[9px] uppercase tracking-[0.25em] text-dim mb-1.5">Name</label>
                            <input v-model="rankDraft.name" class="input-arena !py-2" placeholder="Diamond" />
                        </div>
                        <div class="w-28">
                            <label class="block font-mono text-[9px] uppercase tracking-[0.25em] text-dim mb-1.5">Min MMR</label>
                            <input v-model.number="rankDraft.min_elo" type="number" min="0" max="4000" class="input-arena !py-2" />
                        </div>
                        <div class="w-20">
                            <label class="block font-mono text-[9px] uppercase tracking-[0.25em] text-dim mb-1.5">Color</label>
                            <input v-model="rankDraft.color" type="color" class="input-arena !p-1 h-[38px] cursor-pointer" />
                        </div>
                        <button @click="saveRank" class="btn-hero rounded-xl px-6 py-2.5 text-xs text-white">
                            <span class="relative z-10">{{ rankDraft.id ? 'Save' : 'Create' }}</span>
                        </button>
                    </div>
                    <p v-if="rankError" class="text-p2 text-xs mt-3">{{ rankError }}</p>
                </div>
            </section>
        </main>
    </div>
</template>
