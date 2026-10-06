<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PhotoboothCatalogService
{
    public function definition(): array
    {
        return require __DIR__.'/../Config/photobooth-catalog.php';
    }

    public function preview(array $mapping = []): array
    {
        return $this->plan($this->snapshot(), $mapping);
    }

    public function apply(array $mapping, string $expected, int $actorId, string $reason): array
    {
        if (strlen(trim($reason)) < 8 || ! DB::table('users')->where('id', $actorId)->exists()) {
            throw new RuntimeException('Isi alasan koreksi dan ID pengguna CRM pelaksana.');
        }

        return DB::transaction(function () use ($mapping, $expected, $actorId, $reason) {
            $snapshot = $this->snapshot(true);
            $plan = $this->plan($snapshot, $mapping);
            if (! hash_equals($plan['fingerprint'], $expected)) {
                throw new RuntimeException('Katalog atau mapping berubah. Tinjau preview terbaru.');
            }
            if ($plan['errors']) {
                throw new RuntimeException(implode("\n", $plan['errors']));
            }

            $auditId = DB::table('crm_data_corrections')->insertGetId([
                'kind' => 'photobooth_catalog', 'actor_id' => $actorId, 'reason' => trim($reason),
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'result' => json_encode($plan, JSON_THROW_ON_ERROR), 'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Two passes avoid collisions when PRD-0001 already belongs to a
            // different product. EAV values are updated in the same transaction.
            $temporaryPrefix = 'CRM-TEMP-'.bin2hex(random_bytes(8)).'-';
            foreach ($plan['products'] as $row) {
                $this->updateProduct($row['id'], ['sku' => $temporaryPrefix.$row['id']], $snapshot['attributes']);
            }
            foreach ($plan['products'] as $row) {
                $this->updateProduct($row['id'], ['sku' => $row['new_sku'], 'category' => 'Photobooth'], $snapshot['attributes']);
            }

            $definition = $this->definition();
            $inventoryIds = [];
            foreach ($plan['inventory'] as $key => $row) {
                if (isset($row['create'])) {
                    $item = $definition['inventory'][$key];
                    $inventoryIds[$key] = DB::table('inventory_items')->insertGetId([
                        'code' => $row['create']['code'], 'name' => $item['name'],
                        'tracking_type' => $item['tracking_type'], 'unit' => $item['unit'],
                        'warehouse_id' => $row['create']['warehouse_id'], 'is_active' => true,
                        'quantity_on_hand' => 0, 'minimum_stock' => 0,
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $inventoryIds[$key] = $row['id'];
                }
            }

            foreach ($plan['templates'] as $key => $productIds) {
                $template = $definition['templates'][$key];
                foreach ($productIds as $productId) {
                    $existing = DB::table('product_equipment_templates')->where('product_id', $productId)->first();
                    $values = ['name' => $template['name'].' Equipment Template', 'is_active' => true, 'updated_at' => date('Y-m-d H:i:s')];
                    if ($existing) {
                        $templateId = $existing->id;
                        DB::table('product_equipment_templates')->where('id', $templateId)->update($values);
                    } else {
                        $templateId = DB::table('product_equipment_templates')->insertGetId($values + ['product_id' => $productId, 'created_at' => date('Y-m-d H:i:s')]);
                    }
                    DB::table('product_equipment_template_items')->where('template_id', $templateId)->delete();
                    $order = 0;
                    foreach ($template['items'] as $itemKey => $quantity) {
                        $item = $definition['inventory'][$itemKey];
                        DB::table('product_equipment_template_items')->insert([
                            'template_id' => $templateId, 'inventory_item_id' => $inventoryIds[$itemKey],
                            'name' => $item['name'], 'quantity' => $quantity, 'unit' => $item['unit'],
                            'sort_order' => $order++, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            return ['audit_id' => $auditId, 'products_updated' => count($plan['products']), 'inventory_ids' => $inventoryIds, 'templates' => $plan['templates']];
        });
    }

    private function plan(array $snapshot, array $mapping): array
    {
        $definition = $this->definition();
        $errors = [];
        foreach (array_diff(array_keys($mapping), ['inventory', 'templates']) as $key) {
            $errors[] = 'Mapping key tidak dikenal: '.$key;
        }
        foreach (['inventory', 'templates'] as $section) {
            if (isset($mapping[$section]) && ! is_array($mapping[$section])) {
                throw new RuntimeException('Mapping '.$section.' harus berupa object.');
            }
            foreach (array_diff(array_keys($mapping[$section] ?? []), array_keys($definition[$section])) as $key) {
                $errors[] = 'Mapping '.$section.' tidak dikenal: '.$key;
            }
        }
        foreach ($snapshot['attributes'] as $attribute) {
            if ($attribute['type'] !== 'text') {
                $errors[] = 'Attribute '.$attribute['code'].' harus ditinjau karena bukan tipe text.';
            }
        }

        $products = [];
        foreach ($snapshot['products'] as $index => $product) {
            $products[] = array_intersect_key($product, array_flip(['id', 'name', 'sku', 'category'])) + [
                'new_sku' => 'PRD-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), 'new_category' => 'Photobooth',
            ];
        }
        if (! $products) {
            $errors[] = 'Tidak ada produk untuk diperbarui.';
        }

        $inventory = [];
        $usedInventory = [];
        $newCodes = [];
        foreach ($definition['inventory'] as $key => $item) {
            $selection = $mapping['inventory'][$key] ?? null;
            if (is_array($selection) && isset($selection['create'])) {
                $create = $selection['create'];
                $code = trim((string) ($create['code'] ?? ''));
                $warehouseId = (int) ($create['warehouse_id'] ?? 0);
                $codeKey = strtolower($code);
                if ($code === '' || strlen($code) > 50 || in_array($codeKey, array_map(fn ($i) => strtolower($i['code']), $snapshot['inventory']), true) || in_array($codeKey, $newCodes, true)) {
                    $errors[] = $key.': kode master baru kosong, terlalu panjang, atau sudah digunakan.';
                }
                if (! in_array($warehouseId, array_column($snapshot['warehouses'], 'id'), true)) {
                    $errors[] = $key.': warehouse_id tidak valid.';
                }
                if ($this->matches($snapshot['inventory'], array_merge([$item['name']], $item['aliases'] ?? []))) {
                    $errors[] = $key.': master dengan nama ini sudah ada; pilih ID yang benar.';
                }
                $newCodes[] = $codeKey;
                $inventory[$key] = $item + ['create' => ['code' => $code, 'warehouse_id' => $warehouseId], 'opening_stock' => 0];

                continue;
            }
            $matches = $selection === null
                ? $this->matches($snapshot['inventory'], array_merge([$item['name']], $item['aliases'] ?? []))
                : array_values(array_filter($snapshot['inventory'], fn ($row) => (string) $row['id'] === (string) $selection));
            if (count($matches) !== 1) {
                $errors[] = $key.': pilih satu inventory ID melalui mapping; hasil pencarian '.count($matches).'.';
                $inventory[$key] = ['required' => $item, 'candidates' => $matches];

                continue;
            }
            $row = $matches[0];
            if (! $row['is_active'] || $row['tracking_type'] !== $item['tracking_type'] || ! $this->compatibleUnit($row['unit'], $item['unit'])) {
                $errors[] = $key.': tracking, satuan, atau status aktif master tidak sesuai. Perubahan tipe stok memerlukan pemeriksaan terpisah.';
            }
            if (in_array($row['id'], $usedInventory, true)) {
                $errors[] = $key.': ID inventory sudah dipakai untuk jenis barang lain.';
            }
            $usedInventory[] = $row['id'];
            $inventory[$key] = array_intersect_key($row, array_flip(['id', 'code', 'name', 'tracking_type', 'unit']));
        }

        $templates = [];
        $usedProducts = [];
        foreach ($definition['templates'] as $key => $template) {
            $ids = $mapping['templates'][$key] ?? array_column($this->matches($snapshot['products'], $template['product_names']), 'id');
            if (! is_array($ids)) {
                $errors[] = $key.': isi daftar product ID sebagai array.';
                $ids = [];
            }
            if (! $ids && ! ($template['optional'] ?? false)) {
                $errors[] = $key.': produk untuk template belum dipetakan.';
            }
            foreach ($ids as $id) {
                if (! is_int($id) || ! in_array($id, array_column($snapshot['products'], 'id'), true) || in_array($id, $usedProducts, true)) {
                    $errors[] = $key.': product ID tidak valid atau dipakai pada dua template.';
                }
                $usedProducts[] = $id;
            }
            $templates[$key] = $ids;
        }

        return [
            'scope' => 'SEMUA produk: SKU berurutan menurut ID dan kategori Photobooth.',
            'products' => $products, 'inventory' => $inventory, 'templates' => $templates, 'errors' => $errors,
            'fingerprint' => hash('sha256', json_encode([$snapshot, $mapping, $definition], JSON_THROW_ON_ERROR)),
        ];
    }

    private function snapshot(bool $lock = false): array
    {
        $tables = [
            'products' => DB::table('products'), 'inventory' => DB::table('inventory_items'),
            'warehouses' => DB::table('warehouses'),
            'attributes' => DB::table('attributes')->where('entity_type', 'products')->whereIn('code', ['sku', 'category']),
            'attribute_values' => DB::table('attribute_values')->where('entity_type', 'products')->whereIn('attribute_id', function ($q) {
                $q->select('id')->from('attributes')->where('entity_type', 'products')->whereIn('code', ['sku', 'category']);
            }),
            'templates' => DB::table('product_equipment_templates'), 'template_items' => DB::table('product_equipment_template_items'),
        ];
        $snapshot = [];
        foreach ($tables as $key => $query) {
            $query->orderBy('id');
            $snapshot[$key] = ($lock ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    private function updateProduct(int $id, array $values, array $attributes): void
    {
        DB::table('products')->where('id', $id)->update($values + ['updated_at' => date('Y-m-d H:i:s')]);
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute['code'], $values)) {
                DB::table('attribute_values')->updateOrInsert([
                    'entity_type' => 'products', 'entity_id' => $id, 'attribute_id' => $attribute['id'],
                ], ['text_value' => $values[$attribute['code']]]);
            }
        }
    }

    private function matches(array $rows, array $names): array
    {
        $normalize = fn ($name) => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $name));
        $names = array_map($normalize, $names);

        return array_values(array_filter($rows, fn ($row) => in_array($normalize($row['name']), $names, true)));
    }

    private function compatibleUnit(string $actual, string $expected): bool
    {
        $actual = strtolower(trim($actual));

        return $actual === $expected || ($expected === 'unit' && in_array($actual, ['pcs', 'piece'], true));
    }
}
