import { defineStore } from 'pinia';
import axios from 'axios';
import { getEcho } from '@/echo';
import { useAuthStore } from '@/stores/auth';
import { sfx } from '@/lib/sound';

export const useMatchmakingStore = defineStore('matchmaking', {
    state: () => ({
        inQueue: false,
        waitingSeconds: 0,
        matchedGame: null,   // game payload shown in the MATCH FOUND splash
        handledSlug: null,   // last game the splash was shown for — never replay it
        pollTimer: null,
        listening: false,
    }),

    actions: {
        /**
         * Single entry point for a found match. The WebSocket event and the
         * status poll can both report the same game (and in either order), so
         * the splash is deduped by slug.
         */
        async handleMatch(slug, onMatched) {
            this.stopPolling();
            this.inQueue = false;

            if (!slug || this.handledSlug === slug) return;
            this.handledSlug = slug;
            sfx.match();

            try {
                const { data } = await axios.get(`/api/games/${slug}`);
                this.matchedGame = data;
            } catch {
                this.matchedGame = { slug };
            }

            onMatched?.(this.matchedGame);
        },

        /** Listen on the private user channel for GameStarted. */
        listenForMatches(onMatched) {
            const auth = useAuthStore();
            if (this.listening || !auth.user) return;
            this.listening = true;

            getEcho()
                .private(`App.Models.User.${auth.user.id}`)
                .listen('GameStarted', ({ slug }) => this.handleMatch(slug, onMatched));
        },

        stopListening() {
            const auth = useAuthStore();
            if (auth.user) {
                getEcho().leave(`App.Models.User.${auth.user.id}`);
            }
            this.listening = false;
        },

        async join() {
            // Optimistic: joining can match instantly, in which case the
            // GameStarted event lands before this POST even resolves. The
            // match handler flips inQueue back off; respect that here so we
            // never poll for a queue we already left.
            this.inQueue = true;
            this.waitingSeconds = 0;

            try {
                await axios.post('/api/matchmaking/join');
            } catch (error) {
                this.inQueue = false;
                throw error;
            }

            if (this.inQueue) this.startPolling();
        },

        async leave() {
            this.stopPolling();
            this.inQueue = false;
            await axios.post('/api/matchmaking/leave');
        },

        /** Status poll doubles as the matchmaking heartbeat (widens ELO range server-side). */
        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(async () => {
                try {
                    const { data } = await axios.get('/api/matchmaking/status');
                    this.waitingSeconds = data.waiting_seconds;

                    if (!data.in_queue && data.active_game_slug && this.inQueue) {
                        // WebSocket missed the event (e.g. brief disconnect) — recover.
                        await this.handleMatch(data.active_game_slug);
                    } else if (!data.in_queue && this.inQueue) {
                        // Removed server-side (e.g. by an admin) — stop searching.
                        this.inQueue = false;
                        this.stopPolling();
                    }
                } catch {
                    /* transient poll errors are fine */
                }
            }, 4000);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        clearMatch() {
            this.matchedGame = null;
        },
    },
});
