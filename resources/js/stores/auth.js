import { defineStore } from 'pinia';
import axios from 'axios';
import { destroyEcho } from '@/echo';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        resolved: false,
        loading: false,
        errors: {},
    }),

    actions: {
        async fetchUser() {
            try {
                const { data } = await axios.get('/api/user/me');
                this.user = data;
            } catch {
                this.user = null;
            } finally {
                this.resolved = true;
            }
        },

        async login(credentials) {
            this.loading = true;
            this.errors = {};
            try {
                await axios.get('/sanctum/csrf-cookie');
                const { data } = await axios.post('/api/login', credentials);
                this.user = data;
                return true;
            } catch (error) {
                this.errors = error.response?.data?.errors ?? { email: ['Something went wrong.'] };
                return false;
            } finally {
                this.loading = false;
            }
        },

        async register(payload) {
            this.loading = true;
            this.errors = {};
            try {
                await axios.get('/sanctum/csrf-cookie');
                const { data } = await axios.post('/api/register', payload);
                this.user = data;
                return true;
            } catch (error) {
                this.errors = error.response?.data?.errors ?? { email: ['Something went wrong.'] };
                return false;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await axios.post('/api/logout');
            } finally {
                destroyEcho();
                this.user = null;
            }
        },

        refreshStats(partial) {
            if (this.user) Object.assign(this.user, partial);
        },
    },
});
