<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 50);
        $perPage = min(max($perPage, 1), 100);

        return Clinic::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            // New fields
            'logo_url' => ['nullable', 'string', 'max:500'],
            'phone_main' => ['nullable', 'string', 'max:50'],
            'email_public' => ['nullable', 'email', 'max:255'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_building' => ['nullable', 'string', 'max:50'],
            'slogan' => ['nullable', 'string', 'max:500'],
            'currency_code' => ['nullable', 'string', 'in:UAH,USD,EUR'],
            'requisites' => ['nullable', 'array'],
            'requisites.legal_name' => ['nullable', 'string', 'max:255'],
            'requisites.tax_id' => ['nullable', 'string', 'max:50'],
            'requisites.iban' => ['nullable', 'string', 'regex:/^UA\d{27}$/'],
            'requisites.bank_name' => ['nullable', 'string', 'max:255'],
            'requisites.mfo' => ['nullable', 'string', 'max:20'],
        ]);

        $clinic = Clinic::create($data);

        return response()->json($clinic, 201);
    }

    public function show(Request $request, Clinic $clinic)
    {
        $this->assertClinicAccess($request->user(), $clinic->id);
        return $clinic;
    }

    public function update(Request $request, Clinic $clinic)
    {
        $this->assertClinicAccess($request->user(), $clinic->id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'lat' => ['sometimes', 'nullable', 'numeric'],
            'lng' => ['sometimes', 'nullable', 'numeric'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            // New fields
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone_main' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email_public' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address_street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_building' => ['sometimes', 'nullable', 'string', 'max:50'],
            'slogan' => ['sometimes', 'nullable', 'string', 'max:500'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'in:UAH,USD,EUR'],
            'requisites' => ['sometimes', 'nullable', 'array'],
            'requisites.legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requisites.tax_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'requisites.iban' => ['sometimes', 'nullable', 'string', 'regex:/^UA\d{27}$/'],
            'requisites.bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requisites.mfo' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $clinic->update($data);

        return $clinic;
    }

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }

    public function destroy(Clinic $clinic)
    {
        $clinic->delete();

        return response()->noContent();
    }
}
