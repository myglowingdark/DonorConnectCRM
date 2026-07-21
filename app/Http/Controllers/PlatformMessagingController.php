<?php

namespace App\Http\Controllers;

use App\Http\Requests\Platform\UpdatePlatformMessagingSettingsRequest;
use App\Models\PlatformMessagingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformMessagingController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $settings = PlatformMessagingSetting::current();

        return Inertia::render('Platform/MessagingSettings', [
            'settings' => [
                'email_enabled' => $settings->email_enabled,
                'smtp_host' => $settings->smtp_host,
                'smtp_port' => $settings->smtp_port,
                'smtp_encryption' => $settings->smtp_encryption,
                'smtp_username' => $settings->smtp_username,
                'has_smtp_password' => filled($settings->smtp_password),
                'from_email' => $settings->from_email,
                'from_name' => $settings->from_name,
            ],
        ]);
    }

    public function update(UpdatePlatformMessagingSettingsRequest $request): RedirectResponse
    {
        $settings = PlatformMessagingSetting::current();
        $data = $request->validated();

        if (! array_key_exists('smtp_password', $data) || blank($data['smtp_password'])) {
            unset($data['smtp_password']);
        }

        $settings->fill($data);
        $settings->save();

        return back()->with('success', 'Platform SMTP settings saved.');
    }
}
