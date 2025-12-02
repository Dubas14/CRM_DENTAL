<?php

use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\MedicalRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Controllers\Api\DoctorScheduleSettingsController;

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
    $user->load('doctor.clinic');
    // 🔥 МАГІЯ ТУТ:
    // Ми штучно додаємо поле 'global_role', яке так чекає ваш Фронтенд.
    // Якщо в базі is_admin = true, ми кажемо фронту, що це 'super_admin'.
    // В усіх інших випадках — поки що кажемо 'doctor' (або 'user').
    $user->setAttribute('global_role', $user->is_admin ? 'super_admin' : 'doctor');

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
    return $request->user()->load('doctor.clinic');
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
    Route::apiResource('clinics', ClinicController::class);
    Route::apiResource('doctors', DoctorController::class);
    Route::apiResource('patients', PatientController::class);

    // Медична картка
    Route::get('patients/{patient}/records', [MedicalRecordController::class, 'index']);
    Route::post('patients/{patient}/records', [MedicalRecordController::class, 'store']);
    Route::get('patients/{patient}/notes', [\App\Http\Controllers\Api\PatientController::class, 'getNotes']);
    Route::post('patients/{patient}/notes', [\App\Http\Controllers\Api\PatientController::class, 'addNote']);

    // Зубна формула
    Route::get('patients/{patient}/dental-map', [MedicalRecordController::class, 'getDentalMap']);
    Route::post('patients/{patient}/dental-map', [MedicalRecordController::class, 'updateToothStatus']);

    Route::get('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'schedule']);
    Route::get('doctors/{doctor}/slots', [DoctorScheduleController::class, 'slots']);

    Route::get('doctors/{doctor}/weekly-schedule', [DoctorScheduleSettingsController::class, 'show']);
    Route::put('doctors/{doctor}/weekly-schedule', [DoctorScheduleSettingsController::class, 'update']);
    Route::get('doctors/{doctor}/appointments', [AppointmentController::class, 'doctorAppointments']);


    Route::post('appointments', [AppointmentController::class, 'store']);
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
