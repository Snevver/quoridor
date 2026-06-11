import { computed } from 'vue';
import { useGameStore } from '@/stores/game';

/**
 * Everyone plays bottom-up. p1's row 0 would render at the top, so p1's
 * view is flipped 180° — done by remapping grid coordinates, NOT by a CSS
 * rotation: a transform inside the tilted/perspective board breaks browser
 * pointer hit-testing (cells paint rotated but clicks land unrotated).
 *
 * Only display positions flip. Logical coordinates — props, store state,
 * server payloads, slot data attributes — stay in server space.
 */
export function useBoardView() {
    const game = useGameStore();

    const flipped = computed(() => game.myRole === 'p1');

    /** Grid track (1-based) of a cell coordinate (0-8). */
    function cellTrack(c) {
        return 2 * (flipped.value ? 8 - c : c) + 1;
    }

    /** Display coordinate of a cell (for pawn translate math). */
    function cellCoord(c) {
        return flipped.value ? 8 - c : c;
    }

    /** Grid track pair for a wall / groove anchor (0-7 along its span). */
    function anchorCoord(c) {
        return flipped.value ? 7 - c : c;
    }

    return { flipped, cellTrack, cellCoord, anchorCoord };
}
