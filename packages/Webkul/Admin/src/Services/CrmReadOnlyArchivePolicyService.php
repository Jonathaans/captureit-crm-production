<?php

namespace Webkul\Admin\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmReadOnlyArchivePolicyService
{
    /* CRM_READ_ONLY_ARCHIVE_POLICY_V1 */

    public function assertMutable(Model $model, string $operation): void
    {
        $table = $model->getTable();

        // Voided billing keeps historical operational documents, but cannot gain
        // new financial obligations or operational children through another route.
        if (in_array($table, ['expenses', 'purchase_orders', 'work_orders', 'delivery_orders'], true)
            && $this->parentStatusReason($model, 'invoice_id', 'invoices', ['void'], 'Invoice') !== null
            && ! $this->isExistingReplacementOperation($model, $operation)) {
            throw ValidationException::withMessages(['archive' => 'Invoice VOID dan dokumen terkait tidak dapat diubah atau ditambah.']);
        }

        if ($this->isQuoteIdentityCorrection($model, $operation)) {
            return;
        }

        if (
            $operation === 'create'
            && $table === 'inventory_stock_movements'
        ) {
            return;
        }

        if (
            $operation === 'create'
            && ! in_array(
                $table,
                [
                    'quote_items',
                    'invoice_items',
                    'payments',
                    'work_order_items',
                    'delivery_order_items',
                    'delivery_order_inventory_allocations',
                ],
                true
            )
        ) {
            return;
        }

        $reason = $this->archiveReason($model);

        if ($reason === null) {
            return;
        }

        $label = match ($operation) {
            'delete' => 'dihapus',
            'create' => 'ditambah detailnya',
            default => 'diubah',
        };

        throw ValidationException::withMessages([
            'archive' => sprintf(
                'Record arsip read-only tidak dapat %s. %s Gunakan dokumen revisi atau movement pembalik agar audit trail tetap utuh.',
                $label,
                $reason
            ),
        ]);
    }

    /** Billing replacement must not freeze a real delivery return or an existing vendor PO. */
    private function isExistingReplacementOperation(Model $model, string $operation): bool
    {
        if ($operation !== 'update' || ! $model->exists || ! in_array($model->getTable(), ['purchase_orders', 'work_orders', 'delivery_orders'], true)) {
            return false;
        }
        $invoice = DB::table('invoices')->where('id', $this->raw($model, 'invoice_id'))->first();
        $visited = [];
        while ($invoice && ($invoice->replaced_by_invoice_id ?? null) && ! isset($visited[$invoice->id])) {
            $visited[$invoice->id] = true;
            $next = DB::table('invoices')->where('id', $invoice->replaced_by_invoice_id)
                ->where('quote_id', $invoice->quote_id)->where('replaces_invoice_id', $invoice->id)->first();
            if ($next && $next->status !== 'void') {
                return true;
            }
            $invoice = $next;
        }

        return false;
    }

    /**
     * Permit clerical Bill To corrections without reopening commercial data.
     */
    private function isQuoteIdentityCorrection(
        Model $model,
        string $operation
    ): bool {
        if ($operation !== 'update' || $model->getTable() !== 'quotes') {
            return false;
        }

        $dirtyFields = array_keys($model->getDirty());

        if ($dirtyFields === []) {
            return false;
        }

        $allowedFields = [
            'person_id',
            'bill_to_display_mode',
            'bill_to_person_name',
            'bill_to_company_name',
            'client_signer_name',
            'client_signer_company',
            'updated_at',
        ];

        return array_diff($dirtyFields, $allowedFields) === [];
    }

    public function archiveReason(Model $model): ?string
    {
        $table = $model->getTable();

        if ($table === 'inventory_stock_movements') {
            return 'Inventory movement adalah ledger permanen (append-only).';
        }

        if ($table === 'invoice_replacements') {
            return 'Riwayat penggantian invoice adalah audit permanen.';
        }

        return match ($table) {
            'quotes' => $this->quoteReason($model),
            'quote_items' => $this->parentQuoteReason($model),
            'invoices' => $this->invoiceReason($model),
            'invoice_items' => $this->parentInvoiceReason($model),
            'payments' => $model->exists && DB::table('payment_voids')->where('payment_id', $model->getKey())->exists()
                ? 'Pembayaran VOID adalah arsip koreksi dan tidak dapat diubah/dihapus.'
                : $this->parentInvoiceReason($model),
            'work_orders' => $this->statusReason(
                $this->raw($model, 'status'),
                ['completed', 'cancelled', 'canceled', 'archived'],
                'SPK'
            ),
            'work_order_items' => $this->parentStatusReason(
                $model,
                'work_order_id',
                'work_orders',
                ['completed', 'cancelled', 'canceled', 'archived'],
                'SPK'
            ),
            'delivery_orders' => $this->statusReason(
                $this->raw($model, 'status'),
                ['returned', 'completed', 'cancelled', 'canceled', 'archived'],
                'Surat Jalan'
            ),
            'delivery_order_items', 'delivery_order_inventory_allocations' => $this->parentStatusReason(
                $model,
                'delivery_order_id',
                'delivery_orders',
                ['returned', 'completed', 'cancelled', 'canceled', 'archived'],
                'Surat Jalan'
            ),
            default => null,
        };
    }

