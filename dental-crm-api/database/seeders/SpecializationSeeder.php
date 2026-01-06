<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Specialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecializationSeeder extends Seeder
{
    public function run(): void
    {
        // Базовий довідник
        $defaults = [
            'Терапевт',
            'Ортодонт',
            'Хірург',
            'Ортопед',
            'Ендодонтист',
            'Пародонтолог',
            'Дитячий стоматолог',
            'Гігієніст',
            'Рентгенолог',
        ];

        // Витягуємо унікальні спеціалізації, що вже записані у лікарів (старе поле)
        $fromDoctors = Doctor::query()
            ->whereNotNull('specialization')
            ->distinct()
            ->pluck('specialization')
            ->filter()
            ->values()
            ->all();

        $names = collect($defaults)
            ->merge($fromDoctors)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        $names->each(function (string $name): void {
            Specialization::firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name) ?: null,
                    'is_active' => true,
                ]
            );
        });
    }
}


