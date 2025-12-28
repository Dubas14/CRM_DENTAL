<?php

use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\MedicalRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\CalendarBlockController;
use App\Http\Controllers\Api\ClinicWorkingHoursController;
use App\Http\Controllers\Api\DoctorProcedureController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Controllers\Api\DoctorScheduleSettingsController;
use App\Support\RoleHierarchy;

// ---- AUTH ----

Route::post('/login', function (Request $request) {

    // 1. Валідація
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // 2. Пошук юзера
    $user = User::where('email', $request->email)->first();

    // 3. Перевірка пароля
    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Невірний логін або пароль.'],
        ]);
    }

    // 4. Створення токена
    $token = $user->createToken('crm-spa')->plainTextToken;

    // Підтягуємо ролі + зв'язки для фронта
    $user->load('doctor.clinic', 'roles');
    $roleNames = $user->getRoleNames();

    // ✅ Стабільний global_role з пріоритетом (а не "first()")
    RoleHierarchy::ensureRolesExist();
    $globalRole = RoleHierarchy::highestRole($roleNames->all()) ?? 'user';

    $user->setAttribute('global_role', $globalRole);

    // 5. Відповідь
    return response()->json([
        'token' => $token,
        'user' => $user,
    ]);
});

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    // видаляємо поточний токен
    $request->user()->currentAccessToken()?->delete();

    return response()->noContent();
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user()->load('doctor.clinic', 'roles');
    $roleNames = $user->getRoleNames();

    // ✅ Стабільний global_role з пріоритетом
    RoleHierarchy::ensureRolesExist();
    $globalRole = RoleHierarchy::highestRole($roleNames->all()) ?? 'user';

    $user->setAttribute('global_role', $globalRole);
    $user->setAttribute('roles', $roleNames);

    return $user;
});

// ---- ПУБЛІЧНИЙ health-check ----

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toDateTimeString(),
    ]);
});

// ---- ЗАХИЩЕНІ CRM-РОУТИ ----

Route::middleware('auth:sanctum')->group(function () {
    Route::get('roles', [RoleController::class, 'index']);
    Route::get('roles/users', [RoleController::class, 'users']);
    Route::put('roles/users/{user}', [RoleController::class, 'updateUserRoles']);

    Route::apiResource('clinics', ClinicController::class);
    Route::get('clinics/{clinic}/working-hours', [ClinicWorkingHoursController::class, 'show']);
    Route::put('clinics/{clinic}/working-hours', [ClinicWorkingHoursController::class, 'update']);
    Route::apiResource('doctors', DoctorController::class);
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('assistants', AssistantController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('rooms', RoomController::class);
    Route::apiResource('equipments', EquipmentController::class);
    Route::apiResource('procedures', ProcedureController::class);

    // Медична картка
    Route::get('patients/{patient}/records', [MedicalRecordController::class, 'index']);
    Route::post('patients/{patient}/records', [MedicalRecordController::class, 'store']);
    Route::get('patients/{patient}/notes', [\App\Http\Controllers\Api\PatientController::class, 'getNotes']);
    Route::post('patients/{patient}/notes', [\App\Http\Controllers\Api\PatientController::class, 'addNote']);

    // Зубна формула
    Route::get('patients/{patient}/dental-map', [MedicalRecordController::class, 'getDentalMap']);
    Route::post('patients/{patient}/dental-map', [MedicalRecordController::class, 'updateToothStatus']);

    Route::get('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'schedule']);
    Route::put('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'updateSchedule']);
    Route::get('doctors/{doctor}/slots', [DoctorScheduleController::class, 'slots']);
    Route::get('doctors/{doctor}/recommended-slots', [DoctorScheduleController::class, 'recommended']);

    Route::get('doctors/{doctor}/weekly-schedule', [DoctorScheduleSettingsController::class, 'show']);
    Route::put('doctors/{doctor}/weekly-schedule', [DoctorScheduleSettingsController::class, 'update']);
    Route::get('doctors/{doctor}/appointments', [AppointmentController::class, 'doctorAppointments']);
    Route::get('doctors/{doctor}/procedures', [DoctorProcedureController::class, 'index']);
    Route::put('doctors/{doctor}/procedures', [DoctorProcedureController::class, 'update']);

    Route::apiResource('calendar-blocks', CalendarBlockController::class);

    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::post('appointments/series', [AppointmentController::class, 'storeSeries']);
    Route::put('appointments/{appointment}', [\App\Http\Controllers\Api\AppointmentController::class, 'update']);
    Route::post('appointments/{appointment}/cancel', [\App\Http\Controllers\Api\AppointmentController::class, 'cancel']);
    Route::get('/appointments', [AppointmentController::class, 'index']);

    Route::get('waitlist', [WaitlistController::class, 'index']);
    Route::post('waitlist', [WaitlistController::class, 'store']);
    Route::get('waitlist/candidates', [WaitlistController::class, 'candidates']);
    Route::post('waitlist/{waitlistEntry}/book', [WaitlistController::class, 'markBooked']);
    Route::post('waitlist/{waitlistEntry}/cancel', [WaitlistController::class, 'cancel']);
    Route::post('waitlist/offers/{token}/claim', [WaitlistController::class, 'claim']);

    Route::get('/me/clinics', function (Request $request) {
        $user = $request->user()->load('clinics');

        $clinics = $user->clinics->map(function ($clinic) {
            return [
                'clinic_id'   => $clinic->id,
                'clinic_name' => $clinic->name,
                'clinic_role' => $clinic->pivot->clinic_role,
            ];
        })->values();

        return response()->json([
            'is_super_admin' => $user->isSuperAdmin(),
            'clinics'        => $clinics,
        ]);
    });
});
