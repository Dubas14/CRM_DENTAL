<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService)
    {
    }

    public function index(Request $request)
    {
        $query = Invoice::query()->with(['items', 'payments']);

        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->integer('clinic_id'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function show(Invoice $invoice)
    {
        return $invoice->load(['items', 'payments']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'patient_id' => ['required', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:invoices,invoice_number'],
            'is_prepayment' => ['boolean'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.procedure_id' => ['nullable', 'exists:procedures,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->assertClinicAccess($request->user(), $data['clinic_id']);

        $invoice = $this->invoiceService->create($data, $request->user());

        return response()->json($invoice, 201);
    }

    public function addItems(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.procedure_id' => ['nullable', 'exists:procedures,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->assertClinicAccess($request->user(), $invoice->clinic_id);

        $invoice = $this->invoiceService->addItems($invoice, $data['items']);

        return response()->json($invoice);
    }

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }
}


