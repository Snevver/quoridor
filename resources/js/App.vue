<script setup>
</script>

<template>
    <div class="grain min-h-screen relative">
        <div class="starfield"></div>
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>

        <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
                <component :is="Component" class="relative z-10" />
            </transition>
        </router-view>
    </div>
</template>
