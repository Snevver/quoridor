import { defineStore } from 'pinia';
import axios from 'axios';

export const useRanksStore = defineStore('ranks', {
    state: () => ({
        ranks: [], // ordered by min_elo ascending
    }),

    getters: {
        rankFor: (state) => (elo) => {
            let match = null;
            for (const rank of state.ranks) {
                if (elo >= rank.min_elo) match = rank;
            }
            return match;
        },
        /** The next rank above the given elo (for progress display). */
        nextRankFor: (state) => (elo) => state.ranks.find((r) => r.min_elo > elo) ?? null,
    },

    actions: {
        async fetch(force = false) {
            if (this.ranks.length && !force) return;
            try {
                this.ranks = (await axios.get('/api/ranks')).data;
            } catch {
                this.ranks = [];
            }
        },
    },
});
