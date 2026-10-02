<?php

namespace Webkul\Lead\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Core\Support\SalesLineItem;

class ProductRepository extends Repository
{
    public function create(array $data)
    {
        $unit = app(\Webkul\Product\Repositories\ProductRepository::class)
            ->findOrFail($data['product_id'])->unit ?: 'pcs';

        return parent::create(SalesLineItem::prepare($data, null, $unit));
    }

    public function update(array $data, $id, $attribute = 'id')
    {
        $existing = $this->findOrFail($id);
        $source = (int) ($data['product_id'] ?? $existing->product_id) === (int) $existing->product_id
            ? $existing->getAttributes() : null;

        return parent::update(SalesLineItem::prepare($data, $source), $id, $attribute);
    }

    public function updateOrCreate(array $attributes, array $values = [])
    {
        $existing = $this->findWhere($attributes)->first();
        $data = array_merge($attributes, $values);

        return $existing ? $this->update($data, $existing->id) : $this->create($data);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Lead\Contracts\Product';
    }
}