    private function quoteReason(Model $model): ?string
    {
        $id = (int) $this->raw($model, 'id');
        $expiredAt = $this->raw($model, 'expired_at');

        if ($id > 0 && DB::table('invoices')->where('quote_id', $id)->exists()) {
            return 'Quotation sudah dikonversi menjadi Invoice.';
        }

        if ($this->quoteHasExpired($expiredAt)) {
            return 'Quotation sudah kedaluwarsa.';
        }

        return null;
    }

    private function parentQuoteReason(Model $model): ?string
    {
        $quoteId = (int) $this->raw($model, 'quote_id');

        if ($quoteId <= 0) {
            return null;
        }

        $quote = DB::table('quotes')->where('id', $quoteId)->first();

        if ($quote === null) {
            return null;
        }

        if (DB::table('invoices')->where('quote_id', $quoteId)->exists()) {
            return 'Item Quotation terkunci karena Quotation sudah menjadi Invoice.';
        }

        if ($this->quoteHasExpired($quote->expired_at ?? null)) {
            return 'Item Quotation terkunci karena Quotation sudah kedaluwarsa.';
        }

        return null;
    }

    private function quoteHasExpired(mixed $expiredAt): bool
    {
        if ($expiredAt === null || $expiredAt === '') {
            return false;
        }

        // Valid Until is a calendar date: the quote remains valid all that day.
        $timezone = config('app.timezone');

        return CarbonImmutable::parse($expiredAt, $timezone)->startOfDay()
            ->lt(CarbonImmutable::today($timezone));
    }

    private function invoiceReason(Model $model): ?string
    {
        $status = $this->normalized($this->raw($model, 'status'));
        $eventStatus = $this->normalized($this->raw($model, 'event_status'));

        if (in_array($status, ['paid', 'closed', 'void', 'cancelled', 'canceled', 'archived'], true)) {
            return 'Invoice berstatus final '.strtoupper($status).'.';
        }

        if (in_array($eventStatus, ['cancel', 'cancelled', 'canceled'], true)) {
            return 'Invoice terkait event yang sudah dibatalkan.';
        }

        return null;
    }

    private function parentInvoiceReason(Model $model): ?string
    {
        $invoiceId = (int) $this->raw($model, 'invoice_id');

        if ($invoiceId <= 0) {
            return null;
        }

        $invoice = DB::table('invoices')
            ->select(['status', 'event_status'])
            ->where('id', $invoiceId)
            ->first();

        if ($invoice === null) {
            return null;
        }

        $status = $this->normalized($invoice->status ?? null);
        $eventStatus = $this->normalized($invoice->event_status ?? null);

        if (in_array($status, ['paid', 'closed', 'void', 'cancelled', 'canceled', 'archived'], true)) {
            return 'Detail Invoice terkunci karena Invoice berstatus final '.strtoupper($status).'.';
        }

        if (in_array($eventStatus, ['cancel', 'cancelled', 'canceled'], true)) {
            return 'Detail Invoice terkunci karena event dibatalkan.';
        }

        return null;
    }

    private function parentStatusReason(
        Model $model,
        string $foreignKey,
        string $parentTable,
        array $finalStatuses,
        string $label
    ): ?string {
        $parentId = (int) $this->raw($model, $foreignKey);

        if ($parentId <= 0) {
            return null;
        }

        $status = DB::table($parentTable)
            ->where('id', $parentId)
            ->value('status');

        return $this->statusReason($status, $finalStatuses, $label);
    }

    private function statusReason(mixed $status, array $finalStatuses, string $label): ?string
    {
        $normalized = $this->normalized($status);

        return in_array($normalized, $finalStatuses, true)
            ? $label.' berstatus final '.strtoupper($normalized).'.'
            : null;
    }

    private function raw(Model $model, string $key): mixed
    {
        return $model->getRawOriginal($key) ?? $model->getAttribute($key);
    }

    private function normalized(mixed $value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }
}
