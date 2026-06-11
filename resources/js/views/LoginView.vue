<script setup>
import { computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AuthShell from '@/components/AuthShell.vue';
import GoogleButton from '@/components/ui/GoogleButton.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '' });

// Google callback failures land back here with ?error=…
const oauthError = computed(() => ({
    banned: 'This account has been suspended.',
    google: 'Google sign-in failed — please try again.',
}[route.query.error] ?? null));

async function submit() {
    if (await auth.login(form)) {
        router.push({ name: 'lobby' });
    }
}
</script>

<template>
    <AuthShell title="Welcome back" subtitle="Sign in and claim your next victory.">
        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="block font-mono text-[11px] uppercase tracking-[0.25em] text-dim mb-2">Email</label>
                <input v-model="form.email" type="email" required autofocus
                       class="input-arena" placeholder="you@arena.gg" />
            </div>
            <div>
                <label class="block font-mono text-[11px] uppercase tracking-[0.25em] text-dim mb-2">Password</label>
                <input v-model="form.password" type="password" required
                       class="input-arena" placeholder="••••••••" />
            </div>

            <p v-if="auth.errors.email" class="text-p2 text-sm">{{ auth.errors.email[0] }}</p>
            <p v-else-if="oauthError" class="text-p2 text-sm">{{ oauthError }}</p>

            <button type="submit" :disabled="auth.loading"
                    class="btn-hero w-full rounded-xl py-3.5 text-sm text-white disabled:opacity-60">
                <span class="relative z-10">{{ auth.loading ? 'Entering…' : 'Enter the arena' }}</span>
            </button>
        </form>

        <GoogleButton />

        <template #footer>
            New challenger?
            <router-link to="/register" class="text-p1-bright hover:text-ink transition-colors font-medium">
                Create an account
            </router-link>
        </template>
    </AuthShell>
</template>
