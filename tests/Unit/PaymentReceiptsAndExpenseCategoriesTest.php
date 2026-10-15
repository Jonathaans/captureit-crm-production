<?php

use App\Http\Controllers\AllExpensesExportController;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\HistoricalDocumentDatabase;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Invoice\ExpenseExportController;
use Webkul\Admin\Http\Controllers\Invoice\FinancialReportController;
use Webkul\Core\Menu;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;
use Webkul\Invoice\Repositories\ExpenseCategoryRepository;
use Webkul\Invoice\Services\ExpenseService;
use Webkul\Invoice\Services\PaymentReceiptService;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(TestCase::class);

beforeEach(function () {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.receipt_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ], 'app.key' => str_repeat('a', 32)]);
    DB::setDefaultConnection('receipt_test');
    Schema::clearResolvedInstance('db.schema');
    HistoricalDocumentDatabase::createFinancialSchema();
    Schema::table('expenses', function (Blueprint $table) {
        $table->string('vendor_name')->nullable();
        $table->string('receipt_path')->nullable();
        $table->text('notes')->nullable();
        $table->unsignedInteger('created_by')->nullable();
        $table->timestamps();
    });
    Schema::create('delivery_orders', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('invoice_id');
    });
    Schema::create('purchase_orders', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('expense_id')->nullable();
        $table->string('status')->nullable();
    });
    (require base_path('database/migrations/2026_10_15_200000_add_receipts_to_payments.php'))->up();
    (require base_path('database/migrations/2026_10_15_200100_create_expense_categories_table.php'))->up();
    DB::connection()->getPdo()->sqliteCreateFunction('YEAR', fn ($date) => (int) substr($date, 0, 4));
    DB::connection()->getPdo()->sqliteCreateFunction('MONTH', fn ($date) => (int) substr($date, 5, 2));
    DB::table('users')->insert(['id' => 1, 'name' => 'Admin Finance']);
    $this->actingAs(receiptUser(), 'user');
    $this->withoutMiddleware(CanInstall::class);
    $this->travelTo(now()->parse('2026-10-15 10:00:00'));
    Storage::fake('local');
    Storage::fake('public');
    $this->invoice = Invoice::create([
        'invoice_number' => 'INV-BON-01', 'subject' => 'Photobooth Wedding', 'project_code' => 'PRJ-BON',
        'grand_total' => 1000000, 'balance_due' => 1000000, 'issued_at' => '2026-10-15',
        'event_date' => '2026-10-20', 'event_status' => 'confirm',
    ]);
    $this->categories = app(ExpenseCategoryRepository::class);
});

