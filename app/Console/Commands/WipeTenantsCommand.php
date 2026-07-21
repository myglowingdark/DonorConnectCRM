<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WipeTenantsCommand extends Command
{
    protected $signature = 'donorconnect:wipe-tenants
                            {--force : Required. Skip the interactive confirm prompt.}
                            {--keep-super-admin-email= : Optional. Keep only this super admin email (default: all super_admin users).}';

    protected $description = 'Delete ALL organizations (+ cascaded data) and ALL users except Super Admin(s). Irreversible.';

    public function handle(): int
    {
        $superQuery = User::query()->where('role', UserRole::SuperAdmin);
        if ($email = $this->option('keep-super-admin-email')) {
            $superQuery->where('email', $email);
        }

        $supers = $superQuery->get(['id', 'name', 'email']);
        if ($supers->isEmpty()) {
            $this->error('Abort: no Super Admin user found to keep.');

            return self::FAILURE;
        }

        $orgCount = Organization::query()->count();
        $userCount = User::query()->whereNotIn('id', $supers->pluck('id'))->count();

        $this->warn('This will permanently delete:');
        $this->line("  • {$orgCount} organization(s) and all related donor/donation/sync/commission data");
        $this->line("  • {$userCount} non–super-admin user(s)");
        $this->newLine();
        $this->info('Keeping Super Admin(s):');
        foreach ($supers as $user) {
            $this->line("  • #{$user->id} {$user->email}");
        }
        $this->newLine();
        $this->line('App env: '.config('app.env').' | DB: '.config('database.connections.'.config('database.default').'.database'));

        if (! $this->option('force')) {
            $this->error('Refusing to run without --force (safety).');
            $this->line('Example: php artisan donorconnect:wipe-tenants --force');

            return self::FAILURE;
        }

        if ($this->input->isInteractive()) {
            $confirm = $this->ask('Type DELETE ALL ORGS to confirm');
            if ($confirm !== 'DELETE ALL ORGS') {
                $this->warn('Cancelled.');

                return self::FAILURE;
            }
        }

        DB::beginTransaction();
        try {
            $superIds = $supers->pluck('id')->all();

            Organization::query()->orderBy('id')->each(function (Organization $org) {
                $this->line("Deleting org #{$org->id} {$org->name}");
                $org->delete();
            });

            DB::table('organization_user')->delete();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')
                    ->whereIn('user_id', User::query()->whereNotIn('id', $superIds)->select('id'))
                    ->delete();
            }

            $deletedUsers = User::query()->whereNotIn('id', $superIds)->delete();

            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->whereNotNull('organization_id')->delete();
            }

            DB::commit();

            $this->newLine();
            $this->info("Done. Orgs left: ".Organization::count()." | Users left: ".User::count()." | Users removed: {$deletedUsers}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('FAILED: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
