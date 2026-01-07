<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
            
            $this->invalidateStatsCache($invoice->clinic_id);

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

    public function replaceItems(Invoice $invoice, array $items): Invoice
    {
        return DB::transaction(function () use ($invoice, $items) {
            $this->assertEditable($invoice);
            
            // Видалити всі існуючі items
            $invoice->items()->delete();
            
            // Створити нові items
            $this->syncItems($invoice, $items, append: false);
            $this->recalculateTotals($invoice);
            
            $this->invalidateStatsCache($invoice->clinic_id);

            return $invoice->fresh(['items', 'payments']);
        });
    }

    public function recalculateTotals(Invoice $invoice): void
    {
        $subtotal = (float) $invoice->items()->sum('total');
        $discountAmount = (float) ($invoice->discount_amount ?? 0);
        
        // Застосувати знижку
        $total = $subtotal - $discountAmount;
        if ($total < 0) {
            $total = 0;
        }
        
        // Сумуємо всі платежі (refund мають від'ємну суму)
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
            
            $this->invalidateStatsCache($invoice->clinic_id);

            return $payment;
        });
    }

    public function assertEditable(Invoice $invoice): void
    {
        // Перевіряємо тільки не-refund платежі
        $hasNonRefundPayments = $invoice->payments()
            ->where('is_refund', false)
            ->exists();
            
        if ($hasNonRefundPayments) {
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

    public function applyDiscount(Invoice $invoice, string $type, float $value): Invoice
    {
        return DB::transaction(function () use ($invoice, $type, $value) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            
            $currentAmount = (float) $invoice->amount;
            $paidAmount = (float) $invoice->paid_amount;
            
            $discountAmount = 0;
            if ($type === 'percent') {
                $discountAmount = $currentAmount * ($value / 100);
            } else {
                $discountAmount = $value;
            }
            
            $newAmount = $currentAmount - $discountAmount;
            
            // Валідація: нова сума не може бути меншою за вже сплачену
            if ($newAmount < $paidAmount) {
                throw ValidationException::withMessages([
                    'discount' => "Знижка неможлива: нова сума ({$this->formatMoney($newAmount)}) менша за сплачену ({$this->formatMoney($paidAmount)})"
                ]);
            }
            
            $invoice->discount_amount = $this->formatMoney($discountAmount);
            $invoice->discount_type = $type;
            $invoice->amount = $this->formatMoney($newAmount);
            $invoice->syncStatusFromTotals();
            $invoice->save();
            
            $this->invalidateStatsCache($invoice->clinic_id);
            
            return $invoice->fresh(['items', 'payments']);
        });
    }

    public function cancel(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            
            // Перевірка: якщо є оплати, скасування заборонено
            if ($invoice->payments()->where('is_refund', false)->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Неможливо скасувати рахунок з наявними оплатами. Спочатку зробіть повернення коштів.'
                ]);
            }
            
            $invoice->status = Invoice::STATUS_CANCELLED;
            $invoice->save();
            
            $this->invalidateStatsCache($invoice->clinic_id);
            
            return $invoice->fresh(['items', 'payments']);
        });
    }

    private function invalidateStatsCache(int $clinicId): void
    {
        Cache::forget("finance_stats:{$clinicId}");
    }

    private function toFloat($value): float
    {
        return (float) number_format((float) $value, 2, '.', '');
    }
}


