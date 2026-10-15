<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Repositories\ExpenseCategoryRepository;

class ExpenseService
{
    public function addExpense(Invoice $invoice, array $data): Expense
    {
        return DB::transaction(function () use ($invoice, $data) {
            $amount = (float) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new InvalidArgumentException(
                    'Expense amount must be greater than zero.'
                );
            }

            app(ExpenseCategoryRepository::class)->assertSelectable($data['category']);

            return Expense::create([
                'invoice_id' => $invoice->id,
                'category' => $data['category'],
                'description' => $data['description'],
                'amount' => $amount,
                'expense_date' => $data['expense_date'],
                'vendor_name' => $data['vendor_name'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        });
    }

    public function updateExpense(
        Expense $expense,
        array $data
    ): Expense {
        return DB::transaction(function () use ($expense, $data) {
            $amount = (float) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new InvalidArgumentException(
                    'Expense amount must be greater than zero.'
                );
            }

            app(ExpenseCategoryRepository::class)->assertSelectable($data['category'], $expense->category);

            $expense->update([
                'category' => $data['category'],
                'description' => $data['description'],
                'amount' => $amount,
                'expense_date' => $data['expense_date'],
                'vendor_name' => $data['vendor_name'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'receipt_path' => $data['receipt_path']
                    ?? $expense->receipt_path,
                'notes' => $data['notes'] ?? null,
            ]);

            return $expense->fresh();
        });
    }

    public function deleteExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $expense->delete();
        });
    }
}
