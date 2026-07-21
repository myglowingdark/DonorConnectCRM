<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WipeTenantsCommand extends Command
{
    protected $signature = 'donorconnect:wipe-tenants
                            {--force : Required safety flag.}
                            {--keep-super-admin-email= : Keep only this super admin email (default: first super_admin).}';

    protected $description = 'Wipe ALL app data. Leaves exactly one Super Admin. Irreversible.';

    /** Tables that must never be truncated. */
    protected array $preserveTables = [
        'migrations',
    ];

    public function handle(): int
    {
        $email = $this->option('keep-super-admin-email');

        $superQuery = User::query()->where('role', UserRole::SuperAdmin)->orderBy('id');
        if ($email) {
            $superQuery->where('email', $email);
        }

        $super = $superQuery->first();
        if (! $super) {
            $this->error('Abort: no Super Admin found to keep.');

            return self::FAILURE;
        }

        $this->warn('This will DELETE EVERYTHING except one Super Admin.');
        $this->line("Keeping: #{$super->id} {$super->email}");
        $this->line('App env: '.config('app.env').' | DB: '.$this->databaseName());

        if (! $this->option('force')) {
            $this->error('Refusing without --force.');
            $this->line('Example: php artisan donorconnect:wipe-tenants --force --no-interaction');

            return self::FAILURE;
        }

        if ($this->input->isInteractive()) {
            $confirm = $this->ask('Type WIPE EVERYTHING to confirm');
            if ($confirm !== 'WIPE EVERYTHING') {
                $this->warn('Cancelled.');

                return self::FAILURE;
            }
        }

        try {
            $this->wipeAllExceptSuperAdmin($super);
        } catch (Throwable $e) {
            $this->error('FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Wipe complete.');
        $this->line('users='.DB::table('users')->count().' organizations='.(Schema::hasTable('organizations') ? DB::table('organizations')->count() : 0));
        $user = User::query()->first();
        $this->line('Remaining user: #'.$user?->id.' '.$user?->email.' '.$user?->role?->value);

        return self::SUCCESS;
    }

    protected function wipeAllExceptSuperAdmin(User $super): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($this->listTables() as $table) {
                if (in_array($table, $this->preserveTables, true)) {
                    continue;
                }

                if ($table === 'users') {
                    // Keep only the chosen super admin row.
                    DB::table('users')->where('id', '!=', $super->id)->delete();
                    // Reset extras on the kept user (optional cleanup of 2FA / remember tokens).
                    DB::table('users')->where('id', $super->id)->update([
                        'remember_token' => null,
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                        'two_factor_confirmed_at' => null,
                    ]);

                    continue;
                }

                DB::table($table)->delete();
                $this->line("Cleared {$table}");
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    /** @return list<string> */
    protected function listTables(): array
    {
        $driver = Schema::getConnection()->getDriverName();
        $database = $this->databaseName();

        return match ($driver) {
            'mysql' => collect(DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
                ->map(fn ($row) => array_values((array) $row)[0])
                ->values()
                ->all(),
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->values()
                ->all(),
            default => throw new \RuntimeException("Unsupported database driver [{$driver}] for wipe."),
        };
    }

    protected function databaseName(): string
    {
        $connection = config('database.default');

        return (string) config("database.connections.{$connection}.database");
    }
}
