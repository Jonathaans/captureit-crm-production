<tr>
    <td><select aria-label="Produk CRM" data-product name="documents[0][items][{{ $i }}][product_id]"><option value="">Tanpa produk master (invoice)</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-name="{{ $product->name }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>{{ $product->sku }} · {{ $product->name }}</option>@endforeach</select><input aria-label="Nama item" data-item-name name="documents[0][items][{{ $i }}][name]" placeholder="Nama item pada dokumen lama" required value="{{ $item['name'] ?? '' }}"><input aria-label="Deskripsi item" name="documents[0][items][{{ $i }}][description]" placeholder="Deskripsi (opsional)" value="{{ $item['description'] ?? '' }}"></td>
    <td><input aria-label="Jumlah" name="documents[0][items][{{ $i }}][quantity]" type="number" min="1" step="1" required value="{{ $item['quantity'] ?? 1 }}"></td>
    <td><input aria-label="Hari" name="documents[0][items][{{ $i }}][day]" type="number" min="1" step="1" required value="{{ $item['day'] ?? 1 }}"></td>
    <td><input aria-label="Total item" name="documents[0][items][{{ $i }}][total]" type="number" min="0.01" step="0.01" required value="{{ $item['total'] ?? '' }}"></td>
    <td><button type="button" class="fw-link" data-remove-row>Hapus</button></td>
</tr>
