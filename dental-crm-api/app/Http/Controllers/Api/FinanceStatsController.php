<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinanceStatsController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $clinicId = $request->integer('clinic_id');

        // Перевірка доступу до клініки
        if (! $user->isSuperAdmin()) {
            if (! $user->hasClinicRole($clinicId, ['clinic_admin', 'receptionist'])) {
                abort(403, 'Немає доступу до статистики цієї клініки');
            }
        }

        $cacheKey = "finance_stats:{$clinicId}";
        $cacheTtl = now()->addMinutes(5); // Shorter cache for more accurate stats

        return Cache::remember($cacheKey, $cacheTtl, function () use ($clinicId) {
            // Загальний борг (unpaid + partially_paid)
            $totalDebt = Invoice::where('clinic_id', $clinicId)
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
                ->selectRaw('SUM(amount - paid_amount) as total')
                ->value('total') ?? 0;

            // Оплати сьогодні
            $paidToday = Payment::where('clinic_id', $clinicId)
                ->where('is_refund', false)
                ->whereDate('created_at', today())
                ->sum('amount') ?? 0;

            // Оплати за тиждень
            $paidThisWeek = Payment::where('clinic_id', $clinicId)
                ->where('is_refund', false)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('amount') ?? 0;

            // Оплати за місяць
            $paidThisMonth = Payment::where('clinic_id', $clinicId)
                ->where('is_refund', false)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount') ?? 0;

            // Кількість неоплачених рахунків
            $unpaidCount = Invoice::where('clinic_id', $clinicId)
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
                ->count();

            // Топ боржників (пацієнти з найбільшим боргом)
            $topDebtors = Invoice::where('clinic_id', $clinicId)
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
                ->select('patient_id', DB::raw('SUM(amount - paid_amount) as total_debt'))
                ->groupBy('patient_id')
                ->orderByDesc('total_debt')
                ->limit(10)
                ->with('patient:id,full_name')
                ->get()
                ->map(function ($item) {
                    return [
                        'patient_id' => $item->patient_id,
                        'patient_name' => $item->patient?->full_name ?? 'Невідомий',
                        'debt' => (float) $item->total_debt,
                    ];
                });

            // Статистика за минулий місяць (для trend)
            $paidLastMonth = Payment::where('clinic_id', $clinicId)
                ->where('is_refund', false)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount') ?? 0;

            $monthTrend = $paidLastMonth > 0
                ? round((($paidThisMonth - $paidLastMonth) / $paidLastMonth) * 100, 1)
                : 0;

            return [
                'total_debt' => (float) $totalDebt,
                'paid_today' => (float) $paidToday,
                'paid_this_week' => (float) $paidThisWeek,
                'paid_this_month' => (float) $paidThisMonth,
                'paid_last_month' => (float) $paidLastMonth,
                'month_trend' => $monthTrend,
                'unpaid_invoices_count' => $unpaidCount,
                'top_debtors' => $topDebtors,
            ];
        });
    }

    public function invalidateCache(Request $request)
    {
        $user = $request->user();
        $clinicId = $request->integer('clinic_id');

        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу');
        }

        Cache::forget("finance_stats:{$clinicId}");

        return response()->json(['message' => 'Кеш статистики очищено']);
    }
}
