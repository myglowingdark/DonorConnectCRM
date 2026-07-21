<?php

namespace App\Console\Commands;

use App\Services\Reports\ReportMailService;
use Illuminate\Console\Command;

class SendDueReportsCommand extends Command
{
    protected $signature = 'reports:send-due';

    protected $description = 'Send due weekly/monthly organization report emails';

    public function handle(ReportMailService $service): int
    {
        $sent = $service->sendDueSchedules();
        $this->info("Sent {$sent} report email(s).");

        return self::SUCCESS;
    }
}
