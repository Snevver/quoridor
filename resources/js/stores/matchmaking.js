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
        pollTimer: null,
        listening: false,
    }),

    actions: {
        /** Listen on the private user channel for GameStarted. */
        listenForMatches(onMatched) {
            const auth = useAuthStore();
            if (this.listening || !auth.user) return;
            this.listening = true;

            getEcho()
                .private(`App.Models.User.${auth.user.id}`)
                .listen('GameStarted', async ({ game_id }) => {
                    this.stopPolling();
                    this.inQueue = false;
                    sfx.match();

                    try {
                        const { data } = await axios.get(`/api/games/${game_id}`);
                        this.matchedGame = data;
                    } catch {
                        this.matchedGame = { id: game_id };
                    }

                    onMatched?.(this.matchedGame);
                });
        },

        stopListening() {
            const auth = useAuthStore();
            if (auth.user) {
                getEcho().leave(`App.Models.User.${auth.user.id}`);
            }
            this.listening = false;
        },

        async join() {
            await axios.post('/api/matchmaking/join');
            this.inQueue = true;
            this.waitingSeconds = 0;
            this.startPolling();
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

                    if (!data.in_queue && data.active_game_id && this.inQueue) {
                        // WebSocket missed the event (e.g. brief disconnect) — recover.
                        this.inQueue = false;
                        this.stopPolling();
                        const { data: game } = await axios.get(`/api/games/${data.active_game_id}`);
                        this.matchedGame = game;
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
