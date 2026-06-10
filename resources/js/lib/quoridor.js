/**
 * Client-side mirror of the server's Quoridor rules — used only for instant
 * wall-preview feedback (green/red). The server remains authoritative.
 *
 * Same coordinate system as the backend:
 *   H wall {x, y} blocks rows y<->y+1 across columns x and x+1.
 *   V wall {x, y} blocks columns x<->x+1 across rows y and y+1.
 */

const DIRS = [[1, 0], [-1, 0], [0, 1], [0, -1]];

export const SIZE = 9;

export function goalRow(player) {
    return player === 'p1' ? 8 : 0;
}

function inBounds(x, y) {
    return x >= 0 && x < SIZE && y >= 0 && y < SIZE;
}

export function isBlocked(walls, fx, fy, tx, ty) {
    for (const w of walls) {
        if (w.orientation === 'H') {
            if (fx !== tx) continue;
            if (w.y === Math.min(fy, ty) && (w.x === fx || w.x === fx - 1)) return true;
        } else {
            if (fy !== ty) continue;
            if (w.x === Math.min(fx, tx) && (w.y === fy || w.y === fy - 1)) return true;
        }
    }
    return false;
}

function wallsConflict(a, b) {
    if (a.x === b.x && a.y === b.y) return true;
    if (a.orientation !== b.orientation) return false;
    if (a.orientation === 'H') return a.y === b.y && Math.abs(a.x - b.x) === 1;
    return a.x === b.x && Math.abs(a.y - b.y) === 1;
}

export function bfsHasPath(boardState, player, walls = null) {
    walls = walls ?? boardState.walls;
    const start = boardState.pawns[player];
    const goal = goalRow(player);
    const visited = new Array(SIZE * SIZE).fill(false);
    visited[start.y * SIZE + start.x] = true;
    const queue = [[start.x, start.y]];

    while (queue.length) {
        const [x, y] = queue.shift();
        if (y === goal) return true;
        for (const [dx, dy] of DIRS) {
            const nx = x + dx;
            const ny = y + dy;
            if (!inBounds(nx, ny) || visited[ny * SIZE + nx]) continue;
            if (isBlocked(walls, x, y, nx, ny)) continue;
            visited[ny * SIZE + nx] = true;
            queue.push([nx, ny]);
        }
    }
    return false;
}

export function isWallValid(boardState, player, x, y, orientation) {
    if (x < 0 || x > 7 || y < 0 || y > 7) return false;
    if ((boardState.walls_left?.[player] ?? 0) < 1) return false;

    const candidate = { x, y, orientation };
    if (boardState.walls.some((w) => wallsConflict(w, candidate))) return false;

    const walls = [...boardState.walls, candidate];
    return bfsHasPath(boardState, 'p1', walls) && bfsHasPath(boardState, 'p2', walls);
}
