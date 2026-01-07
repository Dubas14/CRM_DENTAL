<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PatientFileController extends Controller
{
    public function index(Patient $patient)
    {
        return $patient->files()->with('uploader:id,name')->orderByDesc('created_at')->get();
    }

    public function store(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'file_type' => ['required', Rule::in(['xray', 'photo', 'contract', 'anamnesis'])],
        ]);

        $path = $data['file']->store('patient-files', 'public');

        $file = PatientFile::create([
            'patient_id' => $patient->id,
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'file_name' => $data['file']->getClientOriginalName(),
            'file_type' => $data['file_type'],
        ]);

        return response()->json($file, 201);
    }

    public function destroy(Patient $patient, PatientFile $patientFile)
    {
        if ($patientFile->patient_id !== $patient->id) {
            abort(404);
        }

        Storage::disk('public')->delete($patientFile->file_path);
        $patientFile->delete();

        return response()->noContent();
    }
}
