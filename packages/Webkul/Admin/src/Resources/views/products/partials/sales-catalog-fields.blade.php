<x-admin::form.control-group>
    <x-admin::form.control-group.label>Category</x-admin::form.control-group.label>
    <x-admin::form.control-group.control
        type="text"
        name="category"
        :value="old('category', $product->category ?? '')"
        label="Category"
        placeholder="Contoh: Photobooth, Print, Add-on"
    />
    <x-admin::form.control-group.error control-name="category" />
</x-admin::form.control-group>

<x-admin::form.control-group>
    <x-admin::form.control-group.label class="required">Default Unit</x-admin::form.control-group.label>
    <x-admin::form.control-group.control
        type="select"
        name="unit"
        :value="old('unit', $product->unit ?? 'pcs')"
        rules="required"
        label="Default Unit"
    >
        <option value="pcs">Pcs</option>
        <option value="day">Day</option>
    </x-admin::form.control-group.control>
    <x-admin::form.control-group.error control-name="unit" />
    <p class="mt-1 text-xs text-gray-500">Satuan awal saat produk dipilih. Dapat diubah pada Lead, Quote, atau Invoice.</p>
</x-admin::form.control-group>
