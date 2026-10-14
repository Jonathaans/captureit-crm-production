<tr>
    <td><input aria-label="Tanggal pembayaran" name="documents[0][payments][{{ $i }}][paid_at]" type="date" max="{{ today()->toDateString() }}" required value="{{ $payment['paid_at'] ?? '' }}"></td>
    <td><input aria-label="Nominal pembayaran" name="documents[0][payments][{{ $i }}][amount]" type="number" min="0.01" step="0.01" required value="{{ $payment['amount'] ?? '' }}"></td>
    <td><input aria-label="Metode pembayaran" name="documents[0][payments][{{ $i }}][payment_method]" required placeholder="Transfer bank" value="{{ $payment['payment_method'] ?? '' }}"><input aria-label="Referensi pembayaran" name="documents[0][payments][{{ $i }}][reference_number]" placeholder="Nomor referensi" value="{{ $payment['reference_number'] ?? '' }}"></td>
    <td><button type="button" class="fw-link" data-remove-row>Hapus</button></td>
</tr>
