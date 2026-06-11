import { defineStore } from 'pinia';
import axios from 'axios';
import { getEcho } from '@/echo';
import { useAuthStore } from '@/stores/auth';
import { isWallValid } from '@/lib/quoridor';
import { sfx } from '@/lib/sound';

// Module-scoped: not state, just plumbing for cleanup.
let pollTimer = null;
let connectionHandler = null;

export const useGameStore = defineStore('game', {
    state: () => ({
        game: null,            // serialized game from the API
        boardState: null,      // live board_state (single source of truth)
        myRole: null,          // 'p1' | 'p2'
        version: 0,            // monotonic move counter; stale payloads are discarded
        legalMoves: [],
        pendingWall: null,     // { x, y, orientation, valid }
        lastMove: null,        // { player_id, move_type, payload }
        eloResult: null,       // { p1_before, p1_after, ... } once finished
        opponentOnline: false,
        submitting: false,
        shakeSignal: 0,        // bump to trigger the board shake animation
        channelName: null,
    }),

    getters: {
        oppRole: (s) => (s.myRole === 'p1' ? 'p2' : 'p1'),
        isMyTurn: (s) => s.boardState?.status === 'active' && s.boardState?.current_turn === s.myRole,
        isFinished: (s) => s.boardState?.status === 'finished',
        iWon: (s) => s.boardState?.winner === s.myRole,
        me: (s) => s.game?.players?.[s.myRole],
        opponent: (s) => s.game?.players?.[s.myRole === 'p1' ? 'p2' : 'p1'],
    },

    actions: {
        async joinGame(slug) {
            this.leaveGame(); // retry safety: drop any previous channel/poll first

            const { data } = await axios.get(`/api/games/${slug}`);
            this.game = data;
            this.boardState = data.board_state;
            this.myRole = data.my_role;
            this.version = data.version ?? 0;

            if (data.status === 'finished') {
                this.eloResult = data.elo;
            }

            this.subscribeToChannel(data.id);
            if (data.status === 'active') this.startPolling();
            await this.refreshLegalMoves();
        },

        subscribeToChannel(gameId) {
            this.channelName = `game.${gameId}`;
            const auth = useAuthStore();

            getEcho().join(this.channelName)
                .here((members) => {
                    this.opponentOnline = members.some((m) => m.id !== auth.user.id);
                })
                .joining((member) => {
                    if (member.id !== auth.user.id) this.opponentOnline = true;
                })
                .leaving((member) => {
                    if (member.id !== auth.user.id) this.opponentOnline = false;
                })
                .listen('GameStateUpdated', (payload) => this.onMoveReceived(payload));

            // If the socket drops and comes back, pusher resubscribes the
            // channels itself — but any moves made meanwhile were missed, so
            // resync immediately instead of waiting for the next poll tick.
            const connection = getEcho().connector.pusher.connection;
            connectionHandler = ({ previous, current }) => {
                if (current === 'connected' && previous !== 'initialized') {
                    this.pollState();
                }
            };
            connection.bind('state_change', connectionHandler);
        },

        /**
         * The websocket is the fast path; this poll is the safety net that
         * keeps a match playable when the socket silently dies. The version
         * guard makes applying a payload twice (or out of order) a no-op.
         */
        startPolling() {
            this.stopPolling();
            pollTimer = setInterval(() => this.pollState(), 5000);
        },

        stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        },

        async pollState() {
            if (!this.game || this.isFinished || this.submitting) return;
            try {
                const { data } = await axios.get(`/api/games/${this.game.slug}`);
                if (!this.game || this.isFinished) return; // finished while in flight
                const newer = (data.version ?? 0) > this.version;
                const justFinished = data.board_state?.status === 'finished';
                if (!newer && !justFinished) return;

                this.version = Math.max(this.version, data.version ?? 0);
                this.boardState = data.board_state;

                if (justFinished) {
                    this.eloResult = data.elo;
                    this.onGameFinished();
                    return;
                }
                await this.refreshLegalMoves();
            } catch {
                /* transient poll errors are fine */
            }
        },

        async onMoveReceived({ board_state, last_move, elo, version }) {
            // Stale or duplicate broadcast (e.g. our own move echoing back
            // after the optimistic update, or a race with the poll) — skip,
            // unless it carries the game's end.
            if ((version ?? Infinity) <= this.version && board_state.status !== 'finished') return;

            const auth = useAuthStore();
            const fromOpponent = last_move && last_move.player_id !== auth.user.id;

            this.version = Math.max(this.version, version ?? 0);
            this.boardState = board_state;
            this.lastMove = last_move;

            if (fromOpponent) {
                last_move.move_type === 'wall' ? sfx.wall() : sfx.move();
            }

            if (board_state.status === 'finished') {
                this.eloResult = elo ?? this.eloResult;
                this.onGameFinished();
                return;
            }

            await this.refreshLegalMoves();
        },

        async refreshLegalMoves() {
            if (!this.isMyTurn) {
                this.legalMoves = [];
                return;
            }
            try {
                const { data } = await axios.get(`/api/games/${this.game.slug}/legal-moves`);
                this.legalMoves = data.moves;
            } catch {
                this.legalMoves = [];
            }
        },

        async submitPawnMove(x, y) {
            if (!this.isMyTurn || this.submitting) return;
            if (!this.legalMoves.some((m) => m.x === x && m.y === y)) return;

            const snapshot = { board: JSON.parse(JSON.stringify(this.boardState)), version: this.version };

            // Optimistic: glide the pawn instantly.
            this.boardState.pawns[this.myRole] = { x, y };
            this.boardState.current_turn = this.oppRole;
            this.version++;
            this.legalMoves = [];
            sfx.move();

            await this.send({ move_type: 'pawn', to: [x, y] }, snapshot);
        },

        async submitWallPlacement(x, y, orientation) {
            if (!this.isMyTurn || this.submitting) return;
            if (!isWallValid(this.boardState, this.myRole, x, y, orientation)) {
                this.rejectMove();
                return;
            }

            const snapshot = { board: JSON.parse(JSON.stringify(this.boardState)), version: this.version };

            this.boardState.walls.push({ x, y, orientation });
            this.boardState.walls_left[this.myRole]--;
            this.boardState.current_turn = this.oppRole;
            this.version++;
            this.pendingWall = null;
            this.legalMoves = [];
            sfx.wall();

            await this.send({ move_type: 'wall', x, y, orientation }, snapshot);
        },

        /** POST the move; roll back the optimistic state if the server vetoes it. */
        async send(payload, snapshot) {
            this.submitting = true;
            try {
                const { data } = await axios.post(`/api/games/${this.game.slug}/move`, payload);
                this.boardState = data.board_state;
                this.version = Math.max(this.version, data.version ?? 0);

                if (data.status === 'finished') {
                    this.eloResult = data.elo;
                    this.onGameFinished();
                }
            } catch {
                this.boardState = snapshot.board;
                this.version = snapshot.version;
                this.rejectMove();
                await this.refreshLegalMoves();
            } finally {
                this.submitting = false;
            }
        },

        async resign() {
            if (!this.game || this.isFinished) return;
            const { data } = await axios.post(`/api/games/${this.game.slug}/resign`);
            this.boardState = data.board_state;
            this.eloResult = data.elo;
            this.onGameFinished();
        },

        onGameFinished() {
            this.stopPolling();
            this.legalMoves = [];
            this.pendingWall = null;
            this.iWon ? sfx.win() : sfx.lose();

            // Sync the header / lobby ELO without a refetch round-trip.
            const auth = useAuthStore();
            const mine = this.myRole === 'p1' ? this.eloResult?.p1_after : this.eloResult?.p2_after;
            if (mine) auth.refreshStats({ elo: mine });
        },

        rejectMove() {
            this.shakeSignal++;
            sfx.error();
        },

        previewWall(x, y, orientation) {
            if (!this.isMyTurn || this.isFinished) return;
            this.pendingWall = {
                x,
                y,
                orientation,
                valid: isWallValid(this.boardState, this.myRole, x, y, orientation),
            };
        },

        clearPreview() {
            this.pendingWall = null;
        },

        leaveGame() {
            this.stopPolling();
            if (connectionHandler) {
                getEcho().connector.pusher.connection.unbind('state_change', connectionHandler);
                connectionHandler = null;
            }
            if (this.channelName) {
                getEcho().leave(this.channelName);
            }
            this.reset();
        },

        reset() {
            this.$reset();
        },
    },
});
