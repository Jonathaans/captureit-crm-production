<x-admin::layouts>
    <x-slot:title>
        Invoices
    </x-slot>

    {!! view_render_event('admin.invoices.index.before') !!}

    {{-- ========================================================= --}}
    {{-- PAGE HEADER --}}
    {{-- ========================================================= --}}

    <div
        class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid gap-1">
            <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                Invoices
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage invoice, project, event status, payment, and expense.
            </p>
        </div>
        {{-- EXPORT ALL EXPENSES CSV V1 --}}
        @if (bouncer()->hasPermission('invoices.expense.export-all'))
            <div class="flex items-center gap-2 max-sm:w-full">
                <a
                    href="{{ route('admin.invoices.expenses.export-all') }}"
                    class="primary-button max-sm:w-full max-sm:justify-center"
                >
                    Export All Expenses
                </a>
            </div>
        @endif
    </div>

    {!! view_render_event('admin.invoices.index.header.after') !!}

    {{-- ========================================================= --}}
    {{-- NATIVE KRAYIN DATAGRID --}}
    {{--
        Search, filters, sorting, pagination, and row actions are
        rendered by the native Krayin DataGrid component.

        Column definitions, filters, and row actions are controlled by:
        packages/Webkul/Admin/src/DataGrids/Invoice/InvoiceDataGrid.php
    --}}
    {{-- ========================================================= --}}

    <div class="mt-3.5">
        <x-admin::datagrid
            :src="route('admin.invoices.index')"
        />
    </div>

    {!! view_render_event('admin.invoices.index.after') !!}
</x-admin::layouts>