afterEach(function () {
    $this->travelBack();
    DB::purge('receipt_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

function receiptUser(?array $permissions = null): User
{
    return (new User)->forceFill(['id' => 1, 'name' => 'Admin Finance', 'status' => 1])
        ->setRelation('role', (new Role)->forceFill(['permission_type' => $permissions === null ? 'all' : 'custom', 'permissions' => $permissions ?? []]));
}

function receiptFile(string $extension = 'pdf'): UploadedFile
{
    $content = match ($extension) {
        'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4ioAAAAASUVORK5CYII='),
        'jpg', 'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAb/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCVgA//2Q=='),
        default => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n",
    };

    return UploadedFile::fake()->createWithContent('bon.'.$extension, $content);
}

function receiptPayload(): array
{
    return ['amount' => 250000, 'paid_date' => '2026-10-15', 'paid_time' => '09:30',
        'payment_method' => 'Transfer BCA', 'reference_number' => 'BANK-001', '_transaction_form' => 'payment'];
}

function categoryPayload(string $name = 'Akomodasi Kru', bool $active = true): array
{
    return ['name' => $name, 'description' => 'Hotel dan penginapan kru', 'is_active' => (int) $active, '_category_form' => 'create'];
}

function receiptExpensePayload(string $category = 'transport'): array
{
    return ['category' => $category, 'description' => 'Transport event', 'amount' => 50000,
        'expense_date' => '2026-10-15', '_transaction_form' => 'expense'];
}

function receiptReport(): array
{
    return app(FinancialReportController::class)->index(Request::create('/', 'GET', ['year' => 2026, 'month' => 10]))->getData();
}

it('stores real payment receipts privately and updates invoice and financial report exactly once', function (string $extension, string $mime) {
    $file = receiptFile($extension);
    $content = file_get_contents($file->getRealPath());
    $this->post(route('admin.invoices.payments.store', $this->invoice->id), receiptPayload() + ['receipt' => $file])
        ->assertRedirect()->assertSessionHasNoErrors();
    $payment = Payment::sole();
    Storage::disk('local')->assertExists($payment->receipt_path);
    Storage::disk('public')->assertMissing($payment->receipt_path);
    expect($payment->receipt_mime)->toBe($mime)
        ->and($payment->receipt_name)->toBe('bon.'.$extension)
        ->and(Storage::disk('local')->get($payment->receipt_path))->toBe($content)
        ->and((float) $this->invoice->fresh()->paid_amount)->toBe(250000.0)
        ->and((float) $this->invoice->fresh()->balance_due)->toBe(750000.0)
        ->and($this->invoice->fresh()->status)->toBe('partial');
    $report = receiptReport()['financialSummary'];
    expect($report['received'])->toBe(250000.0)->and($report['outstanding'])->toBe(750000.0)
        ->and($report['invoiced'])->toBe(1000000.0);
    $this->get(route('admin.invoices.payments.receipt', [$this->invoice->id, $payment->id]))
        ->assertOk()->assertDownload('bon.'.$extension)->assertHeader('Content-Type', $mime)
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($content);
})->with([['pdf', 'application/pdf'], ['png', 'image/png'], ['jpg', 'image/jpeg'], ['jpeg', 'image/jpeg']]);

it('keeps receipts optional and ignores forged metadata', function () {
    $this->post(route('admin.invoices.payments.store', $this->invoice->id), receiptPayload() + ['receipt_path' => 'private/secrets.pdf'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(Payment::sole()->receipt_path)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
    $this->get(route('admin.invoices.payments.receipt', [$this->invoice->id, Payment::sole()->id]))->assertNotFound();
});

it('rejects invalid or oversized bon without recording money', function (string $kind) {
    $file = match ($kind) {
        'fake-pdf' => UploadedFile::fake()->createWithContent('bon.pdf', '<script>alert(1)</script>'),
        'fake-image' => UploadedFile::fake()->createWithContent('bon.jpg', 'not an image'),
        'oversize' => UploadedFile::fake()->createWithContent('bon.pdf', '%PDF-1.4'.str_repeat('a', 5 * 1024 * 1024)),
        default => UploadedFile::fake()->createWithContent('bon.php', "%PDF-1.4\n%%EOF"),
    };
    $this->post(route('admin.invoices.payments.store', $this->invoice->id), receiptPayload() + ['receipt' => $file])
        ->assertSessionHasErrors('receipt');
    expect(Payment::count())->toBe(0)->and((float) $this->invoice->fresh()->balance_due)->toBe(1000000.0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['fake-pdf', 'fake-image', 'oversize', 'wrong-extension']);

it('cleans up uploaded files when an overpayment or void invoice is rejected', function (bool $void) {
    if ($void) {
        DB::table('invoices')->where('id', $this->invoice->id)->update(['status' => 'void']);
    }
    $this->post(route('admin.invoices.payments.store', $this->invoice->id), array_replace(receiptPayload(), [
        'amount' => $void ? 250000 : 2000000, 'receipt' => receiptFile(),
    ]))->assertSessionHasErrors($void ? 'invoice' : 'amount');
    expect(Payment::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([])
        ->and((float) $this->invoice->fresh()->paid_amount)->toBe(0.0);
})->with([false, true]);

it('rolls back payment and balance if receipt metadata cannot be saved', function () {
    Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('receipt_name'));
    expect(fn () => app(PaymentReceiptService::class)->addPayment($this->invoice, ['amount' => 250000], receiptFile()))
        ->toThrow(QueryException::class);
    expect(Payment::count())->toBe(0)->and((float) $this->invoice->fresh()->balance_due)->toBe(1000000.0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('does not create a payment when receipt storage fails', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('put')->once()->andReturn(false);
    $disk->shouldReceive('delete')->zeroOrMoreTimes()->andReturn(true);
    expect(fn () => app(PaymentReceiptService::class)->addPayment($this->invoice, ['amount' => 250000], receiptFile()))
        ->toThrow(ValidationException::class);
    expect(Payment::count())->toBe(0)->and((float) $this->invoice->fresh()->balance_due)->toBe(1000000.0);
});

it('scopes receipts to the invoice and preserves them in void payment history', function () {
    $payment = app(PaymentReceiptService::class)->addPayment($this->invoice, ['amount' => 250000], receiptFile());
    $other = Invoice::create(['invoice_number' => 'INV-OTHER']);
    $this->get(route('admin.invoices.payments.receipt', [$other->id, $payment->id]))->assertNotFound();
    DB::table('invoices')->where('id', $this->invoice->id)->update(['status' => 'void']);
    $this->get(route('admin.invoices.payments.receipt', [$this->invoice->id, $payment->id]))->assertOk();
});

it('enforces payment and receipt permissions independently', function () {
    $payment = app(PaymentReceiptService::class)->addPayment($this->invoice, ['amount' => 250000], receiptFile());
    $this->actingAs(receiptUser(['invoices.view']), 'user');
    $this->post(route('admin.invoices.payments.store', $this->invoice->id), receiptPayload())->assertStatus(401);
    $this->get(route('admin.invoices.payments.receipt', [$this->invoice->id, $payment->id]))->assertOk();
    $this->actingAs(receiptUser(['invoices.payment']), 'user');
    $this->get(route('admin.invoices.payments.receipt', [$this->invoice->id, $payment->id]))->assertStatus(401);
    $this->post(route('admin.invoices.payments.store', $this->invoice->id), receiptPayload())->assertRedirect()->assertSessionHasNoErrors();
    expect(Payment::count())->toBe(2);
    auth()->guard('user')->logout();
    $this->get(route('admin.invoices.payments.receipt', [$this->invoice->id, $payment->id]))->assertRedirect();
});

it('creates a category through Settings then uses it in expense and financial exports', function () {
    $this->post(route('admin.settings.expense-categories.store'), categoryPayload())->assertRedirect()->assertSessionHasNoErrors();
    $category = DB::table('expense_categories')->where('name', 'Akomodasi Kru')->first();
    $this->post(route('admin.invoices.expenses.store', $this->invoice->id), receiptExpensePayload($category->code))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(Expense::sole()->category)->toBe($category->code)
        ->and(receiptReport()['expenseByCategory']->first())->toBe(['category' => 'Akomodasi Kru', 'total' => 50000.0])
        ->and(receiptReport()['financialSummary']['expense'])->toBe(50000.0);
    foreach ([app(ExpenseExportController::class)->export(), app(AllExpensesExportController::class)()] as $csv) {
        ob_start();
        $csv->sendContent();
        $content = ob_get_clean();
        expect($content)->toContain('Transport event / Akomodasi Kru', '50000')->not->toContain($category->code);
    }
});

it('rejects duplicate category names ignoring case and repeated whitespace', function () {
    $this->post(route('admin.settings.expense-categories.store'), categoryPayload('  Akomodasi   Kru  '))->assertSessionHasNoErrors();
    $this->post(route('admin.settings.expense-categories.store'), categoryPayload('akomodasi kru'))->assertSessionHasErrors('normalized_name');
    $this->post(route('admin.settings.expense-categories.store'), categoryPayload('   '))->assertSessionHasErrors('name');
    expect(DB::table('expense_categories')->where('normalized_name', 'akomodasi kru')->count())->toBe(1);
});

it('renames and deactivates categories without changing expense identity or financial totals', function () {
    $category = DB::table('expense_categories')->where('code', 'transport')->first();
    $expense = app(ExpenseService::class)->addExpense($this->invoice, receiptExpensePayload());
    $before = receiptReport()['financialSummary'];
    $this->put(route('admin.settings.expense-categories.update', $category->id), categoryPayload('Transportasi Event', false) + ['code' => 'forged'])
        ->assertSessionHasNoErrors();
    expect($expense->fresh()->category)->toBe('transport')->and(receiptReport()['financialSummary'])->toBe($before)
        ->and(receiptReport()['expenseByCategory']->first()['category'])->toBe('Transportasi Event');
    $this->post(route('admin.invoices.expenses.store', $this->invoice->id), receiptExpensePayload())->assertSessionHasErrors('category');
    $this->put(route('admin.invoices.expenses.update', [$this->invoice->id, $expense->id]), array_replace(receiptExpensePayload(), ['amount' => 70000]))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect((float) $expense->fresh()->amount)->toBe(70000.0);
    $other = app(ExpenseService::class)->addExpense($this->invoice, receiptExpensePayload('crew'));
    expect(fn () => app(ExpenseService::class)->updateExpense($other, receiptExpensePayload()))->toThrow(ValidationException::class);
    $this->put(route('admin.settings.expense-categories.update', $category->id), categoryPayload('Transportasi Event'))
        ->assertSessionHasNoErrors();
    $this->post(route('admin.invoices.expenses.store', $this->invoice->id), receiptExpensePayload())->assertSessionHasNoErrors();
    expect(Expense::count())->toBe(3);
});

it('rejects unknown categories before saving an expense receipt', function () {
    $this->post(route('admin.invoices.expenses.store', $this->invoice->id), receiptExpensePayload('invented') + ['receipt' => receiptFile()])
        ->assertSessionHasErrors('category');
    expect(Expense::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('imports historical category codes without rewriting existing expenses', function () {
    Schema::drop('expense_categories');
    DB::table('expenses')->insert([
        ['invoice_id' => $this->invoice->id, 'category' => 'hotel_kru', 'amount' => 20000, 'expense_date' => '2026-10-15'],
        ['invoice_id' => $this->invoice->id, 'category' => 'TRANSPORT', 'amount' => 30000, 'expense_date' => '2026-10-15'],
    ]);
    $before = DB::table('expenses')->get()->toArray();
    (require base_path('database/migrations/2026_10_15_200100_create_expense_categories_table.php'))->up();
    expect(DB::table('expenses')->get()->toArray())->toEqual($before)
        ->and($this->categories->labels()['hotel_kru'])->toBe('Hotel Kru')
        ->and(DB::table('expense_categories')->count())->toBe(10);
    $this->categories->assertSelectable('hotel_kru');
});

it('keeps category settings permissions separate from expense entry permissions', function () {
    $key = 'settings.finance.expense-categories';
    $this->actingAs(receiptUser(['invoices.expense']), 'user');
    $this->get(route('admin.settings.expense-categories.index'))->assertStatus(401);
    $this->post(route('admin.settings.expense-categories.store'), categoryPayload())->assertStatus(401);
    expect((new Menu)->getAdminMenuByKey('settings'))->toBeNull();
    $this->actingAs(receiptUser([$key]), 'user');
    $settings = (new Menu)->getAdminMenuByKey('settings');
    expect($settings->getChildren()->first()->getChildren()->first()->getKey())->toBe($key);
    $this->post(route('admin.settings.expense-categories.store'), categoryPayload())->assertStatus(401);
    $this->put(route('admin.settings.expense-categories.update', 1), categoryPayload())->assertStatus(401);
    $this->actingAs(receiptUser([$key, $key.'.create']), 'user');
    $this->post(route('admin.settings.expense-categories.store'), categoryPayload())->assertSessionHasNoErrors();
    $this->put(route('admin.settings.expense-categories.update', 1), categoryPayload())->assertStatus(401);
});

it('renders dynamic categories and bon input according to category status and permissions', function () {
    $category = $this->categories->addCategory(['name' => 'Parkir & Tol', 'normalized_name' => 'parkir & tol', 'is_active' => true]);
    $data = ['invoice' => $this->invoice, 'expenseCategories' => $this->categories->directory()];
    $html = view('admin::invoices.partials.transaction-forms', $data)->render();
    expect($html)->toContain('multipart/form-data', 'Bon / bukti pembayaran', 'Parkir &amp; Tol', $category->code);
    $this->categories->updateCategory($category->id, ['is_active' => false]);
    $data['expenseCategories'] = $this->categories->directory();
    expect(view('admin::invoices.partials.transaction-forms', $data)->render())->not->toContain($category->code);
    $this->actingAs(receiptUser(['invoices.view']), 'user');
    expect(view('admin::invoices.partials.transaction-forms', $data)->render())->not->toContain('name="receipt"');
});

it('renders the complete Settings and invoice pages with new category navigation and receipt links', function () {
    $payment = app(PaymentReceiptService::class)->addPayment($this->invoice, ['amount' => 250000], receiptFile());
    app(ExpenseService::class)->addExpense($this->invoice, receiptExpensePayload());
    $this->get(route('admin.settings.index'))->assertOk()->assertSee('Kategori Expense');
    $this->get(route('admin.settings.expense-categories.index'))->assertOk()->assertSee('Tambah Kategori');
    $this->get(route('admin.invoices.show', $this->invoice->id))->assertOk()->assertSee('Unduh bon');
});
