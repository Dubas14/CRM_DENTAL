<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialization;
use App\Support\QuerySearch;
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess($request->user());

        $query = Specialization::query();

        if ($request->filled('active')) {
            $active = $request->boolean('active');
            $query->where('is_active', $active);
        }

        if ($search = $request->string('search')->toString()) {
            QuerySearch::applyIlike($query, $search, ['name', 'slug']);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $this->authorizeAccess($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:specializations,name'],
        ]);

        $spec = Specialization::create([
            'name' => $data['name'],
            'slug' => null,
            'is_active' => true,
        ]);

        return response()->json($spec, 201);
    }

    public function update(Request $request, Specialization $specialization)
    {
        $this->authorizeAccess($request->user());

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:specializations,name,' . $specialization->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $specialization->update($data);

        return response()->json($specialization);
    }

    public function destroy(Request $request, Specialization $specialization)
    {
        $this->authorizeAccess($request->user());

        // М'яке видалення: просто деактивуємо
        $specialization->update(['is_active' => false]);

        return response()->noContent();
    }

    private function authorizeAccess($user): void
    {
        if (! $user->hasRole('super_admin') && ! $user->hasRole('clinic_admin')) {
            abort(403, 'Немає доступу до спеціалізацій');
        }
    }
}


