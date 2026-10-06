<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Core\Support\SalesLineItem;

class LeadQuotePrefillService
{
    private array $attributeDefinitions = [];

    public function products(?object $lead): array
    {
        if (! $lead?->products?->isNotEmpty()) {
            return [];
        }

        return $lead->products->map(function ($product) {
            $item = SalesLineItem::display($product->toArray());

            return array_merge($item, [
                'id' => null,
                'description' => $item['description'] ?? $product->product?->description ?? '',
                'total' => (float) $item['price'] * (float) $item['quantity'],
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]);
        })->values()->toArray();
    }

    /**
     * Only fields with a Quotation counterpart become initial form values.
     * Quote numbers, project codes and commercial totals are generated separately.
     */
    public function attributes(object $lead): array
    {
        $address = $this->firstAddress([
            $this->value($lead, 'billing_address'),
            $this->value($lead, 'address'),
            $this->value($lead->person?->organization ?? null, 'address'),
            $this->value($lead->person ?? null, 'address'),
        ]);

        return [
            'subject' => $lead->title ?? '',
            'description' => $lead->description ?? '',
            'person_id' => $lead->person_id ?? null,
            'user_id' => $lead->user_id ?? null,
            'billing_address' => $address,
            'shipping_address' => $address,
            'expired_at' => $this->date($lead->expected_close_date ?? null) ?? now()->toDateString(),
            'event_date' => $this->date($this->value($lead, 'event_date')),
            'location' => $this->value($lead, 'location') ?? '',
            'business_unit' => $this->value($lead, 'business_unit') ?? '',
            'payment_term' => $this->value($lead, 'payment_term') ?? '',
        ];
    }

    private function value(?object $entity, string $code): mixed
    {
        if ($entity instanceof Model && ! array_key_exists($code, $entity->getAttributes())) {
            if (! method_exists($entity, 'getCustomAttributeValue')) {
                return null;
            }

            // Attribute codes may occur on both Lead and Quote. Resolve the
            // correct entity's definition instead of using an unscoped code lookup.
            $table = $entity->getTable();
            $this->attributeDefinitions[$table] ??= app(AttributeRepository::class)
                ->findWhere(['entity_type' => $table])
                ->keyBy('code');
            $attribute = $this->attributeDefinitions[$table]->get($code);

            return $attribute ? $entity->getCustomAttributeValue($attribute) : null;
        }

        return $entity?->{$code} ?? null;
    }

    private function firstAddress(array $candidates): array
    {
        foreach ($candidates as $address) {
            if (is_string($address)) {
                $decoded = json_decode($address, true);
                $address = is_array($decoded) ? $decoded : ['address' => $address];
            }

            if (! is_array($address)) {
                continue;
            }

            $address = array_intersect_key($address, array_flip(['address', 'country', 'state', 'city', 'postcode']));
            $address = array_map(static fn ($value) => is_scalar($value) ? (string) $value : '', $address);

            if (array_filter($address, static fn ($value) => is_scalar($value) && trim((string) $value) !== '') !== []) {
                return $address;
            }
        }

        return [];
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
