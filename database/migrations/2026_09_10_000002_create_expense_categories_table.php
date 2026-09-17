<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $defaults = [
            'Utilities', 'Rent', 'Equipment', 'Maintenance', 'Salaries',
            'Marketing', 'Supplies', 'Insurance', 'Other',
        ];

        $fromExpenses = [];
        if (Schema::hasTable('expenses')) {
            $fromExpenses = DB::table('expenses')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->pluck('category')
                ->all();
        }

        $names = collect($defaults)
            ->merge($fromExpenses)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n))
            ->values();

        $now = now();
        foreach ($names as $name) {
            DB::table('expense_categories')->insert([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
