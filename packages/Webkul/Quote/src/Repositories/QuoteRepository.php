<?php

namespace Webkul\Quote\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Core\Support\SalesLineItem;
use Webkul\Lead\Repositories\ProductRepository;
use Webkul\Quote\Contracts\Quote;

class QuoteRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'subject',
        'description',
        'person_id',
        'person.name',
        'user_id',
        'user.name',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected QuoteItemRepository $quoteItemRepository,
        protected ProductRepository $productRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Quote::class;
    }

    /**
     * Create.
     *
     * @return Quote
     */
    public function create(array $data)
    {
        $data = $this->prepareItems($data);

        return DB::transaction(function () use ($data) {
            $quote = parent::create($data);

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $quote->id,
            ]));

            foreach ($data['items'] as $itemData) {
                $this->quoteItemRepository->create(array_merge($itemData, [
                    'quote_id' => $quote->id,
                ]));
            }

            return $quote->fresh('items');
        });
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @param  array  $attribute
     * @return Quote
     */
    public function update(array $data, $id, $attributes = [])
    {
        $quote = $this->find($id);

        if (empty($attributes) && isset($data['items'])) {
            $data = $this->prepareItems($data, $quote);
        }

        return DB::transaction(function () use ($data, $id, $attributes, $quote) {

            $originalLeadId = $quote->lead_id;

            parent::update($data, $id);

            /**
             * If attributes are provided then only save the provided attributes and return.
             */
            if (! empty($attributes)) {
                $conditions = ['entity_type' => $data['entity_type']];

                if (isset($data['quick_add'])) {
                    $conditions['quick_add'] = 1;
                }

                $attributes = $this->attributeRepository->where($conditions)
                    ->whereIn('code', $attributes)
                    ->get();

                $this->attributeValueRepository->save(array_merge($data, [
                    'entity_id' => $quote->id,
                ]), $attributes);

                return $quote->fresh('items');
            }

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $quote->id,
            ]));

            if (! isset($data['items'])) {
                return $quote->fresh('items');
            }

            $previousItemIds = $quote->items->pluck('id');

            if (isset($data['items'])) {
                foreach ($data['items'] as $itemId => $itemData) {
                    if (Str::contains($itemId, 'item_')) {
                        $this->quoteItemRepository->create(array_merge($itemData, [
                            'quote_id' => $id,
                        ]));

                        if (! empty($data['lead_id'])) {
                            $this->productRepository->updateOrCreate(
                                [
                                    'lead_id' => $data['lead_id'],
                                    'product_id' => $itemData['product_id'],
                                ],
                                [
                                    'lead_id' => $data['lead_id'],
                                    'unit' => $itemData['unit'],
                                    'day' => 1,
                                    'quantity' => $itemData['quantity'],
                                    'price' => $itemData['price'],
                                ]
                            );
                        }
                    } else {
                        if (is_numeric($index = $previousItemIds->search($itemId))) {
                            $previousItemIds->forget($index);
                        }

                        $this->quoteItemRepository->update($itemData, $itemId);
                    }
                }
            }

            foreach ($previousItemIds as $itemId) {
                if (! empty($originalLeadId)) {
                    $deletedItem = $quote->items->firstWhere('id', $itemId);

                    if ($deletedItem?->product_id) {
                        $this->productRepository->deleteWhere([
                            'lead_id' => $originalLeadId,
                            'product_id' => $deletedItem->product_id,
                        ]);
                    }
                }

                $this->quoteItemRepository->delete($itemId);
            }

            return $quote->fresh('items');
        });
    }

    /** Prepare billing and equipment snapshots before persisting the header. */
    private function prepareItems(array $data, $quote = null): array
    {
        if (! isset($data['items'])) {
            return $data;
        }

        $existing = $quote?->items->keyBy('id') ?? collect();
        $leadProducts = ! empty($data['lead_id'])
            ? $this->productRepository->findWhere(['lead_id' => $data['lead_id']])->keyBy('product_id')
            : collect();
        $catalog = app(\Webkul\Product\Repositories\ProductRepository::class);
        $subtotal = $discount = $tax = 0;

        foreach ($data['items'] as $key => $item) {
            $source = $existing->get($key);

            if ($quote && ! Str::contains((string) $key, 'item_') && ! $source) {
                throw ValidationException::withMessages(['items' => 'Item does not belong to this quote.']);
            }

            if (! $source || (int) $source->product_id !== (int) $item['product_id']) {
                $source = $leadProducts->get($item['product_id']);
            }

            $product = $catalog->findOrFail($item['product_id']);
            $item = SalesLineItem::prepare($item, $source?->getAttributes(), $product->unit ?: 'pcs');
            $item['total'] = $item['amount'];
            $item['discount_amount'] = round((float) ($item['discount_amount'] ?? 0), 4);
            $item['tax_amount'] = round((float) ($item['tax_amount'] ?? 0), 4);
            $item['final_total'] = $item['total'] - $item['discount_amount'] + $item['tax_amount'];

            if ($item['final_total'] < 0) {
                throw ValidationException::withMessages(['items' => 'Line total cannot be negative.']);
            }

            $data['items'][$key] = $item;
            $subtotal += $item['total'];
            $discount += $item['discount_amount'];
            $tax += $item['tax_amount'];
        }

        $data['sub_total'] = round($subtotal, 4);
        $data['discount_amount'] = round($discount, 4);
        $data['tax_amount'] = round($tax, 4);
        $data['grand_total'] = round(max(0, $subtotal - $discount + $tax + (float) ($data['adjustment_amount'] ?? 0)), 4);

        return $data;
    }

    /**
     * Retrieves customers count based on date.
     *
     * @return number
     */
    public function getQuotesCount($startDate, $endDate)
    {
        return $this
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->count();
    }
}
