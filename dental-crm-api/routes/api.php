<?php

use App\Http\Controllers\Api\DoctorScheduleController;
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
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        throw ValidationException::withMessages([
            'email' => 'Невірний email або пароль.',
        ]);
    }

    // створюємо персональний токен Sanctum
    $token = $user->createToken('crm-spa')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user->load('doctor.clinic'),
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
