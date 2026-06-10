<template>
    <div class="flex flex-col items-center gap-5 py-16">
        <div class="relative w-14 h-14">
            <span class="absolute inset-0 rounded-full border-2 border-line"></span>
            <span class="absolute inset-0 rounded-full border-2 border-transparent border-t-p1 animate-spin"></span>
            <span class="absolute inset-2 rounded-full border-2 border-transparent border-b-p2 animate-spin"
                  style="animation-direction: reverse; animation-duration: 0.9s"></span>
        </div>
        <p class="font-mono text-[11px] uppercase tracking-[0.4em] text-dim">loading</p>
    </div>
</template>
