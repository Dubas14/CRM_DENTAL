<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class InvoiceService
{
    public function create(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'clinic_id' => $data['clinic_id'],
                'patient_id' => $data['patient_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? $this->generateInvoiceNumber(),
                'amount' => 0,
                'paid_amount' => 0,
                'status' => Invoice::STATUS_UNPAID,
                'is_prepayment' => (bool) ($data['is_prepayment'] ?? false),
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items'] ?? []);
            $this->recalculateTotals($invoice);

            return $invoice->fresh(['items', 'payments']);
        });
    }

    public function addItems(Invoice $invoice, array $items): Invoice
    {
        return DB::transaction(function () use ($invoice, $items) {
            $this->assertEditable($invoice);
            $this->syncItems($invoice, $items, append: true);
            $this->recalculateTotals($invoice);

            return $invoice->fresh(['items', 'payments']);
        });
    }

    public function recalculateTotals(Invoice $invoice): void
    {
        $total = (float) $invoice->items()->sum('total');
        $paid = (float) $invoice->payments()->sum('amount');

        $invoice->amount = $this->formatMoney($total);
        $invoice->paid_amount = $this->formatMoney($paid);
        $invoice->syncStatusFromTotals();
        $invoice->save();
    }

    public function addPayment(Invoice $invoice, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            $amount = $this->toFloat($data['amount']);
            $debt = max(0, $this->toFloat($invoice->amount) - $this->toFloat($invoice->paid_amount));

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Сума оплати має бути більшою за 0']);
            }

            if ($amount - $debt > 1e-6) {
                throw ValidationException::withMessages(['amount' => 'Сума оплати перевищує борг по інвойсу']);
            }

            $payment = Payment::create([
                'clinic_id' => $invoice->clinic_id,
                'invoice_id' => $invoice->id,
                'amount' => $this->formatMoney($amount),
                'method' => $data['method'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->recalculateTotals($invoice);

            return $payment;
        });
    }

    public function assertEditable(Invoice $invoice): void
    {
        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Інвойс уже має оплати. Редагування позицій заборонено.',
            ]);
        }
    }

    private function syncItems(Invoice $invoice, array $items, bool $append = false): void
    {
        if (! $append) {
            $invoice->items()->delete();
        }

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = $this->toFloat($item['price'] ?? 0);
            $total = $this->formatMoney($price * $quantity);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'procedure_id' => $item['procedure_id'] ?? null,
                'name' => $item['name'] ?? 'Послуга',
                'quantity' => $quantity,
                'price' => $this->formatMoney($price),
                'total' => $total,
            ]);
        }
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
    }

    private function formatMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function toFloat($value): float
    {
        return (float) number_format((float) $value, 2, '.', '');
    }
}


