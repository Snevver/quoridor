<script setup>
import { watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useMatchmakingStore } from '@/stores/matchmaking';
import MatchFoundOverlay from '@/components/ui/MatchFoundOverlay.vue';

const auth = useAuthStore();
const matchmaking = useMatchmakingStore();
const router = useRouter();

// Match handling lives at the app root: no matter which page the player is
// on when a match lands, the splash shows once, then we enter the arena and
// the state is cleared — nothing stale survives to a later lobby visit.
watch(() => matchmaking.matchedGame, (game) => {
    if (!game?.slug) return;
    setTimeout(() => {
        matchmaking.clearMatch();
        router.push({ name: 'game', params: { slug: game.slug } });
    }, 2400);
});
</script>

<template>
    <div class="grain min-h-screen relative">
        <div class="starfield"></div>
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>

        <MatchFoundOverlay
            v-if="matchmaking.matchedGame && auth.user"
            :game="matchmaking.matchedGame"
            :my-id="auth.user.id"
        />

        <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
                <component :is="Component" class="relative z-10" />
            </transition>
        </router-view>
    </div>
</template>
