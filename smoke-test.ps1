# End-to-end smoke test: two players register, queue, match, and play.
$base = 'http://127.0.0.1:8000'
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Web

function New-ArenaSession {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-RestMethod -Uri "$base/sanctum/csrf-cookie" -WebSession $s -Method Get | Out-Null
    return $s
}

function Get-Xsrf($session) {
    $cookie = $session.Cookies.GetCookies($base) | Where-Object { $_.Name -eq 'XSRF-TOKEN' }
    return [System.Web.HttpUtility]::UrlDecode($cookie.Value)
}

function Invoke-Api($session, $method, $path, $body = $null) {
    $headers = @{
        'X-XSRF-TOKEN' = (Get-Xsrf $session)
        'Accept'       = 'application/json'
        'Referer'      = "$base/"
    }
    $params = @{
        Uri        = "$base$path"
        Method     = $method
        WebSession = $session
        Headers    = $headers
    }
    if ($null -ne $body) {
        $params.Body = ($body | ConvertTo-Json -Compress)
        $params.ContentType = 'application/json'
    }
    return Invoke-RestMethod @params
}

$stamp = Get-Random -Maximum 99999

# 1. Register two players
$a = New-ArenaSession
$userA = Invoke-Api $a Post '/api/register' @{ name = "SmokeA$stamp"; email = "a$stamp@test.gg"; password = 'password123' }
Write-Output "REGISTER A: id=$($userA.id) elo=$($userA.elo)"

$b = New-ArenaSession
$userB = Invoke-Api $b Post '/api/register' @{ name = "SmokeB$stamp"; email = "b$stamp@test.gg"; password = 'password123' }
Write-Output "REGISTER B: id=$($userB.id) elo=$($userB.elo)"

# 2. Both join the queue
Invoke-Api $a Post '/api/matchmaking/join' @{} | Out-Null
Invoke-Api $b Post '/api/matchmaking/join' @{} | Out-Null
Write-Output 'QUEUE: both joined'

# 3. Wait for matchmaking to pair them
$gameId = $null
foreach ($i in 1..15) {
    Start-Sleep -Seconds 1
    $status = Invoke-Api $a Get '/api/matchmaking/status'
    if ($status.active_game_slug) { $gameId = $status.active_game_slug; break }
}
if (-not $gameId) { throw 'MATCHMAKING FAILED: no game created after 15s' }
Write-Output "MATCHED: game slug $gameId"

# 4. Load the game from both sides
$gameA = Invoke-Api $a Get "/api/games/$gameId"
$gameB = Invoke-Api $b Get "/api/games/$gameId"
Write-Output "ROLES: A=$($gameA.my_role) B=$($gameB.my_role) turn=$($gameA.board_state.current_turn)"

# Identify who is p1
if ($gameA.my_role -eq 'p1') { $p1 = $a; $p2 = $b } else { $p1 = $b; $p2 = $a }

# 5. Legal moves for p1
$legal = Invoke-Api $p1 Get "/api/games/$gameId/legal-moves"
Write-Output ("LEGAL MOVES P1: " + (($legal.moves | ForEach-Object { "($($_.x),$($_.y))" }) -join ' '))

# 6. p1 moves pawn down, p2 places a wall
$after1 = Invoke-Api $p1 Post "/api/games/$gameId/move" @{ move_type = 'pawn'; to = @(4, 1) }
Write-Output "MOVE 1 (pawn): p1 at ($($after1.board_state.pawns.p1.x),$($after1.board_state.pawns.p1.y)) turn=$($after1.board_state.current_turn)"

$after2 = Invoke-Api $p2 Post "/api/games/$gameId/move" @{ move_type = 'wall'; x = 4; y = 4; orientation = 'H' }
Write-Output "MOVE 2 (wall): walls=$($after2.board_state.walls.Count) p2_left=$($after2.board_state.walls_left.p2) turn=$($after2.board_state.current_turn)"

# 7. Illegal move must be rejected with 422
try {
    Invoke-Api $p1 Post "/api/games/$gameId/move" @{ move_type = 'pawn'; to = @(0, 0) } | Out-Null
    throw 'ILLEGAL MOVE WAS ACCEPTED — BUG'
} catch {
    if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 422) {
        Write-Output 'ILLEGAL MOVE: correctly rejected (422)'
    } else { throw }
}

# 8. p1 resigns -> p2 wins, ELO settles
$final = Invoke-Api $p1 Post "/api/games/$gameId/resign"
Write-Output "RESIGN: status=$($final.status) winner=$($final.board_state.winner) p1: $($final.elo.p1_before)->$($final.elo.p1_after) p2: $($final.elo.p2_before)->$($final.elo.p2_after)"

# 9. Leaderboard sanity
$lb = Invoke-Api $a Get '/api/leaderboard'
Write-Output ("LEADERBOARD TOP: " + (($lb.players | Select-Object -First 3 | ForEach-Object { "$($_.name)=$($_.elo)" }) -join ', '))

Write-Output 'SMOKE TEST PASSED'
