# Quoridor Online

Real-time 2-player Quoridor with ELO matchmaking. Laravel 11 + Reverb + Vue 3 SPA.

## Stack

- **Backend** — Laravel 11, server-authoritative `GameService` (BFS path validation, jump rules, ELO K=32)
- **Real-time** — Laravel Reverb (presence channel per game, private channel per user for match alerts)
- **Frontend** — Vue 3 + Pinia + Vue Router SPA, Tailwind v3, WebAudio synth SFX
- **DB** — MySQL (`quoridor`), queue on the database driver

## Run it (3 processes)

```bash
php artisan serve --port=8000                            # web + API
php artisan reverb:start --host=127.0.0.1 --port=8080    # websockets
php artisan queue:work                                   # matchmaking job
```

Then open **http://localhost:8000** in two browser windows (one incognito), register two accounts, and hit *Find opponent* in both.

Frontend assets are pre-built (`npm run build`); use `npm run dev` for hot reload during development.

## Controls

- Click a glowing cell to move your pawn
- `W` switches to wall mode — hover the grooves between cells (green = legal), click to place
- `M` / `Esc` back to move mode

## Verification

- `php artisan test` — 24 tests covering pawn moves, jumps, diagonal jumps, wall overlap, BFS sealing, full game + ELO settlement
- `.\smoke-test.ps1` — two-session API flow: register → queue → match → moves → resign → leaderboard
- `node test-realtime.mjs` — full WebSocket round trip: one player subscribes via Reverb, the other moves, event must arrive
