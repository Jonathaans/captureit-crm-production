<tr data-revision-item>
    <td style="min-width:250px">
        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item['id'] ?? '' }}">
        <label class="fw-field">Produk
            <select name="items[{{ $index }}][product_id]" data-product required>
                <option value="">Pilih produk</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" data-name="{{ $product->name }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>{{ $product->sku }} · {{ $product->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="fw-field" style="margin-top:10px">Nama di dokumen<input name="items[{{ $index }}][name]" data-name required maxlength="255" value="{{ $item['name'] ?? '' }}"></label>
        <label class="fw-field" style="margin-top:10px">Deskripsi<textarea name="items[{{ $index }}][description]" rows="2">{{ $item['description'] ?? '' }}</textarea></label>
    </td>
    <td style="min-width:110px">
        <label class="fw-field">Qty<input type="number" min="1" max="1000000" step="1" required name="items[{{ $index }}][quantity]" data-quantity value="{{ $item['quantity'] ?? 1 }}"></label>
        <label class="fw-field" style="margin-top:10px">Satuan<select name="items[{{ $index }}][unit]"><option value="pcs" @selected(($item['unit'] ?? 'pcs') === 'pcs')>pcs</option><option value="day" @selected(($item['unit'] ?? '') === 'day')>day</option></select></label>
    </td>
    <td style="min-width:175px"><label class="fw-field">Harga satuan (Rp)<input type="number" min="0" step="0.0001" required name="items[{{ $index }}][price]" data-price value="{{ $item['price'] ?? 0 }}"></label></td>
    <td style="min-width:170px">
        <label class="fw-field">Diskon item (Rp)<input type="number" min="0" step="0.01" required name="items[{{ $index }}][discount_amount]" data-discount value="{{ $item['discount_amount'] ?? 0 }}"></label>
        <label class="fw-field" style="margin-top:10px">Pajak item (Rp)<input type="number" min="0" step="0.01" required name="items[{{ $index }}][tax_amount]" data-tax value="{{ $item['tax_amount'] ?? 0 }}"></label>
    </td>
    <td class="fw-number"><strong data-line-total></strong><br><button type="button" data-remove-item class="fw-link" style="margin-top:14px">Hapus item</button></td>
</tr>
