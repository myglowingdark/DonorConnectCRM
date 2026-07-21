<?php

namespace App\Http\Controllers;

use App\Http\Requests\Platform\UpdatePlatformMessagingSettingsRequest;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\MetaEmbeddedSignupService;
use App\Services\Messaging\MetaWhatsAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformMessagingController extends Controller
{
    public function edit(): RedirectResponse
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        return redirect()->route('site-settings.index', ['tab' => 'messaging']);
    }

    public function update(UpdatePlatformMessagingSettingsRequest $request, SiteSettingsController $siteSettings): RedirectResponse
    {
        return $siteSettings->updateMessaging($request);
    }

    public function connectWhatsApp(Request $request, SiteSettingsController $siteSettings, MetaEmbeddedSignupService $signup): RedirectResponse
    {
        return $siteSettings->connectWhatsApp($request, $signup);
    }

    public function testWhatsApp(Request $request, SiteSettingsController $siteSettings, MetaWhatsAppClient $client): RedirectResponse
    {
        return $siteSettings->testWhatsApp($request, $client);
    }

    public function testSmtp(Request $request, SiteSettingsController $siteSettings, MessageService $messages): RedirectResponse
    {
        return $siteSettings->testSmtp($request, $messages);
    }
}
