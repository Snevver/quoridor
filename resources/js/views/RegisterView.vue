<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AuthShell from '@/components/AuthShell.vue';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ name: '', email: '', password: '' });

async function submit() {
    if (await auth.register(form)) {
        router.push({ name: 'lobby' });
    }
}

const firstError = () => {
    const errors = auth.errors;
    return errors.name?.[0] ?? errors.email?.[0] ?? errors.password?.[0] ?? null;
};
</script>

<template>
    <AuthShell title="Join the arena" subtitle="Forge your name. Start at 1200 ELO. Climb.">
        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="block font-mono text-[11px] uppercase tracking-[0.25em] text-dim mb-2">Battle name</label>
                <input v-model="form.name" type="text" required autofocus maxlength="30"
                       class="input-arena" placeholder="WallLord_9000" />
            </div>
            <div>
                <label class="block font-mono text-[11px] uppercase tracking-[0.25em] text-dim mb-2">Email</label>
                <input v-model="form.email" type="email" required
                       class="input-arena" placeholder="you@arena.gg" />
            </div>
            <div>
                <label class="block font-mono text-[11px] uppercase tracking-[0.25em] text-dim mb-2">Password</label>
                <input v-model="form.password" type="password" required minlength="8"
                       class="input-arena" placeholder="min. 8 characters" />
            </div>

            <p v-if="firstError()" class="text-p2 text-sm">{{ firstError() }}</p>

            <button type="submit" :disabled="auth.loading"
                    class="btn-hero w-full rounded-xl py-3.5 text-sm text-white disabled:opacity-60">
                <span class="relative z-10">{{ auth.loading ? 'Forging…' : 'Forge my legend' }}</span>
            </button>
        </form>

        <template #footer>
            Already enlisted?
            <router-link to="/login" class="text-p1-bright hover:text-ink transition-colors font-medium">
                Sign in
            </router-link>
        </template>
    </AuthShell>
</template>
