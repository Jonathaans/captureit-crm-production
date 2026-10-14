<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\HistoricalDocumentBatch;
use Webkul\Admin\Services\HistoricalDocumentService;
use Webkul\Admin\Services\HistoricalWorkbook;
use Webkul\Core\Support\BusinessUnit;

class HistoricalDocumentController extends Controller
{
    private function authorizeImport(): int
    {
        abort_unless(auth()->guard('user')->check() && bouncer()->hasPermission('historical-documents'), 403);

        return (int) auth()->guard('user')->id();
    }

    public function index()
    {
        $actor = $this->authorizeImport();

        return view('admin::historical-documents.index', [
            'batches' => HistoricalDocumentBatch::where('created_by', $actor)->latest()->paginate(15),
        ]);
    }

    public function create(Request $request)
    {
        $actor = $this->authorizeImport();
        $document = $request->filled('batch')
            ? HistoricalDocumentBatch::where('created_by', $actor)->where('source', 'Input manual')->findOrFail($request->input('batch'))->payload['documents'][0]
            : null;

        return view('admin::historical-documents.create', [
            'document' => $document,
            'people' => DB::table('persons')->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id')
                ->select('persons.id', 'persons.name', 'organizations.name as company')->orderBy('persons.name')->get(),
            'owners' => DB::table('users')->select('id', 'name')->orderBy('name')->get(),
            'products' => DB::table('products')->select('id', 'sku', 'name')->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::options(),
        ]);
    }

    public function preview(Request $request, HistoricalDocumentService $service)
    {
        $actor = $this->authorizeImport();
        $input = $request->validate(['documents' => 'required|array|max:200']);
        $documents = $service->validate($input['documents']);
        $batch = HistoricalDocumentBatch::create([
            'id' => (string) Str::uuid(), 'created_by' => $actor, 'source' => 'Input manual',
            'status' => 'preview', 'payload' => ['documents' => $documents], 'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('admin.historical-documents.show', $batch->id);
    }

    public function upload(Request $request, HistoricalWorkbook $workbook)
    {
        $actor = $this->authorizeImport();
        $request->validate(['file' => 'required|file|extensions:xlsx,xls,csv|max:2048']);
        try {
            $sheets = $workbook->read($request->file('file'));
        } catch (Exception $e) {
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca. Simpan ulang sebagai XLSX atau CSV.']);
        }
        $batch = HistoricalDocumentBatch::create([
            'id' => (string) Str::uuid(), 'created_by' => $actor,
            'source' => mb_substr(basename($request->file('file')->getClientOriginalName()), 0, 255),
            'status' => 'mapping', 'payload' => ['sheets' => $sheets], 'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('admin.historical-documents.show', $batch->id);
    }

    public function show(string $id, HistoricalWorkbook $workbook)
    {
        $actor = $this->authorizeImport();
        $batch = HistoricalDocumentBatch::where('created_by', $actor)->findOrFail($id);

        return view('admin::historical-documents.show', [
            'batch' => $batch, 'fields' => HistoricalWorkbook::FIELDS, 'labels' => HistoricalWorkbook::LABELS,
            'mapping' => $batch->payload['mapping'] ?? $workbook->defaults($batch->payload['sheets'] ?? []),
        ]);
    }

    public function map(Request $request, string $id, HistoricalWorkbook $workbook, HistoricalDocumentService $service)
    {
        $actor = $this->authorizeImport();
        $request->validate(['mapping' => 'required|array', 'mapping.*.sheet' => 'nullable|integer|between:0,7', 'mapping.*.columns' => 'required|array', 'mapping.*.columns.*' => 'nullable|integer|between:0,59']);
        DB::transaction(function () use ($id, $actor, $request, $workbook, $service) {
            $batch = HistoricalDocumentBatch::where('created_by', $actor)->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($batch->status, ['mapping', 'preview'], true) && isset($batch->payload['sheets']) && $batch->expires_at->isFuture(), 409);
            $mapping = $request->input('mapping');
            $documents = $service->validate($workbook->documents($batch->payload['sheets'], $mapping));
            $batch->update(['status' => 'preview', 'payload' => array_merge($batch->payload, compact('mapping', 'documents'))]);
        });

        return redirect()->route('admin.historical-documents.show', $id);
    }

    public function store(Request $request, string $id, HistoricalDocumentService $service)
    {
        $actor = $this->authorizeImport();
        $request->validate(['confirm' => 'required|accepted']);
        $service->commit($id, $actor);

        return redirect()->route('admin.historical-documents.show', $id)->with('success', 'Data lama berhasil disimpan.');
    }

    public function template(HistoricalWorkbook $workbook)
    {
        $this->authorizeImport();
        $book = $workbook->template();

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'template-data-lama-crm.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
