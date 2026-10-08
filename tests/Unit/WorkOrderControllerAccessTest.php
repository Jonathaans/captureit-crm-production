<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CrmCorrectionScenarios;
use Webkul\Admin\Http\Controllers\WorkOrder\WorkOrderController;
use Webkul\Admin\Services\WorkOrderAccessService;
use Webkul\Invoice\Models\WorkOrder;
use Webkul\Invoice\Services\WorkOrderNumberService;
use Webkul\Invoice\Services\WorkOrderService;

it('lets warehouse staff open and save SPK without granting invoice generation', function (): void {
    CrmCorrectionScenarios::run(function () {
        Schema::table('work_orders', function (Blueprint $table) {
            foreach (['status', 'location', 'notes', 'admin_sales_name', 'sales_name', 'operational_name'] as $column) {
                $table->string($column)->nullable();
            }
            $table->unsignedInteger('user_id')->nullable();
            $table->date('event_date')->nullable();
            $table->timestamps();
        });
        Schema::create('work_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('work_order_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->integer('sort_order');
            $table->timestamps();
        });
        Schema::table('delivery_orders', fn (Blueprint $table) => $table->unsignedInteger('work_order_id')->nullable());
        $events = new Dispatcher(app());
        $filesystem = new Filesystem;
        $engines = new EngineResolver;
        $engines->register('blade', fn () => new CompilerEngine(new BladeCompiler($filesystem, sys_get_temp_dir())));
        $finder = new FileViewFinder($filesystem, []);
        $finder->addNamespace('admin', dirname(__DIR__, 2).'/packages/Webkul/Admin/src/Resources/views');
        app()->instance(ViewFactory::class, new Factory($engines, $finder, $events));
        $router = new Router($events, app());
        $router->get('admin/work-orders/{id}', fn () => '')->name('admin.work-orders.show');
        $router->getRoutes()->refreshNameLookups();
        $session = new Store('spk-test', new ArraySessionHandler(120));
        $redirect = new Redirector(new UrlGenerator($router->getRoutes(), Request::create('https://crm.test')));
        $redirect->setSession($session);
        app()->instance('redirect', $redirect);
        app()->instance('session', $session);

        $user = new class
        {
            public int $id = 7;

            public object $role;

            public function __construct()
            {
                $this->role = (object) ['name' => 'Warehouse Staff', 'permission_type' => 'custom', 'permissions' => ['work-orders.view', 'work-orders.edit']];
            }

            public function hasPermission(string $permission): bool
            {
                return in_array($permission, $this->role->permissions, true);
            }
        };
        $access = new class($user) extends WorkOrderAccessService
        {
            public function __construct(private object $actor) {}

            public function user()
            {
                return $this->actor;
            }
        };
        $controller = new WorkOrderController($access);
        $order = WorkOrder::create(['status' => 'draft', 'user_id' => 11, 'notes' => 'Original']);
        $view = $controller->edit($order->id);
        expect($view->name())->toBe('admin::work-orders.edit');
        expect($view->getData()['workOrder']->id)->toBe($order->id);

        $request = new class extends Request
        {
            public function validate(array $rules): array
            {
                return app('validator')->make($this->all(), $rules)->validate();
            }
        };
        $request->replace(['event_date' => '2026-10-09', 'notes' => 'Tambahan instruksi gudang', 'items' => [['name' => 'Classic Photobooth', 'notes' => 'Siapkan sebelum loading']]]);
        $response = $controller->update($request, $order->id);
        expect($response->getStatusCode())->toBe(302);
        expect($order->fresh()->notes)->toBe('Tambahan instruksi gudang');
        expect($order->fresh()->event_date->format('Y-m-d'))->toBe('2026-10-09');
        expect($order->items()->firstOrFail()->name)->toBe('Classic Photobooth');

        $isolatedContainer = app();
        try {
            // Use the real framework abort() implementation for access denials.
            new Application(dirname(__DIR__, 2));
            $blocked = [fn () => $controller->storeFromInvoice(999, new WorkOrderService(new WorkOrderNumberService))];
            foreach ($blocked as $action) {
                try {
                    $action();
                    throw new RuntimeException('Invoice generation must be denied.');
                } catch (HttpException $exception) {
                    expect($exception->getStatusCode())->toBe(403);
                }
            }
            $user->role->permissions = ['work-orders.generate'];
            foreach ([fn () => $controller->edit($order->id), fn () => $controller->update($request, $order->id)] as $action) {
                try {
                    $action();
                    throw new RuntimeException('Generate permission must not grant edit access.');
                } catch (HttpException $exception) {
                    expect($exception->getStatusCode())->toBe(403);
                }
            }
            $user->role->name = 'Sales User';
            $user->role->permissions = ['work-orders.edit'];
            try {
                $controller->edit($order->id);
                throw new RuntimeException('Sales ownership restriction must remain.');
            } catch (HttpException $exception) {
                expect($exception->getStatusCode())->toBe(403);
            }
        } finally {
            Container::setInstance($isolatedContainer);
        }
        expect(DB::table('work_orders')->count())->toBe(1);
        expect(DB::table('invoices')->count())->toBe(0);
    });
});
