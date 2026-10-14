<div
    v-if="Object.keys(errors).length"
    role="alert"
    aria-live="polite"
    class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
>
    <p class="font-semibold">Quote belum tersimpan. Periksa isian berikut:</p>

    <ul class="mt-2 list-inside list-disc">
        <li v-for="(message, field) in errors" :key="field">@{{ message }}</li>
    </ul>
</div>
