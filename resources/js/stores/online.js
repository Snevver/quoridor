import { defineStore } from 'pinia';
import { ref } from 'vue';
import { getEcho } from '@/echo';

export const useOnlineStore = defineStore('online', () => {
    const count = ref(0);

    function join() {
        getEcho().join('online')
            .here(members => { count.value = members.length; })
            .joining(() => { count.value++; })
            .leaving(() => { count.value--; });
    }

    function leave() {
        try { getEcho().leave('online'); } catch {}
        count.value = 0;
    }

    return { count, join, leave };
});
