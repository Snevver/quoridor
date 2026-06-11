<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import AuthShell from '@/components/AuthShell.vue';

// Final step of a first-time Google sign-in: the account exists only once
// a unique battle name is claimed (the pending Google identity lives in
// the server session).
const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const error = ref('');
const loading = ref(false);

onMounted(async () => {
    try {
        const { data } = await axios.get('/auth/google/pending');
        if (!data.pending) router.replace({ name: 'login' });
    } catch {
        router.replace({ name: 'login' });
    }
});

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await axios.get('/sanctum/csrf-cookie');
        const { data } = await axios.post('/auth/google/complete', { name: name.value });
        auth.user = data;
        router.push({ name: 'lobby' });
    } catch (err) {
        error.value = err.response?.data?.errors?.name?.[0]
            ?? err.response?.data?.message
            ?? 'Something went wrong — try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AuthShell title="One last step" subtitle="Claim the battle name the arena will know you by.">
        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="block font-mono text-[11px] uppercase tracking-[0.25em] text-dim mb-2">Battle name</label>
                <input v-model="name" type="text" required autofocus maxlength="30"
                       class="input-arena" placeholder="WallLord_9000" />
            </div>

            <p v-if="error" class="text-p2 text-sm">{{ error }}</p>

            <button type="submit" :disabled="loading || !name"
                    class="btn-hero w-full rounded-xl py-3.5 text-sm text-white disabled:opacity-60">
                <span class="relative z-10">{{ loading ? 'Forging…' : 'Enter the arena' }}</span>
            </button>
        </form>

        <template #footer>
            Changed your mind?
            <router-link to="/login" class="text-p1-bright hover:text-ink transition-colors font-medium">
                Back to sign in
            </router-link>
        </template>
    </AuthShell>
</template>
