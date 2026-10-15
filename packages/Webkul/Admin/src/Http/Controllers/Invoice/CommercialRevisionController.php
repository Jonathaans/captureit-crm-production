<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\CommercialRevisionService;

class CommercialRevisionController extends Controller
{
    public function edit(string $documentType, int $id, CommercialRevisionService $service)
    {
        $this->authorizeRevision();
        $preview = $service->preview($documentType, $id);

        return view('admin::invoices.revisions.edit', $preview + [
            'sourceType' => $documentType, 'sourceId' => $id,
            'people' => DB::table('persons')->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id')
                ->select('persons.id', 'persons.name', 'organizations.name as company')->orderBy('persons.name')->get(),
            'products' => DB::table('products')->select('id', 'name', 'sku')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, string $documentType, int $id, CommercialRevisionService $service)
    {
        $this->authorizeRevision();
        try {
            $auditId = $service->apply($documentType, $id, $request->all(), (int) auth()->guard('user')->id());
        } catch (ValidationException $e) {
            // Never repopulate an obsolete form over somebody else's newer revision.
            if (isset($e->errors()['fingerprint'])) {
                return redirect()->route('admin.commercial-revisions.edit', [$documentType, $id])->withErrors($e->errors())->withInput([]);
            }

            throw $e;
        }

        return redirect()->route('admin.commercial-revisions.edit', [$documentType, $id])
            ->with('success', 'Revisi tersimpan. Quote, Proforma, Invoice, dan financial report sudah diperbarui. Riwayat #'.$auditId.'.');
    }

    private function authorizeRevision(): void
    {
        abort_unless(auth()->guard('user')->check() && bouncer()->hasPermission('invoices.revise'), 403);
    }
}
