<?php

namespace App\Http\Controllers;

use App\Http\Requests\Platform\UpdatePlatformMessagingSettingsRequest;
use App\Http\Requests\SiteSettings\StoreDiscountCouponRequest;
use App\Http\Requests\SiteSettings\UpdateDiscountCouponRequest;
use App\Http\Requests\SiteSettings\UpdatePlatformBillingSettingsRequest;
use App\Http\Requests\SiteSettings\UpdatePlatformCommissionDefaultsRequest;
use App\Http\Requests\SiteSettings\UpdatePlanRequest;
use App\Http\Requests\SiteSettings\UpdateSiteModulesRequest;
use App\Models\DiscountCoupon;
use App\Models\Plan;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformCommissionSetting;
use App\Models\PlatformMessagingSetting;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\MetaEmbeddedSignupService;
use App\Services\Messaging\MetaWhatsAppClient;
use App\Services\Messaging\MetaWhatsAppCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingsController extends Controller
{
    public const TABS = ['modules', 'messaging', 'plans', 'billing', 'coupons', 'defaults'];

    public const FEATURE_KEYS = [
        'messaging',
        'reports',
        'razorpay',
        'api',
        'webhooks',
        'whatsapp',
        'internal_telecallers',
        'white_label',
        'capacity_booking',
    ];

    public function index(Request $request, MetaEmbeddedSignupService $signup): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'modules';
        }

        $messaging = PlatformMessagingSetting::current();
        $billing = PlatformBillingSetting::current();
        $commissionDefaults = PlatformCommissionSetting::current();

        return Inertia::render('SiteSettings/Index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'featureKeys' => self::FEATURE_KEYS,
            'messaging' => [
                'email_enabled' => $messaging->email_enabled,
                'whatsapp_enabled' => $messaging->whatsapp_enabled,
                'whatsapp_module_enabled' => $messaging->whatsapp_module_enabled,
                'smtp_host' => $messaging->smtp_host,
                'smtp_port' => $messaging->smtp_port,
                'smtp_encryption' => $messaging->smtp_encryption,
                'smtp_username' => $messaging->smtp_username,
                'has_smtp_password' => filled($messaging->smtp_password),
                'from_email' => $messaging->from_email,
                'from_name' => $messaging->from_name,
                'has_meta_access_token' => filled($messaging->meta_access_token),
                'meta_phone_number_id' => $messaging->meta_phone_number_id,
                'meta_waba_id' => $messaging->meta_waba_id,
                'meta_app_id' => $messaging->meta_app_id ?: config('services.meta.app_id'),
                'has_meta_app_secret' => filled($messaging->meta_app_secret) || filled(config('services.meta.app_secret')),
                'meta_api_version' => $messaging->meta_api_version ?: (config('services.meta.api_version') ?: 'v21.0'),
                'meta_embedded_signup_config_id' => $messaging->meta_embedded_signup_config_id
                    ?: config('services.meta.embedded_signup_config_id'),
            ],
            'webhookUrl' => url('/webhooks/meta/whatsapp'),
            'verifyTokenHint' => config('services.meta.webhook_verify_token') ?: 'Set META_WEBHOOK_VERIFY_TOKEN in .env (or use App ID)',
            'embeddedSignup' => $signup->publicConfig($messaging),
            'instructions' => [
                'Create a Meta app at developers.facebook.com and add the WhatsApp product.',
                'Under Facebook Login for Business, create an Embedded Signup configuration and copy the Config ID.',
                'Put META_APP_ID, META_APP_SECRET, and META_EMBEDDED_SIGNUP_CONFIG_ID in .env (or paste App ID / Config ID below). Keep App Secret in .env when possible.',
                'Add this site domain to Valid OAuth Redirect URIs and Allowed Domains in the Meta app.',
                'Turn on WhatsApp module (site-wide), then click Connect with Meta to link a WABA + phone number.',
                'In Meta App → WhatsApp → Configuration, set the webhook callback URL shown below and subscribe to messages.',
                'After connecting, create WhatsApp templates in Messaging → Templates, Submit to Meta, then Sync status until Approved.',
            ],
            'plans' => Plan::query()->orderBy('sort_order')->orderBy('id')->get(),
            'platformBilling' => [
                'enabled' => $billing->enabled,
                'razorpay_key_id' => $billing->razorpay_key_id,
                'has_razorpay_key_secret' => filled($billing->razorpay_key_secret),
                'has_razorpay_webhook_secret' => filled($billing->razorpay_webhook_secret),
            ],
            'coupons' => DiscountCoupon::query()->latest()->get()->map(fn (DiscountCoupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'type' => $c->type,
                'value' => $c->value,
                'plan_ids' => $c->plan_ids,
                'max_redemptions' => $c->max_redemptions,
                'redeemed_count' => $c->redeemed_count,
                'starts_at' => $c->starts_at?->toIso8601String(),
                'ends_at' => $c->ends_at?->toIso8601String(),
                'is_active' => $c->is_active,
            ]),
            'commissionDefaults' => [
                'individual_enabled' => $commissionDefaults->individual_enabled,
                'individual_default_percent' => (float) $commissionDefaults->individual_default_percent,
                'shared_enabled' => $commissionDefaults->shared_enabled,
                'shared_percent' => (float) $commissionDefaults->shared_percent,
                'shared_eligibility' => $commissionDefaults->shared_eligibility,
                'internal_individual_enabled' => $commissionDefaults->internal_individual_enabled,
                'internal_individual_default_percent' => (float) $commissionDefaults->internal_individual_default_percent,
                'internal_shared_enabled' => $commissionDefaults->internal_shared_enabled,
                'internal_shared_percent' => (float) $commissionDefaults->internal_shared_percent,
            ],
        ]);
    }

    public function updateModules(UpdateSiteModulesRequest $request): RedirectResponse
    {
        $settings = PlatformMessagingSetting::current();
        $settings->fill($request->validated());
        $settings->save();

        return redirect()
            ->route('site-settings.index', ['tab' => 'modules'])
            ->with('success', 'Site modules saved.');
    }

    public function updateMessaging(UpdatePlatformMessagingSettingsRequest $request): RedirectResponse
    {
        $settings = PlatformMessagingSetting::current();
        $data = $request->validated();

        foreach (['smtp_password', 'meta_access_token', 'meta_app_secret'] as $secret) {
            if (! array_key_exists($secret, $data) || blank($data[$secret])) {
                unset($data[$secret]);
            }
        }

        if (empty($data['meta_api_version'])) {
            $data['meta_api_version'] = $settings->meta_api_version ?: 'v21.0';
        }

        $settings->fill($data);
        $settings->save();

        return redirect()
            ->route('site-settings.index', ['tab' => 'messaging'])
            ->with('success', 'Platform messaging settings saved.');
    }

    public function connectWhatsApp(Request $request, MetaEmbeddedSignupService $signup): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $payload = $request->validate([
            'code' => ['required', 'string'],
            'phone_number_id' => ['required', 'string', 'max:64'],
            'waba_id' => ['required', 'string', 'max:64'],
        ]);

        $settings = $signup->connectPlatform($payload);
        $label = $settings->meta_phone_number_id;

        return redirect()
            ->route('site-settings.index', ['tab' => 'messaging'])
            ->with('success', "Connected Meta WhatsApp for the platform (Phone Number ID {$label}).");
    }

    public function testWhatsApp(Request $request, MetaWhatsAppClient $client): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $settings = PlatformMessagingSetting::current();

        if (! $settings->hasMetaCredentials()) {
            $gaps = implode(', ', $settings->metaCredentialGaps());

            return redirect()
                ->route('site-settings.index', ['tab' => 'messaging'])
                ->with('error', "Platform Meta WhatsApp credentials are incomplete: {$gaps}. Use Connect with Meta, or paste Phone Number ID + WABA ID (digits only) and an access token from Meta → WhatsApp → API Setup.");
        }

        $credentials = new MetaWhatsAppCredentials(
            accessToken: (string) $settings->meta_access_token,
            phoneNumberId: (string) $settings->meta_phone_number_id,
            wabaId: (string) $settings->meta_waba_id,
            apiVersion: $settings->meta_api_version ?: 'v21.0',
            appId: $settings->meta_app_id,
            appSecret: $settings->meta_app_secret,
            source: 'platform',
        );

        try {
            $result = $client->getPhoneNumber($credentials);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?: 'Meta WhatsApp connection test failed.';

            return redirect()
                ->route('site-settings.index', ['tab' => 'messaging'])
                ->with('error', $message);
        }

        $display = $result['display_phone_number'] ?? $result['id'] ?? 'connected';

        return redirect()
            ->route('site-settings.index', ['tab' => 'messaging'])
            ->with('success', "Platform Meta WhatsApp connected: {$display}");
    }

    public function testSmtp(Request $request, MessageService $messages): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $to = $request->user()->email;
        if (blank($to)) {
            return redirect()
                ->route('site-settings.index', ['tab' => 'messaging'])
                ->with('error', 'Your user account has no email address to receive the test message.');
        }

        try {
            $result = $messages->sendPlatformSmtpTestEmail($to);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'SMTP test failed.';

            return redirect()
                ->route('site-settings.index', ['tab' => 'messaging'])
                ->with('error', $message);
        }

        return redirect()
            ->route('site-settings.index', ['tab' => 'messaging'])
            ->with('success', "Test email sent to {$result['to']} via {$result['source']} (from {$result['from']}).");
    }

    public function updatePlan(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();
        $features = array_values(array_intersect($data['features'] ?? [], self::FEATURE_KEYS));
        $data['features'] = $features;

        foreach ([
            'seats_limit',
            'donors_limit',
            'campaigns_limit',
            'whatsapp_monthly_limit',
            'telecaller_hours_monthly',
            'imports_monthly_limit',
        ] as $limit) {
            if (array_key_exists($limit, $data) && $data[$limit] === '') {
                $data[$limit] = null;
            }
        }

        $plan->fill($data);
        $plan->save();

        return redirect()
            ->route('site-settings.index', ['tab' => 'plans'])
            ->with('success', "Plan “{$plan->name}” saved.");
    }

    public function updateBilling(UpdatePlatformBillingSettingsRequest $request): RedirectResponse
    {
        $settings = PlatformBillingSetting::current();
        $data = $request->validated();

        foreach (['razorpay_key_secret', 'razorpay_webhook_secret'] as $secret) {
            if (! array_key_exists($secret, $data) || blank($data[$secret])) {
                unset($data[$secret]);
            }
        }

        $settings->fill($data);
        $settings->save();

        return redirect()
            ->route('site-settings.index', ['tab' => 'billing'])
            ->with('success', 'Platform billing settings saved.');
    }

    public function storeCoupon(StoreDiscountCouponRequest $request): RedirectResponse
    {
        $data = $this->normalizeCouponPayload($request->validated());
        DiscountCoupon::query()->create($data);

        return redirect()
            ->route('site-settings.index', ['tab' => 'coupons'])
            ->with('success', 'Coupon created.');
    }

    public function updateCoupon(UpdateDiscountCouponRequest $request, DiscountCoupon $coupon): RedirectResponse
    {
        $data = $this->normalizeCouponPayload($request->validated());
        $coupon->fill($data);
        $coupon->save();

        return redirect()
            ->route('site-settings.index', ['tab' => 'coupons'])
            ->with('success', 'Coupon updated.');
    }

    public function destroyCoupon(Request $request, DiscountCoupon $coupon): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $coupon->delete();

        return redirect()
            ->route('site-settings.index', ['tab' => 'coupons'])
            ->with('success', 'Coupon deleted.');
    }

    public function updateCommissionDefaults(UpdatePlatformCommissionDefaultsRequest $request): RedirectResponse
    {
        $settings = PlatformCommissionSetting::current();
        $settings->fill($request->validated());
        $settings->save();

        return redirect()
            ->route('site-settings.index', ['tab' => 'defaults'])
            ->with('success', 'Default commissions saved.');
    }

    /** @param  array<string, mixed>  $data */
    private function normalizeCouponPayload(array $data): array
    {
        $data['code'] = strtoupper(trim($data['code']));

        if (array_key_exists('plan_ids', $data)) {
            $ids = $data['plan_ids'];
            if ($ids === null || $ids === [] || $ids === '') {
                $data['plan_ids'] = null;
            } else {
                $data['plan_ids'] = array_values(array_map('intval', (array) $ids));
            }
        }

        foreach (['max_redemptions', 'starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
