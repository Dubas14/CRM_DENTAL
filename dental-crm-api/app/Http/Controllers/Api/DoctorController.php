<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Clinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        $query = Doctor::query()
            ->with('clinic:id,name,city');

        if ($authUser->hasRole('doctor')) {
            $query->where('user_id', $authUser->id);
        }

        // фільтр по клініці (на майбутнє)
        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->integer('clinic_id'));
        }

        return $query
            ->orderBy('full_name')
            ->get();
    }

    public function store(Request $request)
    {
        $authUser = $request->user();

        $data = $request->validate([
            'clinic_id'      => ['required', 'exists:clinics,id'],
            'full_name'      => ['required', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio'            => ['nullable', 'string'],
            'color'          => ['nullable', 'string', 'max:20'],

            // дані акаунта користувача
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:6'],
        ]);

        // перевірка прав: супер адмін або адмін цієї клініки
        if (! $authUser->isSuperAdmin() && ! $authUser->hasClinicRole($data['clinic_id'], ['clinic_admin'])) {
            abort(403, 'У вас немає права створювати лікарів для цієї клініки');
        }

        $doctor = DB::transaction(function () use ($data) {
            // 1) створюємо юзера
            $user = User::create([
                'name'        => $data['full_name'],
                'email'       => $data['email'],
                'password'    => Hash::make($data['password']),
            ]);
            Role::findOrCreate('doctor');
            $user->assignRole('doctor');

            // 2) привʼязуємо до клініки як лікаря
            $clinic = Clinic::findOrFail($data['clinic_id']);
            $clinic->users()->syncWithoutDetaching([
                $user->id => ['clinic_role' => 'doctor'],
            ]);

            // 3) створюємо профіль лікаря
            $doctor = Doctor::create([
                'user_id'       => $user->id,
                'clinic_id'     => $data['clinic_id'],
                'full_name'     => $data['full_name'],
                'specialization'=> $data['specialization'] ?? null,
                'bio'           => $data['bio'] ?? null,
                'color'         => $data['color'] ?? '#22c55e',
                'is_active'     => true,
            ]);

            return $doctor->load('clinic');
        });

        return response()->json($doctor, 201);
    }

    public function show(Doctor $doctor)
    {
        $authUser = request()->user();

        // доступ: супер адмін, адмін клініки, сам лікар
        $canView =
            $authUser->isSuperAdmin()
            || $authUser->hasClinicRole($doctor->clinic_id, ['clinic_admin'])
            || ($doctor->user_id && $doctor->user_id === $authUser->id);

        if (! $canView) {
            abort(403, 'У вас немає права переглядати цього лікаря');
        }

        $doctor->load('clinic', 'user');

        return response()->json($doctor);
    }

    public function update(Request $request, Doctor $doctor)
    {
        $authUser = $request->user();

        // хто може редагувати:
        // - супер адмін
        // - адмін клініки, де працює лікар
        // - сам лікар (user_id збігається)
        $canEdit =
            $authUser->isSuperAdmin()
            || $authUser->hasClinicRole($doctor->clinic_id, ['clinic_admin'])
            || ($doctor->user_id && $doctor->user_id === $authUser->id);

        if (! $canEdit) {
            abort(403, 'У вас немає права редагувати цього лікаря');
        }

        $data = $request->validate([
            'full_name'      => ['sometimes', 'string', 'max:255'],
            'specialization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio'            => ['sometimes', 'nullable', 'string'],
            'color'          => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active'      => ['sometimes', 'boolean'],
        ]);

        $doctor->fill($data);
        $doctor->save();

        // опціонально: оновити імʼя користувача, якщо змінили full_name
        if (array_key_exists('full_name', $data) && $doctor->user) {
            $doctor->user->name = $data['full_name'];
            $doctor->user->save();
        }

        return response()->json($doctor->fresh()->load('clinic'));
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();

        return response()->noContent();
    }
}
