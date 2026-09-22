<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.quotes.edit.title')
    </x-slot>

    {!! view_render_event('admin.contacts.quotes.edit.form_controls.before', ['quote' => $quote]) !!}

    <x-admin::form
        :action="route('admin.quotes.update', $quote->id) . '?' . http_build_query(array_merge(
            request()->route()->parameters(),
            request()->all()
        ))"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs
                        name="quotes.edit"
                        :entity="$quote"
                    />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.quotes.edit.title')
                    </div>
                </div>

<div class="flex items-center gap-x-2.5">
    <div class="flex items-center gap-x-2.5">
        {!! view_render_event('admin.contacts.quotes.edit.save_button.before', ['quote' => $quote]) !!}

        <!-- Generate Invoice -->
        <button
            type="submit"
            form="generate-invoice-form"
            class="secondary-button"
        >
            Generate Invoice
        </button>

        <!-- Save Quote -->
        @if ($archiveReason)
            <button
                type="button"
                class="primary-button"
                onclick="this.form.submit()"
            >
                Save Bill To
            </button>
        @else
            <button
                type="submit"
                class="primary-button"
            >
                @lang('admin::app.quotes.edit.save-btn')
            </button>
        @endif

        {!! view_render_event('admin.contacts.quotes.edit.save_button.after', ['quote' => $quote]) !!}
    </div>
</div>
            </div>

            @if ($archiveReason)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                    <p class="font-semibold">Quotation lama bersifat read-only.</p>
                    <p class="mt-1">
                        {{ $archiveReason }} Tombol Save Quote hanya akan menyimpan pilihan Bill To,
                        nama contact/company, dan identitas penandatangan. Nilai, item, alamat, serta
                        tanggal quotation tetap tidak berubah.
                    </p>
                </div>
            @endif

            @if ($errors->has('archive'))
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300">
                    {{ $errors->first('archive') }}
                </div>
            @endif

            <v-quote :errors="errors">
                <x-admin::shimmer.quotes />
            </v-quote>
        </div>
    </x-admin::form>

    <form
    id="generate-invoice-form"
    method="POST"
    action="{{ route('admin.invoices.generate', $quote->id) }}"
    class="hidden"
>
    @csrf
