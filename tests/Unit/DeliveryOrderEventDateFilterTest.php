<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Tests\Support\CrmCorrectionScenarios;
use Webkul\Admin\DataGrids\DeliveryOrder\DeliveryOrderDataGrid;

it('filters Surat Jalan by inclusive event dates independently from delivery dates', function (): void {
    CrmCorrectionScenarios::run(function () {
        app()->instance('events', new Dispatcher(app()));
        app()->instance('translator', new Translator(new ArrayLoader, 'en'));
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->date('event_date')->nullable();
            $table->date('delivery_date')->nullable();
        });
        foreach ([1 => '2026-10-07', 2 => '2026-10-08', 3 => '2026-10-09', 4 => '2026-10-10', 5 => null] as $id => $date) {
            DB::table('delivery_orders')->insert(['id' => $id, 'event_date' => $date, 'delivery_date' => '2026-10-08', 'status' => $id === 3 ? 'issued' : 'draft']);
        }
        $grid = new class extends DeliveryOrderDataGrid
        {
            public function ids(array $filters): array
            {
                $this->setQueryBuilder(DB::table('delivery_orders'));

                return $this->processRequestedFilters($filters)->orderBy('id')->pluck('id')->all();
            }
        };
        $grid->prepareColumns();
        $eventDate = collect($grid->getColumns())->first(fn ($column) => $column->getIndex() === 'event_date');
        expect($eventDate->getFilterableType())->toBe('date_range');
        expect($grid->ids(['event_date' => [['2026-10-08', '2026-10-08']]]))->toBe([2]);
        expect($grid->ids(['event_date' => [['2026-10-08', '2026-10-09']]]))->toBe([2, 3]);
        expect($grid->ids(['event_date' => [['2026-10-09', '']]]))->toBe([3, 4]);
        expect($grid->ids(['event_date' => [['', '2026-10-08']]]))->toBe([1, 2]);
        expect($grid->ids(['event_date' => [['2026-10-08', '2026-10-09']], 'status' => ['issued']]))->toBe([3]);
        expect($grid->ids([]))->toBe([1, 2, 3, 4, 5]);
        $previousNow = Carbon::getTestNow();
        try {
            Carbon::setTestNow('2026-10-08 12:00:00');
            expect($grid->ids(['event_date' => 'today']))->toBe([2]);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    });
});
