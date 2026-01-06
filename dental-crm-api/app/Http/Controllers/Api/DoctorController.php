<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Clinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use App\Support\QuerySearch;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        $query = Doctor::query()
            ->with([
                'clinic:id,name,city',
                'clinics:id,name',
                'user:id,email',
                'specializations:id,name',
            ]);

        // ✅ Access rules priority:
        // super_admin -> all
        // clinic_admin -> doctors of own clinics
        // doctor -> only self (only if NOT clinic_admin)
        $clinicAdminClinicIds = $authUser->clinics()
            ->wherePivot('clinic_role', 'clinic_admin')
            ->pluck('clinics.id');

        if (!$authUser->hasRole('super_admin')) {
            if ($clinicAdminClinicIds->isNotEmpty()) {
                $query->whereIn('clinic_id', $clinicAdminClinicIds);
            } elseif ($authUser->hasRole('doctor')) {
                $query->where('user_id', $authUser->id);
            }
        }

        // фільтр по клініці (якщо явно передали)
        if ($request->filled('clinic_id')) {
            $clinicId = $request->integer('clinic_id');
            $query->where(function ($q) use ($clinicId) {
                $q->where('clinic_id', $clinicId)
                  ->orWhereHas('clinics', fn($qq) => $qq->where('clinics.id', $clinicId));
            });
        }

        // search filter (case-insensitive)
        if ($search = $request->string('search')->toString()) {
            QuerySearch::applyIlike($query, $search, ['full_name', 'specialization']);
        }

        // пагінація
        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // обмеження 1-100

        return $query
            ->orderBy('full_name')
            ->paginate($perPage);
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
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'room'           => ['nullable', 'string', 'max:255'],
            'admin_contact'  => ['nullable', 'string', 'max:255'],
            'vacation_from'  => ['nullable', 'date'],
            'vacation_to'    => ['nullable', 'date', 'after_or_equal:vacation_from'],
            'address'        => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:255'],
            'state'          => ['nullable', 'string', 'max:255'],
            'zip'            => ['nullable', 'string', 'max:50'],

            // дані акаунта користувача
            'password'       => ['required', 'string', 'min:6'],
            'clinic_ids'     => ['nullable', 'array'],
            'clinic_ids.*'   => ['integer', 'exists:clinics,id'],
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

            $guard = config('auth.defaults.guard', 'web');
            Role::findOrCreate('doctor', $guard);
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
                'phone'         => $data['phone'] ?? null,
                'email'         => $data['email'],
                'room'          => $data['room'] ?? null,
                'admin_contact' => $data['admin_contact'] ?? null,
                'vacation_from' => $data['vacation_from'] ?? null,
                'vacation_to'   => $data['vacation_to'] ?? null,
                'address'       => $data['address'] ?? null,
                'city'          => $data['city'] ?? null,
                'state'         => $data['state'] ?? null,
                'zip'           => $data['zip'] ?? null,
                'bio'           => $data['bio'] ?? null,
                'color'         => $data['color'] ?? '#22c55e',
                'is_active'     => true,
            ]);

            $clinicIds = collect($data['clinic_ids'] ?? [])->filter()->all();
            if ($clinicIds) {
                $doctor->clinics()->sync($clinicIds);
            }

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

        $doctor->load('clinic', 'clinics', 'user', 'specializations:id,name');

        return response()->json($doctor);
    }

    public function update(Request $request, Doctor $doctor)
    {
        $authUser = $request->user();

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
            'phone'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'          => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,' . $doctor->user_id],
            'room'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'admin_contact'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'vacation_from'  => ['sometimes', 'nullable', 'date'],
            'vacation_to'    => ['sometimes', 'nullable', 'date', 'after_or_equal:vacation_from'],
            'address'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'state'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'zip'            => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active'      => ['sometimes', 'boolean'],
            'status'         => ['sometimes', 'string', 'in:active,vacation,inactive'],
            'clinic_ids'     => ['sometimes', 'array'],
            'clinic_ids.*'   => ['integer', 'exists:clinics,id'],
            'specialization_ids' => ['sometimes', 'array'],
            'specialization_ids.*' => ['integer', 'exists:specializations,id'],
        ]);

        $doctor->fill($data);
        $doctor->save();

        if (array_key_exists('full_name', $data) && $doctor->user) {
            $doctor->user->name = $data['full_name'];
            $doctor->user->save();
        }

        if (array_key_exists('email', $data) && $doctor->user && $data['email']) {
            $doctor->user->email = $data['email'];
            $doctor->user->save();
        }

        // sync is_active with status
        if (array_key_exists('status', $data)) {
            $doctor->is_active = $data['status'] === 'active';
            $doctor->save();
        }

        if (array_key_exists('clinic_ids', $data)) {
            $clinicIds = collect($data['clinic_ids'] ?? [])->filter()->all();
            if ($clinicIds) {
                // перша клініка стає primary clinic_id
                $doctor->clinic_id = $clinicIds[0];
                $doctor->save();
                $doctor->clinics()->sync($clinicIds);
            } else {
                $doctor->clinics()->sync([]);
            }
        }

        if (array_key_exists('specialization_ids', $data)) {
            $specIds = collect($data['specialization_ids'] ?? [])->filter()->all();
            $doctor->specializations()->sync($specIds);
        }

        return response()->json($doctor->fresh()->load('clinic', 'clinics', 'specializations:id,name'));
    }

    public function uploadAvatar(Request $request, Doctor $doctor)
    {
        $authUser = $request->user();

        $canEdit =
            $authUser->isSuperAdmin()
            || $authUser->hasClinicRole($doctor->clinic_id, ['clinic_admin'])
            || ($doctor->user_id && $doctor->user_id === $authUser->id);

        if (! $canEdit) {
            abort(403, 'У вас немає права редагувати цього лікаря');
        }

        $validated = $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:4096'],
        ]);

        $file = $validated['avatar'];
        $path = $file->store('doctor-avatars', 'public');

        if ($doctor->avatar_path && Storage::disk('public')->exists($doctor->avatar_path)) {
            Storage::disk('public')->delete($doctor->avatar_path);
        }

        $doctor->avatar_path = $path;
        $doctor->save();

        return response()->json($doctor->fresh()->load('clinic'));
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();

        return response()->noContent();
    }
}
