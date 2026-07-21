<?php

namespace Database\Seeders;

use App\Enums\CallOutcome;
use App\Enums\DonorStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\DonorInteraction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hope = Organization::create([
            'name' => 'Hope Foundation',
            'slug' => 'hope-foundation',
            'brand_color' => '#1e3a8a',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $seva = Organization::create([
            'name' => 'Seva Trust',
            'slug' => 'seva-trust',
            'brand_color' => '#0f766e',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $green = Organization::create([
            'name' => 'Green Future NGO',
            'slug' => 'green-future-ngo',
            'brand_color' => '#166534',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@donorconnect.test',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
            'phone' => '+91 9000000001',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $hopeAdmin = User::create([
            'name' => 'Hope Admin',
            'email' => 'hope.admin@donorconnect.test',
            'password' => Hash::make('password'),
            'role' => UserRole::OrganizationAdmin,
            'phone' => '+91 9000000002',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $priya = User::create([
            'name' => 'Priya Sharma',
            'email' => 'priya@donorconnect.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Volunteer,
            'phone' => '+91 9876543210',
            'languages' => ['hi', 'en'],
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $rahul = User::create([
            'name' => 'Rahul Verma',
            'email' => 'rahul@donorconnect.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Volunteer,
            'phone' => '+91 9876543211',
            'languages' => ['en', 'hi', 'mr'],
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $aisha = User::create([
            'name' => 'Aisha Khan',
            'email' => 'aisha@donorconnect.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Volunteer,
            'phone' => '+91 9876543212',
            'languages' => ['en', 'ur', 'hi'],
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $superAdmin->organizations()->attach([$hope->id, $seva->id, $green->id]);
        $hopeAdmin->organizations()->attach([$hope->id]);
        $priya->organizations()->attach([$hope->id, $seva->id]);
        $rahul->organizations()->attach([$hope->id]);
        $aisha->organizations()->attach([$seva->id, $green->id]);

        foreach ([$hope, $seva, $green] as $org) {
            Campaign::create([
                'organization_id' => $org->id,
                'name' => $org->name.' Annual Appeal',
                'status' => 'active',
                'starts_at' => now()->startOfYear(),
                'ends_at' => now()->endOfYear(),
            ]);
        }

        $namedDonors = [
            ['Anita Mehta', 'anita.mehta@example.com', '+91 9811111111'],
            ['Rohan Gupta', 'rohan.gupta@example.com', '+91 9822222222'],
            ['Neha Patel', 'neha.patel@example.com', '+91 9833333333'],
        ];

        foreach ([$hope, $seva, $green] as $orgIndex => $org) {
            foreach ($namedDonors as $i => [$name, $email, $phone]) {
                $donor = Donor::create([
                    'organization_id' => $org->id,
                    'external_donor_id' => 'wp-'.$org->slug.'-'.($i + 1),
                    'full_name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'city' => ['Mumbai', 'Delhi', 'Ahmedabad'][$i],
                    'state' => ['Maharashtra', 'Delhi', 'Gujarat'][$i],
                    'country' => 'India',
                    'donor_status' => DonorStatus::New,
                    'last_donation_at' => now()->subMonths(2 + $i),
                    'last_donation_amount' => [5000, 2100, 11000][$i],
                    'total_donated' => [15000, 6300, 22000][$i],
                ]);

                Donation::create([
                    'organization_id' => $org->id,
                    'donor_id' => $donor->id,
                    'external_donation_id' => 'wp-don-'.$org->id.'-'.($i + 1),
                    'amount' => $donor->last_donation_amount,
                    'currency' => 'INR',
                    'donated_at' => $donor->last_donation_at,
                    'payment_status' => 'completed',
                    'payment_method' => 'UPI',
                ]);
            }

            Donor::factory()->count(12)->create([
                'organization_id' => $org->id,
            ])->each(function (Donor $donor) use ($org) {
                Donation::factory()->create([
                    'organization_id' => $org->id,
                    'donor_id' => $donor->id,
                    'amount' => $donor->last_donation_amount ?: 1000,
                    'donated_at' => $donor->last_donation_at ?: now()->subMonth(),
                ]);
            });
        }

        // Assign Hope donors to Priya and Rahul
        $hopeDonors = Donor::query()->forOrganization($hope->id)->orderBy('id')->get();
        foreach ($hopeDonors->take(8) as $index => $donor) {
            $volunteer = $index % 2 === 0 ? $priya : $rahul;
            DonorAssignment::create([
                'organization_id' => $hope->id,
                'donor_id' => $donor->id,
                'volunteer_id' => $volunteer->id,
                'assigned_by' => $hopeAdmin->id,
                'assigned_at' => now()->subDays(3),
                'is_active' => true,
            ]);
        }

        // Sample interactions for Priya
        $anita = Donor::query()->forOrganization($hope->id)->where('full_name', 'Anita Mehta')->first();
        if ($anita) {
            DonorInteraction::create([
                'organization_id' => $hope->id,
                'donor_id' => $anita->id,
                'volunteer_id' => $priya->id,
                'interaction_type' => 'call',
                'outcome' => CallOutcome::Interested,
                'notes' => 'Wants to support education drive next month.',
                'contacted_at' => now()->subDay(),
                'follow_up_at' => now()->addDay()->setTime(11, 0),
            ]);

            $anita->update([
                'donor_status' => DonorStatus::Interested,
                'last_contacted_at' => now()->subDay(),
                'next_follow_up_at' => now()->addDay()->setTime(11, 0),
            ]);
        }

        $this->command?->info('Demo accounts (password: password):');
        $this->command?->info('  Super Admin: admin@donorconnect.test');
        $this->command?->info('  Org Admin:   hope.admin@donorconnect.test');
        $this->command?->info('  Volunteer:   priya@donorconnect.test');
    }
}
