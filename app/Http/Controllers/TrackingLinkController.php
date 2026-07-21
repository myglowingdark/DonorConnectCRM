<?php

namespace App\Http\Controllers;

use App\Enums\TrackingEventType;
use App\Http\Requests\Tracking\RecordTrackingEventRequest;
use App\Http\Requests\Tracking\StoreDonorTrackingLinkRequest;
use App\Models\Donor;
use App\Models\TrackingLink;
use App\Services\Messaging\MessageService;
use App\Services\Tracking\TrackingLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackingLinkController extends Controller
{
    public function redirect(string $code, TrackingLinkService $service): Response
    {
        $link = TrackingLink::query()->where('code', $code)->firstOrFail();
        $service->recordOpen($link, request()->fullUrl());

        return redirect()->away($service->redirectTarget($link));
    }

    public function recordEvent(RecordTrackingEventRequest $request, TrackingLinkService $service): JsonResponse
    {
        $data = $request->validated();
        $link = TrackingLink::query()->where('code', $data['dcr'])->firstOrFail();

        $type = TrackingEventType::from($data['event_type']);
        if ($type === TrackingEventType::Opened) {
            $service->recordOpen($link, $data['page_url'] ?? null);
        } else {
            $service->recordEvent(
                $link,
                $type,
                meta: ['user_agent' => $request->userAgent()],
                pageUrl: $data['page_url'] ?? null,
                projectId: isset($data['project_id']) ? (string) $data['project_id'] : null,
                amount: isset($data['amount']) ? (float) $data['amount'] : null,
            );
        }

        return $this->corsJson(['ok' => true]);
    }

    public function eventsOptions(): Response
    {
        return response('', 204, $this->corsHeaders());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function corsJson(array $payload): JsonResponse
    {
        return response()->json($payload, 200, $this->corsHeaders());
    }

    /**
     * @return array<string, string>
     */
    protected function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
        ];
    }

    public function store(
        StoreDonorTrackingLinkRequest $request,
        Donor $donor,
        TrackingLinkService $tracking,
        MessageService $messages,
    ): RedirectResponse|JsonResponse {
        $this->authorizeDonorAccess($request, $donor);

        $data = $request->validated();
        $channel = $data['channel'] ?? 'copy';
        $link = $tracking->resolveOrCreate($donor, $request->user(), $data['target_url'], $channel);

        if ($channel === 'copy') {
            $tracking->markSent($link, channel: 'copy');

            if ($request->wantsJson()) {
                return response()->json([
                    'code' => $link->code,
                    'url' => $link->publicUrl(),
                    'target_url' => $link->target_url,
                ]);
            }

            return back()->with('success', 'Tracking link ready to copy.')->with('tracking_link_url', $link->publicUrl());
        }

        $body = $data['body'] ?? "Please consider supporting this cause: {{donation_link}}";
        if (! str_contains($body, '{{donation_link}}') && ! str_contains($body, $link->publicUrl())) {
            $body = trim($body."\n\n".$link->publicUrl());
        }

        $message = $messages->sendToDonor($donor, $request->user(), [
            'channel' => $channel,
            'subject' => $data['subject'] ?? 'A cause you may want to support',
            'body' => $body,
            'message_template_id' => $data['message_template_id'] ?? null,
            'target_url' => $link->target_url,
            'tracking_link' => $link,
        ]);

        $tracking->markSent($link, $message->id, $channel);

        return back()->with('success', 'Tracking link sent via '.$channel.'.');
    }

    protected function authorizeDonorAccess(Request $request, Donor $donor): void
    {
        abort_unless($request->user(), 403);

        $user = $request->user();
        if ($user->isSuperAdmin() || $user->isOrganizationAdmin()) {
            return;
        }

        $assigned = $donor->activeAssignment?->volunteer_id === $user->id;
        abort_unless($assigned, 403);
    }
}
