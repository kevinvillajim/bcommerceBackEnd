<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository
{
    /**
     * ✅ Obtiene todas las facturas con paginación y filtros
     */
    public function getAllWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::with(['user', 'order'])
            ->orderBy('created_at', 'desc');

        // ✅ Filtro por estado
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // ✅ Filtro por rango de fechas
        if (! empty($filters['date_from'])) {
            $query->whereDate('issue_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('issue_date', '<=', $filters['date_to']);
        }

        // ✅ Filtro por identificación de cliente
        if (! empty($filters['customer_identification'])) {
            $query->where('customer_identification', 'like', '%'.$filters['customer_identification'].'%');
        }

        // ✅ Filtro por nombre de cliente
        if (! empty($filters['customer_name'])) {
            $query->where('customer_name', 'like', '%'.$filters['customer_name'].'%');
        }

        // ✅ Filtro por número de factura
        if (! empty($filters['invoice_number'])) {
            $query->where('invoice_number', 'like', '%'.$filters['invoice_number'].'%');
        }

        // ✅ Filtro por rango de montos
        if (! empty($filters['amount_from'])) {
            $query->where('total_amount', '>=', $filters['amount_from']);
        }

        if (! empty($filters['amount_to'])) {
            $query->where('total_amount', '<=', $filters['amount_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * ✅ Obtiene facturas que pueden ser reintentadas
     */
    public function getRetryableInvoices(): Collection
    {
        return Invoice::retryable()->get();
    }

    /**
     * ✅ Obtiene facturas definitivamente fallidas
     */
    public function getDefinitivelyFailedInvoices(): Collection
    {
        return Invoice::definitivelyFailed()->get();
    }

    /**
     * ✅ Obtiene estadísticas de facturas
     */
    public function getStatistics(): array
    {
        $stats = [
            'total' => Invoice::count(),
            'authorized' => Invoice::where('status', Invoice::STATUS_AUTHORIZED)->count(),
            'pending' => Invoice::where('status', Invoice::STATUS_SENT_TO_SRI)->count(),
            'failed' => Invoice::where('status', Invoice::STATUS_FAILED)->count(),
            'definitively_failed' => Invoice::where('status', Invoice::STATUS_DEFINITIVELY_FAILED)->count(),
            'draft' => Invoice::where('status', Invoice::STATUS_DRAFT)->count(),
        ];

        // ✅ Calcular tasa de éxito
        $stats['success_rate'] = $stats['total'] > 0
            ? round(($stats['authorized'] / $stats['total']) * 100, 2)
            : 0;

        // ✅ Totales monetarios por estado
        $stats['total_amounts'] = [
            'authorized' => Invoice::where('status', Invoice::STATUS_AUTHORIZED)->sum('total_amount'),
            'pending' => Invoice::where('status', Invoice::STATUS_SENT_TO_SRI)->sum('total_amount'),
            'failed' => Invoice::where('status', Invoice::STATUS_FAILED)->sum('total_amount'),
            'definitively_failed' => Invoice::where('status', Invoice::STATUS_DEFINITIVELY_FAILED)->sum('total_amount'),
        ];

        return $stats;
    }

    /**
     * ✅ Encuentra facturas por usuario
     */
    public function findByUser(int $userId): Collection
    {
        return Invoice::where('user_id', $userId)
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * ✅ Encuentra factura por orden
     */
    public function findByOrder(int $orderId): ?Invoice
    {
        return Invoice::where('order_id', $orderId)
            ->with(['items.product', 'user'])
            ->first();
    }

    /**
     * ✅ Encuentra facturas por rango de fechas y estado
     */
    public function findByDateRangeAndStatus(string $dateFrom, string $dateTo, ?string $status = null): Collection
    {
        $query = Invoice::whereBetween('issue_date', [$dateFrom, $dateTo])
            ->with(['user', 'order'])
            ->orderBy('issue_date', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * ✅ Obtiene facturas que necesitan reintento automático (para cron jobs)
     */
    public function getInvoicesForAutomaticRetry(): Collection
    {
        return Invoice::where('status', Invoice::STATUS_FAILED)
            ->where('retry_count', '<', 9)
            ->where('last_retry_at', '<', now()->subMinutes(30)) // Al menos 30 min desde último intento
            ->orWhere('last_retry_at', null)
            ->orderBy('last_retry_at', 'asc')
            ->limit(10) // Procesar máximo 10 por vez para no sobrecargar
            ->get();
    }

    /**
     * ✅ Busca facturas por clave de acceso del SRI
     */
    public function findBySriAccessKey(string $accessKey): ?Invoice
    {
        return Invoice::where('sri_access_key', $accessKey)->first();
    }

    /**
     * ✅ Obtiene reportes mensuales de facturación
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate)); // Último día del mes

        $invoices = Invoice::whereBetween('issue_date', [$startDate, $endDate])
            ->where('status', Invoice::STATUS_AUTHORIZED)
            ->get();

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'totals' => [
                'count' => $invoices->count(),
                'subtotal' => $invoices->sum('subtotal'),
                'tax_amount' => $invoices->sum('tax_amount'),
                'total_amount' => $invoices->sum('total_amount'),
            ],
            'daily_breakdown' => $invoices->groupBy(function ($invoice) {
                return $invoice->issue_date->format('Y-m-d');
            })->map(function ($dayInvoices) {
                return [
                    'count' => $dayInvoices->count(),
                    'total_amount' => $dayInvoices->sum('total_amount'),
                ];
            }),
        ];
    }
}
