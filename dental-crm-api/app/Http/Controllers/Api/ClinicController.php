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
            'name'        => ['required', 'string', 'max:255'],
            'legal_name'  => ['nullable', 'string', 'max:255'],
            'address'     => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:255'],
            'country'     => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'lat'         => ['nullable', 'numeric'],
            'lng'         => ['nullable', 'numeric'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'email'       => ['nullable', 'email', 'max:255'],
            'website'     => ['nullable', 'string', 'max:255'],
            'is_active'   => ['boolean'],
        ]);

        $clinic = Clinic::create($data);

        return response()->json($clinic, 201);
    }

    public function show(Clinic $clinic)
    {
        return $clinic;
    }

    public function update(Request $request, Clinic $clinic)
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'legal_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'address'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'country'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'lat'         => ['sometimes', 'nullable', 'numeric'],
            'lng'         => ['sometimes', 'nullable', 'numeric'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'       => ['sometimes', 'nullable', 'email', 'max:255'],
            'website'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $clinic->update($data);

        return $clinic;
    }

    public function destroy(Clinic $clinic)
    {
        $clinic->delete();

        return response()->noContent();
    }
}
