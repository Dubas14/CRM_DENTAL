<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Services\Calendar\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PrecomputeSlots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slots:precompute {--days=7 : Number of days to precompute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Precompute available slots for all active doctors for the next N days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $this->info("Precomputing slots for the next {$days} days...");

        $doctors = Doctor::where('is_active', true)->get();
        $availability = new AvailabilityService;
        $today = Carbon::today();

        $totalProcessed = 0;
        $totalSlots = 0;

        foreach ($doctors as $doctor) {
            $this->line("Processing doctor: {$doctor->full_name} (ID: {$doctor->id})");

            for ($i = 0; $i < $days; $i++) {
                $date = $today->copy()->addDays($i);

                try {
                    // Отримуємо слоти для стандартної тривалості (30 хвилин)
                    $slots = $availability->getAvailableSlots($doctor, $date, 30);

                    // Кешування відбувається автоматично в getAvailableSlots
                    $slotCount = count($slots['slots'] ?? []);
                    $totalSlots += $slotCount;
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $this->error("Error processing {$doctor->full_name} for {$date->toDateString()}: ".$e->getMessage());
                }
            }
        }

        $this->info("Completed! Processed {$totalProcessed} doctor-days, found {$totalSlots} total slots.");

        return Command::SUCCESS;
    }
}
