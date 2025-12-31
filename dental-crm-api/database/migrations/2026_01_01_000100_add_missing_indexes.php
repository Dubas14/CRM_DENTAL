<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing indexes for appointments table
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_patient_id_index ON appointments (patient_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_status_index ON appointments (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_clinic_id_index ON appointments (clinic_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_start_at_index ON appointments (start_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_end_at_index ON appointments (end_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_clinic_id_start_at_index ON appointments (clinic_id, start_at)');

        // Add indexes for schedules table
        DB::statement('CREATE INDEX IF NOT EXISTS schedules_doctor_id_index ON schedules (doctor_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS schedules_weekday_index ON schedules (weekday)');
        DB::statement('CREATE INDEX IF NOT EXISTS schedules_doctor_id_weekday_index ON schedules (doctor_id, weekday)');

        // Add indexes for schedule_exceptions table
        DB::statement('CREATE INDEX IF NOT EXISTS schedule_exceptions_doctor_id_index ON schedule_exceptions (doctor_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS schedule_exceptions_date_index ON schedule_exceptions (date)');
        DB::statement('CREATE INDEX IF NOT EXISTS schedule_exceptions_doctor_id_date_index ON schedule_exceptions (doctor_id, date)');

        // Add indexes for waitlist_entries table
        DB::statement('CREATE INDEX IF NOT EXISTS waitlist_entries_clinic_id_index ON waitlist_entries (clinic_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS waitlist_entries_doctor_id_index ON waitlist_entries (doctor_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS waitlist_entries_procedure_id_index ON waitlist_entries (procedure_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS waitlist_entries_status_index ON waitlist_entries (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS waitlist_entries_preferred_date_index ON waitlist_entries (preferred_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS waitlist_entries_clinic_id_status_index ON waitlist_entries (clinic_id, status)');

        // Add indexes for medical_records table
        DB::statement('CREATE INDEX IF NOT EXISTS medical_records_patient_id_index ON medical_records (patient_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS medical_records_appointment_id_index ON medical_records (appointment_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS medical_records_doctor_id_index ON medical_records (doctor_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS medical_records_created_at_index ON medical_records (created_at)');

        // Add indexes for patients table
        DB::statement('CREATE INDEX IF NOT EXISTS patients_clinic_id_index ON patients (clinic_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS patients_full_name_index ON patients (full_name)');
        DB::statement('CREATE INDEX IF NOT EXISTS patients_phone_index ON patients (phone)');
        DB::statement('CREATE INDEX IF NOT EXISTS patients_email_index ON patients (email)');

        // Add indexes for doctors table
        DB::statement('CREATE INDEX IF NOT EXISTS doctors_clinic_id_index ON doctors (clinic_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS doctors_user_id_index ON doctors (user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS doctors_is_active_index ON doctors (is_active)');

        // Add indexes for procedures table
        DB::statement('CREATE INDEX IF NOT EXISTS procedures_clinic_id_index ON procedures (clinic_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS procedures_category_index ON procedures (category)');

        // Add indexes for rooms table
        DB::statement('CREATE INDEX IF NOT EXISTS rooms_clinic_id_index ON rooms (clinic_id)');

        // Add indexes for equipments table
        DB::statement('CREATE INDEX IF NOT EXISTS equipments_clinic_id_index ON equipments (clinic_id)');

        // Add indexes for calendar_blocks table
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_clinic_id_index ON calendar_blocks (clinic_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_doctor_id_index ON calendar_blocks (doctor_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_room_id_index ON calendar_blocks (room_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_equipment_id_index ON calendar_blocks (equipment_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_assistant_id_index ON calendar_blocks (assistant_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_type_index ON calendar_blocks (type)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_start_at_index ON calendar_blocks (start_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_end_at_index ON calendar_blocks (end_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS calendar_blocks_clinic_id_start_at_end_at_index ON calendar_blocks (clinic_id, start_at, end_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'appointments_patient_id_index',
            'appointments_status_index',
            'appointments_clinic_id_index',
            'appointments_start_at_index',
            'appointments_end_at_index',
            'appointments_clinic_id_start_at_index',
            'schedules_doctor_id_index',
            'schedules_weekday_index',
            'schedules_doctor_id_weekday_index',
            'schedule_exceptions_doctor_id_index',
            'schedule_exceptions_date_index',
            'schedule_exceptions_doctor_id_date_index',
            'waitlist_entries_clinic_id_index',
            'waitlist_entries_doctor_id_index',
            'waitlist_entries_procedure_id_index',
            'waitlist_entries_status_index',
            'waitlist_entries_preferred_date_index',
            'waitlist_entries_clinic_id_status_index',
            'medical_records_patient_id_index',
            'medical_records_appointment_id_index',
            'medical_records_doctor_id_index',
            'medical_records_created_at_index',
            'patients_clinic_id_index',
            'patients_full_name_index',
            'patients_phone_index',
            'patients_email_index',
            'doctors_clinic_id_index',
            'doctors_user_id_index',
            'doctors_is_active_index',
            'procedures_clinic_id_index',
            'procedures_category_index',
            'rooms_clinic_id_index',
            'equipments_clinic_id_index',
            'calendar_blocks_clinic_id_index',
            'calendar_blocks_doctor_id_index',
            'calendar_blocks_room_id_index',
            'calendar_blocks_equipment_id_index',
            'calendar_blocks_assistant_id_index',
            'calendar_blocks_type_index',
            'calendar_blocks_start_at_index',
            'calendar_blocks_end_at_index',
            'calendar_blocks_clinic_id_start_at_end_at_index',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
