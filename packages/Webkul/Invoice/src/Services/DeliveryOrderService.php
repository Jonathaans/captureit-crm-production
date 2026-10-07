<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Invoice\Models\Invoice;
use Webkul\Product\Models\ProductEquipmentTemplate;
use Webkul\Product\Support\EquipmentQuantity;

class DeliveryOrderService
{
    public function __construct(
        protected DeliveryOrderNumberService $numberService
    ) {
    }

    /**
     * Membuat Surat Jalan dari Invoice.
     *
     * One Invoice -> Many Delivery Orders.
     *
     * Aturan:
     * - SJ pertama tetap auto-populate requirement dari Product Equipment Template.
     * - SJ kedua dan seterusnya dibuat sebagai DRAFT kosong agar requirement
     *   pengiriman pertama tidak terduplikasi secara tidak sengaja.
     * - Setiap pemanggilan selalu meminta nomor SJ baru dari number service.
     * - Inventory allocation / issue / return tetap berdiri sendiri per SJ.
     */
    public function createFromInvoice(
        Invoice $invoice,
        ?int $createdBy = null,
        ?int $workOrderId = null
    ): DeliveryOrder {
        /*
         * SPK WORK ORDER V1 GUARD
         *
         * Surat Jalan is no longer allowed to be generated directly
         * from Invoice. Every new SJ must have an SPK parent.
         */
        if (! $workOrderId) {
            throw new \LogicException(
                'Surat Jalan harus dibuat dari Surat Perintah Kerja (SPK), bukan langsung dari Invoice.'
            );
        }
        return DB::transaction(
            function () use (
                $invoice,
                $createdBy,
                $workOrderId
            ) {
                /*
                |--------------------------------------------------------------------------
                | Lock Invoice
                |--------------------------------------------------------------------------
                |
                | Serialize generation untuk Invoice yang sama sehingga dua request
                | yang datang hampir bersamaan tidak sama-sama dianggap "SJ pertama".
                |
                */

                $invoice = Invoice::query()
                    ->whereKey($invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingDeliveryOrderCount =
                    DeliveryOrder::query()
                        ->where(
                            'invoice_id',
                            $invoice->id
                        )
                        ->count();

                $isFirstDeliveryOrder =
                    $existingDeliveryOrderCount === 0;

                /*
                |--------------------------------------------------------------------------
                | Load Data
                |--------------------------------------------------------------------------
                */

                $invoice->loadMissing([
                    'quote',
                    'person',
                    'user',
                    'items',
                ]);

                $customerName =
                    $invoice->person?->name;

                $salesPersonName =
                    $invoice->user?->name;

                $deliveryAddress =
                    $this->formatAddress(
                        $invoice->shipping_address
                        ?: $invoice->billing_address
                    );

                /*
                |--------------------------------------------------------------------------
                | Create New Delivery Order Header
                |--------------------------------------------------------------------------
                |
                | generateForInvoice() dipanggil SETIAP kali.
                | Artinya nomor Surat Jalan mengikuti sequence global SJ yang
                | sudah digunakan sistem, bukan menggunakan nomor Invoice.
                |
                */

                $deliveryOrder = DeliveryOrder::create([
                    'delivery_order_number' =>
                        $this->numberService
                            ->generateForInvoice(
                                $invoice
                            ),

                    'invoice_id' =>
                        $invoice->id,

                    'quote_id' =>
                        $invoice->quote_id,

                    'invoice_number' =>
                        $invoice->invoice_number,

                    'quote_number' =>
                        $invoice->quote?->quote_number,

                    'project_code' =>
                        $invoice->project_code,

                    'business_unit' =>
                        $invoice->business_unit,

                    'project_name' =>
                        $invoice->subject,

                    'person_id' =>
                        $invoice->person_id,

                    'customer_name' =>
                        $customerName,

                    'user_id' =>
                        $invoice->user_id,

                    'sales_person_name' =>
                        $salesPersonName,

                    'recipient_name' =>
                        $customerName,

                    'recipient_phone' =>
                        null,

                    'pic_name' =>
                        null,

                    'pic_phone' =>
                        null,

                    'event_date' =>
                        $invoice->event_date,

                    'event_time' =>
                        null,

                    'location' =>
                        $invoice->location,

                    'delivery_address' =>
                        $deliveryAddress,

                    'delivery_date' =>
                        $invoice->event_date,

                    'delivery_time' =>
                        null,

                    'status' =>
                        'draft',

                    /*
                     * SJ tambahan sengaja diberi catatan agar operator tahu
                     * bahwa requirement harus diisi hanya dengan barang
                     * tertinggal / tambahan untuk pengiriman tersebut.
                     */
                    'notes' =>
                        $isFirstDeliveryOrder
                            ? null
                            : sprintf(
                                'Additional Surat Jalan #%d untuk %s. Isi hanya requirement barang tambahan / tertinggal pada pengiriman ini.',
                                $existingDeliveryOrderCount + 1,
                                $invoice->invoice_number
                            ),

                    'created_by' =>
                        $createdBy,
                ]);


                /*
                 * SPK WORK ORDER V1 LINK
                 */
                $deliveryOrder->forceFill([
                    'work_order_id' =>
                        $workOrderId,
                ])->save();
                /*
                |--------------------------------------------------------------------------
                | First SJ Only: Copy Standard Equipment Requirement
                |--------------------------------------------------------------------------
                |
                | SJ tambahan TIDAK menyalin semua equipment lagi.
                | Ini mencegah seluruh Camera / Printer / Lighting dari SJ pertama
                | masuk lagi dan tanpa sengaja dialokasikan dua kali.
                |
                */

                if ($isFirstDeliveryOrder) {
                    $this->copyEquipmentFromInvoice(
                        $invoice,
                        $deliveryOrder
                    );
                }

                return $deliveryOrder;
            }
        );
    }

    protected function copyEquipmentFromInvoice(
        Invoice $invoice,
        DeliveryOrder $deliveryOrder
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Ambil Invoice Item yang mempunyai Product
        |--------------------------------------------------------------------------
        */

        $invoiceItems = $invoice->items
            ->filter(function ($invoiceItem) {
                return ! empty($invoiceItem->product_id);
            });

        if ($invoiceItems->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Ambil semua Product ID pada Invoice
        |--------------------------------------------------------------------------
        */

        $productIds = $invoiceItems
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Ambil Equipment Template yang aktif
        |--------------------------------------------------------------------------
        */

        $templates = ProductEquipmentTemplate::query()
            ->with('items')
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('product_id');

        if ($templates->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Temporary Equipment List
        |--------------------------------------------------------------------------
        |
        | Equipment dengan Name + Description + Unit + Notes yang sama
        | akan digabung quantity-nya.
        |
        | Contoh:
        | Classic Photobooth -> Printer 1
        | Additional Printer -> Printer 1
        |
        | Hasil Surat Jalan -> Printer 2
        |
        */

        $equipment = [];

        foreach ($invoiceItems as $invoiceItem) {
            $template = $templates->get($invoiceItem->product_id);

            /*
             * Product tidak mempunyai template aktif.
             */
            if (! $template) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Quantity Product
            |--------------------------------------------------------------------------
            |
            | Jika Classic Photobooth Qty 2 dan template Lighting Qty 2,
            | maka hasil Surat Jalan Lighting Qty 4.
            |
            */

            foreach ($template->items as $templateItem) {
                $name = trim((string) $templateItem->name);

                if ($name === '') {
                    continue;
                }

                $description = trim(
                    (string) ($templateItem->description ?? '')
                );

                $unit = trim(
                    (string) ($templateItem->unit ?: 'unit')
                );

                $notes = trim(
                    (string) ($templateItem->notes ?? '')
                );

                $quantity = EquipmentQuantity::forLine($templateItem->getAttributes(), $invoiceItem->getAttributes());
                $requiresInventory = $templateItem->requires_inventory
                    || ($templateItem->quantity_basis ?? 'equipment') !== 'equipment';

                /*
                 * Inventory master yang dipetakan dari Equipment Template.
                 *
                 * Null berarti requirement masih berupa text dan belum
                 * terhubung ke Inventory Master.
                 */
                $inventoryItemId = $templateItem->inventory_item_id
                    ? (int) $templateItem->inventory_item_id
                    : null;

                /*
                |--------------------------------------------------------------------------
                | Merge Key
                |--------------------------------------------------------------------------
                |
                | inventory_item_id ikut menjadi bagian merge key.
                | Dua baris text yang sama tetapi menunjuk master inventory
                | berbeda tidak boleh digabung menjadi satu requirement.
                |
                */

                $mergeKey = mb_strtolower(
                    $name
                    .'|'
                    .$description
                    .'|'
                    .$unit
                    .'|'
                    .$notes
                    .'|inventory:'
                    .($inventoryItemId ?? 'none')
                    .'|required:'.(int) $requiresInventory
                    .'|pending:'.(int) ($requiresInventory && $quantity <= 0)
                );

                if (isset($equipment[$mergeKey])) {
                    $equipment[$mergeKey]['quantity'] += $quantity;

                    /*
                     * Jika item hasil merge berasal dari product yang berbeda,
                     * kosongkan referensi product agar tidak misleading.
                     */
                    if (
                        $equipment[$mergeKey]['product_id']
                        !== (int) $invoiceItem->product_id
                    ) {
                        $equipment[$mergeKey]['product_id'] = null;
                        $equipment[$mergeKey]['sku'] = null;
                    }

                    continue;
                }

                $sku = trim(
                    (string) ($invoiceItem->sku ?? '')
                );

                $equipment[$mergeKey] = [
                    'product_id' => (int) $invoiceItem->product_id,
                    'inventory_item_id' => $inventoryItemId,
                    'sku' => $sku !== '' ? $sku : null,

                    'name' => $name,

                    'description' => $description !== ''
                        ? $description
                        : null,

                    'quantity' => $quantity,
                    'requires_inventory' => $requiresInventory,

                    'unit' => $unit !== ''
                        ? $unit
                        : 'unit',

                    'notes' => $notes !== ''
                        ? $notes
                        : null,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan ke delivery_order_items
        |--------------------------------------------------------------------------
        */

        $sortOrder = 0;

        foreach ($equipment as $item) {
            DeliveryOrderItem::create([
                'delivery_order_id' => $deliveryOrder->id,

                'product_id' => $item['product_id'],
                'inventory_item_id' => $item['inventory_item_id'],
                'sku' => $item['sku'],

                'name' => $item['name'],
                'description' => $item['description'],

                'quantity' => $item['quantity'],
                'requires_inventory' => $item['requires_inventory'],
                'unit' => $item['unit'],

                'notes' => $item['notes'],

                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * Ubah address Invoice menjadi text untuk Surat Jalan.
     */
    protected function formatAddress(
        mixed $address
    ): ?string {
        if (empty($address)) {
            return null;
        }

        if (is_string($address)) {
            $address = trim($address);

            return $address !== ''
                ? $address
                : null;
        }

        if (! is_array($address)) {
            return null;
        }

        /*
         * Beberapa kemungkinan key address dari CRM.
         */
        $preferredKeys = [
            'address',
            'street',
            'city',
            'state',
            'postcode',
            'postal_code',
            'zip',
            'country',
        ];

        $parts = [];

        foreach ($preferredKeys as $key) {
            if (
                isset($address[$key])
                && is_scalar($address[$key])
                && trim((string) $address[$key]) !== ''
            ) {
                $parts[] = trim((string) $address[$key]);
            }
        }

        /*
         * Kalau struktur address berbeda,
         * ambil scalar values sebagai fallback.
         */
        if (empty($parts)) {
            foreach ($address as $value) {
                if (
                    is_scalar($value)
                    && trim((string) $value) !== ''
                ) {
                    $parts[] = trim((string) $value);
                }
            }
        }

        $parts = array_values(
            array_unique($parts)
        );

        return empty($parts)
            ? null
            : implode(', ', $parts);
    }
}
