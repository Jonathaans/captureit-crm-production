<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Invoice\Repositories\ExpenseCategoryRepository;

/**
 * FINANCIAL REPORT EXPORT ALL EXPENSES V4 DATA MAPPING FIX
 *
 * One row is one expense. Every project and product value is resolved from
 * that expense's invoice_id; no value is shared globally between CSV rows.
 */
final class AllExpensesExportController extends Controller
{
    private const BATCH_SIZE = 500;

    public function __invoke(): StreamedResponse
    {
        $categoryLabels = app(ExpenseCategoryRepository::class)->labels();
        $filename = 'all-expenses-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($categoryLabels): void {
                $out = fopen('php://output', 'wb');

                if ($out === false) {
                    return;
                }

                fwrite($out, "\xEF\xBB\xBF");

                $this->writeRow($out, [
                    'Invoice Number',
                    'Project Code',
                    'Product',
                    'Event / Project',
                    'Event Date',
                    'Expense Date',
                    'Expense Name / Category',
                    'Amount',
                    'Note',
                    'Image / Receipt',
                    'Created By',
                    'Created At',
                ]);

                $hasPurchaseOrderFallback = $this->hasPurchaseOrderFallback();
                $lastExpenseId = 0;

                do {
                    $expenses = DB::table('expenses')
                        ->leftJoin('invoices', 'expenses.invoice_id', '=', 'invoices.id')
                        ->leftJoin('quotes', 'invoices.quote_id', '=', 'quotes.id')
                        ->leftJoin('users', 'expenses.created_by', '=', 'users.id')
                        ->where('expenses.id', '>', $lastExpenseId)
                        ->orderBy('expenses.id')
                        ->limit(self::BATCH_SIZE)
                        ->get([
                            'expenses.id as expense_id',
                            'expenses.invoice_id',
                            'expenses.category',
                            'expenses.description',
                            'expenses.amount',
                            'expenses.expense_date',
                            'expenses.notes',
                            'expenses.receipt_path',
                            'expenses.created_at',
                            'invoices.quote_id',
                            'invoices.invoice_number',
                            'invoices.project_code as invoice_project_code',
                            'invoices.subject as invoice_subject',
                            'invoices.event_date as invoice_event_date',
                            'quotes.project_code as quote_project_code',
                            'quotes.subject as quote_subject',
                            'quotes.event_date as quote_event_date',
                            'users.name as created_by_name',
                        ]);

                    if ($expenses->isEmpty()) {
                        break;
                    }

                    $invoiceIds = $expenses
                        ->pluck('invoice_id')
                        ->map(static fn ($id): int => (int) $id)
                        ->unique()
                        ->values();

                    $quoteIds = $expenses
                        ->pluck('quote_id')
                        ->filter()
                        ->map(static fn ($id): int => (int) $id)
                        ->unique()
                        ->values();

                    $productsByInvoice = $this->productNames(
                        'invoice_items',
                        'invoice_id',
                        $invoiceIds
                    );

                    $productsByQuote = $this->productNames(
                        'quote_items',
                        'quote_id',
                        $quoteIds
                    );

                    $purchaseOrdersByExpense = collect();
                    $productsByPurchaseOrder = collect();

                    if ($hasPurchaseOrderFallback) {
                        $purchaseOrdersByExpense = DB::table('purchase_orders')
                            ->whereIn('expense_id', $expenses->pluck('expense_id'))
                            ->orderBy('id')
                            ->get([
                                'id',
                                'expense_id',
                                'project_code',
                                'project_name',
                            ])
                            ->keyBy('expense_id');

                        $productsByPurchaseOrder = $this->productNames(
                            'purchase_order_items',
                            'purchase_order_id',
                            $purchaseOrdersByExpense->pluck('id')
                        );
                    }

                    foreach ($expenses as $expense) {
                        $purchaseOrder = $purchaseOrdersByExpense->get($expense->expense_id);

                        $projectCode = $this->firstFilled([
                            $expense->invoice_project_code ?? null,
                            $expense->quote_project_code ?? null,
                            $purchaseOrder?->project_code,
                        ]);

                        $product = $this->firstFilled([
                            $productsByInvoice->get($expense->invoice_id),
                            $productsByQuote->get($expense->quote_id),
                            $productsByPurchaseOrder->get($purchaseOrder?->id),
                        ]);

                        $eventProject = $this->firstFilled([
                            $expense->invoice_subject ?? null,
                            $expense->quote_subject ?? null,
                            $purchaseOrder?->project_name,
                        ]);

                        $eventDate = $this->firstFilled([
                            $expense->invoice_event_date ?? null,
                            $expense->quote_event_date ?? null,
                        ]);

                        $expenseName = trim((string) ($expense->description ?? ''));
                        $category = ExpenseCategoryRepository::label($expense->category, $categoryLabels);

                        if ($expenseName !== '' && $category !== '') {
                            $expenseName .= ' / '.$category;
                        } elseif ($expenseName === '') {
                            $expenseName = $category;
                        }

                        $this->writeRow($out, [
                            $this->safeCell($expense->invoice_number ?? ''),
                            $this->safeCell($projectCode),
                            $this->safeCell($product),
                            $this->safeCell($eventProject),
                            $this->dateValue($eventDate),
                            $this->dateValue($expense->expense_date ?? null),
                            $this->safeCell($expenseName),
                            (string) ($expense->amount ?? '0'),
                            $this->safeCell($expense->notes ?? ''),
                            $this->safeCell($this->receiptUrl($expense->receipt_path ?? null)),
                            $this->safeCell($expense->created_by_name ?? ''),
                            $this->dateTimeValue($expense->created_at ?? null),
                        ]);

                        $lastExpenseId = (int) $expense->expense_id;
                    }

                    if (function_exists('flush')) {
                        flush();
                    }
                } while (true);

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function productNames(
        string $itemTable,
        string $ownerColumn,
        Collection $ownerIds
    ): Collection {
        if ($ownerIds->isEmpty()) {
            return collect();
        }

        return DB::table($itemTable)
            ->leftJoin('products', $itemTable.'.product_id', '=', 'products.id')
            ->whereIn($itemTable.'.'.$ownerColumn, $ownerIds)
            ->orderBy($itemTable.'.id')
            ->get([
                $itemTable.'.'.$ownerColumn.' as owner_id',
                $itemTable.'.name as snapshot_name',
                'products.name as master_name',
            ])
            ->groupBy('owner_id')
            ->map(
                fn (Collection $items): string => $items
                    ->map(
                        fn ($item): string => $this->firstFilled([
                            $item->snapshot_name ?? null,
                            $item->master_name ?? null,
                        ])
                    )
                    ->filter()
                    ->unique()
                    ->implode(' | ')
            );
    }

    private function hasPurchaseOrderFallback(): bool
    {
        return Schema::hasTable('purchase_orders')
            && Schema::hasColumns('purchase_orders', [
                'id',
                'expense_id',
                'project_code',
                'project_name',
            ])
            && Schema::hasTable('purchase_order_items')
            && Schema::hasColumns('purchase_order_items', [
                'id',
                'purchase_order_id',
                'product_id',
                'name',
            ]);
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function writeRow($stream, array $row): void
    {
        fputcsv($stream, $row, ',', '"', '');
    }

    private function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        $formulaCandidate = ltrim($text, " \t\r\n");

        if (
            $formulaCandidate !== ''
            && in_array($formulaCandidate[0], ['=', '+', '-', '@'], true)
        ) {
            return "'".$text;
        }

        return $text;
    }

    private function receiptUrl(mixed $value): string
    {
        $path = trim((string) ($value ?? ''));

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (str_starts_with($path, 'storage/')) {
            return url('/'.$path);
        }

        return url('/storage/'.$path);
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '' : substr($value, 0, 10);
    }

    private function dateTimeValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '' : substr($value, 0, 19);
    }
}
