# Quoridor Online

Real-time 2-player Quoridor with ELO matchmaking. Laravel 11 + Reverb + Vue 3 SPA.

## Stack

- **Backend** — Laravel 11, server-authoritative `GameService` (BFS path validation, jump rules, ELO K=32)
- **Real-time** — Laravel Reverb (presence channel per game, private channel per user for match alerts)
- **Frontend** — Vue 3 + Pinia + Vue Router SPA, Tailwind v3, WebAudio synth SFX, fully responsive
- **DB** — MySQL (`quoridor`); ranks + MMR thresholds live in the `ranks` table

## Run it (2 processes)

```bash
php artisan serve --port=8000                            # web + API
php artisan reverb:start --host=127.0.0.1 --port=8080    # websockets
```

Matchmaking runs synchronously on join/poll — no queue worker required.

Then open **http://localhost:8000** in two browser windows (one incognito), register two accounts, and hit *Find opponent* in both.

Frontend assets are pre-built (`npm run build`); use `npm run dev` for hot reload during development.

## Controls

- Click a glowing cell to move your pawn
- `W` switches to wall mode — hover the grooves between cells (green = legal), click to place
- `M` / `Esc` back to move mode

## Admin

Promote an account (toggles): `php artisan user:promote you@example.com`

Admins get the **⌖ Command Deck** (`/admin`): live arena stats, player management
(search, inline ELO edits, ban/unban, promote/demote), match inspection with a live
mini-board + move log, force-ending games (declare winner or void without ELO),
queue control, and the rank ladder editor (names, MMR thresholds, colors).
Admins still play, queue, and gain/lose ELO like everyone else.

## Verification

- `php artisan test` — 39 tests covering pawn moves, jumps, diagonal jumps, wall overlap, BFS sealing, full game + ELO settlement, admin powers, ranks
- `.\smoke-test.ps1` — two-session API flow: register → queue → match → moves → resign → leaderboard
- `.\verify-seal.ps1` — proves a wall that would fully seal a player off is rejected (422)
- `node test-realtime.mjs` — full WebSocket round trip: one player subscribes via Reverb, the other moves, event must arrive
