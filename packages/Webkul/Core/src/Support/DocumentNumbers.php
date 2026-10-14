<?php

namespace Webkul\Core\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DocumentNumbers
{
    public static function key(string $number): string
    {
        return mb_strtoupper(preg_replace('/[\s-]+/u', '', trim($number)));
    }

    public function all(string $type): array
    {
        $numbers = DB::table($type === 'quote' ? 'quotes' : 'invoices')
            ->whereNotNull($type.'_number')->pluck($type.'_number')->all();
        if ($type === 'invoice' && Schema::hasTable('crm_data_corrections')) {
            $numbers = array_merge($numbers, DB::table('crm_data_corrections')
                ->whereIn('kind', ['invoice', 'invoice_void'])->pluck('invoice_number')->filter()->all());
        }
        if (Schema::hasTable('crm_document_numbers')) {
            $numbers = array_merge($numbers, DB::table('crm_document_numbers')->where('type', $type)->pluck('number')->all());
        }

        return $numbers;
    }

    public function lock(string $type): void
    {
        DB::table('crm_document_number_locks')->insertOrIgnore(['type' => $type]);
        DB::table('crm_document_number_locks')->where('type', $type)->lockForUpdate()->first();
    }

    /** Caller holds the type lock inside the document transaction. */
    public function reserve(string $type, string $number): void
    {
        $key = self::key($number);
        foreach ($this->all($type) as $existing) {
            if (self::key($existing) === $key) {
                throw ValidationException::withMessages(['number' => 'Nomor '.$number.' sudah digunakan.']);
            }
        }
        DB::table('crm_document_numbers')->insert([
            'type' => $type, 'number' => $number, 'number_key' => hash('sha256', $key),
        ]);
    }

    public function next(string $type, string $prefix): string
    {
        return DB::transaction(function () use ($type, $prefix) {
            $hasRegistry = Schema::hasTable('crm_document_numbers');
            if ($hasRegistry) {
                $this->lock($type);
            }
            $key = self::key($prefix);
            $last = '0';
            foreach ($this->all($type) as $number) {
                $normalized = self::key($number);
                if (str_starts_with($normalized, $key)) {
                    $suffix = substr($normalized, strlen($key));
                    if (ctype_digit($suffix)) {
                        $suffix = ltrim($suffix, '0') ?: '0';
                        if (strlen($suffix) > strlen($last) || (strlen($suffix) === strlen($last) && strcmp($suffix, $last) > 0)) {
                            $last = $suffix;
                        }
                    }
                }
            }
            // Manual legacy numbers can contain more digits than a PHP integer.
            $next = $last;
            for ($i = strlen($next) - 1; $i >= 0 && $next[$i] === '9'; $i--) {
                $next[$i] = '0';
            }
            if ($i < 0) {
                $next = '1'.$next;
            } else {
                $next[$i] = (string) ((int) $next[$i] + 1);
            }
            $number = $prefix.str_pad($next, 4, '0', STR_PAD_LEFT);
            if (strlen($number) > 30) {
                throw ValidationException::withMessages(['number' => 'Urutan nomor '.$prefix.' telah melampaui panjang nomor dokumen.']);
            }
            if ($hasRegistry) {
                $this->reserve($type, $number);
            }

            return $number;
        }, 3);
    }
}
