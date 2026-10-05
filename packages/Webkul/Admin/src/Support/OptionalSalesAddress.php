<?php

namespace Webkul\Admin\Support;

final class OptionalSalesAddress
{
    public static function appliesTo(string $entityType): bool
    {
        return in_array($entityType, ['leads', 'quotes'], true);
    }

    public static function rules(string $key): array
    {
        return [
            $key => ['nullable', 'array'],
            $key.'.address' => ['nullable', 'string'],
            $key.'.country' => ['nullable', 'string', 'max:2'],
            $key.'.state' => ['nullable', 'string', 'max:100'],
            $key.'.city' => ['nullable', 'string', 'max:100'],
            $key.'.postcode' => ['nullable', 'string', 'max:20'],
        ];
    }
}
