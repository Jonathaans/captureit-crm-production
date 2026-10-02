<?php

namespace Webkul\Admin\Http\Controllers\Products;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Product\ProductDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\ProductResource;
use Webkul\Product\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;
use Webkul\Product\Models\ProductEquipmentTemplate;
use Webkul\Product\Models\ProductEquipmentTemplateItem;

class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected ProductRepository $productRepository)
    {
        request()->request->add(['entity_type' => 'products']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(ProductDataGrid::class)->process();
        }

        return view('admin::products.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::products.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request)
    {
        $request->validate([
            'unit' => 'sometimes|required|in:pcs,day',
            'category' => 'nullable|string|max:255',
        ]);

        Event::dispatch('product.create.before');

        $product = $this->productRepository->create($request->all());

        Event::dispatch('product.create.after', $product);

        if (request()->ajax()) {
            return response()->json([
                'data' => $product,
                'message' => trans('admin::app.products.index.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.products.index.create-success'));

        return redirect()->route('admin.products.index');
    }

    /**
     * Show the form for viewing the specified resource.
     */
    public function view(int $id): View
    {
        $product = $this->productRepository->findOrFail($id);

        return view('admin::products.view', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|JsonResponse
{
    $product = $this->productRepository->findOrFail($id);

    $product->load([
        'equipmentTemplate.items',
    ]);

    $inventories = $product->inventories()
        ->with('location')
        ->get()
        ->map(function ($inventory) {
            return [
                'id'                    => $inventory->id,
                'name'                  => $inventory->location->name,
                'warehouse_id'          => $inventory->warehouse_id,
                'warehouse_location_id' => $inventory->warehouse_location_id,
                'in_stock'              => $inventory->in_stock,
                'allocated'             => $inventory->allocated,
            ];
        });

    return view(
        'admin::products.edit',
        compact(
            'product',
            'inventories'
        )
    );
}

    /**
     * Update the specified resource in storage.
     */
   public function update(AttributeForm $request, int $id)
{
    /*
    |--------------------------------------------------------------------------
    | Validate Equipment Template
    |--------------------------------------------------------------------------
    */

    $request->validate([
        'unit' => 'sometimes|required|in:pcs,day',
        'category' => 'nullable|string|max:255',
        'equipment_template_name' => [
            'nullable',
            'string',
            'max:255',
        ],

        'equipment_template_active' => [
            'nullable',
            'boolean',
        ],

        'equipment_template_notes' => [
            'nullable',
            'string',
        ],

        'equipment_items' => [
            'nullable',
            'array',
        ],

        'equipment_items.*.inventory_item_id' => [
            'nullable',
            'integer',
            'exists:inventory_items,id',
        ],

        'equipment_items.*.name' => [
            'nullable',
            'string',
            'max:255',
        ],

        'equipment_items.*.description' => [
            'nullable',
            'string',
        ],

        'equipment_items.*.quantity' => [
            'nullable',
            'numeric',
            'min:0.01',
        ],

        'equipment_items.*.unit' => [
            'nullable',
            'string',
            'max:30',
        ],

        'equipment_items.*.notes' => [
            'nullable',
            'string',
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Pisahkan data Product dengan Equipment Template
    |--------------------------------------------------------------------------
    */

    $productData = $request->except([
        'equipment_template_name',
        'equipment_template_active',
        'equipment_template_notes',
        'equipment_items',
    ]);

    Event::dispatch(
        'product.update.before',
        $id
    );

    $product = DB::transaction(
        function () use (
            $request,
            $productData,
            $id
        ) {
            /*
            |--------------------------------------------------------------------------
            | Update Product seperti biasa
            |--------------------------------------------------------------------------
            */

            $product = $this
                ->productRepository
                ->update(
                    $productData,
                    $id
                );

            /*
            |--------------------------------------------------------------------------
            | Create / Update Equipment Template
            |--------------------------------------------------------------------------
            */

            $templateName = trim(
                (string) $request->input(
                    'equipment_template_name'
                )
            );

            if ($templateName === '') {
                $templateName =
                    $product->name
                    .' Equipment Template';
            }

            $template = ProductEquipmentTemplate::updateOrCreate(
                [
                    'product_id' => $product->id,
                ],
                [
                    'name' => $templateName,

                    'is_active' =>
                        $request->boolean(
                            'equipment_template_active'
                        ),

                    'notes' =>
                        $request->input(
                            'equipment_template_notes'
                        ),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Rebuild Template Items
            |--------------------------------------------------------------------------
            |
            | Template product adalah master.
            | Setiap save, item lama dibangun ulang dari form.
            |
            */

            $template
                ->items()
                ->delete();

            $equipmentItems =
                $request->input(
                    'equipment_items',
                    []
                );

            $sortOrder = 0;

            foreach ($equipmentItems as $item) {
                $name = trim(
                    (string) (
                        $item['name']
                        ?? ''
                    )
                );

                /*
                 * Row kosong tidak disimpan.
                 */
                if ($name === '') {
                    continue;
                }

                ProductEquipmentTemplateItem::create([
                    'template_id' =>
                        $template->id,

                    'inventory_item_id' =>
                        ! empty($item['inventory_item_id'])
                            ? (int) $item['inventory_item_id']
                            : null,

                    'name' =>
                        $name,

                    'description' =>
                        $item['description']
                        ?? null,

                    'quantity' =>
                        $item['quantity']
                        ?? 1,

                    'unit' =>
                        ! empty($item['unit'])
                            ? $item['unit']
                            : 'unit',

                    'notes' =>
                        $item['notes']
                        ?? null,

                    'sort_order' =>
                        $sortOrder++,
                ]);
            }

            return $product;
        }
    );

    Event::dispatch(
        'product.update.after',
        $product
    );

    if (request()->ajax()) {
        return response()->json([
            'message' => trans(
                'admin::app.products.index.update-success'
            ),
        ]);
    }

    session()->flash(
        'success',
        trans(
            'admin::app.products.index.update-success'
        )
    );

    /*
     * Saya ubah redirect agar kembali ke Edit Product.
     *
     * Ini lebih enak untuk mengatur Equipment Template.
     */
    return redirect()->route(
        'admin.products.edit',
        $product->id
    );
}

    /**
     * Store a newly created resource in storage.
     */
    public function storeInventories(int $id, ?int $warehouseId = null): JsonResponse
    {
        $this->validate(request(), [
            'inventories' => 'array',
            'inventories.*.warehouse_location_id' => 'required',
            'inventories.*.warehouse_id' => 'required',
            'inventories.*.in_stock' => 'required|integer|min:0',
            'inventories.*.allocated' => 'required|integer|min:0',
        ]);

        $product = $this->productRepository->findOrFail($id);

        Event::dispatch('product.update.before', $id);

        $this->productRepository->saveInventories(request()->all(), $id, $warehouseId);

        Event::dispatch('product.update.after', $product);

        return new JsonResponse([
            'message' => trans('admin::app.products.index.update-success'),
        ], 200);
    }

    /**
     * Search product results
     */
    public function search(): JsonResource
    {
        $query = $this->productRepository
            ->pushCriteria(app(RequestCriteria::class))
            ->orderBy('created_at', 'desc');

        $excludedIds = request()->input('exclude_ids', []);

        if (is_string($excludedIds)) {
            $excludedIds = array_filter(array_map('trim', explode(',', $excludedIds)));
        }

        if (! empty($excludedIds)) {
            $query->whereNotIn('products.id', $excludedIds);
        }

        $products = $query->get();

        return ProductResource::collection($products);
    }

    /**
     * Returns product inventories grouped by warehouse.
     */
    public function warehouses(int $id): JsonResponse
    {
        $warehouses = $this->productRepository->getInventoriesGroupedByWarehouse($id);

        return response()->json(array_values($warehouses));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);

        try {
            Event::dispatch('settings.products.delete.before', $id);

            $product->delete($id);

            Event::dispatch('settings.products.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.products.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.products.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $indices = $massDestroyRequest->input('indices');

        foreach ($indices as $index) {
            Event::dispatch('product.delete.before', $index);

            $this->productRepository->delete($index);

            Event::dispatch('product.delete.after', $index);
        }

        return new JsonResponse([
            'message' => trans('admin::app.products.index.delete-success'),
        ]);
    }
}
