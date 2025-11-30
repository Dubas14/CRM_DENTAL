<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

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
                ->whereHas('appointments', function ($q) use ($doctor) {
                    $q->where('doctor_id', $doctor->id);
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

        $patient = Patient::create($data)->load('clinic:id,name,city');

        return response()->json($patient, 201);
    }

    public function show(Patient $patient)
    {
        $patient->load('clinic:id,name,city');
        return $patient;
    }

    public function update(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'clinic_id'  => ['sometimes', 'exists:clinics,id'],
            'full_name'  => ['sometimes', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'      => ['sometimes', 'nullable', 'email', 'max:255'],
            'address'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'note'       => ['sometimes', 'nullable', 'string'],
        ]);

        $patient->update($data);

        return $patient->load('clinic:id,name,city');
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();

        return response()->noContent();
    }
}
