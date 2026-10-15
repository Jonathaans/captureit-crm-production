@php($restore = old('_category_form') === $formKey)
@if ($restore && $errors->any())
    <div class="finance-errors" role="alert">
        @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
    </div>
@endif
<label class="fw-field">Nama kategori <span aria-hidden="true">*</span>
    <input name="name" type="text" maxlength="255" required placeholder="Contoh: Akomodasi kru"
        value="{{ $restore ? old('name') : $category?->name }}">
</label>
<label class="fw-field">Deskripsi <span class="finance-optional">opsional</span>
    <textarea name="description" rows="2" maxlength="500" placeholder="Kapan kategori ini digunakan?">{{ $restore ? old('description') : $category?->description }}</textarea>
</label>
