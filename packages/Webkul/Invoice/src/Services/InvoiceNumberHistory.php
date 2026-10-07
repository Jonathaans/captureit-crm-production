<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceNumberHistory
{
    /** Include removed invoices so their document numbers are never reused. */
    public function lastSequence(string $prefix): int
    {
        $numbers = DB::table('invoices')->where('invoice_number', 'like', $prefix.'%')->pluck('invoice_number');

        if (Schema::hasTable('crm_data_corrections')) {
            $numbers = $numbers->merge(DB::table('crm_data_corrections')
                ->where('kind', 'invoice')->where('invoice_number', 'like', $prefix.'%')->pluck('invoice_number'));
        }

        $last = 0;
        foreach ($numbers as $number) {
            $suffix = substr($number, strlen($prefix));
            if (ctype_digit($suffix)) {
                $last = max($last, (int) $suffix);
            }
        }

        return $last;
    }
}
