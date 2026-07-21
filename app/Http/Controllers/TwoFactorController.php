<?php

namespace App\Http\Controllers;

use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function setup(Request $request, TotpService $totp): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->two_factor_confirmed_at === null && blank($user->two_factor_secret)) {
            $user->forceFill(['two_factor_secret' => $totp->generateSecret()])->save();
            $user->refresh();
        }

        $secret = $user->two_factor_secret;
        $issuer = config('app.name', 'DonorConnect');
        $label = rawurlencode("{$issuer}:{$user->email}");
        $otpauthUrl = "otpauth://totp/{$label}?secret={$secret}&issuer=".rawurlencode($issuer);

        return Inertia::render('Profile/TwoFactor', [
            'enabled' => $user->two_factor_confirmed_at !== null,
            'secret' => $secret,
            'otpauthUrl' => $otpauthUrl,
            'ssoDeferred' => true,
        ]);
    }

    public function confirm(Request $request, TotpService $totp): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->two_factor_secret, 422);

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        abort_unless($totp->verify($user->two_factor_secret, $request->string('code')->toString()), 422, 'Invalid verification code.');

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return redirect()->route('dashboard')->with('success', 'Two-factor authentication enabled.');
    }

    public function disable(Request $request, TotpService $totp): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        abort_unless(
            $user->two_factor_secret && $totp->verify($user->two_factor_secret, $request->string('code')->toString()),
            422,
            'Invalid verification code.',
        );

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Two-factor authentication disabled.');
    }

    public function skip(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Clear any unfinished setup so 2FA stays fully optional.
        if ($user->two_factor_confirmed_at === null) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ])->save();
        }

        return redirect()->route('dashboard')->with('success', 'Two-factor setup skipped. You can enable it later from Profile.');
    }
}
