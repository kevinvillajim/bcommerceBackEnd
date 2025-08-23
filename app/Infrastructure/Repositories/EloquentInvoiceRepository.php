<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\InvoiceEntity;
use App\Domain\Entities\InvoiceItemEntity;
use App\Domain\Entities\SriTransactionEntity;
use App\Domain\Repositories\InvoiceRepositoryInterface;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SriTransaction;
use DateTime;
use Illuminate\Support\Facades\DB;

class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function createInvoice(InvoiceEntity $invoice): InvoiceEntity
    {
        DB::beginTransaction();

        try {
            $model = new Invoice;
            $model->invoice_number = $invoice->invoiceNumber;
            $model->order_id = $invoice->orderId;
            $model->user_id = $invoice->userId;
            $model->seller_id = $invoice->sellerId;
            $model->transaction_id = $invoice->transactionId;
            $model->issue_date = $invoice->issueDate;
            $model->subtotal = $invoice->subtotal;
            $model->tax_amount = $invoice->taxAmount;
            $model->total_amount = $invoice->totalAmount;
            $model->status = $invoice->status;
            $model->sri_authorization_number = $invoice->sriAuthorizationNumber;
            $model->sri_access_key = $invoice->sriAccessKey;
            $model->sri_response = $invoice->sriResponse ? json_encode($invoice->sriResponse) : null;
            $model->save();

            $invoice->id = $model->id;

            // Guardar los ítems de la factura
            foreach ($invoice->items as $item) {
                $item->invoiceId = $invoice->id;
                $this->addInvoiceItem($item);
            }

            DB::commit();

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getInvoiceById(int $id): ?InvoiceEntity
    {
        $model = Invoice::find($id);
        if (! $model) {
            return null;
        }

        $invoice = $this->mapInvoiceModelToEntity($model);
        $invoice->items = $this->getInvoiceItems($id);

        return $invoice;
    }

    public function getInvoiceByNumber(string $invoiceNumber): ?InvoiceEntity
    {
        $model = Invoice::where('invoice_number', $invoiceNumber)->first();
        if (! $model) {
            return null;
        }

        $invoice = $this->mapInvoiceModelToEntity($model);
        $invoice->items = $this->getInvoiceItems($invoice->id);

        return $invoice;
    }

    public function getInvoiceByAccessKey(string $accessKey): ?InvoiceEntity
    {
        $model = Invoice::where('sri_access_key', $accessKey)->first();
        if (! $model) {
            return null;
        }

        $invoice = $this->mapInvoiceModelToEntity($model);
        $invoice->items = $this->getInvoiceItems($invoice->id);

        return $invoice;
    }

    public function getInvoiceByOrderId(int $orderId): ?InvoiceEntity
    {
        $model = Invoice::where('order_id', $orderId)->first();
        if (! $model) {
            return null;
        }

        $invoice = $this->mapInvoiceModelToEntity($model);
        $invoice->items = $this->getInvoiceItems($invoice->id);

        return $invoice;
    }

    public function updateInvoice(InvoiceEntity $invoice): InvoiceEntity
    {
        $model = Invoice::find($invoice->id);
        if (! $model) {
            throw new \Exception("Invoice not found with ID: {$invoice->id}");
        }

        $model->invoice_number = $invoice->invoiceNumber;
        $model->transaction_id = $invoice->transactionId;
        $model->issue_date = $invoice->issueDate;
        $model->subtotal = $invoice->subtotal;
        $model->tax_amount = $invoice->taxAmount;
        $model->total_amount = $invoice->totalAmount;
        $model->status = $invoice->status;
        $model->sri_authorization_number = $invoice->sriAuthorizationNumber;
        $model->sri_access_key = $invoice->sriAccessKey;
        $model->cancellation_reason = $invoice->cancellationReason;
        $model->cancelled_at = $invoice->cancelledAt;
        $model->sri_response = $invoice->sriResponse ? json_encode($invoice->sriResponse) : null;
        $model->save();

        return $invoice;
    }

    public function addInvoiceItem(InvoiceItemEntity $item): InvoiceItemEntity
    {
        $model = new InvoiceItem;
        $model->invoice_id = $item->invoiceId;
        $model->product_id = $item->productId;
        $model->description = $item->description;
        $model->quantity = $item->quantity;
        $model->unit_price = $item->unitPrice;
        $model->discount = $item->discount;
        $model->tax_rate = $item->taxRate;
        $model->tax_amount = $item->taxAmount;
        $model->total = $item->total;
        $model->sri_product_code = $item->sriProductCode;
        $model->save();

        $item->id = $model->id;

        return $item;
    }

    public function getInvoiceItems(int $invoiceId): array
    {
        $models = InvoiceItem::where('invoice_id', $invoiceId)->get();

        return $models->map(function ($model) {
            return $this->mapInvoiceItemModelToEntity($model);
        })->toArray();
    }

    public function cancelInvoice(int $invoiceId, string $reason): bool
    {
        $model = Invoice::find($invoiceId);
        if (! $model || $model->status === 'CANCELLED') {
            return false;
        }

        $model->status = 'CANCELLED';
        $model->cancellation_reason = $reason;
        $model->cancelled_at = now();

        return $model->save();
    }

    public function recordSriTransaction(SriTransactionEntity $transaction): SriTransactionEntity
    {
        $model = new SriTransaction;
        $model->invoice_id = $transaction->invoiceId;
        $model->type = $transaction->type;
        $model->request_data = json_encode($transaction->requestData);
        $model->response_data = $transaction->responseData ? json_encode($transaction->responseData) : null;
        $model->success = $transaction->success;
        $model->error_message = $transaction->errorMessage;
        $model->save();

        $transaction->id = $model->id;

        return $transaction;
    }

    public function getSriTransactions(int $invoiceId): array
    {
        $models = SriTransaction::where('invoice_id', $invoiceId)->orderBy('created_at', 'desc')->get();

        return $models->map(function ($model) {
            return $this->mapSriTransactionModelToEntity($model);
        })->toArray();
    }

    public function listInvoices(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $query = Invoice::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('issue_date', [$filters['start_date'], $filters['end_date']]);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        $total = $query->count();
        $models = $query->orderBy('issue_date', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $invoices = $models->map(function ($model) {
            $invoice = $this->mapInvoiceModelToEntity($model);
            $invoice->items = $this->getInvoiceItems($invoice->id);

            return $invoice;
        })->toArray();

        return [
            'data' => $invoices,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage),
        ];
    }

    private function mapInvoiceModelToEntity(Invoice $model): InvoiceEntity
    {
        return new InvoiceEntity(
            id: $model->id,
            invoiceNumber: $model->invoice_number,
            orderId: $model->order_id,
            userId: $model->user_id,
            sellerId: $model->seller_id,
            transactionId: $model->transaction_id,
            issueDate: new DateTime($model->issue_date),
            subtotal: $model->subtotal,
            taxAmount: $model->tax_amount,
            totalAmount: $model->total_amount,
            status: $model->status,
            sriAuthorizationNumber: $model->sri_authorization_number,
            sriAccessKey: $model->sri_access_key,
            cancellationReason: $model->cancellation_reason,
            cancelledAt: $model->cancelled_at ? new DateTime($model->cancelled_at) : null,
            sriResponse: $model->sri_response ? json_decode($model->sri_response, true) : null
        );
    }

    private function mapInvoiceItemModelToEntity(InvoiceItem $model): InvoiceItemEntity
    {
        return new InvoiceItemEntity(
            id: $model->id,
            invoiceId: $model->invoice_id,
            productId: $model->product_id,
            description: $model->description,
            quantity: $model->quantity,
            unitPrice: $model->unit_price,
            discount: $model->discount,
            taxRate: $model->tax_rate,
            taxAmount: $model->tax_amount,
            total: $model->total,
            sriProductCode: $model->sri_product_code
        );
    }

    private function mapSriTransactionModelToEntity(SriTransaction $model): SriTransactionEntity
    {
        return new SriTransactionEntity(
            id: $model->id,
            invoiceId: $model->invoice_id,
            type: $model->type,
            requestData: json_decode($model->request_data, true),
            responseData: $model->response_data ? json_decode($model->response_data, true) : null,
            success: $model->success,
            errorMessage: $model->error_message
        );
    }
}
