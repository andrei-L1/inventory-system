<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Check if user already exists
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Link account if not linked
                if (! $user->google_id) {
                    $user->update([
                        'google_id' => (string) $googleUser->getId(),
                        'google_token' => $googleUser->token,
                        'google_refresh_token' => $googleUser->refreshToken,
                    ]);
                }
            } else {
                // Create new user
                $staffRole = Role::where('name', 'staff')->first();

                $user = User::create([
                    'role_id' => $staffRole ? $staffRole->id : 2, // Fallback to staff
                    'username' => Str::slug($googleUser->getName().'-'.Str::random(4)),
                    'first_name' => $googleUser->user['given_name'] ?? $googleUser->getName(),
                    'last_name' => $googleUser->user['family_name'] ?? '',
                    'email' => $googleUser->getEmail(),
                    'google_id' => (string) $googleUser->getId(),
                    'google_token' => $googleUser->token,
                    'google_refresh_token' => $googleUser->refreshToken,
                    'password' => null, // No password for OAuth users unless they set one later
                ]);
            }

            Auth::login($user);

            return redirect()->intended('/dashboard');

        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', ['exception' => $e]);

            return redirect('/login')->with('error', 'Google sign-in is unavailable. Please try again later.');
        }
    }
}
