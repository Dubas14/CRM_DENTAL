<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(private InvoiceService $invoiceService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Payment::query()->with(['invoice', 'creator']);

        // Doctor Scope
        if ($user->hasRole('doctor') && !$user->isSuperAdmin()) {
            $doctorId = $user->doctor?->id;
            if ($doctorId) {
                $query->whereHas('invoice.appointment', fn($q) => $q->where('doctor_id', $doctorId));
            } else {
                return response()->json(['data' => [], 'total' => 0]);
            }
        } else {
            if ($request->filled('clinic_id')) {
                $query->where('clinic_id', $request->integer('clinic_id'));
            } elseif (!$user->isSuperAdmin()) {
                $userClinicIds = $user->clinics()->pluck('clinics.id')->toArray();
                if (!empty($userClinicIds)) {
                    $query->whereIn('clinic_id', $userClinicIds);
                } else {
                    return response()->json(['data' => [], 'total' => 0]);
                }
            }
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        if ($request->filled('is_refund')) {
            $query->where('is_refund', $request->boolean('is_refund'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function store(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        $this->assertClinicAccess($request->user(), $invoice->clinic_id);

        $payment = $this->invoiceService->addPayment($invoice, $data, $request->user());

        $invoice->refresh();

        return response()->json([
            'payment' => $payment,
            'invoice' => $invoice->load(['items', 'payments']),
        ], 201);
    }

    public function refund(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->assertClinicAccess($request->user(), $payment->clinic_id);

        // Перевірка: не можна повернути вже повернений платіж
        if ($payment->is_refund) {
            abort(422, 'Цей платіж вже було повернено');
        }

        // Створити refund запис
        $refund = Payment::create([
            'clinic_id' => $payment->clinic_id,
            'invoice_id' => $payment->invoice_id,
            'amount' => -abs((float) $payment->amount), // Від'ємна сума
            'method' => $payment->method,
            'transaction_id' => $payment->transaction_id ? 'REFUND-' . $payment->transaction_id : null,
            'created_by' => $request->user()->id,
            'is_refund' => true,
            'refund_reason' => $data['reason'],
            'original_payment_id' => $payment->id,
            'refunded_by' => $request->user()->id,
            'refunded_at' => now(),
        ]);

        // Перерахувати totals інвойсу
        $invoice = $payment->invoice;
        $this->invoiceService->recalculateTotals($invoice);
        
        // Інвалідувати кеш статистики
        Cache::forget("finance_stats:{$invoice->clinic_id}");

        return response()->json([
            'refund' => $refund,
            'invoice' => $invoice->fresh(['items', 'payments']),
        ], 201);
    }

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }
}


