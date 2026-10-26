@if (is_array($value))
    <details>
        <summary class="cursor-pointer underline">{{ count($value) }} elemen · lihat semua</summary>
        <pre style="max-height:220px;max-width:360px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere;" class="mt-2 text-xs">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </details>
@elseif (is_bool($value))
    {{ $value ? 'Ya' : 'Tidak' }}
@elseif ($value === null)
    —
@elseif (is_numeric($value))
    {{ $money ? 'Rp '.number_format((float) $value, 4, ',', '.') : number_format((float) $value, 0, ',', '.') }}
@else
    {{ $value }}
@endif
