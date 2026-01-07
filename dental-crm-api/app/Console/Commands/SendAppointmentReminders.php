<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for appointments 24 hours before the appointment time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting appointment reminders...');

        // Знаходимо записи, які мають відбутися через 24 години
        $targetTime = Carbon::now()->addHours(24);
        $startRange = $targetTime->copy()->subHours(1); // Дозволяємо 1 годину вікно
        $endRange = $targetTime->copy()->addHours(1);

        $appointments = Appointment::whereIn('status', ['planned', 'confirmed'])
            ->whereBetween('start_at', [$startRange, $endRange])
            ->where('status', '!=', 'reminded') // Не нагадуємо вже нагадані
            ->with(['patient', 'doctor', 'procedure'])
            ->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($appointments as $appointment) {
            try {
                // Тут можна додати відправку SMS/Email через Notification
                // Наприклад: $appointment->patient->notify(new AppointmentReminderNotification($appointment));

                // Оновлюємо статус на 'reminded'
                $appointment->update(['status' => 'reminded']);

                $sentCount++;
                $this->info("Reminder sent for appointment #{$appointment->id} at {$appointment->start_at}");
            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to send reminder for appointment #{$appointment->id}: ".$e->getMessage());
                $this->error("Failed to send reminder for appointment #{$appointment->id}: ".$e->getMessage());
            }
        }

        $this->info("Reminders sent: {$sentCount}, Failed: {$failedCount}");

        return Command::SUCCESS;
    }
}
