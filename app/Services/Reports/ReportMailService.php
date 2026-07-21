<?php

namespace App\Services\Reports;

use App\Mail\OrgReportMail;
use App\Models\CommissionCycle;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\ReportRecipient;
use App\Models\ReportSchedule;
use App\Services\Messaging\MessageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class ReportMailService
{
    public function sendDueSchedules(?Carbon $now = null): int
    {
        $now = $now ?? now();
        $sent = 0;

        $schedules = ReportSchedule::query()
            ->where('is_active', true)
            ->with('organization')
            ->get();

        foreach ($schedules as $schedule) {
            if (! $this->isDue($schedule, $now)) {
                continue;
            }

            $count = $this->sendSchedule($schedule);
            if ($count > 0) {
                $schedule->update(['last_sent_at' => $now]);
                $sent += $count;
            }
        }

        return $sent;
    }

    public function isDue(ReportSchedule $schedule, Carbon $now): bool
    {
        $tz = $schedule->timezone ?: 'Asia/Kolkata';
        $local = $now->copy()->timezone($tz);
        $sendAt = $schedule->send_at ? substr((string) $schedule->send_at, 0, 5) : '09:00';
        $hour = (int) explode(':', $sendAt)[0];

        // Hourly scheduler: fire once during the configured local hour.
        if ((int) $local->format('H') !== $hour) {
            return false;
        }

        if ($schedule->last_sent_at) {
            $lastLocal = $schedule->last_sent_at->copy()->timezone($tz);
            if ($lastLocal->isSameDay($local)) {
                return false;
            }
        }

        $frequency = $schedule->frequency ?: (
            $schedule->type === 'monthly_commission_summary' ? 'monthly' : 'weekly'
        );

        if ($frequency === 'weekly') {
            return $local->dayOfWeek === Carbon::MONDAY;
        }

        $day = (int) ($schedule->day_of_month ?: 1);

        return (int) $local->format('j') === $day;
    }

    public function sendSchedule(ReportSchedule $schedule): int
    {
        $org = $schedule->organization ?? Organization::query()->find($schedule->organization_id);
        if (! $org) {
            return 0;
        }

        $recipients = ReportRecipient::query()
            ->forOrganization($org->id)
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return 0;
        }

        [$subject, $body] = match ($schedule->type) {
            'monthly_commission_summary' => $this->monthlyCommissionBody($org),
            default => $this->weeklyStatsBody($org),
        };

        $sent = 0;
        foreach ($recipients as $recipient) {
            $this->dispatchMail($org, $recipient->email, $subject, $body);
            $sent++;
        }

        return $sent;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function weeklyStatsBody(Organization $org): array
    {
        $from = now()->subDays(7)->startOfDay();
        $to = now()->endOfDay();

        $calls = DonorInteraction::query()
            ->forOrganization($org->id)
            ->whereBetween('contacted_at', [$from, $to])
            ->count();
        $conversions = DonorInteraction::query()
            ->forOrganization($org->id)
            ->whereBetween('contacted_at', [$from, $to])
            ->whereIn('outcome', ['pledged', 'donated'])
            ->count();
        $collection = Donation::query()
            ->forOrganization($org->id)
            ->whereBetween('donated_at', [$from, $to])
            ->sum('amount');
        $followUps = Donor::query()->forOrganization($org->id)->followUpDue()->count();

        $subject = "{$org->name} — weekly telecalling stats";
        $body = "Weekly stats for {$org->name} ({$from->toDateString()} to {$to->toDateString()}):\n\n"
            ."Calls: {$calls}\n"
            ."Conversions (pledged/donated): {$conversions}\n"
            .'Collection: ₹'.number_format((float) $collection, 2)."\n"
            ."Follow-ups due: {$followUps}\n";

        return [$subject, $body];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function monthlyCommissionBody(Organization $org): array
    {
        $cycle = CommissionCycle::query()
            ->forOrganization($org->id)
            ->orderByDesc('period')
            ->first();

        $subject = "{$org->name} — monthly commission summary";

        if (! $cycle) {
            return [$subject, "No commission cycles calculated yet for {$org->name}."];
        }

        $cycle->load('lineItems.volunteer');
        $lines = $cycle->lineItems->map(function ($item) {
            $name = $item->volunteer?->name ?? 'Volunteer #'.$item->volunteer_id;

            return "- {$name}: ₹".number_format((float) $item->final_payable, 2)
                .' (attributed ₹'.number_format((float) $item->attributed_donation_total, 2).')';
        })->implode("\n");

        $body = "Commission cycle {$cycle->period} ({$cycle->status->value}) for {$org->name}:\n\n"
            .'Verified donations: ₹'.number_format((float) $cycle->verified_donation_total, 2)."\n"
            .'Individual total: ₹'.number_format((float) $cycle->individual_total, 2)."\n"
            .'Shared pool: ₹'.number_format((float) $cycle->shared_pool, 2)."\n"
            .'Payable total: ₹'.number_format((float) $cycle->payable_total, 2)."\n\n"
            ."Line items:\n{$lines}\n";

        return [$subject, $body];
    }

    protected function dispatchMail(Organization $org, string $to, string $subject, string $body): void
    {
        $resolved = app(MessageService::class)->resolveOutboundMailer($org);

        if (blank($resolved['from_email'])) {
            throw new \RuntimeException('No From email is configured for outbound reports.');
        }

        $mailable = new OrgReportMail($subject, $body, $resolved['from_email'], $resolved['from_name']);

        if ($resolved['mailer']) {
            Mail::mailer($resolved['mailer'])->to($to)->send($mailable);
        } else {
            Mail::to($to)->send($mailable);
        }
    }
}
