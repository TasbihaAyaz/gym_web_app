<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $this->ensureDefaults();

        $stored = Setting::query()->get()->keyBy('key');
        $definitions = collect($this->definitions())->map(function ($def) use ($stored) {
            $def['value'] = $stored[$def['key']]->value ?? $def['default'];

            return $def;
        })->groupBy('group');

        return view('settings.index', [
            'grouped' => $definitions,
            'groups' => $this->groupMeta(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $definitions = collect($this->definitions())->keyBy('key');

        $rules = [];
        foreach ($definitions as $key => $def) {
            if ($def['type'] === 'boolean') {
                $rules["settings.$key"] = ['nullable'];
            } else {
                $rules["settings.$key"] = $def['rules'];
            }
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $definitions) {
            foreach ($definitions as $key => $def) {
                if ($def['type'] === 'boolean') {
                    $value = $request->boolean("settings.$key") ? '1' : '0';
                } else {
                    $value = (string) $request->input("settings.$key", $def['default']);
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'group' => $def['group'],
                        'type' => $def['type'],
                    ]
                );
            }
        });

        return redirect()->route('settings.index')->with('success', 'Settings saved successfully.');
    }

    private function ensureDefaults(): void
    {
        foreach ($this->definitions() as $def) {
            Setting::query()->firstOrCreate(
                ['key' => $def['key']],
                [
                    'value' => $def['default'],
                    'group' => $def['group'],
                    'type' => $def['type'],
                ]
            );
        }
    }

    private function groupMeta(): array
    {
        return [
            'general' => ['label' => 'General', 'desc' => 'Gym identity and contact details'],
            'finance' => ['label' => 'Finance', 'desc' => 'Currency and invoice preferences'],
            'operations' => ['label' => 'Operations', 'desc' => 'Attendance and class defaults'],
            'notifications' => ['label' => 'Notifications', 'desc' => 'Alerts and reminder preferences'],
        ];
    }

    private function definitions(): array
    {
        return [
            [
                'key' => 'gym_name',
                'label' => 'Gym Name',
                'group' => 'general',
                'type' => 'string',
                'default' => 'Fit Generation',
                'rules' => ['required', 'string', 'max:150'],
            ],
            [
                'key' => 'gym_tagline',
                'label' => 'Tagline',
                'group' => 'general',
                'type' => 'string',
                'default' => 'GYM MANAGEMENT',
                'rules' => ['nullable', 'string', 'max:150'],
            ],
            [
                'key' => 'gym_email',
                'label' => 'Email',
                'group' => 'general',
                'type' => 'string',
                'default' => 'info@fitgeneration.com',
                'rules' => ['nullable', 'email', 'max:150'],
            ],
            [
                'key' => 'gym_phone',
                'label' => 'Phone',
                'group' => 'general',
                'type' => 'string',
                'default' => '',
                'rules' => ['nullable', 'string', 'max:40'],
            ],
            [
                'key' => 'gym_address',
                'label' => 'Address',
                'group' => 'general',
                'type' => 'text',
                'default' => '',
                'rules' => ['nullable', 'string', 'max:500'],
            ],
            [
                'key' => 'timezone',
                'label' => 'Timezone',
                'group' => 'general',
                'type' => 'string',
                'default' => 'Asia/Karachi',
                'rules' => ['required', 'string', 'max:80'],
            ],
            [
                'key' => 'currency',
                'label' => 'Currency Code',
                'group' => 'finance',
                'type' => 'string',
                'default' => 'PKR',
                'rules' => ['required', 'string', 'max:10'],
            ],
            [
                'key' => 'currency_symbol',
                'label' => 'Currency Symbol',
                'group' => 'finance',
                'type' => 'string',
                'default' => '₨',
                'rules' => ['required', 'string', 'max:10'],
            ],
            [
                'key' => 'invoice_prefix',
                'label' => 'Receipt Prefix (legacy)',
                'group' => 'finance',
                'type' => 'string',
                'default' => 'INV',
                'rules' => ['required', 'string', 'max:20'],
            ],
            [
                'key' => 'tax_rate',
                'label' => 'Default Tax Rate (%)',
                'group' => 'finance',
                'type' => 'number',
                'default' => '0',
                'rules' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ],
            [
                'key' => 'invoice_due_days',
                'label' => 'Default Due Days',
                'group' => 'finance',
                'type' => 'number',
                'default' => '7',
                'rules' => ['nullable', 'integer', 'min:0', 'max:365'],
            ],
            [
                'key' => 'class_default_capacity',
                'label' => 'Default Class Capacity',
                'group' => 'operations',
                'type' => 'number',
                'default' => '20',
                'rules' => ['nullable', 'integer', 'min:1', 'max:500'],
            ],
            [
                'key' => 'attendance_method',
                'label' => 'Default Attendance Method',
                'group' => 'operations',
                'type' => 'string',
                'default' => 'manual',
                'rules' => ['required', 'in:manual,biometric,app'],
            ],
            [
                'key' => 'open_time',
                'label' => 'Opening Time',
                'group' => 'operations',
                'type' => 'string',
                'default' => '06:00',
                'rules' => ['nullable', 'date_format:H:i'],
            ],
            [
                'key' => 'close_time',
                'label' => 'Closing Time',
                'group' => 'operations',
                'type' => 'string',
                'default' => '23:00',
                'rules' => ['nullable', 'date_format:H:i'],
            ],
            [
                'key' => 'notify_due_payments',
                'label' => 'Notify Due Payments',
                'group' => 'notifications',
                'type' => 'boolean',
                'default' => '1',
                'rules' => ['nullable', 'boolean'],
            ],
            [
                'key' => 'notify_new_members',
                'label' => 'Notify New Members',
                'group' => 'notifications',
                'type' => 'boolean',
                'default' => '1',
                'rules' => ['nullable', 'boolean'],
            ],
            [
                'key' => 'notify_maintenance',
                'label' => 'Notify Maintenance Alerts',
                'group' => 'notifications',
                'type' => 'boolean',
                'default' => '1',
                'rules' => ['nullable', 'boolean'],
            ],
        ];
    }
}
