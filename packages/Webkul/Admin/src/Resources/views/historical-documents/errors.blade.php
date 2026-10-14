@if ($errors->any())
    <div role="alert" class="fw-note"><strong>Data belum disimpan. Periksa kembali:</strong><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
