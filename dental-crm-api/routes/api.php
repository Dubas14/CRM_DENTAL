<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\BookingSuggestionController;
use App\Http\Controllers\Api\CalendarBlockController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\ClinicWorkingHoursController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorProcedureController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\DoctorScheduleSettingsController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientFileController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\UserAvatarController;
use App\Http\Controllers\Api\UserPasswordController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

// ---- CORS PREFLIGHT (OPTIONS) ----
// Деякі браузери/проксі можуть не пропускати OPTIONS коректно, особливо для PUT/PATCH з Authorization.
// Тому додаємо явну відповідь 204 на будь-який OPTIONS /api/* запит.
Route::options('/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

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
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $user->load('doctor.clinic', 'roles');
    // Беремо ролі напряму з рілейшена, щоб не впливали guard-кеші
    $roleNames = $user->roles()->pluck('name');
    $permissions = $user->getAllPermissions()->pluck('name');

    // Скидаємо кеш прав перед обчисленням
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // ✅ Стабільний global_role з пріоритетом (а не "first()")
    RoleHierarchy::ensureRolesExist();
    $globalRole = RoleHierarchy::highestRole($roleNames->all()) ?? 'user';

    $user->setAttribute('global_role', $globalRole);
    $user->setAttribute('permissions', $permissions);

    // 5. Відповідь
    return response()->json([
        'token' => $token,
        'user' => new UserResource($user),
    ]);
})->middleware('throttle:auth');

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    // видаляємо поточний токен
    $request->user()->currentAccessToken()?->delete();

    return response()->noContent();
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $request->user()->load('doctor.clinic', 'roles');
    // Беремо ролі напряму з рілейшена, щоб не впливали guard-кеші
    $roleNames = $user->roles()->pluck('name');
    $permissions = $user->getAllPermissions()->pluck('name');

    // ✅ Стабільний global_role з пріоритетом
    RoleHierarchy::ensureRolesExist();
    $globalRole = RoleHierarchy::highestRole($roleNames->all()) ?? 'user';

    $user->setAttribute('global_role', $globalRole);
    $user->setAttribute('permissions', $permissions);
    $user->setAttribute('roles', $roleNames);

    return new UserResource($user);
});

// ---- ПУБЛІЧНИЙ health-check ----

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toDateTimeString(),
    ]);
});

// ---- ПУБЛІЧНІ ROUTES ДЛЯ ПАЦІЄНТІВ ----

// Підтвердження запису через токен (без авторизації)
Route::post('/appointments/confirm/{token}', [AppointmentController::class, 'confirm']);

// Перевірка передоплати (потребує авторизації)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/appointments/{appointment}/check-prepayment', [AppointmentController::class, 'checkPrepayment']);
});

// ---- ЗАХИЩЕНІ CRM-РОУТИ ----

