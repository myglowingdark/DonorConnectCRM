<?php

namespace App\Http\Controllers;

use App\Enums\MessageChannel;
use App\Http\Requests\Messaging\SendDonorMessageRequest;
use App\Http\Requests\Messaging\StoreMessageTemplateRequest;
use App\Http\Requests\Messaging\UpdateMessagingSettingsRequest;
use App\Http\Requests\Messaging\UpdateMessageTemplateRequest;
use App\Models\Donor;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Services\Messaging\MessageService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessagingController extends Controller
{
    public function settings(Request $request, MessageService $messages): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $settings = $messages->settingsFor($orgId);

        return Inertia::render('Messaging/Settings', [
            'settings' => [
                'email_enabled' => $settings->email_enabled,
                'whatsapp_enabled' => $settings->whatsapp_enabled,
                'sms_enabled' => $settings->sms_enabled,
                'smtp_host' => $settings->smtp_host,
                'smtp_port' => $settings->smtp_port,
                'smtp_encryption' => $settings->smtp_encryption,
                'smtp_username' => $settings->smtp_username,
                'has_smtp_password' => filled($settings->smtp_password),
                'from_email' => $settings->from_email,
                'from_name' => $settings->from_name,
                'whatsapp_provider' => $settings->whatsapp_provider,
                'whatsapp_from_number' => $settings->whatsapp_from_number,
                'has_whatsapp_api_key' => filled($settings->whatsapp_api_key),
                'sms_provider' => $settings->sms_provider,
                'sms_from_number' => $settings->sms_from_number,
                'has_sms_api_key' => filled($settings->sms_api_key),
            ],
        ]);
    }

    public function updateSettings(
        UpdateMessagingSettingsRequest $request,
        MessageService $messages,
    ): RedirectResponse {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $settings = $messages->settingsFor($orgId);
        $data = $request->validated();

        foreach (['smtp_password', 'whatsapp_api_key', 'sms_api_key'] as $secret) {
            if (! array_key_exists($secret, $data) || blank($data[$secret])) {
                unset($data[$secret]);
            }
        }

        $settings->fill($data);
        $settings->save();

        return back()->with('success', 'Messaging settings saved.');
    }

    public function templates(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $templates = MessageTemplate::query()
            ->forOrganization($orgId)
            ->orderBy('channel')
            ->orderBy('name')
            ->get();

        return Inertia::render('Messaging/Templates', [
            'templates' => $templates,
            'channels' => collect(MessageChannel::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    public function storeTemplate(StoreMessageTemplateRequest $request): RedirectResponse
    {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        MessageTemplate::create([
            ...$request->validated(),
            'organization_id' => $orgId,
        ]);

        return back()->with('success', 'Template created.');
    }

    public function updateTemplate(
        UpdateMessageTemplateRequest $request,
        MessageTemplate $template,
    ): RedirectResponse {
        $orgId = OrganizationContext::id();
        abort_unless($orgId && $template->organization_id === $orgId, 403);

        $template->update($request->validated());

        return back()->with('success', 'Template updated.');
    }

    public function destroyTemplate(Request $request, MessageTemplate $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId && $template->organization_id === $orgId, 403);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    public function send(
        SendDonorMessageRequest $request,
        Donor $donor,
        MessageService $messages,
    ): RedirectResponse {
        $this->authorize('view', $donor);

        $message = $messages->sendToDonor($donor, $request->user(), $request->validated());

        $label = $message->channel->label();
        $status = $message->status->label();

        return back()->with('success', "{$label} message {$status}.");
    }

    public function history(Request $request, Donor $donor): Response
    {
        $this->authorize('view', $donor);

        $messages = OutboundMessage::query()
            ->where('donor_id', $donor->id)
            ->with('sender')
            ->latest()
            ->paginate(20);

        return Inertia::render('Messaging/History', [
            'donor' => $donor->only(['id', 'full_name', 'email', 'phone']),
            'messages' => $messages,
        ]);
    }
}
