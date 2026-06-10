# Proves a wall that would fully seal a player off is rejected by the server.
$base = 'http://127.0.0.1:8000'
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Web

function New-ArenaSession {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-RestMethod -Uri "$base/sanctum/csrf-cookie" -WebSession $s -Method Get | Out-Null
    return $s
}

function Invoke-Api($session, $method, $path, $body = $null) {
    $cookie = $session.Cookies.GetCookies($base) | Where-Object { $_.Name -eq 'XSRF-TOKEN' }
    $headers = @{
        'X-XSRF-TOKEN' = [System.Web.HttpUtility]::UrlDecode($cookie.Value)
        'Accept'       = 'application/json'
        'Referer'      = "$base/"
    }
    $params = @{ Uri = "$base$path"; Method = $method; WebSession = $session; Headers = $headers }
    if ($null -ne $body) { $params.Body = ($body | ConvertTo-Json -Compress); $params.ContentType = 'application/json' }
    return Invoke-RestMethod @params
}

$stamp = Get-Random -Maximum 99999
$a = New-ArenaSession
Invoke-Api $a Post '/api/register' @{ name = "SealA$stamp"; email = "sa$stamp@t.gg"; password = 'password123' } | Out-Null
$b = New-ArenaSession
Invoke-Api $b Post '/api/register' @{ name = "SealB$stamp"; email = "sb$stamp@t.gg"; password = 'password123' } | Out-Null

Invoke-Api $a Post '/api/matchmaking/join' @{} | Out-Null
Invoke-Api $b Post '/api/matchmaking/join' @{} | Out-Null
Start-Sleep -Seconds 1
$gameId = (Invoke-Api $a Get '/api/matchmaking/status').active_game_slug
$game = Invoke-Api $a Get "/api/games/$gameId"
if ($game.my_role -eq 'p1') { $p1 = $a; $p2 = $b } else { $p1 = $b; $p2 = $a }
Write-Output "game $gameId started"

# Build a wall of H walls across row 0 (columns 0-7), alternating turns.
Invoke-Api $p1 Post "/api/games/$gameId/move" @{ move_type = 'wall'; x = 0; y = 0; orientation = 'H' } | Out-Null
Invoke-Api $p2 Post "/api/games/$gameId/move" @{ move_type = 'wall'; x = 2; y = 0; orientation = 'H' } | Out-Null
Invoke-Api $p1 Post "/api/games/$gameId/move" @{ move_type = 'wall'; x = 4; y = 0; orientation = 'H' } | Out-Null
Invoke-Api $p2 Post "/api/games/$gameId/move" @{ move_type = 'wall'; x = 6; y = 0; orientation = 'H' } | Out-Null
Write-Output 'four walls placed across row 0 — only the column-8 gap remains'

# The final wall would seal p1 in completely -> must be rejected.
try {
    Invoke-Api $p1 Post "/api/games/$gameId/move" @{ move_type = 'wall'; x = 7; y = 0; orientation = 'V' } | Out-Null
    throw 'SEALING WALL WAS ACCEPTED — BUG'
} catch {
    if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 422) {
        Write-Output 'SEALING WALL REJECTED (422) — path rule enforced'
    } else { throw }
}

# A harmless wall elsewhere still works.
$after = Invoke-Api $p1 Post "/api/games/$gameId/move" @{ move_type = 'wall'; x = 4; y = 4; orientation = 'H' }
Write-Output "harmless wall accepted (total walls: $($after.board_state.walls.Count))"
Write-Output 'SEAL TEST PASSED'
