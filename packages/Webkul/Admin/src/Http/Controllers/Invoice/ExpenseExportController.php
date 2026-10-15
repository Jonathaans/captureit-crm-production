<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Repositories\ExpenseCategoryRepository;

/**
 * EXPORT ALL EXPENSES CSV V1
 *
 * One CSV row is one expense. Keyset batches keep memory usage bounded.
 */
class ExpenseExportController extends Controller
{
    private const BATCH_SIZE = 500;

    public function export(): StreamedResponse
    {
        $categoryLabels = app(ExpenseCategoryRepository::class)->labels();
        $fileName = 'All-Expenses-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($categoryLabels): void {
                $stream = fopen('php://output', 'w');

                if ($stream === false) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");

                $this->writeRow($stream, [
                    'Invoice Number',
                    'Project Code',
                    'Product Event',
                    'Project Event Date',
                    'Expense Date',
                    'Expense Name / Category',
                    'Amount',
                    'Note',
                    'Image / Receipt',
                    'Created By',
                    'Created At',
                ]);

                $lastExpenseId = 0;

                do {
                    $expenses = DB::table('expenses')
                        ->join('invoices', 'expenses.invoice_id', '=', 'invoices.id')
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
                            'invoices.invoice_number',
                            'invoices.project_code',
                            'invoices.subject as project_event',
                            'invoices.event_date as project_event_date',
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

                    $productsByInvoice = DB::table('invoice_items')
                        ->whereIn('invoice_id', $invoiceIds)
                        ->whereNotNull('name')
                        ->where('name', '<>', '')
                        ->orderBy('name')
                        ->get(['invoice_id', 'name'])
                        ->groupBy('invoice_id')
                        ->map(
                            static fn ($items): string => $items
                                ->pluck('name')
                                ->map(static fn ($name): string => trim((string) $name))
                                ->filter()
                                ->unique()
                                ->implode(', ')
                        );

                    foreach ($expenses as $expense) {
                        $productEvent = trim((string) ($productsByInvoice->get($expense->invoice_id) ?? ''));

                        if ($productEvent === '') {
                            $productEvent = (string) ($expense->project_event ?? '');
                        }

                        $expenseNameCategory = trim((string) ($expense->description ?? ''));
                        $category = ExpenseCategoryRepository::label($expense->category, $categoryLabels);

                        if ($expenseNameCategory !== '' && $category !== '') {
                            $expenseNameCategory .= ' / '.$category;
                        } elseif ($expenseNameCategory === '') {
                            $expenseNameCategory = $category;
                        }

                        $this->writeRow($stream, [
                            $this->csvText($expense->invoice_number ?? ''),
                            $this->csvText($expense->project_code ?? ''),
                            $this->csvText($productEvent),
                            $this->dateValue($expense->project_event_date ?? null),
                            $this->dateValue($expense->expense_date ?? null),
                            $this->csvText($expenseNameCategory),
                            (string) ($expense->amount ?? '0'),
                            $this->csvText($expense->notes ?? ''),
                            $this->csvText($this->receiptUrl($expense->receipt_path ?? null)),
                            $this->csvText($expense->created_by_name ?? ''),
                            $this->dateTimeValue($expense->created_at ?? null),
                        ]);

                        $lastExpenseId = (int) $expense->expense_id;
                    }

                    if (function_exists('flush')) {
                        flush();
                    }
                } while (true);

                fclose($stream);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function writeRow($stream, array $row): void
    {
        fputcsv($stream, $row, ',', '"', '');
    }

    private function csvText(mixed $value): string
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

        return asset('storage/'.ltrim($path, '/'));
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
