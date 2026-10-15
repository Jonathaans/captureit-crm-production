<?php

namespace Webkul\Invoice\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Core\Eloquent\Repository;
use Webkul\Invoice\Contracts\ExpenseCategory;

class ExpenseCategoryRepository extends Repository
{
    public function model()
    {
        return ExpenseCategory::class;
    }

    public function directory(): Collection
    {
        // Legacy reports remain readable during a rolling deployment.
        if (! Schema::hasTable('expense_categories')) {
            return collect([
                'transport' => 'Transport', 'crew' => 'Crew', 'printing' => 'Printing',
                'equipment' => 'Equipment', 'vendor' => 'Vendor', 'consumption' => 'Consumption',
                'other' => 'Other', 'vendor_outsource' => 'Vendor Outsource',
            ])->map(fn ($name, $code) => (object) ['code' => $code, 'name' => $name, 'is_active' => true]);
        }

        return $this->model->newQuery()->orderBy('name')->get();
    }

    public function labels(): array
    {
        return $this->directory()->pluck('name', 'code')->all();
    }

    public static function label(?string $code, array $labels): string
    {
        return $labels[$code ?? ''] ?? ucwords(str_replace('_', ' ', $code ?: 'uncategorized'));
    }

    public function assertSelectable(string $code, ?string $currentCode = null): void
    {
        // Existing expenses may keep their category after it is deactivated.
        if ($currentCode !== null && $code === $currentCode) {
            return;
        }

        $category = $this->directory()->firstWhere('code', $code);
        if (! $category || ! $category->is_active) {
            throw ValidationException::withMessages(['category' => 'Pilih kategori expense yang aktif. Kelola kategori melalui Settings.']);
        }
    }

    public function addCategory(array $data)
    {
        return $this->create(['code' => 'expense_'.Str::uuid(), ...$data]);
    }

    public function updateCategory(int $id, array $data)
    {
        $category = $this->findOrFail($id);

        // The code is immutable so renaming never rewrites past expenses.
        return $this->update(collect($data)->only(['name', 'normalized_name', 'description', 'is_active'])->all(), $category->id);
    }
}
