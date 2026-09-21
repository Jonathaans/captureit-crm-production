<?php

namespace Webkul\Admin\Http\Controllers\Quote;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Prettus\Repository\Criteria\RequestCriteria;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\DataGrids\Quote\QuoteDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\QuoteResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Quote\Repositories\QuoteRepository;

class QuoteController extends Controller
{
    use PDFHandler;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected QuoteRepository $quoteRepository,
        protected LeadRepository $leadRepository,
        protected AttributeRepository $attributeRepository,
        protected PersonRepository $personRepository,
    ) {
        request()->request->add(['entity_type' => 'quotes']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(QuoteDataGrid::class)->process();
        }

        return view('admin::quotes.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $leadId = request('lead_id');

        $lead = $leadId ? $this->leadRepository->find($leadId) : null;

        $quote = $this->quoteRepository->getModel();

        if ($lead) {
            $quote->fill([
                'person_id' => $lead->person_id,
                'user_id' => $lead->user_id,
                'billing_address' => $lead->person->organization?->address,
                'expired_at' => $lead->expected_close_date ?? now()->toDateString(),
            ]);
        }

        $leadProducts = $this->getLeadProductsForQuote($lead);

        $lookUpEntityData = $this->attributeRepository->getLookUpEntity('leads', $leadId);

        /*
         * Initial Bill To / Client.
         *
         * Digunakan oleh lookup Bill To di create.blade.php.
         */
        $personId = old('person_id') ?: $quote->person_id;

        $personLookUpEntityData = $personId
            ? $this->formatBillToPerson($this->personRepository->findOrFail($personId))
            : [];

        return view(
            'admin::quotes.create',
            compact(
                'lead',
                'quote',
                'leadProducts',
                'lookUpEntityData',
                'personLookUpEntityData'
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request): RedirectResponse|JsonResponse
    {
        if (! request()->has('quick_add')) {
            $this->additionalValidation();

            /* QUOTE SALES OWNER ROLE VALIDATION - CREATE */
            $this->validateSalesOwnerSelection();
        }

        $this->syncShippingAddressWithBilling($request);

        Event::dispatch('quote.create.before');

        /*
         * Generate Project Code otomatis.
         *
         * Contoh:
         * PRJ-2026-00001
         * PRJ-2026-00002
         */
        $data = $this->prepareBillToIdentity($request->all());

        /*
         * Generate Project Code.
         *
         * Example:
         * PRJ-2026-00001
         */
        $data['project_code'] = app(
            \Webkul\Quote\Services\ProjectCodeService::class
        )->generate();

        /*
         * Generate Quotation Number.
         *
         * Example:
         * QT 2608-0001
         */
        $data['quote_number'] = app(
            \Webkul\Quote\Services\QuoteNumberService::class
        )->generate();

        $quote = $this->quoteRepository->create($data);

        $leadId = request('lead_id');

        if ($leadId) {
            $lead = $this->leadRepository->find($leadId);

            $lead->quotes()->attach($quote->id);
        }

        Event::dispatch('quote.create.after', $quote);

        if (request()->ajax()) {
            return response()->json([
                'data' => $quote,
                'message' => trans('admin::app.quotes.index.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.quotes.index.create-success'));

        return request()->query('from') === 'lead' && $leadId
            ? redirect()->route('admin.leads.view', ['id' => $leadId, 'from' => 'quotes'])
            : redirect()->route('admin.quotes.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $quote = $this->quoteRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($quote->user_id);

        $leadId = old('lead_id') ?? optional($quote->leads->first())->id;

        $linkedLead = $leadId ? $this->leadRepository->find($leadId) : null;

        $initialQuoteItems = $quote->items;

        if ($initialQuoteItems->isEmpty() && $linkedLead?->products?->isNotEmpty()) {
            $initialQuoteItems = collect($this->getLeadProductsForQuote($linkedLead));
        }

        $lookUpEntityData = $this->attributeRepository->getLookUpEntity('leads', $leadId);

        $personId = old('person_id') ?: $quote->person_id;

        $personLookUpEntityData = $personId
            ? $this->formatBillToPerson($this->personRepository->findOrFail($personId))
            : [];

        return view(
            'admin::quotes.edit',
            compact(
                'quote',
                'linkedLead',
                'initialQuoteItems',
                'lookUpEntityData',
                'personLookUpEntityData'
            )
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id): RedirectResponse
    {
        $this->preventUnauthorizedAccess($this->quoteRepository->findOrFail($id)->user_id);

        $this->additionalValidation();

        /* QUOTE SALES OWNER ROLE VALIDATION - EDIT */
        $currentQuoteOwnerId = (int) $this->quoteRepository
            ->findOrFail($id)
            ->user_id;

        $this->validateSalesOwnerSelection(
            $currentQuoteOwnerId
        );

        $this->syncShippingAddressWithBilling($request);

        Event::dispatch('quote.update.before', $id);

        $quote = $this->quoteRepository->update(
            $this->prepareBillToIdentity($request->all()),
            $id
        );

        $quote->leads()->detach();

        $leadId = request('lead_id');

        if ($leadId) {
            $lead = $this->leadRepository->find($leadId);

            $lead->quotes()->attach($quote->id);
        }

        Event::dispatch('quote.update.after', $quote);

        session()->flash('success', trans('admin::app.quotes.index.update-success'));

        return request()->query('from') === 'lead' && $leadId
            ? redirect()->route('admin.leads.view', ['id' => $leadId, 'from' => 'quotes'])
            : redirect()->route('admin.quotes.index');
    }

    /**
     * Search the quotes.
     */
    public function search(): AnonymousResourceCollection
    {
        $quotes = $this->quoteRepository
            ->pushCriteria(app(RequestCriteria::class))
            ->all();

        return QuoteResource::collection($quotes);
    }

    /**
     * Return products for the selected lead in quote payload format.
     */
    public function leadProducts(int $leadId): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($leadId);

        return response()->json([
            'data' => $this->getLeadProductsForQuote($lead),
        ]);
    }

    /**
     * Return contacts for the Bill To lookup, including their company name.
     */
    public function billToPeople(): JsonResponse
    {
        $searchTerm = trim((string) request()->query('query', ''));
        $limit = min(max((int) request()->query('limit', 20), 1), 50);

        $query = $this->personRepository
            ->getModel()
            ->newQuery()
            ->with('organization')
            ->when($searchTerm !== '', function ($query) use ($searchTerm) {
                $query->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', '%'.$searchTerm.'%')
                        ->orWhereHas('organization', function ($query) use ($searchTerm) {
                            $query->where('name', 'like', '%'.$searchTerm.'%');
                        });
                });
            });

        $authorizedUserIds = bouncer()->getAuthorizedUserIds();

        if ($authorizedUserIds) {
            $query->whereIn('user_id', $authorizedUserIds);
        }

        $people = $query
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn ($person) => $this->formatBillToPerson($person))
            ->values();

        return response()->json($people);
    }

    /**
     * Return one contact for the Bill To lookup.
     */
    public function billToPerson(): JsonResponse
    {
        $person = $this->personRepository->findOrFail(
            (int) request()->query('query')
        );

        if (
            ($authorizedUserIds = bouncer()->getAuthorizedUserIds())
            && ! in_array($person->user_id, $authorizedUserIds)
        ) {
            abort(401, trans('admin::app.errors.unauthorized'));
        }

        return response()->json($this->formatBillToPerson($person));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->preventUnauthorizedAccess($this->quoteRepository->findOrFail($id)->user_id);

        try {
            Event::dispatch('quote.delete.before', $id);

            $this->quoteRepository->delete($id);

            Event::dispatch('quote.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.quotes.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.quotes.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $quotes = $this->filterAuthorizedRecords(
            $this->quoteRepository->findWhereIn('id', $massDestroyRequest->input('indices'))
        );

        try {
            foreach ($quotes as $quotes) {
                Event::dispatch('quote.delete.before', $quotes->id);

                $this->quoteRepository->delete($quotes->id);

                Event::dispatch('quote.delete.after', $quotes->id);
            }

            return response()->json([
                'message' => trans('admin::app.quotes.index.delete-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.quotes.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Print and download the for the specified resource.
     */
    public function print($id): Response|StreamedResponse
    {
        $quote = $this->quoteRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($quote->user_id);

        return $this->downloadPDF(
            view('admin::quotes.pdf', compact('quote'))->render(),
            'Quote_'.$quote->subject.'_'.$quote->created_at->format('d-m-Y')
        );
    }

    /**
     * Mirror the billing address into the shipping address when "same as billing" is enabled.
     */
    private function syncShippingAddressWithBilling(AttributeForm $request): void
    {
        if ($request->boolean('shipping_address_same_as_billing')) {
            $request->merge([
                'shipping_address' => $request->input('billing_address'),
            ]);
        }
    }

    /**
     * QUOTE SALES OWNER ROLE VALIDATION
     *
     * New owner must be Sales Admin or Sales User.
     * Existing legacy owner may stay unchanged on old Quotes.
     */
    private function validateSalesOwnerSelection(
        ?int $currentOwnerId = null
    ): void {
        $selectedOwnerId =
            (int) request(
                'user_id'
            );

        if (
            $currentOwnerId
            && $selectedOwnerId === $currentOwnerId
        ) {
            return;
        }

        if (
            ! app(
                \Webkul\Admin\Services\QuoteSalesOwnerService::class
            )->isEligible(
                $selectedOwnerId
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' =>
                    'Sales Owner harus memiliki role Sales Admin atau Sales User.',
            ]);
        }
    }

    /**
     * Additional validation for quote product items.
     */
    private function additionalValidation(): void
    {
        $this->validate(request(), [
            'bill_to_display_mode' => ['nullable', Rule::in(['person', 'company', 'both'])],
            'client_signer_name' => 'nullable|string|max:255',
            'client_signer_company' => 'nullable|string|max:255',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'required|numeric|min:0',
            'items.*.tax_amount' => 'required|numeric|min:0',
            'items.*.final_total' => 'required|numeric|min:0',
        ]);
    }

    /**
     * Store immutable Bill To labels with the quotation.
     */
    private function prepareBillToIdentity(array $data): array
    {
        $personId = (int) ($data['person_id'] ?? 0);

        if (! $personId) {
            return $data;
        }

        $person = $this->personRepository->findOrFail($personId);
        $personName = trim((string) $person->name);
        $companyName = trim((string) ($person->organization?->name ?? ''));
        $displayMode = $data['bill_to_display_mode'] ?? ($companyName !== '' ? 'both' : 'person');

        if (! in_array($displayMode, ['person', 'company', 'both'], true)) {
            $displayMode = $companyName !== '' ? 'both' : 'person';
        }

        if (
            in_array($displayMode, ['company', 'both'], true)
            && $companyName === ''
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bill_to_display_mode' => 'Contact yang dipilih belum memiliki Company.',
            ]);
        }

        $data['bill_to_display_mode'] = $displayMode;
        $data['bill_to_person_name'] = $personName;
        $data['bill_to_company_name'] = $companyName ?: null;
        $data['client_signer_name'] = trim((string) ($data['client_signer_name'] ?? '')) ?: $personName;

        if (array_key_exists('client_signer_company', $data)) {
            $data['client_signer_company'] = trim((string) $data['client_signer_company']) ?: null;
        } else {
            $data['client_signer_company'] = $companyName ?: null;
        }

        return $data;
    }

    /**
     * Format a contact consistently for the Bill To lookup and form preview.
     */
    private function formatBillToPerson($person): array
    {
        $personName = trim((string) $person->name);
        $companyName = trim((string) ($person->organization?->name ?? ''));

        return [
            'id' => $person->id,
            'name' => $companyName !== ''
                ? $personName.' — '.$companyName
                : $personName,
            'person_name' => $personName,
            'company_name' => $companyName,
        ];
    }

    /**
     * Map linked lead products to quote item payload format.
     */
    private function getLeadProductsForQuote($lead): array
    {
        if (! $lead?->products?->isNotEmpty()) {
            return [];
        }

        return $lead->products
            ->map(function ($product) {
                $quantity = (float) ($product->quantity ?: 1);
                $price = (float) ($product->price ?: 0);

                return [
                    'id' => null,
                    'product_id' => $product->product_id,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'total' => $price * $quantity,
                    'price' => $price,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ];
            })
            ->values()
            ->toArray();
    }
}
