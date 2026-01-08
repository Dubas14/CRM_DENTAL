<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Doctor Scope: лікар бачить тільки рахунки своїх прийомів
        $query = Invoice::query()->with(['items', 'payments', 'appointment.doctor', 'patient', 'clinic']);

        if ($user->hasRole('doctor') && ! $user->isSuperAdmin()) {
            $doctorId = $user->doctor?->id;
            if ($doctorId) {
                $query->whereHas('appointment', fn ($q) => $q->where('doctor_id', $doctorId));
            } else {
                // Якщо у користувача немає doctor_id, повертаємо порожній список
                return response()->json(['data' => [], 'total' => 0]);
            }
        } else {
            // Для super_admin та clinic_admin - фільтр по клініці
            if ($request->filled('clinic_id')) {
                $query->where('clinic_id', $request->integer('clinic_id'));
            } elseif (! $user->isSuperAdmin()) {
                // Якщо не super_admin, фільтруємо по клініці користувача
                $userClinicIds = $user->clinics()->pluck('clinics.id')->toArray();
                if (! empty($userClinicIds)) {
                    $query->whereIn('clinic_id', $userClinicIds);
                } else {
                    return response()->json(['data' => [], 'total' => 0]);
                }
            }
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        // Фільтри по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Фільтри по даті
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        // Doctor Scope: перевірка доступу
        if ($user->hasRole('doctor') && ! $user->isSuperAdmin()) {
            $doctorId = $user->doctor?->id;
            if ($doctorId && $invoice->appointment && $invoice->appointment->doctor_id !== $doctorId) {
                abort(403, 'Немає доступу до цього рахунку');
            }
        } else {
            $this->assertClinicAccess($user, $invoice->clinic_id);
        }

        return $invoice->load(['items', 'payments', 'appointment.doctor', 'patient', 'clinic']);
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

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        $this->assertClinicAccess($request->user(), $invoice->clinic_id);

        // Редагування дозволено тільки якщо немає оплат
        if ($invoice->payments()->where('is_refund', false)->exists()) {
            abort(422, 'Неможливо редагувати рахунок з наявними оплатами');
        }

        $invoice->update($data);

        return response()->json($invoice->fresh(['items', 'payments']));
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

    public function replaceItems(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.procedure_id' => ['nullable', 'exists:procedures,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->assertClinicAccess($request->user(), $invoice->clinic_id);

        // Редагування дозволено тільки якщо немає оплат
        if ($invoice->payments()->where('is_refund', false)->exists()) {
            abort(422, 'Неможливо редагувати рахунок з наявними оплатами');
        }

        $invoice = $this->invoiceService->replaceItems($invoice, $data['items']);

        return response()->json($invoice);
    }

    public function applyDiscount(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        $this->assertClinicAccess($request->user(), $invoice->clinic_id);

        $invoice = $this->invoiceService->applyDiscount($invoice, $data['type'], $data['value']);

        return response()->json($invoice);
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->assertClinicAccess($request->user(), $invoice->clinic_id);

        $invoice = $this->invoiceService->cancel($invoice);

        return response()->json($invoice);
    }

    public function downloadPDF(Request $request, Invoice $invoice)
    {
        // Перевіряємо, чи встановлена бібліотека dompdf
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'PDF бібліотека не встановлена. Виконайте: composer require barryvdh/laravel-dompdf');
        }

        $user = $request->user();

        // Doctor Scope: перевірка доступу
        if ($user->hasRole('doctor') && ! $user->isSuperAdmin()) {
            $doctorId = $user->doctor?->id;
            if ($doctorId && $invoice->appointment && $invoice->appointment->doctor_id !== $doctorId) {
                abort(403, 'Немає доступу до цього рахунку');
            }
        } else {
            $this->assertClinicAccess($user, $invoice->clinic_id);
        }

        // Завантажуємо всі необхідні зв'язки
        $invoice->load(['items', 'patient', 'clinic']);

        // Переконуємося, що requisites - це масив
        if ($invoice->clinic && $invoice->clinic->requisites) {
            if (is_string($invoice->clinic->requisites)) {
                $invoice->clinic->requisites = json_decode($invoice->clinic->requisites, true) ?: [];
            }
        } else {
            $invoice->clinic->requisites = [];
        }

        // Використовуємо facade динамічно
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-pdf', [
            'invoice' => $invoice,
            'clinic' => $invoice->clinic,
            'patient' => $invoice->patient,
        ])->setPaper('a4', 'portrait')
          ->setOption('enable-local-file-access', true)
          ->setOption('isHtml5ParserEnabled', true)
          ->setOption('defaultFont', 'DejaVu Sans');

        $filename = 'invoice_' . $invoice->invoice_number . '_' . date('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }
}
