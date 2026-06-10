<script setup>
defineProps({
    game: { type: Object, required: true },
    myId: { type: Number, required: true },
});
</script>

<template>
    <div class="fixed inset-0 z-50 overlay-veil grid place-items-center vs-flash px-4">
        <div class="text-center w-full max-w-2xl">
            <div class="font-mono text-xs uppercase tracking-[0.6em] text-mint mb-8 animate-pulse">
                match found
            </div>

            <div class="flex items-center justify-center gap-5 sm:gap-10">
                <div class="vs-left flex-1 max-w-[220px]">
                    <div class="glass rounded-2xl p-5 sm:p-6 border-t-2" style="border-top-color: #6d7cff">
                        <div class="w-12 h-12 mx-auto rounded-full grid place-items-center font-display font-bold text-lg bg-p1/20 text-p1-bright shadow-glow-p1 mb-3">
                            {{ (game.players?.p1?.name ?? '?').charAt(0).toUpperCase() }}
                        </div>
                        <div class="font-display text-sm sm:text-base font-semibold truncate">{{ game.players?.p1?.name }}</div>
                        <div class="font-mono text-xs text-dim mt-1 tabular-nums">{{ game.players?.p1?.elo }} ELO</div>
                        <div v-if="game.players?.p1?.id === myId" class="font-mono text-[10px] uppercase tracking-widest text-mint mt-2">you</div>
                    </div>
                </div>

                <div class="vs-bolt font-display font-black text-3xl sm:text-5xl title-gradient shrink-0">VS</div>

                <div class="vs-right flex-1 max-w-[220px]">
                    <div class="glass rounded-2xl p-5 sm:p-6 border-t-2" style="border-top-color: #fb4d6d">
                        <div class="w-12 h-12 mx-auto rounded-full grid place-items-center font-display font-bold text-lg bg-p2/20 text-p2-bright shadow-glow-p2 mb-3">
                            {{ (game.players?.p2?.name ?? '?').charAt(0).toUpperCase() }}
                        </div>
                        <div class="font-display text-sm sm:text-base font-semibold truncate">{{ game.players?.p2?.name }}</div>
                        <div class="font-mono text-xs text-dim mt-1 tabular-nums">{{ game.players?.p2?.elo }} ELO</div>
                        <div v-if="game.players?.p2?.id === myId" class="font-mono text-[10px] uppercase tracking-widest text-mint mt-2">you</div>
                    </div>
                </div>
            </div>

            <div class="font-mono text-[11px] uppercase tracking-[0.4em] text-dim mt-10">
                entering the arena…
            </div>
        </div>
    </div>
</template>
