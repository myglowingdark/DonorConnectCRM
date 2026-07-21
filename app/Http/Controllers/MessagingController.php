<?php

namespace App\Http\Controllers;

use App\Enums\MessageChannel;
use App\Enums\MetaTemplateStatus;
use App\Http\Requests\Messaging\SendDonorMessageRequest;
use App\Http\Requests\Messaging\StoreMessageTemplateRequest;
use App\Http\Requests\Messaging\UpdateMessagingSettingsRequest;
use App\Http\Requests\Messaging\UpdateMessageTemplateRequest;
use App\Models\Donor;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\MetaEmbeddedSignupService;
use App\Services\SaaS\EntitlementService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MessagingController extends Controller
{
    public function settings(Request $request, MessageService $messages, EntitlementService $entitlements, MetaEmbeddedSignupService $signup): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $settings = $messages->settingsFor($orgId);
        $hasWhatsAppFeature = $entitlements->hasFeature($organization, 'whatsapp');
        $canManageWhatsAppConfig = $request->user()->isSuperAdmin() || $request->user()->isOrganizationAdmin();

        $thankYouTemplates = MessageTemplate::query()
            ->forOrganization($orgId)
            ->where('channel', MessageChannel::WhatsApp)
            ->where('meta_status', MetaTemplateStatus::Approved)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

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
                'whatsapp_provider' => $settings->whatsapp_provider ?: 'meta',
                'whatsapp_from_number' => $settings->whatsapp_from_number,
                'has_whatsapp_api_key' => filled($settings->whatsapp_api_key),
                'whatsapp_use_platform' => $settings->whatsapp_use_platform,
                'whatsapp_phone_number_id' => $settings->whatsapp_phone_number_id,
                'whatsapp_waba_id' => $settings->whatsapp_waba_id,
                'sms_provider' => $settings->sms_provider,
                'sms_from_number' => $settings->sms_from_number,
                'has_sms_api_key' => filled($settings->sms_api_key),
                'bulk_whatsapp_enabled' => $settings->bulk_whatsapp_enabled,
                'auto_donation_thankyou_enabled' => $settings->auto_donation_thankyou_enabled,
                'auto_donation_thankyou_template_id' => $settings->auto_donation_thankyou_template_id,
            ],
            'hasWhatsAppFeature' => $hasWhatsAppFeature,
            'canManageWhatsAppConfig' => $canManageWhatsAppConfig,
            'thankYouTemplates' => $thankYouTemplates,
            'embeddedSignup' => $signup->publicConfig(),
            'whatsappInstructions' => $canManageWhatsAppConfig ? [
                'Ask Super Admin to enable the WhatsApp module (Platform messaging → Site-wide modules).',
                'Prefer Connect with Meta below (same flow as Wati) to link your WhatsApp Business Account and phone number.',
                'Or enable “Use platform Meta WhatsApp credentials” if your Super Admin already connected a shared number.',
                'Go to Messaging → Templates, create a WhatsApp template, then Submit to Meta for approval.',
                'Click Sync status until the template shows Approved — only then can staff send WhatsApp to donors.',
                'Keep donor phone numbers in E.164 format (e.g. +9198xxxxxxxx).',
            ] : [],
        ]);
    }

    public function updateSettings(
        UpdateMessagingSettingsRequest $request,
        MessageService $messages,
        EntitlementService $entitlements,
    ): RedirectResponse {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $settings = $messages->settingsFor($orgId);
        $data = $request->validated();
        $user = $request->user();

        $metaKeys = [
            'whatsapp_use_platform',
            'whatsapp_provider',
            'whatsapp_api_key',
            'whatsapp_from_number',
            'whatsapp_phone_number_id',
            'whatsapp_waba_id',
            'bulk_whatsapp_enabled',
            'auto_donation_thankyou_enabled',
            'auto_donation_thankyou_template_id',
        ];

        $touchesMeta = collect($metaKeys)->contains(fn ($key) => array_key_exists($key, $data));

        if ($touchesMeta && ! ($user->isSuperAdmin() || $user->isOrganizationAdmin())) {
            abort(403);
        }

        if (($data['whatsapp_enabled'] ?? false) || $touchesMeta) {
            if (! $entitlements->hasFeature($organization, 'whatsapp')) {
                throw ValidationException::withMessages([
                    'whatsapp' => 'The WhatsApp module is not enabled for this organization.',
                ]);
            }
        }

        foreach (['smtp_password', 'whatsapp_api_key', 'sms_api_key'] as $secret) {
            if (! array_key_exists($secret, $data) || blank($data[$secret])) {
                unset($data[$secret]);
            }
        }

        if (! empty($data['whatsapp_provider'])) {
            $data['whatsapp_provider'] = 'meta';
        }

        $settings->fill($data);
        $settings->save();

        return back()->with('success', 'Messaging settings saved.');
    }

    public function testWhatsAppConnection(
        Request $request,
        MessageService $messages,
    ): RedirectResponse {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->isOrganizationAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $result = $messages->testConnection($organization);

        $display = $result['display_phone_number'] ?? $result['id'] ?? 'connected';

        return back()->with('success', "Meta WhatsApp connected: {$display}");
    }

    public function connectWhatsApp(
        Request $request,
        MetaEmbeddedSignupService $signup,
        EntitlementService $entitlements,
    ): RedirectResponse {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->isOrganizationAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $entitlements->assertFeature($organization, 'whatsapp');

        $payload = $request->validate([
            'code' => ['required', 'string'],
            'phone_number_id' => ['required', 'string', 'max:64'],
            'waba_id' => ['required', 'string', 'max:64'],
        ]);

        $settings = $signup->connectOrganization($organization, $payload);
        $label = $settings->whatsapp_from_number ?: $settings->whatsapp_phone_number_id;

        return back()->with('success', "Connected Meta WhatsApp for this organization ({$label}).");
    }

    public function templates(Request $request, EntitlementService $entitlements): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $canManageWhatsApp = $request->user()->isSuperAdmin() || $request->user()->isOrganizationAdmin();

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
            'hasWhatsAppFeature' => $entitlements->hasFeature($organization, 'whatsapp'),
            'canManageWhatsAppTemplates' => $canManageWhatsApp && $entitlements->hasFeature($organization, 'whatsapp'),
            'metaCategories' => [
                ['value' => 'UTILITY', 'label' => 'Utility'],
                ['value' => 'MARKETING', 'label' => 'Marketing'],
                ['value' => 'AUTHENTICATION', 'label' => 'Authentication'],
            ],
            'metaStatuses' => collect(MetaTemplateStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function storeTemplate(
        StoreMessageTemplateRequest $request,
        EntitlementService $entitlements,
    ): RedirectResponse {
        $orgId = OrganizationContext::id();
        abort_unless($orgId, 403);

        $data = $request->validated();
        $channel = MessageChannel::from($data['channel']);

        if ($channel === MessageChannel::WhatsApp) {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->isOrganizationAdmin(), 403);
            $organization = Organization::query()->findOrFail($orgId);
            $entitlements->assertFeature($organization, 'whatsapp');

            $data['meta_status'] = MetaTemplateStatus::Draft->value;
            $data['meta_name'] = $data['meta_name'] ?? null;
            $data['meta_language'] = $data['meta_language'] ?? 'en';
            $data['meta_category'] = strtoupper($data['meta_category'] ?? 'UTILITY');
            $data['variable_schema'] = $data['variable_schema'] ?? null;
        }

        MessageTemplate::create([
            ...$data,
            'organization_id' => $orgId,
        ]);

        return back()->with('success', 'Template created.');
    }

    public function updateTemplate(
        UpdateMessageTemplateRequest $request,
        MessageTemplate $template,
        EntitlementService $entitlements,
    ): RedirectResponse {
        $orgId = OrganizationContext::id();
        abort_unless($orgId && $template->organization_id === $orgId, 403);

        $data = $request->validated();
        $channel = MessageChannel::from($data['channel']);

        if ($channel === MessageChannel::WhatsApp || $template->isWhatsApp()) {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->isOrganizationAdmin(), 403);
            $organization = Organization::query()->findOrFail($orgId);
            $entitlements->assertFeature($organization, 'whatsapp');
        }

        if ($channel === MessageChannel::WhatsApp) {
            $data['meta_language'] = $data['meta_language'] ?? $template->meta_language ?? 'en';
            $data['meta_category'] = strtoupper($data['meta_category'] ?? $template->meta_category ?? 'UTILITY');
            if (! isset($data['meta_status'])) {
                $data['meta_status'] = $template->meta_status?->value ?? MetaTemplateStatus::Draft->value;
            }
        }

        $template->update($data);

        return back()->with('success', 'Template updated.');
    }

    public function destroyTemplate(Request $request, MessageTemplate $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId && $template->organization_id === $orgId, 403);

        if ($template->isWhatsApp()) {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->isOrganizationAdmin(), 403);
        }

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    public function submitTemplate(
        Request $request,
        MessageTemplate $template,
        MessageService $messages,
    ): RedirectResponse {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->isOrganizationAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId && $template->organization_id === $orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $messages->submitTemplateToMeta($template, $organization, $request->user());

        return back()->with('success', 'Template submitted to Meta for approval.');
    }

    public function syncTemplate(
        Request $request,
        MessageTemplate $template,
        MessageService $messages,
    ): RedirectResponse {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->isOrganizationAdmin(), 403);
        $orgId = OrganizationContext::id();
        abort_unless($orgId && $template->organization_id === $orgId, 403);

        $organization = Organization::query()->findOrFail($orgId);
        $updated = $messages->syncTemplateFromMeta($template, $organization);

        return back()->with('success', 'Template status synced: '.$updated->meta_status?->label());
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
