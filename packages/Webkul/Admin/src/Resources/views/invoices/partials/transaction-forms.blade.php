@include('admin::invoices.partials.workspace-styles')
@include('admin::settings.partials.finance-form-styles')
<aside class="crm-finance-workspace invoice-transaction-forms" style="width:100%" v-pre>
    <div><p class="fw-eyebrow">Transaksi</p><h2 class="fw-title">Payment & Expense</h2><p class="fw-subtitle">Catat pembayaran dan pengeluaran untuk invoice ini.</p></div>
    @if (bouncer()->hasPermission('invoices.payment'))
        <section class="fw-card fw-pad" id="input-payment">
            <div class="fw-between"><h3 class="fw-title">Input Payment</h3><span class="fw-badge fw-badge-converted">Uang masuk</span></div>
            <div class="finance-stats">
                <div><small>Sudah diterima</small><strong class="fw-positive">Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</strong></div>
                <div><small>Sisa tagihan</small><strong>Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}</strong></div>
            </div>
            @if ($invoice->status === 'void')
                <p class="fw-subtitle">Invoice VOID tidak dapat menerima pembayaran.</p>
            @elseif ((float) $invoice->balance_due <= 0)
                <p class="fw-subtitle fw-positive">Pembayaran invoice ini sudah lunas.</p>
            @else
                @php($restorePayment = old('_transaction_form') === 'payment')
                <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice->id) }}" enctype="multipart/form-data" class="finance-form">
                    @csrf
                    <input type="hidden" name="_transaction_form" value="payment">
                    @include('admin::invoices.partials.transaction-errors', ['showErrors' => $restorePayment])
                    <label class="fw-field">Nominal pembayaran (Rp) *
                        <input type="number" name="amount" min="0.01" step="0.01" max="{{ $invoice->balance_due }}" value="{{ $restorePayment ? old('amount') : '' }}" required placeholder="0" inputmode="decimal">
                    </label>
                    <div class="finance-form-grid">
                        <label class="fw-field">Tanggal pembayaran *<input type="date" name="paid_date" required value="{{ $restorePayment ? old('paid_date') : now()->format('Y-m-d') }}"></label>
                        <label class="fw-field">Waktu *<input type="time" name="paid_time" required value="{{ $restorePayment ? old('paid_time') : now()->format('H:i') }}"></label>
                    </div>
                    <label class="fw-field">Metode pembayaran <span class="finance-optional">opsional</span>
                        <input name="payment_method" maxlength="255" placeholder="Contoh: Transfer BCA" value="{{ $restorePayment ? old('payment_method') : '' }}">
                    </label>
                    <label class="fw-field">Nomor referensi <span class="finance-optional">opsional</span>
                        <input name="reference_number" maxlength="255" placeholder="Nomor transaksi / referensi bank" value="{{ $restorePayment ? old('reference_number') : '' }}">
                    </label>
                    <label class="fw-field finance-receipt">Bon / bukti pembayaran <span class="finance-optional">opsional</span>
                        <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="payment-receipt-help">
                        <span class="finance-help" id="payment-receipt-help">PDF, JPG, atau PNG · maksimal 5 MB. Bon dapat diunduh dari riwayat pembayaran.</span>
                    </label>
                    <label class="fw-field">Catatan <span class="finance-optional">opsional</span><textarea name="notes" rows="2" placeholder="Catatan pembayaran…">{{ $restorePayment ? old('notes') : '' }}</textarea></label>
                    <button type="submit" class="primary-button finance-submit">Simpan Pembayaran</button>
                </form>
            @endif
        </section>
    @endif
    @if (bouncer()->hasPermission('invoices.expense'))
        <section class="fw-card fw-pad" id="input-expense">
            <div class="fw-between"><h3 class="fw-title">Input Expense</h3><span class="fw-badge fw-badge-issued">Uang keluar</span></div>
            <p class="fw-subtitle">Catat biaya operasional dan bon pengeluaran project.</p>
            @if ($invoice->event_status === 'cancel')
                <p class="fw-subtitle">Expense baru tidak tersedia untuk event yang sudah cancel.</p>
            @else
                @php($restoreExpense = old('_transaction_form') === 'expense')
                <form method="POST" action="{{ route('admin.invoices.expenses.store', $invoice->id) }}" enctype="multipart/form-data" class="finance-form">
                    @csrf
                    <input type="hidden" name="_transaction_form" value="expense">
                    @include('admin::invoices.partials.transaction-errors', ['showErrors' => $restoreExpense])
                    <label class="fw-field">Kategori *
                        <select name="category" required>
                            <option value="">Pilih kategori expense</option>
                            @foreach ($expenseCategories->where('is_active', true) as $category)
                                <option value="{{ $category->code }}" @selected($restoreExpense && old('category') === $category->code)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (bouncer()->hasPermission('settings.finance.expense-categories'))
                        <a class="fw-link" href="{{ route('admin.settings.expense-categories.index') }}" target="_blank" rel="noopener">Kelola kategori di Settings ↗</a>
                    @endif
                    <label class="fw-field">Deskripsi expense *<input name="description" maxlength="255" required placeholder="Contoh: Transport kru ke lokasi event" value="{{ $restoreExpense ? old('description') : '' }}"></label>
                    <div class="finance-form-grid">
                        <label class="fw-field">Nominal (Rp) *<input type="number" name="amount" min="0.01" step="0.01" required inputmode="decimal" placeholder="0" value="{{ $restoreExpense ? old('amount') : '' }}"></label>
                        <label class="fw-field">Tanggal expense *<input type="date" name="expense_date" required value="{{ $restoreExpense ? old('expense_date') : now()->format('Y-m-d') }}"></label>
                    </div>
                    <label class="fw-field">Vendor <span class="finance-optional">opsional</span><input name="vendor_name" maxlength="255" placeholder="Nama vendor / penerima" value="{{ $restoreExpense ? old('vendor_name') : '' }}"></label>
                    <label class="fw-field">Nomor referensi <span class="finance-optional">opsional</span><input name="reference_number" maxlength="255" placeholder="Nomor bon / referensi" value="{{ $restoreExpense ? old('reference_number') : '' }}"></label>
                    <label class="fw-field finance-receipt">Bon pengeluaran <span class="finance-optional">opsional</span>
                        <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="expense-receipt-help">
                        <span class="finance-help" id="expense-receipt-help">PDF, JPG, atau PNG · maksimal 5 MB.</span>
                    </label>
                    <label class="fw-field">Catatan <span class="finance-optional">opsional</span><textarea name="notes" rows="2" placeholder="Catatan pengeluaran…">{{ $restoreExpense ? old('notes') : '' }}</textarea></label>
                    <button type="submit" class="primary-button finance-submit">Simpan Expense</button>
                </form>
            @endif
        </section>
    @endif
</aside>
