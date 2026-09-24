<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Attendance;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\GymClass;
use App\Models\Invoice;
use App\Models\MaintenanceLog;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Full system access',
            'is_active' => true,
        ]);

        $managerRole = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Gym operations manager',
            'is_active' => true,
        ]);

        $receptionistRole = Role::create([
            'name' => 'Receptionist',
            'slug' => 'receptionist',
            'description' => 'Front desk staff',
            'is_active' => true,
        ]);

        $modules = [
            'dashboard', 'members', 'trainers', 'classes', 'plans',
            'invoices', 'payments', 'expenses', 'accounts',
            'attendance', 'reports',
            'settings', 'users', 'roles',
        ];

        $actions = ['view', 'create', 'edit', 'delete'];
        $allPermissionIds = [];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permission = Permission::create([
                    'name' => ucfirst($action) . ' ' . ucfirst($module),
                    'slug' => "{$module}.{$action}",
                    'module' => $module,
                ]);
                $allPermissionIds[] = $permission->id;
            }
        }

        $adminRole->permissions()->sync($allPermissionIds);

        $managerModules = [
            'dashboard', 'members', 'trainers', 'classes', 'plans',
            'invoices', 'payments', 'expenses', 'accounts',
            'attendance', 'reports',
        ];
        $managerPermissionIds = Permission::query()
            ->whereIn('module', $managerModules)
            ->pluck('id');
        $managerRole->permissions()->sync($managerPermissionIds);

        $receptionistModules = ['dashboard', 'members', 'classes', 'plans', 'invoices', 'payments', 'attendance'];
        $receptionistPermissionIds = Permission::query()
            ->whereIn('module', $receptionistModules)
            ->whereIn('slug', collect($receptionistModules)->flatMap(fn ($m) => [
                "{$m}.view", "{$m}.create", "{$m}.edit",
            ]))
            ->pluck('id')
            ->merge(Permission::query()->where('slug', 'reports.view')->pluck('id'));
        $receptionistRole->permissions()->sync($receptionistPermissionIds);

        User::create([
            'name' => 'John Doe',
            'email' => 'admin@fitgeneration.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'phone' => '+92 300 1111111',
            'status' => 'active',
        ]);

        User::create([
            'name' => 'Sara Malik',
            'email' => 'manager@fitgeneration.com',
            'password' => Hash::make('password'),
            'role_id' => $managerRole->id,
            'phone' => '+92 300 2222222',
            'status' => 'active',
        ]);

        User::create([
            'name' => 'Ali Raza',
            'email' => 'reception@fitgeneration.com',
            'password' => Hash::make('password'),
            'role_id' => $receptionistRole->id,
            'phone' => '+92 300 3333333',
            'status' => 'active',
        ]);

        $plans = [
            ...collect(config('membership_packages', []))->map(fn ($row) => [
                'name' => $row['name'],
                'tier' => $row['tier'] ?? 'basic',
                'price' => $row['price'],
                'duration_days' => $row['duration_days'] ?? 30,
                'features' => [],
            ])->all(),
        ];

        foreach ($plans as $plan) {
            MembershipPlan::create([
                'name' => $plan['name'],
                'slug' => Str::slug($plan['name']),
                'tier' => $plan['tier'],
                'price' => $plan['price'],
                'duration_days' => $plan['duration_days'],
                'description' => $plan['name'] . ' membership package for Fit Generation members.',
                'features' => $plan['features'],
                'is_active' => true,
            ]);
        }

        $trainers = [
            ['first_name' => 'Mike', 'last_name' => 'Johnson', 'specialization' => 'Strength Training', 'hourly_rate' => 45],
            ['first_name' => 'Sarah', 'last_name' => 'Williams', 'specialization' => 'HIIT Cardio', 'hourly_rate' => 40],
            ['first_name' => 'Emma', 'last_name' => 'Davis', 'specialization' => 'Yoga', 'hourly_rate' => 35],
            ['first_name' => 'Anna', 'last_name' => 'Garcia', 'specialization' => 'Zumba Dance', 'hourly_rate' => 38],
        ];

        $trainerModels = [];
        foreach ($trainers as $i => $trainer) {
            $trainerModels[] = Trainer::create([
                'trainer_code' => 'TRN-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'first_name' => $trainer['first_name'],
                'last_name' => $trainer['last_name'],
                'email' => strtolower($trainer['first_name']) . '@fitgeneration.com',
                'phone' => '+1 555 02' . str_pad((string) ($i + 10), 2, '0', STR_PAD_LEFT),
                'specialization' => $trainer['specialization'],
                'hourly_rate' => $trainer['hourly_rate'],
                'hire_date' => now()->subMonths(6 - $i)->toDateString(),
                'avatar' => 'https://i.pravatar.cc/128?img=' . (12 + $i),
                'status' => 'active',
                'bio' => 'Experienced ' . $trainer['specialization'] . ' coach at Fit Generation.',
            ]);
        }

        $classes = [
            ['name' => 'Strength Training', 'trainer' => 0, 'capacity' => 25, 'duration' => 60, 'img' => 33],
            ['name' => 'HIIT Cardio', 'trainer' => 1, 'capacity' => 20, 'duration' => 45, 'img' => 26],
            ['name' => 'Yoga Flex', 'trainer' => 2, 'capacity' => 18, 'duration' => 50, 'img' => 45],
            ['name' => 'Zumba Dance', 'trainer' => 3, 'capacity' => 30, 'duration' => 55, 'img' => 48],
        ];

        $classModels = [];
        foreach ($classes as $class) {
            $classModels[] = GymClass::create([
                'name' => $class['name'],
                'slug' => Str::slug($class['name']),
                'trainer_id' => $trainerModels[$class['trainer']]->id,
                'description' => $class['name'] . ' session led by our certified trainers.',
                'image' => 'https://i.pravatar.cc/128?img=' . $class['img'],
                'capacity' => $class['capacity'],
                'duration_minutes' => $class['duration'],
                'status' => 'active',
            ]);
        }

        foreach ($classModels as $i => $classModel) {
            $classModel->schedules()->create([
                'schedule_date' => now()->addDays($i)->toDateString(),
                'start_time' => sprintf('%02d:00:00', 8 + $i),
                'end_time' => sprintf('%02d:00:00', 9 + $i),
                'room' => 'Studio ' . chr(65 + $i),
                'status' => 'scheduled',
            ]);
        }

        $members = [
            ['first_name' => 'Brooklyn', 'last_name' => 'Simmons', 'status' => 'active', 'gender' => 'female', 'img' => 32, 'plan' => 'premium'],
            ['first_name' => 'Cameron', 'last_name' => 'Williamson', 'status' => 'active', 'gender' => 'male', 'img' => 13, 'plan' => 'premium'],
            ['first_name' => 'Darrell', 'last_name' => 'Steward', 'status' => 'active', 'gender' => 'male', 'img' => 53, 'plan' => 'standard'],
            ['first_name' => 'Ralph', 'last_name' => 'Edwards', 'status' => 'pending', 'gender' => 'male', 'img' => 59, 'plan' => 'basic'],
            ['first_name' => 'Leslie', 'last_name' => 'Alexander', 'status' => 'active', 'gender' => 'female', 'img' => 68, 'plan' => 'standard'],
            ['first_name' => 'Wade', 'last_name' => 'Warren', 'status' => 'leave', 'gender' => 'male', 'img' => 52, 'plan' => 'premium'],
            ['first_name' => 'Theresa', 'last_name' => 'Webb', 'status' => 'active', 'gender' => 'female', 'img' => 25, 'plan' => 'standard'],
            ['first_name' => 'Devon', 'last_name' => 'Lane', 'status' => 'cancelled', 'gender' => 'male', 'img' => 60, 'plan' => 'basic'],
        ];

        $memberModels = [];
        foreach ($members as $i => $member) {
            $memberModels[] = Member::create([
                'member_code' => 'MEM-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name'],
                'email' => strtolower($member['first_name']) . '.' . strtolower($member['last_name']) . '@mail.com',
                'phone' => '+1 555 03' . str_pad((string) ($i + 10), 2, '0', STR_PAD_LEFT),
                'gender' => $member['gender'],
                'avatar' => 'https://i.pravatar.cc/128?img=' . $member['img'],
                'status' => $member['status'],
                'joined_at' => now()->subDays(20 - $i)->toDateString(),
                'address' => '123 Fitness Ave, Suite ' . ($i + 1),
            ]);
        }

        $accounts = [
            ['name' => 'Cash', 'code' => 'CASH-001', 'type' => 'asset', 'opening_balance' => 50000],
            ['name' => 'Bank Account', 'code' => 'BANK-001', 'type' => 'asset', 'opening_balance' => 250000],
            ['name' => 'Membership Income', 'code' => 'INC-001', 'type' => 'income', 'opening_balance' => 0],
            ['name' => 'Operating Expenses', 'code' => 'EXP-001', 'type' => 'expense', 'opening_balance' => 0],
        ];

        $accountModels = [];
        foreach ($accounts as $account) {
            $accountModels[$account['code']] = Account::create([
                'name' => $account['name'],
                'code' => $account['code'],
                'type' => $account['type'],
                'opening_balance' => $account['opening_balance'],
                'current_balance' => $account['opening_balance'],
                'is_active' => true,
            ]);
        }

        $members = Member::all();
        $plans = MembershipPlan::all();
        $admin = User::first();

        foreach ($memberModels as $i => $memberModel) {
            $tier = ['premium', 'premium', 'standard', 'basic', 'standard', 'premium', 'standard', 'basic'][$i] ?? 'basic';
            $plan = $plans->where('tier', $tier)->first() ?? $plans->first();
            if ($plan && $memberModel->status !== 'cancelled') {
                \App\Models\MemberSubscription::create([
                    'member_id' => $memberModel->id,
                    'membership_plan_id' => $plan->id,
                    'start_date' => now()->subDays(20 - $i)->toDateString(),
                    'end_date' => now()->addDays(10 + $i)->toDateString(),
                    'amount_paid' => $plan->price,
                    'status' => $memberModel->status === 'pending' ? 'pending' : 'active',
                ]);
            }

            if ($classModels[$i % count($classModels)] ?? null) {
                \App\Models\ClassEnrollment::create([
                    'gym_class_id' => $classModels[$i % count($classModels)]->id,
                    'member_id' => $memberModel->id,
                    'status' => 'enrolled',
                    'enrolled_at' => now()->subDays($i)->toDateString(),
                ]);
            }
        }

        // Extra enrollments so popular classes show capacity
        $memberCollection = collect($memberModels);
        foreach ($classModels as $ci => $classModel) {
            foreach ($memberCollection->take(3 + $ci) as $m) {
                \App\Models\ClassEnrollment::firstOrCreate(
                    [
                        'gym_class_id' => $classModel->id,
                        'member_id' => $m->id,
                        'class_schedule_id' => null,
                    ],
                    [
                        'status' => 'enrolled',
                        'enrolled_at' => now()->subDays($ci)->toDateString(),
                    ]
                );
            }
        }

        if ($members->count() && $plans->count()) {
            $invoice1 = Invoice::create([
                'invoice_number' => 'INV-' . now()->format('Y') . '-0101',
                'member_id' => $members[0]->id,
                'membership_plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->first()->id,
                'invoice_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(2)->toDateString(),
                'subtotal' => 120,
                'tax' => 0,
                'discount' => 0,
                'total' => 120,
                'amount_paid' => 0,
                'status' => 'unpaid',
            ]);
            $invoice1->items()->create([
                'description' => 'Premium Membership',
                'quantity' => 1,
                'unit_price' => 120,
                'total' => 120,
            ]);

            $invoice2 = Invoice::create([
                'invoice_number' => 'INV-' . now()->format('Y') . '-0102',
                'member_id' => $members[1]->id,
                'membership_plan_id' => $plans->where('tier', 'standard')->first()?->id ?? $plans->first()->id,
                'invoice_date' => now()->subDays(10)->toDateString(),
                'due_date' => now()->subDays(3)->toDateString(),
                'subtotal' => 79,
                'tax' => 0,
                'discount' => 0,
                'total' => 79,
                'amount_paid' => 79,
                'status' => 'paid',
            ]);
            $invoice2->items()->create([
                'description' => 'Standard Membership',
                'quantity' => 1,
                'unit_price' => 79,
                'total' => 79,
            ]);

            $invoice3 = Invoice::create([
                'invoice_number' => 'INV-' . now()->format('Y') . '-0103',
                'member_id' => $members[2]->id,
                'membership_plan_id' => $plans->where('tier', 'basic')->first()?->id ?? $plans->first()->id,
                'invoice_date' => now()->subDays(3)->toDateString(),
                'due_date' => now()->addDays(4)->toDateString(),
                'subtotal' => 49,
                'tax' => 0,
                'discount' => 0,
                'total' => 49,
                'amount_paid' => 20,
                'status' => 'partial',
            ]);
            $invoice3->items()->create([
                'description' => 'Basic Membership',
                'quantity' => 1,
                'unit_price' => 49,
                'total' => 49,
            ]);

            Payment::create([
                'payment_number' => 'PAY-' . now()->format('Y') . '-0101',
                'invoice_id' => $invoice2->id,
                'member_id' => $members[1]->id,
                'account_id' => $accountModels['CASH-001']->id,
                'amount' => 79,
                'method' => 'cash',
                'payment_date' => now()->subDays(8)->toDateString(),
                'status' => 'completed',
                'received_by' => $admin?->id,
            ]);
            $accountModels['CASH-001']->increment('current_balance', 79);

            Payment::create([
                'payment_number' => 'PAY-' . now()->format('Y') . '-0102',
                'invoice_id' => $invoice3->id,
                'member_id' => $members[2]->id,
                'account_id' => $accountModels['BANK-001']->id,
                'amount' => 20,
                'method' => 'bank_transfer',
                'payment_date' => now()->subDays(1)->toDateString(),
                'status' => 'completed',
                'received_by' => $admin?->id,
            ]);
            $accountModels['BANK-001']->increment('current_balance', 20);

            Payment::create([
                'payment_number' => 'PAY-' . now()->format('Y') . '-0103',
                'member_id' => $members[4]->id,
                'account_id' => $accountModels['CASH-001']->id,
                'amount' => 150,
                'method' => 'card',
                'payment_date' => now()->toDateString(),
                'status' => 'pending',
                'notes' => 'Awaiting card confirmation',
                'received_by' => $admin?->id,
            ]);

            // Spread completed payments across the week for charts
            $chartPayments = [
                [now()->startOfWeek()->toDateString(), 11000],
                [now()->startOfWeek()->addDays(1)->toDateString(), 8500],
                [now()->startOfWeek()->addDays(2)->toDateString(), 14200],
                [now()->startOfWeek()->addDays(3)->toDateString(), 9800],
                [now()->startOfWeek()->addDays(4)->toDateString(), 12500],
                [now()->subDays(1)->toDateString(), 7600],
                [now()->toDateString(), 5400],
            ];
            foreach ($chartPayments as $pi => $row) {
                Payment::create([
                    'payment_number' => 'PAY-' . now()->format('Y') . '-02' . str_pad((string) ($pi + 1), 2, '0', STR_PAD_LEFT),
                    'member_id' => $members[$pi % $members->count()]->id,
                    'account_id' => $accountModels['CASH-001']->id,
                    'amount' => $row[1],
                    'method' => 'cash',
                    'payment_date' => $row[0],
                    'status' => 'completed',
                    'received_by' => $admin?->id,
                ]);
                $accountModels['CASH-001']->increment('current_balance', $row[1]);
            }

            $invoice4 = Invoice::create([
                'invoice_number' => 'INV-' . now()->format('Y') . '-0104',
                'member_id' => $members[5]->id,
                'membership_plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->first()->id,
                'invoice_date' => now()->subDays(2)->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'subtotal' => 120,
                'tax' => 0,
                'discount' => 0,
                'total' => 120,
                'amount_paid' => 0,
                'status' => 'unpaid',
            ]);
            $invoice4->items()->create([
                'description' => 'Premium Membership',
                'quantity' => 1,
                'unit_price' => 120,
                'total' => 120,
            ]);

            $invoice5 = Invoice::create([
                'invoice_number' => 'INV-' . now()->format('Y') . '-0105',
                'member_id' => $members[6]->id,
                'membership_plan_id' => $plans->where('tier', 'standard')->first()?->id ?? $plans->first()->id,
                'invoice_date' => now()->subDays(1)->toDateString(),
                'due_date' => now()->addDays(6)->toDateString(),
                'subtotal' => 79,
                'tax' => 0,
                'discount' => 0,
                'total' => 79,
                'amount_paid' => 0,
                'status' => 'unpaid',
            ]);
            $invoice5->items()->create([
                'description' => 'Standard Membership',
                'quantity' => 1,
                'unit_price' => 79,
                'total' => 79,
            ]);

            $expenseData = [
                ['title' => 'Electricity Bill', 'category' => 'Utilities', 'amount' => 18500, 'vendor' => 'LESCO'],
                ['title' => 'Gym Rent', 'category' => 'Rent', 'amount' => 75000, 'vendor' => 'Property Owner'],
                ['title' => 'Dumbbell Set', 'category' => 'Equipment', 'amount' => 32000, 'vendor' => 'FitGear Supplies'],
                ['title' => 'Cleaning Supplies', 'category' => 'Supplies', 'amount' => 4500, 'vendor' => 'CleanPro'],
            ];

            foreach ($expenseData as $i => $row) {
                Expense::create([
                    'expense_number' => 'EXP-' . now()->format('Y') . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'account_id' => $accountModels['BANK-001']->id,
                    'category' => $row['category'],
                    'title' => $row['title'],
                    'amount' => $row['amount'],
                    'expense_date' => now()->subDays(12 - $i)->toDateString(),
                    'payment_method' => $i % 2 === 0 ? 'bank_transfer' : 'cash',
                    'vendor' => $row['vendor'],
                    'status' => $i === 3 ? 'pending' : 'approved',
                    'recorded_by' => $admin?->id,
                ]);

                if ($i !== 3) {
                    $accountModels['BANK-001']->decrement('current_balance', $row['amount']);
                }
            }
        }

        // Operations sample data
        $activeMembers = Member::where('status', 'active')->get();
        foreach ($activeMembers->take(5) as $i => $member) {
            for ($d = 0; $d < 7; $d++) {
                if ($d % 2 === 0 && $i % 2 === 1) {
                    continue;
                }
                Attendance::create([
                    'member_id' => $member->id,
                    'attendance_date' => now()->subDays($d)->toDateString(),
                    'check_in' => sprintf('%02d:%02d:00', 7 + ($i % 4), 10 + $i * 3),
                    'check_out' => $d === 0 && $i === 0 ? null : sprintf('%02d:%02d:00', 9 + ($i % 4), 20 + $i * 2),
                    'method' => ['manual', 'biometric', 'app'][$i % 3],
                ]);
            }
        }

        $equipmentItems = [
            ['name' => 'Treadmill Pro X1', 'category' => 'Cardio', 'brand' => 'LifeFitness', 'model' => 'X1', 'location' => 'Cardio Zone', 'cost' => 350000, 'condition' => 'excellent'],
            ['name' => 'Olympic Barbell Rack', 'category' => 'Strength', 'brand' => 'Rogue', 'model' => 'R-3', 'location' => 'Free Weights', 'cost' => 180000, 'condition' => 'good'],
            ['name' => 'Spin Bike Elite', 'category' => 'Cardio', 'brand' => 'Schwinn', 'model' => 'IC4', 'location' => 'Studio B', 'cost' => 95000, 'condition' => 'good'],
            ['name' => 'Cable Cross Machine', 'category' => 'Functional', 'brand' => 'Technogym', 'model' => 'Pure', 'location' => 'Floor A', 'cost' => 420000, 'condition' => 'fair'],
            ['name' => 'Dumbbell Set 5-50kg', 'category' => 'Free Weights', 'brand' => 'Hammer Strength', 'model' => 'Rubber', 'location' => 'Free Weights', 'cost' => 210000, 'condition' => 'excellent'],
            ['name' => 'Rowing Machine', 'category' => 'Cardio', 'brand' => 'Concept2', 'model' => 'D', 'location' => 'Cardio Zone', 'cost' => 165000, 'condition' => 'poor'],
        ];

        $equipmentModels = [];
        foreach ($equipmentItems as $i => $item) {
            $equipmentModels[] = Equipment::create([
                'name' => $item['name'],
                'code' => 'EQP-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'category' => $item['category'],
                'brand' => $item['brand'],
                'model' => $item['model'],
                'location' => $item['location'],
                'purchase_date' => now()->subMonths(8 - $i)->toDateString(),
                'purchase_cost' => $item['cost'],
                'condition' => $item['condition'],
                'status' => $i === 5 ? 'maintenance' : 'available',
            ]);
        }

        MaintenanceLog::create([
            'equipment_id' => $equipmentModels[5]->id,
            'title' => 'Belt replacement & sensor check',
            'description' => 'Rowing belt slipping during high resistance. Replace belt and calibrate sensor.',
            'scheduled_date' => now()->toDateString(),
            'cost' => 8500,
            'technician' => 'Ali Tech Services',
            'priority' => 'high',
            'status' => 'in_progress',
            'reported_by' => $admin?->id,
        ]);

        MaintenanceLog::create([
            'equipment_id' => $equipmentModels[3]->id,
            'title' => 'Cable lubrication',
            'description' => 'Routine quarterly lubrication and pulley inspection.',
            'scheduled_date' => now()->subDays(12)->toDateString(),
            'completed_date' => now()->subDays(10)->toDateString(),
            'cost' => 3200,
            'technician' => 'In-house',
            'priority' => 'medium',
            'status' => 'completed',
            'reported_by' => $admin?->id,
        ]);

        MaintenanceLog::create([
            'equipment_id' => $equipmentModels[0]->id,
            'title' => 'Console firmware update',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'technician' => 'Vendor Support',
            'priority' => 'low',
            'status' => 'pending',
            'reported_by' => $admin?->id,
        ]);

        $settings = [
            ['key' => 'gym_name', 'value' => 'Fit Generation', 'group' => 'general'],
            ['key' => 'gym_email', 'value' => 'info@fitgeneration.com', 'group' => 'general'],
            ['key' => 'gym_phone', 'value' => '', 'group' => 'general'],
            ['key' => 'gym_address', 'value' => '', 'group' => 'general'],
            ['key' => 'currency', 'value' => 'PKR', 'group' => 'finance'],
            ['key' => 'currency_symbol', 'value' => '₨', 'group' => 'finance'],
            ['key' => 'invoice_prefix', 'value' => 'INV', 'group' => 'finance'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting + ['type' => 'string']);
        }
    }
}
