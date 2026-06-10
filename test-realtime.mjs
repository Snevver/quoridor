// Realtime verification: player B listens on the presence game channel via
// Reverb while player A posts a move through the API. Passes when B's socket
// receives GameStateUpdated with the new pawn position.
import WebSocket from 'ws';

const BASE = 'http://127.0.0.1:8000';
const WS_URL = 'ws://127.0.0.1:8080/app/quoridor-key?protocol=7&client=js&version=8.5.0';

class Client {
    constructor(name) {
        this.name = name;
        this.cookies = new Map();
    }

    cookieHeader() {
        return [...this.cookies.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
    }

    absorb(response) {
        for (const line of response.headers.getSetCookie?.() ?? []) {
            const [pair] = line.split(';');
            const eq = pair.indexOf('=');
            this.cookies.set(pair.slice(0, eq), pair.slice(eq + 1));
        }
    }

    xsrf() {
        return decodeURIComponent(this.cookies.get('XSRF-TOKEN') ?? '');
    }

    async request(method, path, body = null) {
        const response = await fetch(`${BASE}${path}`, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Referer': `${BASE}/`,
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': this.xsrf(),
                'Cookie': this.cookieHeader(),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        this.absorb(response);
        if (!response.ok) throw new Error(`${this.name} ${method} ${path} -> ${response.status}`);
        const text = await response.text();
        return text ? JSON.parse(text) : null;
    }

    async boot(suffix) {
        await this.request('GET', '/sanctum/csrf-cookie');
        this.user = await this.request('POST', '/api/register', {
            name: `Rt${this.name}${suffix}`,
            email: `rt${this.name}${suffix}@test.gg`,
            password: 'password123',
        });
    }
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const a = new Client('A');
const b = new Client('B');
const suffix = Math.floor(Math.random() * 99999);

await a.boot(suffix);
await b.boot(suffix);
console.log(`registered: A=${a.user.id} B=${b.user.id}`);

await a.request('POST', '/api/matchmaking/join');
await b.request('POST', '/api/matchmaking/join');

let gameSlug = null;
for (let i = 0; i < 15 && !gameSlug; i++) {
    await wait(1000);
    gameSlug = (await a.request('GET', '/api/matchmaking/status')).active_game_slug;
}
if (!gameSlug) throw new Error('matchmaking failed');
console.log(`matched: game ${gameSlug}`);

const game = await a.request('GET', `/api/games/${gameSlug}`);
const gameId = game.id; // presence channel stays keyed by numeric id
const mover = game.my_role === 'p1' ? a : b;
const watcher = mover === a ? b : a;

// --- watcher connects to Reverb and subscribes to the presence channel ----
const ws = new WebSocket(WS_URL);
const received = new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('TIMEOUT: no GameStateUpdated within 10s')), 10000);

    ws.on('message', async (raw) => {
        const msg = JSON.parse(raw.toString());

        if (msg.event === 'pusher:connection_established') {
            const { socket_id } = JSON.parse(msg.data);
            console.log(`watcher socket: ${socket_id}`);
            const auth = await watcher.request('POST', '/broadcasting/auth', {
                socket_id,
                channel_name: `presence-game.${gameId}`,
            });
            ws.send(JSON.stringify({
                event: 'pusher:subscribe',
                data: { channel: `presence-game.${gameId}`, auth: auth.auth, channel_data: auth.channel_data },
            }));
        }

        if (msg.event === 'pusher_internal:subscription_succeeded') {
            console.log('watcher subscribed to presence channel');
            // now the mover plays
            await mover.request('POST', `/api/games/${gameId}/move`, { move_type: 'pawn', to: [4, game.my_role === 'p1' ? 1 : 7] });
            console.log('mover posted pawn move');
        }

        if (msg.event === 'App\\Events\\GameStateUpdated') {
            clearTimeout(timer);
            const data = JSON.parse(msg.data);
            resolve(data);
        }
    });

    ws.on('error', (err) => { clearTimeout(timer); reject(err); });
});

const payload = await received;
console.log(`event received: pawn ${game.my_role} at (${payload.board_state.pawns[game.my_role].x},${payload.board_state.pawns[game.my_role].y}), turn=${payload.board_state.current_turn}`);
console.log('REALTIME TEST PASSED');
ws.close();
process.exit(0);
