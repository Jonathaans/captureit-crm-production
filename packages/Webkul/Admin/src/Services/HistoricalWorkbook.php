<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Webkul\Core\Support\DocumentNumbers;

class HistoricalWorkbook
{
    public const FIELDS = [
        'documents' => ['type', 'number', 'document_date', 'person_id', 'person_name', 'user_id', 'sales_name', 'subject', 'business_unit', 'project_code', 'event_date', 'location', 'address', 'payment_term', 'bill_to_display_mode', 'quote_number', 'billing_type', 'dp_invoice_number', 'due_at', 'expired_at', 'grand_total', 'discount_amount', 'tax_amount', 'adjustment_amount', 'event_status', 'description'],
        'items' => ['type', 'number', 'product_id', 'sku', 'name', 'description', 'quantity', 'day', 'total'],
        'payments' => ['number', 'amount', 'paid_at', 'payment_method', 'reference_number', 'notes'],
    ];

    public const LABELS = ['documents' => 'Dokumen', 'items' => 'Item', 'payments' => 'Pembayaran'];

    public function read(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        if (! in_array($reader::class, [Xlsx::class, Xls::class, Csv::class], true)) {
            $this->fail('Gunakan file XLSX, XLS, atau CSV.');
        }
        if ($reader instanceof Xlsx) {
            $zip = new \ZipArchive;
            $zip->open($file->getRealPath());
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            $zip->close();
            if ($size > 64 * 1024 * 1024) {
                $this->fail('Isi workbook terlalu besar. Pisahkan menjadi beberapa file.');
            }
        }
        $inputSheets = array_values(array_filter($reader->listWorksheetInfo($file->getRealPath()),
            fn ($sheet) => $sheet['worksheetName'] !== 'Petunjuk' && ! str_starts_with($sheet['worksheetName'], 'Master ')));
        if (count($inputSheets) > 8) {
            $this->fail('Maksimal 8 sheet data per file.');
        }
        foreach ($inputSheets as $sheet) {
            if ($sheet['totalRows'] > 2501 || $sheet['totalColumns'] > 60) {
                $this->fail('Maksimal 2.500 baris data dan 60 kolom per sheet: '.$sheet['worksheetName']);
            }
        }
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= 2502 && Coordinate::columnIndexFromString($columnAddress) <= 60;
            }
        });
        $book = $reader->load($file->getRealPath());
        $sheets = [];
        try {
            foreach ($book->getAllSheets() as $sheet) {
                if ($sheet->getTitle() === 'Petunjuk' || str_starts_with($sheet->getTitle(), 'Master ')) {
                    continue;
                }
                if (count($sheets) >= 8 || $sheet->getHighestDataRow() > 2501) {
                    $this->fail('Maksimal 8 sheet dan 2.500 baris data per sheet.');
                }
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $values = [];
                    foreach ($row->getCellIterator() as $cell) {
                        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                            $this->fail('Ubah rumus menjadi nilai (Paste Values) sebelum import: '.$sheet->getTitle().'!'.$cell->getCoordinate());
                        }
                        $values[] = $cell->getValue();
                    }
                    $rows[] = $values;
                }
                $headers = array_map(fn ($v) => trim((string) $v), array_shift($rows) ?? []);
                if (array_filter($headers)) {
                    $sheets[] = ['name' => $sheet->getTitle(), 'headers' => $headers, 'rows' => $rows];
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }
        if (! $sheets) {
            $this->fail('File tidak memiliki header pada baris pertama.');
        }

        return $sheets;
    }

    public function defaults(array $sheets): array
    {
        $mapping = [];
        foreach (self::LABELS as $group => $label) {
            $index = array_search($label, array_column($sheets, 'name'), true);
            if ($index === false) {
                $index = $group === 'documents' ? 0 : '';
            }
            $columns = [];
            foreach (self::FIELDS[$group] as $field) {
                $col = array_search($field, $sheets[$index]['headers'] ?? [], true);
                $columns[$field] = $col === false ? '' : $col;
            }
            $mapping[$group] = ['sheet' => $index, 'columns' => $columns];
        }

        return $mapping;
    }

    public function documents(array $sheets, array $mapping): array
    {
        $groups = [];
        foreach (self::FIELDS as $group => $fields) {
            $groups[$group] = [];
            $source = $mapping[$group]['sheet'] ?? '';
            if ($source === '' || $source === null) {
                continue;
            }
            if (! isset($sheets[$source])) {
                $this->fail('Sheet '.$group.' tidak valid.');
            }
            foreach ($sheets[$source]['rows'] as $index => $row) {
                if (! array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                    continue;
                }
                $record = [];
                foreach ($fields as $field) {
                    $column = $mapping[$group]['columns'][$field] ?? '';
                    if ($column !== '' && $column !== null) {
                        $value = $row[(int) $column] ?? null;
                        if ($value !== null && $value !== '') {
                            $record[$field] = is_string($value) ? trim($value) : $value;
                        }
                    }
                }
                if (! $record) {
                    continue;
                }
                foreach (['document_date', 'event_date', 'due_at', 'expired_at', 'paid_at'] as $date) {
                    if (isset($record[$date])) {
                        $record[$date] = $this->date($record[$date]);
                    }
                }
                $record['_row'] = $sheets[$source]['name'].' baris '.($index + 2);
                $groups[$group][] = $record;
            }
        }
        $documents = [];
        foreach ($groups['documents'] as $record) {
            $record['type'] = strtolower($record['type'] ?? 'quote');
            $record['number'] = (string) ($record['number'] ?? '');
            $key = $record['type'].':'.DocumentNumbers::key($record['number']);
            if (isset($documents[$key])) {
                $this->fail($record['_row'].': satu nomor hanya boleh memiliki satu baris pada sheet Dokumen.');
            }
            $record['person_id'] = $this->resolveId('persons', 'name', $record['person_id'] ?? null, $record['person_name'] ?? null);
            $record['user_id'] = $this->resolveId('users', 'name', $record['user_id'] ?? null, $record['sales_name'] ?? null);
            $record['items'] = [];
            $record['payments'] = [];
            $documents[$key] = $record;
        }
        foreach ($groups['items'] as $record) {
            $key = strtolower($record['type'] ?? 'quote').':'.DocumentNumbers::key((string) ($record['number'] ?? ''));
            if (! isset($documents[$key])) {
                $this->fail($record['_row'].': nomor dokumen untuk item tidak ditemukan.');
            }
            $record['product_id'] = $this->resolveId('products', 'sku', $record['product_id'] ?? null, $record['sku'] ?? null);
            $record['quantity'] ??= 1;
            $record['day'] ??= 1;
            $documents[$key]['items'][] = $record;
        }
        foreach ($groups['payments'] as $record) {
            $key = 'invoice:'.DocumentNumbers::key((string) ($record['number'] ?? ''));
            if (! isset($documents[$key])) {
                $this->fail($record['_row'].': pembayaran harus merujuk invoice pada file ini.');
            }
            $documents[$key]['payments'][] = $record;
        }

        return array_values($documents);
    }

    private function resolveId(string $table, string $column, mixed $id, mixed $name): mixed
    {
        if ($id !== null && $id !== '') {
            return $id;
        }
        if ($name === null || $name === '') {
            return null;
        }
        $matches = DB::table($table)->where($column, trim((string) $name))->pluck('id');
        if ($matches->count() !== 1) {
            $this->fail('Tidak dapat menentukan '.$table.' untuk "'.$name.'". Isi ID CRM yang tepat.');
        }

        return $matches->first();
    }

    private function date(mixed $value): string
    {
        if (is_numeric($value) && (float) $value > 0 && (float) $value < 100000) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', (string) $value, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return (string) $value;
    }

    public function template(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $this->sheet($book, 'Petunjuk', [
            ['INPUT DATA LAMA — maksimum 200 dokumen per import'],
            ['Dokumen: satu baris per quotation/invoice. type = quote atau invoice.'],
            ['Item: satu atau lebih baris per dokumen; type dan number harus sama dengan Dokumen.'],
            ['Pembayaran: satu baris per penerimaan; number adalah nomor invoice. Kosongkan jika belum dibayar.'],
            ['Nomor asli dipertahankan. Tanggal: YYYY-MM-DD atau DD/MM/YYYY. Nominal: angka tanpa pemisah ribuan.'],
            ['person_id / user_id: gunakan ID dari master. Alternatif: person_name / sales_name yang cocok tepat dan unik.'],
            ['product_id / sku wajib untuk item quotation. Nama item tetap isi sesuai dokumen lama.'],
            ['total pada Item = nilai baris sebelum diskon/pajak tingkat dokumen. quantity dan day default 1.'],
            ['grand_total = jumlah total item - discount_amount + tax_amount + adjustment_amount.'],
            ['business_unit = capture_it / varbel / vartech. bill_to_display_mode = person / company / both.'],
            ['Invoice: due_at wajib; billing_type = full_payment / down_payment / settlement. event_status = confirm / prospect / cancel.'],
            ['DP/pelunasan wajib quote_number. Pelunasan wajib dp_invoice_number dari invoice DP pada quote yang sama.'],
            ['Invoice penuh tanpa quotation diperbolehkan. Tidak membuat proforma, SPK, jadwal, atau email.'],
            ['paid_at = tanggal penerimaan asli. Status pembayaran dihitung otomatis dari nominal penerimaan.'],
            ['Periode yang dikunci tidak dapat diimpor. Rumus harus diganti nilai (Paste Values).'],
        ]);
        foreach (self::LABELS as $group => $label) {
            $this->sheet($book, $label, [self::FIELDS[$group]]);
        }
        $this->sheet($book, 'Master Contact', array_merge([['person_id', 'person_name', 'company']], DB::table('persons')->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id')->select('persons.id', 'persons.name', 'organizations.name as company')->orderBy('persons.id')->get()->map(fn ($row) => array_values((array) $row))->all()));
        $this->sheet($book, 'Master Sales', array_merge([['user_id', 'sales_name']], DB::table('users')->select('id', 'name')->orderBy('id')->get()->map(fn ($row) => array_values((array) $row))->all()));
        $this->sheet($book, 'Master Produk', array_merge([['product_id', 'sku', 'name']], DB::table('products')->select('id', 'sku', 'name')->orderBy('id')->get()->map(fn ($row) => array_values((array) $row))->all()));
        $book->setActiveSheetIndex(1);

        return $book;
    }

    private function sheet(Spreadsheet $book, string $title, array $rows): void
    {
        $sheet = new Worksheet($book, $title);
        $book->addSheet($sheet);
        foreach ($rows as $r => $values) {
            foreach ($values as $c => $value) {
                $sheet->setCellValueExplicit([$c + 1, $r + 1], (string) ($value ?? ''), DataType::TYPE_STRING);
            }
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->getDefaultColumnDimension()->setWidth(22);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
