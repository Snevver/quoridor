import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/lobby' },
        { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
        { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guest: true } },
        { path: '/lobby', name: 'lobby', component: () => import('@/views/LobbyView.vue'), meta: { auth: true } },
        { path: '/game/:id', name: 'game', component: () => import('@/views/GameView.vue'), meta: { auth: true } },
        { path: '/:pathMatch(.*)*', redirect: '/lobby' },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.resolved) {
        await auth.fetchUser();
    }

    if (to.meta.auth && !auth.user) return { name: 'login' };
    if (to.meta.guest && auth.user) return { name: 'lobby' };

    return true;
});

export default router;