Route::middleware('auth:sanctum')->group(function () {
    Route::get('roles', [RoleController::class, 'index']);
    Route::get('roles/list', [RoleController::class, 'listRoles']); // All roles with permissions (for RoleManager)
    Route::post('roles', [RoleController::class, 'storeRole']); // Create role
    Route::put('roles/{role}', [RoleController::class, 'updateRole']); // Update role
    Route::get('roles/users', [RoleController::class, 'users']);
    Route::put('roles/users/{user}', [RoleController::class, 'updateUserRoles']);
    Route::post('users/{user}/assign-role', [RoleController::class, 'assignRole']); // Assign single role

    Route::apiResource('clinics', ClinicController::class);
    Route::get('clinics/{clinic}/working-hours', [ClinicWorkingHoursController::class, 'show']);
    Route::put('clinics/{clinic}/working-hours', [ClinicWorkingHoursController::class, 'update']);
    Route::apiResource('doctors', DoctorController::class);
    Route::post('doctors/{doctor}/avatar', [DoctorController::class, 'uploadAvatar']);
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('assistants', AssistantController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('rooms', RoomController::class);
    Route::apiResource('equipments', EquipmentController::class);
    Route::apiResource('procedures', ProcedureController::class);
    Route::apiResource('specializations', SpecializationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'store', 'update']);
    Route::post('invoices/{invoice}/items', [InvoiceController::class, 'addItems']);
    Route::put('invoices/{invoice}/items', [InvoiceController::class, 'replaceItems']);
    Route::post('invoices/{invoice}/discount', [InvoiceController::class, 'applyDiscount']);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store']);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);
    Route::get('finance/stats', [\App\Http\Controllers\Api\FinanceStatsController::class, 'stats']);
    Route::post('finance/stats/invalidate', [\App\Http\Controllers\Api\FinanceStatsController::class, 'invalidateCache']);
    Route::apiResource('inventory-items', InventoryItemController::class);
    Route::apiResource('inventory-transactions', InventoryTransactionController::class)->only(['index', 'store']);

    // Медична картка
    Route::get('patients/{patient}/records', [MedicalRecordController::class, 'index']);
    Route::post('patients/{patient}/records', [MedicalRecordController::class, 'store']);
    Route::get('patients/{patient}/notes', [\App\Http\Controllers\Api\PatientController::class, 'getNotes']);
    Route::post('patients/{patient}/notes', [\App\Http\Controllers\Api\PatientController::class, 'addNote']);
    Route::get('patients/{patient}/files', [PatientFileController::class, 'index']);
    Route::post('patients/{patient}/files', [PatientFileController::class, 'store']);
    Route::delete('patients/{patient}/files/{patientFile}', [PatientFileController::class, 'destroy']);

    // Зубна формула
    Route::get('patients/{patient}/dental-map', [MedicalRecordController::class, 'getDentalMap']);
    Route::post('patients/{patient}/dental-map', [MedicalRecordController::class, 'updateToothStatus']);

    Route::get('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'schedule']);
    Route::put('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'updateSchedule']);
    Route::get('doctors/{doctor}/slots', [DoctorScheduleController::class, 'slots']);
    Route::get('doctors/{doctor}/recommended-slots', [DoctorScheduleController::class, 'recommended']);
    Route::get('booking-suggestions', [BookingSuggestionController::class, 'index']);

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
    Route::post('appointments/{appointment}/finish', [\App\Http\Controllers\Api\AppointmentController::class, 'finish']);
    Route::get('appointments/{appointment}/check-prepayment', [AppointmentController::class, 'checkPrepayment']);
    Route::get('/appointments', [AppointmentController::class, 'index']);

    Route::get('waitlist', [WaitlistController::class, 'index']);
    Route::post('waitlist', [WaitlistController::class, 'store']);
    Route::get('waitlist/candidates', [WaitlistController::class, 'candidates']);
    Route::post('waitlist/{waitlistEntry}/book', [WaitlistController::class, 'markBooked']);
    Route::post('waitlist/{waitlistEntry}/cancel', [WaitlistController::class, 'cancel']);
    Route::post('waitlist/offers/{token}/claim', [WaitlistController::class, 'claim']);

    Route::get('/me/clinics', function (Request $request) {
        $user = $request->user()->load('clinics', 'doctor.clinics');

        // Для лікарів - беремо ТІЛЬКИ клініки з doctor_clinic
        if ($user->doctor && $user->doctor->clinics->isNotEmpty()) {
            $clinics = $user->doctor->clinics->map(function ($clinic) {
                return [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->name,
                    'clinic_role' => 'doctor',
                ];
            })->values();

            return response()->json([
                'is_super_admin' => $user->isSuperAdmin(),
                'clinics' => $clinics,
            ]);
        }

        // Для не-лікарів (адміни, реєстратори) - беремо з clinic_user
        $clinics = $user->clinics->map(function ($clinic) {
            return [
                'clinic_id' => $clinic->id,
                'clinic_name' => $clinic->name,
                'clinic_role' => $clinic->pivot->clinic_role ?? 'member',
            ];
        })->values();

        return response()->json([
            'is_super_admin' => $user->isSuperAdmin(),
            'clinics' => $clinics,
        ]);
    });

    // Analytics endpoints
    Route::prefix('analytics')->group(function () {
        Route::get('doctors/load', [AnalyticsController::class, 'doctorsLoad']);
        Route::get('rooms/load', [AnalyticsController::class, 'roomsLoad']);
        Route::get('procedures/popular', [AnalyticsController::class, 'popularProcedures']);
        Route::get('appointments/conversion', [AnalyticsController::class, 'conversion']);
        Route::get('appointments/no-show', [AnalyticsController::class, 'noShowRate']);
    });

    // Reports endpoints
    Route::get('reports/appointments', [ReportsController::class, 'appointments']);

    // Update own avatar
    Route::post('user/avatar', UserAvatarController::class);
    // Update own password
    Route::post('user/password', [UserPasswordController::class, 'update']);
});
