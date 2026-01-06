<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(private InvoiceService $invoiceService)
    {
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

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }
}


