<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use App\Models\User;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        $query = Patient::query()
            ->with('clinic:id,name,city');

        if ($authUser->global_role === 'doctor') {
            $doctor = $authUser->doctor;

            if (! $doctor) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'per_page' => 20,
                    'current_page' => 1,
                ]);
            }

            $query->where('clinic_id', $doctor->clinic_id)
                ->where(function ($q) use ($doctor, $authUser) {
                    $q->whereHas('appointments', function ($q) use ($doctor) {
                        $q->where('doctor_id', $doctor->id);
                    })->orWhere('user_id', $authUser->id);
                });
        }


        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->integer('clinic_id'));
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy('full_name')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id'  => ['required', 'exists:clinics,id'],
            'full_name'  => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'email'      => ['nullable', 'email', 'max:255'],
            'address'    => ['nullable', 'string', 'max:255'],
            'note'       => ['nullable', 'string'],
        ]);

        $data['user_id'] = $request->user()->id;

        $patient = Patient::create($data)->load('clinic:id,name,city');

        return response()->json($patient, 201);
    }

    public function show(Request $request, Patient $patient)
    {
        $this->authorizePatient($request->user(), $patient);

        $patient->load([
            'clinic:id,name,city',
            'appointments' => function ($query) {
                $query->with([
                    'doctor:id,full_name,specialization,clinic_id,color',
                    'clinic:id,name,city',
                ])->orderByDesc('start_at');
            },
        ]);

        return $patient;
    }

    public function update(Request $request, Patient $patient)
    {
        $this->authorizePatient($request->user(), $patient);

        $data = $request->validate([
            'clinic_id'  => ['sometimes', 'exists:clinics,id'],
            'full_name'  => ['sometimes', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'      => ['sometimes', 'nullable', 'email', 'max:255'],
            'address'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'note'       => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($data['clinic_id'])
            && $data['clinic_id'] !== $patient->clinic_id
            && ! $request->user()->hasClinicRole($data['clinic_id'], ['clinic_admin'])
            && ! $request->user()->isSuperAdmin()) {
            abort(403, 'У вас немає доступу змінювати клініку пацієнта');
        }
        $patient->update($data);

        return $patient->load('clinic:id,name,city');
    }

    public function destroy(Request $request, Patient $patient)
    {
        $this->authorizePatient($request->user(), $patient);
        $patient->delete();

        return response()->noContent();
    }

    protected function authorizePatient(User $user, Patient $patient): void
    {
        if ($this->canAccessPatient($user, $patient)) {
            return;
        }

        abort(403, 'У вас немає доступу до цього пацієнта');
    }

    protected function canAccessPatient(User $user, Patient $patient): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasClinicRole($patient->clinic_id, ['clinic_admin', 'registrar'])) {
            return true;
        }

        if ($user->global_role === 'doctor') {
            $doctor = $user->doctor;

            if ($doctor && $doctor->clinic_id === $patient->clinic_id) {
                $hasAppointment = $patient->appointments()
                    ->where('doctor_id', $doctor->id)
                    ->exists();

                if ($hasAppointment || $patient->user_id === $user->id) {
                    return true;
                }
            }
        }

        return false;
    }
}
