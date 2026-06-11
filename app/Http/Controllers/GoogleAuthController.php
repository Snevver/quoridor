<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Google sends the user back here. Known accounts (by google_id, or by
     * email for password accounts that get linked) are logged in directly;
     * brand-new users go pick a battle name first.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect('/login?error=google');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $google->getEmail())->first();

        if ($user) {
            if (!$user->google_id) {
                $user->forceFill(['google_id' => $google->getId()])->save();
            }

            if ($user->banned_at) {
                return redirect('/login?error=banned');
            }

            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();

            return redirect('/lobby');
        }

        $request->session()->put('google_pending', [
            'google_id' => $google->getId(),
            'email' => $google->getEmail(),
        ]);

        return redirect('/choose-name');
    }

    /** Does the session hold a Google signup waiting for a battle name? */
    public function pending(Request $request): JsonResponse
    {
        return response()->json([
            'pending' => $request->session()->has('google_pending'),
        ]);
    }

    /** Finish a Google signup: claim a battle name, create the account. */
    public function complete(Request $request): JsonResponse
    {
        $pending = $request->session()->get('google_pending');
        abort_unless($pending, 404, 'No Google sign-in in progress.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', 'unique:users,name'],
        ]);

        // The email may have registered normally while the name was being
        // chosen — link instead of tripping the unique constraint.
        $user = User::where('email', $pending['email'])->first();

        if ($user) {
            $user->forceFill(['google_id' => $pending['google_id']])->save();
        } else {
            $user = User::create([
                'name' => $data['name'],
                'email' => $pending['email'],
                'google_id' => $pending['google_id'],
                'password' => null,
            ]);
        }

        $request->session()->forget('google_pending');

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return response()->json($user->refresh(), 201);
    }
}
