<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Repositories\ExpenseCategoryRepository;

class ExpenseCategoryController extends Controller
{
    public function __construct(private ExpenseCategoryRepository $categories) {}

    public function index(): View
    {
        $this->assertPermission();

        return view('admin::settings.expense-categories.index', ['categories' => $this->categories->directory()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertPermission('create');
        $data = $this->validated($request);
        try {
            $this->categories->addCategory($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Nama kategori sudah digunakan.']);
        }

        return redirect()->route('admin.settings.expense-categories.index')->with('success', 'Kategori expense berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->assertPermission('edit');
        $this->categories->findOrFail($id);
        $data = $this->validated($request, $id);
        try {
            $this->categories->updateCategory($id, $data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Nama kategori sudah digunakan.']);
        }

        return redirect()->route('admin.settings.expense-categories.index')->with('success', 'Kategori expense berhasil diperbarui.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);
        $name = trim(preg_replace('/\s+/u', ' ', $request->input('name')));
        $request->merge(['name' => $name, 'normalized_name' => mb_strtolower($name)]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'normalized_name' => ['required', Rule::unique('expense_categories')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ], ['normalized_name.unique' => 'Nama kategori sudah digunakan.']);

        return $data;
    }

    private function assertPermission(?string $action = null): void
    {
        $key = 'settings.finance.expense-categories';
        abort_unless(auth()->guard('user')->check() && bouncer()->hasPermission($key)
            && (! $action || bouncer()->hasPermission($key.'.'.$action)), 403);
    }
}
