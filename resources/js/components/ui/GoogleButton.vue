<script setup>
// Plain navigation: the OAuth dance is classic redirects, not XHR.
function signIn() {
    window.location.href = '/auth/google/redirect';
}
</script>

<template>
    <div>
        <div class="flex items-center gap-3 my-5">
            <span class="flex-1 h-px bg-line"></span>
            <span class="font-mono text-[10px] uppercase tracking-[0.3em] text-dim">or</span>
            <span class="flex-1 h-px bg-line"></span>
        </div>

        <button type="button" @click="signIn"
                class="btn-ghost w-full rounded-xl py-3 text-sm flex items-center justify-center gap-3">
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M23.5 12.27c0-.85-.08-1.66-.22-2.45H12v4.64h6.45a5.52 5.52 0 0 1-2.39 3.62v3h3.87c2.26-2.09 3.57-5.17 3.57-8.81z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.93-2.91l-3.87-3a7.24 7.24 0 0 1-10.78-3.8H1.29v3.1A12 12 0 0 0 12 24z"/>
                <path fill="#FBBC05" d="M5.28 14.29a7.2 7.2 0 0 1 0-4.58v-3.1H1.29a12 12 0 0 0 0 10.78l3.99-3.1z"/>
                <path fill="#EA4335" d="M12 4.77c1.76 0 3.35.6 4.6 1.8l3.43-3.43A11.97 11.97 0 0 0 1.29 6.61l3.99 3.1A7.24 7.24 0 0 1 12 4.77z"/>
            </svg>
            Continue with Google
        </button>
    </div>
</template>
