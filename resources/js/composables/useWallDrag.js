import { reactive } from 'vue';
import { useGameStore } from '@/stores/game';

/**
 * Walls are placed by dragging a chip from your wall stack onto the board.
 * While dragging, a ghost wall follows the pointer and the nearest groove
 * within SNAP_RADIUS shows the usual green/red preview (via the store's
 * pendingWall). Releasing on a valid preview places the wall; releasing
 * anywhere else cancels.
 *
 * Snapping works on slot center points measured with getBoundingClientRect,
 * which returns visual-space boxes — so the board tilt and the 180° flip
 * for p1 need no special handling.
 */

const SNAP_RADIUS = 34; // px from a groove center before the wall snaps

// Module-level singleton: one drag can exist at a time, shared by every
// source (side-panel stack, mobile tray) and the ghost renderer.
const drag = reactive({
    active: false,
    x: 0,
    y: 0,
    orientation: 'H', // orientation of the ghost while not snapped
});

let slotRects = [];

function cacheSlotRects() {
    slotRects = [...document.querySelectorAll('.qslot')].map((el) => {
        const rect = el.getBoundingClientRect();
        return {
            cx: Number(el.dataset.cx),
            cy: Number(el.dataset.cy),
            orientation: el.dataset.orientation,
            x: rect.left + rect.width / 2,
            y: rect.top + rect.height / 2,
        };
    });
}

function nearestSlot(px, py) {
    let best = null;
    let bestDist = SNAP_RADIUS ** 2;
    for (const slot of slotRects) {
        const dist = (slot.x - px) ** 2 + (slot.y - py) ** 2;
        if (dist < bestDist) {
            bestDist = dist;
            best = slot;
        }
    }
    return best;
}

export function useWallDrag() {
    const game = useGameStore();

    function startDrag(event) {
        if (drag.active) return; // a second finger must not hijack the drag
        if (!game.isMyTurn || game.isFinished || game.submitting) return;
        if ((game.boardState?.walls_left?.[game.myRole] ?? 0) < 1) return;

        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        cacheSlotRects();
        drag.active = true;
        onDragMove(event);
    }

    function onDragMove(event) {
        if (!drag.active) return;

        drag.x = event.clientX;
        drag.y = event.clientY;

        const slot = nearestSlot(event.clientX, event.clientY);
        if (!slot) {
            game.clearPreview();
            return;
        }

        // A 2-cell wall is anchored at the snapped segment, clamped to the
        // board — same rule WallSlot's click flow used.
        const x = slot.orientation === 'H' ? Math.min(slot.cx, 7) : slot.cx;
        const y = slot.orientation === 'H' ? slot.cy : Math.min(slot.cy, 7);

        drag.orientation = slot.orientation;
        game.previewWall(x, y, slot.orientation);
    }

    function endDrag() {
        if (!drag.active) return;
        drag.active = false;

        const pending = game.pendingWall;
        if (pending?.valid) {
            game.submitWallPlacement(pending.x, pending.y, pending.orientation);
        } else if (pending) {
            game.rejectMove(); // dropped on a blocked groove — shake, keep the wall
            game.clearPreview();
        } else {
            game.clearPreview(); // dropped off-board — quiet cancel
        }
    }

    function cancelDrag() {
        if (!drag.active) return;
        drag.active = false;
        game.clearPreview();
    }

    return { drag, startDrag, onDragMove, endDrag, cancelDrag };
}
