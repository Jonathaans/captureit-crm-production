<div
    v-if="personEntity?.id"
    class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
>
    <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
        <div>
            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">
                Bill To Format *
            </label>

            <select
                name="bill_to_display_mode"
                v-model="billToDisplayMode"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                required
            >
                <option value="person">Contact only</option>
                <option
                    value="company"
                    :disabled="! personCompanyName"
                >
                    Company only
                </option>
                <option
                    value="both"
                    :disabled="! personCompanyName"
                >
                    Company + Contact
                </option>
            </select>

            <x-admin::form.control-group.error control-name="bill_to_display_mode" />

            <p
                v-if="! personCompanyName"
                class="mt-1 text-[11px] leading-4 text-amber-600 dark:text-amber-400"
            >
                Contact ini belum terhubung ke Company, sehingga format Company belum dapat dipilih.
            </p>
        </div>

        <div>
            <p class="mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                PDF Preview
            </p>

            <div class="min-h-[38px] rounded-md border border-dashed border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <p
                    v-if="billToDisplayMode !== 'person' && personCompanyName"
                    class="font-semibold"
                >
                    @{{ personCompanyName }}
                </p>

                <p v-if="billToDisplayMode !== 'company'">
                    <span v-if="billToDisplayMode === 'both'">Attn: </span>@{{ personName }}
                </p>
            </div>
        </div>
    </div>

    <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-800">
        <p class="text-sm font-semibold text-gray-800 dark:text-white">
            Client Signature Identity
        </p>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Nama berikut akan dicetak pada bagian persetujuan quotation dan tetap dapat disesuaikan.
        </p>

        <div class="mt-3 grid grid-cols-2 gap-4 max-md:grid-cols-1">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">
                    Signer Name *
                </label>

                <input
                    type="text"
                    name="client_signer_name"
                    v-model.trim="clientSignerName"
                    maxlength="255"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                    required
                >

                <x-admin::form.control-group.error control-name="client_signer_name" />
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">
                    Signer Company
                </label>

                <input
                    type="text"
                    name="client_signer_company"
                    v-model.trim="clientSignerCompany"
                    maxlength="255"
                    placeholder="Optional"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                >

                <x-admin::form.control-group.error control-name="client_signer_company" />
            </div>
        </div>
    </div>
</div>