</form>

    {!! view_render_event('admin.contacts.quotes.edit.form_controls.after', ['quote' => $quote]) !!}

    @pushOnce('scripts')
        @include('admin::quotes.partials.sales-owner-lookup')

        <script
            type="text/x-template"
            id="v-quote-template"
        >
            <div class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex w-full gap-2 border-b border-gray-300 dark:border-gray-800">
                    {!! view_render_event('admin.contacts.quotes.edit.tags.before', ['quote' => $quote]) !!}

                    <template
                        v-for="tab in tabs"
                        :key="tab.id"
                    >
                        <a
                            :href="'#' + tab.id"
                            :class="[
                                'inline-block px-3 py-2.5 border-b-2  text-sm font-medium ',
                                activeTab === tab.id
                                ? 'text-brandColor border-brandColor dark:brandColor dark:brandColor'
                                : 'text-gray-600 dark:text-gray-300  border-transparent hover:text-gray-800 hover:border-gray-400 dark:hover:border-gray-400  dark:hover:text-white'
                            ]"
                            @click="scrollToSection(tab.id)"
                            :text="tab.label"
                        ></a>
                    </template>

                    {!! view_render_event('admin.contacts.quotes.edit.tags.after', ['quote' => $quote]) !!}
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">
                    {!! view_render_event('admin.contacts.quotes.edit.quote_information.before', ['quote' => $quote]) !!}

                    <!-- Quote information -->
                    <div
                        id="quote-info"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.quotes.create.quote-info')
                            </p>

                            <p class="text-sm text-gray-600 dark:text-white">@lang('admin::app.quotes.create.quote-info-info')</p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    'entity_type' => 'quotes',
                                    ['code', 'IN', ['subject']],
                                ])"
                                :custom-validations="[
                                    'expired_at' => [
                                        'required',
                                        'date_format:yyyy-MM-dd',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                                :entity="$quote"
                            />

                            <!-- Project Details -->
                            <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                                <!-- Event Date -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Event Date
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="date"
                                        name="event_date"
                                        :value="old(
                                            'event_date',
                                            $quote->event_date
                                                ? \Carbon\Carbon::parse($quote->event_date)->format('Y-m-d')
                                                : ''
                                        )"
                                        rules="required"
                                        label="Event Date"
                                    />

                                    <x-admin::form.control-group.error
                                        control-name="event_date"
                                    />
                                </x-admin::form.control-group>

                                <!-- Location -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Location
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="location"
                                        :value="old('location', $quote->location)"
                                        rules="required"
                                        label="Location"
                                        placeholder="Contoh: Hotel Mulia Jakarta"
                                    />

                                    <x-admin::form.control-group.error
                                        control-name="location"
                                    />
                                </x-admin::form.control-group>

                                <!-- Payment Term -->
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Payment Term
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="select"
                                        name="payment_term"
                                        :value="old('payment_term', $quote->payment_term)"
                                        rules="required"
                                        label="Payment Term"
                                    >
                                        <option value="">
                                            Select Payment Term
                                        </option>

                                        <option
                                            value="7 Days"
                                            @selected(old('payment_term', $quote->payment_term) === '7 Days')
                                        >
                                            7 Days
                                        </option>

                                        <option
                                            value="14 Days"
                                            @selected(old('payment_term', $quote->payment_term) === '14 Days')
                                        >
                                            14 Days
                                        </option>

                                        <option
                                            value="30 Days"
                                            @selected(old('payment_term', $quote->payment_term) === '30 Days')
                                        >
                                            30 Days
                                        </option>

                                        <option
                                            value="Full Payment Before Event"
                                            @selected(old('payment_term', $quote->payment_term) === 'Full Payment Before Event')
                                        >
                                            Full Payment Before Event
                                        </option>
                                    </x-admin::form.control-group.control>

                                    <x-admin::form.control-group.error
                                        control-name="payment_term"
                                    />
                                </x-admin::form.control-group>
                            </div>

                            

                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                        'entity_type' => 'quotes',
                                        ['code', 'IN', ['description']],
                                    ])"
                                :custom-validations="[
                                    'expired_at' => [
                                        'required',
                                        'date_format:yyyy-MM-dd',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                                :entity="$quote"
                            />

                            <div class="flex gap-4">
                                <x-admin::attributes
                                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                        'entity_type' => 'quotes',
                                        ['code', 'IN', ['expired_at']],
                                    ])->sortBy('sort_order')"
                                    :custom-validations="[
                                        'expired_at' => [
                                            'required',
                                            'date_format:yyyy-MM-dd',
                                            'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                        ],
                                    ]"
                                    :entity="$quote"
                                />
                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.label class="required">
                                        Sales Owner
                                    </x-admin::form.control-group.label>

                                    <v-quote-sales-owner-lookup
                                        :initial-owner='@json($salesOwnerLookUpData ?? [])'
                                        search-url="{{ route('admin.quotes.sales_owners') }}"
                                        :disabled="@json((bool) $archiveReason)"
                                    ></v-quote-sales-owner-lookup>

                                    <x-admin::form.control-group.error control-name="user_id" />
                                </x-admin::form.control-group>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                                <!-- Bill To / Client -->
                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.label class="required">
                                        Bill To
                                    </x-admin::form.control-group.label>

                                    <v-lookup-component
                                        :key="personLookupKey"
                                        :attribute="{
                                            code: 'person_id',
                                            name: 'Bill To',
                                            lookup_type: 'persons'
                                        }"
                                        :value="personEntity"
                                        search-url="{{ route('admin.quotes.bill_to_people') }}"
                                        lookup-entity-url="{{ route('admin.quotes.bill_to_person') }}"
                                        @lookup-added="setPersonEntity"
                                        @lookup-removed="setPersonEntity"
                                    ></v-lookup-component>

                                    <x-admin::form.control-group.error control-name="person_id" />

                                    @include('admin::quotes.partials.bill-to-identity-fields')
                                </x-admin::form.control-group>

                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.quotes.create.link-to-lead')
                                    </x-admin::form.control-group.label>

                                    <v-lookup-component
                                        :key="leadEntity.id"
                                        :attribute="{'code': 'lead_id', 'name': 'Lead', 'lookup_type': 'leads'}"
                                        :value="leadEntity"
                                        can-add-new="true"
                                        @lookup-added="setLeadEntity"
                                        @lookup-removed="setLeadEntity"
                                    ></v-lookup-component>
                                </x-admin::form.control-group>
                            </div>

                            <x-admin::attributes.edit.lookup />

                            <!-- Custom Attributes -->
                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    'entity_type' => 'quotes',
                                    'is_user_defined' => 1,
                                ])->sortBy('sort_order')"
                                :custom-validations="[
                                    'expired_at' => [
                                        'required',
                                        'date_format:yyyy-MM-dd',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                                :entity="$quote"
                            />
                        </div>
                    </div>

                    {!! view_render_event('admin.contacts.quotes.edit.quote_information.after', ['quote' => $quote]) !!}

                    {!! view_render_event('admin.contacts.quotes.edit.address_information.before', ['quote' => $quote]) !!}

                    <!-- Address information -->
                    <div
                        id="address-info"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.quotes.create.address-info')
                            </p>

                            <p class="text-sm text-gray-600 dark:text-white">
                                @lang('admin::app.quotes.create.address-info-info')
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            @php
                                $billingAddressAttributes = app(
                                    'Webkul\Attribute\Repositories\AttributeRepository'
                                )->findWhere([
                                    'entity_type' => 'quotes',
                                    ['code', 'IN', ['billing_address']],
                                ]);

                                $billingAddressAttributes->each(function ($attribute) {
                                    if ($attribute->code === 'billing_address') {
                                        $attribute->name = 'Address';
                                    }
                                });
                            @endphp

                            <!-- Address -->
                            <x-admin::attributes
                                :custom-attributes="$billingAddressAttributes"
                                :custom-validations="[
                                    'billing_address' => [
                                        'max:100',
                                    ],
                                ]"
                                :entity="$quote"
                            />

                            <!-- Backend: shipping address otomatis mengikuti billing address -->
                            <input
                                type="hidden"
                                name="shipping_address_same_as_billing"
                                value="1"
                            />
                        </div>
                    </div>

                    {!! view_render_event('admin.contacts.quotes.edit.address_information.after', ['quote' => $quote]) !!}

                    {!! view_render_event('admin.contacts.quotes.edit.quote_information.before', ['quote' => $quote]) !!}

                    <!-- Quote Item Information -->
                    <div
                        id="quote-items"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                @lang('admin::app.quotes.create.quote-items')
                            </p>

                            <p class="text-sm text-gray-600 dark:text-white">
                                @lang('admin::app.quotes.create.quote-item-info')
                            </p>
                        </div>

                        <!-- Quote Item List Vue Component -->
                        <v-quote-item-list
                            :errors="errors"
                            :lead-entity="leadEntity"
                        ></v-quote-item-list>
                    </div>

                    {!! view_render_event('admin.contacts.quotes.edit.quote_information.after', ['quote' => $quote]) !!}
                </div>

                {!! view_render_event('admin.contacts.quotes.edit.form_controls.after', ['quote' => $quote]) !!}
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-quote-item-list-template"
        >
            <div class="flex flex-col gap-4">
                <div class="block w-full">
                    <!-- Table -->
                    <x-admin::table>
                        <!-- Table Head -->
                        <x-admin::table.thead>
                            <x-admin::table.thead.tr>
                                <x-admin::table.th>
                                    @lang('admin::app.quotes.create.product-name')
                                </x-admin::table.th>

                                <x-admin::table.th class="text-center">
                                    @lang('admin::app.quotes.create.quantity')
                                </x-admin::table.th>

                                <x-admin::table.th class="text-center">
                                    @lang('admin::app.quotes.create.price')
                                </x-admin::table.th>

                                <x-admin::table.th class="text-center">
                                    @lang('admin::app.quotes.create.amount')
                                </x-admin::table.th>

                                <x-admin::table.th class="text-center">
                                    @lang('admin::app.quotes.create.discount')
                                </x-admin::table.th>

                                <x-admin::table.th class="text-center">
                                    @lang('admin::app.quotes.create.tax')
                                </x-admin::table.th>

                                <x-admin::table.th class="text-center">
                                    @lang('admin::app.quotes.create.total')
                                </x-admin::table.th>

                                <x-admin::table.th
                                    v-if="products.length > 1"
                                    class="!px-2 ltr:text-right rtl:text-left"
                                >
                                    @lang('admin::app.quotes.create.action')
                                </x-admin::table.th>
                            </x-admin::table.thead.tr>
                        </x-admin::table.thead>

                        <!-- Table Body -->
                        <x-admin::table.tbody>
                            <!-- Quote Item Vue component -->
                            <template
                                v-for='(product, index) in products'
                                :key="index"
                            >
                                <v-quote-item
                                    :product="product"
                                    :index="index"
                                    :errors="errors"
                                    @onRemoveProduct="removeProduct($event)"
                                ></v-quote-item>
                            </template>
                        </x-admin::table.tbody>
                    </x-admin::table>
                    <x-admin::form.control-group.error name="items"/>
                </div>

                <!-- Add New Quote Item -->
                <span
                    class="text-md flex max-w-max cursor-pointer items-center gap-2 text-brandColor"
                    @click="addProduct"
                >
                    @lang('admin::app.quotes.create.add-item')
                </span>

                <div class="flex justify-end">
                    <div class="grid w-[348px] gap-4 rounded-lg bg-gray-100 p-4 text-sm dark:bg-gray-950 dark:text-white">
                        <div class="flex w-full justify-between gap-x-5">
                            @lang('admin::app.quotes.create.sub-total', ['symbol' => core()->currencySymbol(config('app.currency'))])

                            <input
                                type="hidden"
                                name="sub_total"
                                class="control"
                                :value="subTotal"
                                readonly
                            >

                            <p>@{{ subTotal }}</p>
                        </div>

                        <div class="flex w-full justify-between gap-x-5">
                            @lang('admin::app.quotes.create.total-discount', ['symbol' => core()->currencySymbol(config('app.currency'))])

                            <input
                                type="hidden"
                                name="discount_amount"
                                :value="discountAmount"
                            >

                            <p>@{{ discountAmount }}</p>
                        </div>

                        <div class="flex w-full justify-between gap-x-5">
                            @lang('admin::app.quotes.create.total-tax', ['symbol' => core()->currencySymbol(config('app.currency'))])

                            <input
                                type="hidden"
                                name="tax_amount"
                                :value="taxAmount"
                            >

                            <p>@{{ taxAmount }}</p>
                        </div>

                        <div class="flex w-full justify-between gap-x-5">
                            @lang('admin::app.quotes.create.total-adjustment', ['symbol' => core()->currencySymbol(config('app.currency'))])

                            <x-admin::form.control-group.control
                                type="inline"
                                ::name="`adjustment_amount`"
                                ::value="adjustmentAmount"
                                rules="required|decimal:4"
                                ::errors="errors"
                                :label="trans('admin::app.quotes.create.adjustment-amount')"
                                :placeholder="trans('admin::app.quotes.create.adjustment-amount')"
                                @on-change="handleAdjustmentAmountChange"
                            />
                        </div>

                        <div class="flex w-full justify-between gap-x-5">
                            @lang('admin::app.quotes.create.grand-total', ['symbol' => core()->currencySymbol(config('app.currency'))])

                            <input
                                type="hidden"
                                name="grand_total"
                                :value="grandTotal"
                            >

                            <p>@{{ grandTotal }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-quote-item-template"
        >
            <x-admin::table.thead.tr>
                <!-- Quote Product Name -->
                <x-admin::table.td>
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::lookup
                            ::src="src"
                            ::name="`${inputName}[product_id]`"
                            ::params="params"
                            ::value="{ id: product.product_id, name: product.name }"
                            @on-selected="(product) => addProduct(product)"
                            :placeholder="trans('admin::app.quotes.edit.search-products')"
                            rules="required"
                            :label="trans('admin::app.quotes.edit.product-name')"
                            ::class="errors[`${inputName}[product_id]`] ? 'border !border-red-600 hover:border-red-600' : ''"
                        />
                        <x-admin::form.control-group.error name="`items.${product.id}.product_id`"/>
                        <x-admin::form.control-group.error ::name="`${inputName}[product_id]`"/>
                    </x-admin::form.control-group>
                </x-admin::table.td>

                <!-- Quantity -->
                <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.control
                            type="inline"
                            ::name="`${inputName}[quantity]`"
                            ::value="product.quantity"
                            rules="required|decimal:4"
                            ::errors="errors"
                            :label="trans('admin::app.quotes.create.quantity')"
                            :placeholder="trans('admin::app.quotes.create.quantity')"
                            @on-change="(event) => product.quantity = event.value"
                            position="center"
                        />
                        <x-admin::form.control-group.error ::name="`items.${product.id}.quantity`"/>
                    </x-admin::form.control-group>
                </x-admin::table.td>

                <!-- Price -->
                <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.control
                            type="inline"
                            ::name="`${inputName}[price]`"
                            ::value="(product.price) ?? 0"
                            rules="required|decimal:4"
                            ::errors="errors"
                            :label="trans('admin::app.quotes.create.price')"
                            :placeholder="trans('admin::app.quotes.create.price')"
                            @on-change="(event) => product.price = event.value"
                            position="center"
                            ::value-label="$admin.formatPrice(product.price)"
                        />
                        <x-admin::form.control-group.error name="`items.${product.id}.price`"/>
                        <x-admin::form.control-group.error ::name="`${inputName}[price]`"/>
                    </x-admin::form.control-group>
                </x-admin::table.td>

                <!-- Total -->
                <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.control
                            type="inline"
                            ::name="`${inputName}[total]`"
                            ::value="(product.price * product.quantity) ?? 0"
                            rules="required|decimal:4"
                            ::errors="errors"
                            :label="trans('admin::app.quotes.create.total')"
                            :placeholder="trans('admin::app.quotes.create.total')"
                            :allowEdit="false"
                            position="center"
                            ::value-label="$admin.formatPrice(product.price * product.quantity)"
                        />
                        <x-admin::form.control-group.error name="`items.${product.id}.total`"/>
                        <x-admin::form.control-group.error ::name="`${inputName}[total]`"/>
                    </x-admin::form.control-group>
                </x-admin::table.td>

                <!-- Discount Amount -->
                <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.control
                            type="inline"
                            ::name="`${inputName}[discount_amount]`"
                            ::value="product.discount_amount"
                            rules="required|decimal:4"
                            ::errors="errors"
                            :label="trans('admin::app.quotes.create.discount-amount')"
                            :placeholder="trans('admin::app.quotes.create.discount-amount')"
                            @on-change="(event) => product.discount_amount = event.value"
                            position="center"
                            ::value-label="$admin.formatPrice(product.discount_amount)"
                        />
                        <x-admin::form.control-group.error name="`items.${product.id}.discount_amount`"/>
                        <x-admin::form.control-group.error ::name="`${inputName}[discount_amount]`"/>
                    </x-admin::form.control-group>
                </x-admin::table.td>

                <!-- Tax Amount -->
                <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.control
                            type="inline"
                            ::name="`${inputName}[tax_amount]`"
                            ::value="product.tax_amount"
                            rules="required|decimal:4"
                            ::errors="errors"
                            :label="trans('admin::app.quotes.create.tax-amount')"
                            :placeholder="trans('admin::app.quotes.create.tax-amount')"
                            @on-change="(event) => product.tax_amount = event.value"
                            position="center"
                            ::value-label="$admin.formatPrice(product.tax_amount)"
                        />
                    </x-admin::form.control-group>
                </x-admin::table.td>

                <!-- Total with Discount -->
                <x-admin::table.td class="!px-2 ltr:text-right rtl:text-left">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.control
                            type="inline"
                            ::name="`${inputName}[final_total]`"
                            ::errors="errors"
                            ::value="parseFloat(product.price * product.quantity) + parseFloat(product.tax_amount) - parseFloat(product.discount_amount)"
                            :allowEdit="false"
                            position="center"
                            ::value-label="$admin.formatPrice(parseFloat(product.price * product.quantity) + parseFloat(product.tax_amount) - parseFloat(product.discount_amount))"
                        />
                    </x-admin::form.control-group>
                </x-admin::table.td>

                <!-- Action -->
                <x-admin::table.td
                    v-if="$parent.products.length > 1"
                    class="!p-2 !px-2 ltr:text-right rtl:text-left"
                >
                    <x-admin::form.control-group class="!mb-0">
                        <i
                            @click="removeProduct"
                            class="icon-delete cursor-pointer text-2xl"
                        ></i>
                    </x-admin::form.control-group>
                </x-admin::table.td>
            </x-admin::table.thead.tr>
        </script>

        @php
            $initialBillToDisplayMode = old(
                'bill_to_display_mode',
                $quote->bill_to_person_name
                    ? ($quote->bill_to_display_mode ?: 'person')
                    : (! empty($personLookUpEntityData['company_name']) ? 'both' : 'person')
            );

            $initialClientSignerName = old(
                'client_signer_name',
                $quote->bill_to_person_name
                    ? ($quote->client_signer_name ?: $quote->bill_to_person_name)
                    : ($personLookUpEntityData['person_name'] ?? '')
            );

            $initialClientSignerCompany = old(
                'client_signer_company',
                $quote->bill_to_person_name
                    ? ($quote->client_signer_company ?? '')
                    : ($personLookUpEntityData['company_name'] ?? '')
            );
        @endphp

        <script type="module">
            app.component('v-quote', {
                template: '#v-quote-template',

                props: ['errors'],

                data() {
                    return {
                        activeTab: 'quote-info',

                        tabs: [
                            { id: 'quote-info', label: "@lang('admin::app.quotes.create.quote-info')" },
                            { id: 'address-info', label: "@lang('admin::app.quotes.create.address-info')" },
                            { id: 'quote-items', label: "@lang('admin::app.quotes.create.quote-items')" }
                        ],

                        leadEntity: @json($lookUpEntityData ?? []),

                        personEntity: @json($personLookUpEntityData ?? []),

                        personLookupKey: 0,

                        personName: @json($personLookUpEntityData['person_name'] ?? ''),

                        personCompanyName: @json($personLookUpEntityData['company_name'] ?? ''),

                        billToDisplayMode: @json($initialBillToDisplayMode),

                        clientSignerName: @json($initialClientSignerName),

                        clientSignerCompany: @json($initialClientSignerCompany),

                    };
                },

                mounted() {
                    this.$nextTick(() => {
                        this.applyQuoteLabelOverrides();

                        if (! this.$el) {
                            return;
                        }

                        // Keep custom labels after lookup/attribute components re-render.
                        this._quoteLabelObserver = new MutationObserver(() => {
                            this.applyQuoteLabelOverrides();
                        });

                        this._quoteLabelObserver.observe(this.$el, {
                            childList: true,
                            subtree: true,
                        });
                    });
                },

                beforeUnmount() {
                    if (this._quoteLabelObserver) {
                        this._quoteLabelObserver.disconnect();
                    }
                },

                methods: {
                    /**
                     * Override only the visible labels of default Quote attributes.
                     * Database codes remain subject, expired_at, and person_id.
                     */
                    applyQuoteLabelOverrides() {
                        const sections = [
                            document.getElementById('quote-info'),
                            document.getElementById('address-info'),
                        ].filter(Boolean);

                        const replacements = {
                            'Subject': 'Project Name',
                            'Expired At': 'Valid Until',
                            'Person': 'Bill To',
                            'Billing Address': 'Address',
                        };

                        sections.forEach((section) => {
                            section.querySelectorAll('label').forEach((label) => {
                                const currentLabel = label.textContent
                                    .replace(/\s+/g, ' ')
                                    .trim();

                                Object.entries(replacements).forEach(([from, to]) => {
                                    if (
                                        currentLabel !== from
                                        && currentLabel !== `${from} *`
                                    ) {
                                        return;
                                    }

                                    const walker = document.createTreeWalker(
                                        label,
                                        NodeFilter.SHOW_TEXT
                                    );

                                    let textNode;

                                    while ((textNode = walker.nextNode())) {
                                        if (textNode.nodeValue.includes(from)) {
                                            textNode.nodeValue =
                                                textNode.nodeValue.replace(from, to);

                                            break;
                                        }
                                    }
                                });
                            });
                        });
                    },

                    /**
                     * Scroll to the section.
                     *
                     * @param {String} tabId
                     *
                     * @returns {void}
                     */
                    scrollToSection(tabId) {
                        const section = document.getElementById(tabId);

                        if (section) {
                            section.scrollIntoView({ behavior: 'smooth' });
                        }
                    },

                    setLeadEntity($event) {
                        this.leadEntity = $event ?? { id: '', name: '' };
                    },

                    setPersonEntity($event) {
                        this.applyPersonEntity($event);
                    },

                    applyPersonEntity(person) {
                        if (! person?.id) {
                            this.personEntity = { id: '', name: '' };
                            this.personName = '';
                            this.personCompanyName = '';
                            this.billToDisplayMode = 'person';
                            this.clientSignerName = '';
                            this.clientSignerCompany = '';

                            return;
                        }

                        const personName = person.person_name || person.name || '';
                        const companyName = person.company_name || person.organization?.name || '';

                        this.personEntity = {
                            ...person,
                            name: companyName
                                ? `${personName} — ${companyName}`
                                : personName,
                            person_name: personName,
                            company_name: companyName,
                        };
                        this.personName = personName;
                        this.personCompanyName = companyName;
                        this.billToDisplayMode = companyName ? 'both' : 'person';
                        this.clientSignerName = personName;
                        this.clientSignerCompany = companyName;
                    },
                },
            });

            app.component('v-quote-item-list', {
                template: '#v-quote-item-list-template',

                props: ['errors', 'leadEntity'],

                data() {
                    return {
                        adjustmentAmount: '0.0000',

                        products: @json($initialQuoteItems),
                    }
                },

                watch: {
                    'leadEntity.id': function(newLeadId, oldLeadId) {
                        if (newLeadId === oldLeadId) {
                            return;
                        }

                        if (! newLeadId) {
                            this.products = [];

                            return;
                        }

                        this.fetchLeadProducts(newLeadId);
                    },
                },

                computed: {
                    /**
                     * Calculate the sub total of the products.
                     *
                     * @returns {Number}
                     */
                    subTotal() {
                        const total = this.products.reduce((carry, product) => {
                            return carry + this.getProductBaseTotal(product);
                        }, 0);

                        return this.formatDecimal(total);
                    },

                    /**
                     * Calculate the total discount amount of the products.
                     *
                     * @returns {Number}
                     */
                    discountAmount() {
                        const total = this.products.reduce((carry, product) => {
                            return carry + this.parseDecimal(product.discount_amount);
                        }, 0);

                        return this.formatDecimal(total);
                    },

                    /**
                     * Calculate the total tax amount of the products.
                     *
                     * @returns {Number}
                     */
                    taxAmount() {
                        const total = this.products.reduce((carry, product) => {
                            return carry + this.parseDecimal(product.tax_amount);
                        }, 0);

                        return this.formatDecimal(total);
                    },

                    /**
                     * Calculate the grand total of the products.
                     *
                     * @returns {Number}
                     */
                    grandTotal() {
                        const itemsTotal = this.products.reduce((carry, product) => {
                            return carry
                                + this.getProductBaseTotal(product)
                                + this.parseDecimal(product.tax_amount)
                                - this.parseDecimal(product.discount_amount);
                        }, 0);

                        return this.formatDecimal(itemsTotal + this.parseDecimal(this.adjustmentAmount));
                    },
                },

                methods: {
                    /**
                     * Parse decimal-like values safely.
                     *
                     * @param {Number|String|null} value
                     *
                     * @returns {Number}
                     */
                    parseDecimal(value) {
                        const parsedValue = Number.parseFloat(value);

                        return Number.isFinite(parsedValue) ? parsedValue : 0;
                    },

                    /**
                     * Format numeric values as fixed decimals.
                     *
                     * @param {Number|String|null} value
                     *
                     * @returns {String}
                     */
                    formatDecimal(value) {
                        return this.parseDecimal(value).toFixed(4);
                    },

                    /**
                     * Calculate product line subtotal.
                     *
                     * @param {Object} product
                     *
                     * @returns {Number}
                     */
                    getProductBaseTotal(product) {
                        return this.parseDecimal(product.price) * this.parseDecimal(product.quantity);
                    },

                    /**
                     * Keep adjustment amount stored as a fixed decimal string.
                     *
                     * @param {Object} event
                     *
                     * @returns {void}
                     */
                    handleAdjustmentAmountChange(event) {
                        this.adjustmentAmount = this.formatDecimal(event.value);
                    },

                    /**
                     * Fetch and replace items with selected lead products.
                     *
                     * @param {Number|String} leadId
                     *
                     * @returns {void}
                     */
                    fetchLeadProducts(leadId) {
                        this.$axios
                            .get("{{ route('admin.quotes.lead_products', '__LEAD_ID__') }}".replace('__LEAD_ID__', leadId))
                            .then((response) => {
                                const leadProducts = response.data?.data ?? [];

                                this.products = leadProducts;

                                this.$emitter.emit('add-flash', {
                                    type: leadProducts.length ? 'success' : 'info',
                                    message: leadProducts.length
                                        ? 'Lead products assigned to quote. See items section.'
                                        : 'No products found for selected lead.',
                                });
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error?.response?.data?.message || 'Unable to fetch lead products.',
                                });
                            });
                    },

                    /**
                     * Add a new product.
                     *
                     * @returns {void}
                     */
                    addProduct() {
                        this.products.push({
                            id: null,
                            product_id: null,
                            name: '',
                            quantity: 1,
                            total: '0.0000',
                            price: '0.0000',
                            discount_amount: '0.0000',
                            tax_amount: '0.0000',
                        });
                    },

                    /**
                     * Remove the product.
                     *
                     * @param {Object} product
                     */
                    removeProduct(product) {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                if (this.products.length === 1) {
                                    this.products = [{
                                        id: null,
                                        product_id: null,
                                        name: '',
                                        quantity: null,
                                        total: 0,
                                        price: null,
                                        discount_amount: null,
                                        tax_amount: null,
                                    }];
                                } else {
                                    const index = this.products.indexOf(product);

                                    if (index !== -1) {
                                        this.products.splice(index, 1);
                                    }
                                }
                            },
                        });
                    },
                },
            });

            app.component('v-quote-item', {
                template: '#v-quote-item-template',

                props: ['index', 'product', 'errors'],

                data() {
                    return {
                        state: this.product['product_id'] ? 'old' : '',

                        products: [],
                    }
                },

                computed: {
                    /**
                     * Get the input name.
                     *
                     * @returns {String}
                     */
                    inputName() {
                        if (this.product.id) {
                            return "items[" + this.product.id + "]";
                        }

                        return "items[item_" + this.index + "]";
                    },

                    /**
                     * Get the source URL.
                     *
                     * @returns {String}
                     */
                    src() {
                        return "{{ route('admin.products.search') }}";
                    },

                    params() {
                        return {
                            params: {
                                query: this.product.name,
                            },
                        };
                    },
                },

                methods: {
                    /**
                     * Add the product.
                     *
                     * @param {Object} result
                     *
                     * @return {void}
                     */
                    addProduct(result) {
                        this.product.product_id = result.id;
                        this.product.name = result.name;
                        this.product.price = result.price ?? 0;
                        this.product.quantity = result.quantity ?? 1;
                        this.product.discount_amount = 0;
                        this.product.tax_amount = 0;
                    },

                    /**
                     * Remove the product.
                     *
                     * @return {void}
                     */
                    removeProduct() {
                        this.$emit('onRemoveProduct', this.product);
                    },
                },
            });
        </script>
    @endPushOnce

    @pushOnce('styles')
        <style>
            html {
                scroll-behavior: smooth;
            }
        </style>
    @endPushOnce
</x-admin::layouts>
