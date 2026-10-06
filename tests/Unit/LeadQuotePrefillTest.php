<?php

use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Webkul\Admin\Bouncer;
use Webkul\Admin\Http\Controllers\Quote\QuoteController;
use Webkul\Admin\Services\CrmReadOnlyArchivePolicyService;
use Webkul\Admin\Services\LeadQuotePrefillService;
use Webkul\Admin\Services\QuoteSalesOwnerService;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Repositories\QuoteRepository;

uses(TestCase::class);

function leadQuotePrefillControllerFixture(object $lead, int $leadId): QuoteController
{
    $quotes = Mockery::mock(QuoteRepository::class);
    $quotes->shouldReceive('getModel')->andReturn(new Quote);
    $leads = Mockery::mock(LeadRepository::class);
    $leads->shouldReceive('findOrFail')->with($leadId)->andReturn($lead);
    $attributes = Mockery::mock(AttributeRepository::class);
    $attributes->shouldReceive('getLookUpEntity')->with('leads', $leadId)->andReturn(['id' => $leadId, 'name' => $lead->title]);
    $owners = Mockery::mock(QuoteSalesOwnerService::class);
    $owners->shouldReceive('initialSelection')->andReturn(['id' => 7, 'name' => 'Sales A']);
    $bouncer = Mockery::mock(Bouncer::class);
    $bouncer->shouldReceive('getAuthorizedUserIds')->andReturn([7]);
    app()->instance('bouncer', $bouncer);

    return new QuoteController(
        $quotes, $leads, $attributes,
        Mockery::mock(PersonRepository::class),
        Mockery::mock(CrmReadOnlyArchivePolicyService::class),
        $owners
    );
}

it('uses the same Lead defaults for Generate Quotation and Link to Lead', function (): void {
    $lead = (object) ['title' => 'Gathering', 'description' => 'Evening event', 'user_id' => 7, 'person_id' => null, 'person' => null, 'products' => collect()];
    request()->merge(['lead_id' => 41]);
    $controller = leadQuotePrefillControllerFixture($lead, 41);
    $view = $controller->create()->getData();
    $response = $controller->leadProducts(41)->getData(true);

    expect($view['quote']->subject)->toBe('Gathering')
        ->and($view['quote']->description)->toBe('Evening event')
        ->and($view['lookUpEntityData']['id'])->toBe(41)
        ->and($response['quote']['subject'])->toBe($view['quote']->subject)
        ->and($response['quote']['description'])->toBe($view['quote']->description)
        ->and($response['quote']['billing_address'])->toBe([])
        ->and($response['sales_owner']['id'])->toBe(7)
        ->and($response['data'])->toBe([]);
});

it('restores the submitted Lead and edited items after a validation redirect including cleared notes', function (): void {
    $lead = (object) ['title' => 'Original title', 'description' => 'Original note', 'user_id' => 7, 'person_id' => null, 'person' => null, 'products' => collect()];
    request()->merge(['lead_id' => 41]);
    request()->setLaravelSession(app('session')->driver());
    $items = ['item_0' => ['product_id' => 9, 'name' => 'Changed package', 'quantity' => 4, 'unit' => 'day', 'price' => 123]];
    session()->flashInput(['lead_id' => 42, 'subject' => 'Edited title', 'description' => null, 'items' => $items]);
    $controller = leadQuotePrefillControllerFixture($lead, 42);
    $view = $controller->create()->getData();

    expect($view['lookUpEntityData']['id'])->toBe(42)
        ->and($view['quote']->subject)->toBe('Edited title')
        ->and($view['quote']->description)->toBeNull()
        ->and($view['leadProducts'])->toBe(array_values($items));
});

it('copies product details and prices while preserving pcs day and historical line amounts', function (): void {
    $makeItem = static function (array $attributes) {
        $model = new class extends Model {};
        $model->setRawAttributes($attributes);
        $model->setRelation('product', (object) ['description' => 'Master package description']);

        return $model;
    };
    $lead = (object) ['products' => collect([
        $makeItem(['id' => 91, 'product_id' => 5, 'name' => 'Camera', 'quantity' => 2, 'unit' => 'pcs', 'price' => 100]),
        $makeItem(['id' => 92, 'product_id' => 6, 'name' => 'Booth', 'description' => 'Lead note', 'quantity' => 3, 'unit' => 'day', 'price' => 200]),
        $makeItem(['id' => 93, 'product_id' => 7, 'name' => 'Legacy', 'quantity' => 2, 'day' => 3, 'price' => 100]),
    ])];
    $service = new LeadQuotePrefillService;
    $items = $service->products($lead);

    expect($items)->toHaveCount(3)
        ->and(array_column($items, 'product_id'))->toBe([5, 6, 7])
        ->and(array_column($items, 'unit'))->toBe(['pcs', 'day', 'day'])
        ->and(array_column($items, 'total'))->toBe([200.0, 600.0, 600.0])
        ->and($items[0]['name'])->toBe('Camera')
        ->and($items[0]['description'])->toBe('Master package description')
        ->and($items[1]['description'])->toBe('Lead note')
        ->and($items[0]['id'])->toBeNull()
        ->and($service->products(null))->toBe([]);
});

it('copies matching Lead fields and keeps quotation numbering and totals independent', function (): void {
    $lead = (object) [
        'title' => 'Gathering PT Contoh',
        'description' => 'Photobooth di ballroom, mulai pukul 18.00.',
        'person_id' => 31,
        'user_id' => 7,
        'person' => (object) ['organization' => (object) ['address' => ['city' => 'Jakarta']]],
        'expected_close_date' => new DateTimeImmutable('2026-11-15'),
        'event_date' => '2026-11-20',
        'location' => 'Ballroom',
        'business_unit' => 'photobooth',
        'payment_term' => '14 Days',
        'lead_value' => 999999,
    ];

    $values = (new LeadQuotePrefillService)->attributes($lead);

    expect($values['subject'])->toBe($lead->title)
        ->and($values['description'])->toBe($lead->description)
        ->and($values['person_id'])->toBe(31)
        ->and($values['user_id'])->toBe(7)
        ->and($values['billing_address'])->toBe(['city' => 'Jakarta'])
        ->and($values['shipping_address'])->toBe($values['billing_address'])
        ->and($values['expired_at'])->toBe('2026-11-15')
        ->and($values['event_date'])->toBe('2026-11-20')
        ->and($values['location'])->toBe('Ballroom')
        ->and($values['business_unit'])->toBe('photobooth')
        ->and($values['payment_term'])->toBe('14 Days')
        ->and(array_intersect_key($values, array_flip(['quote_number', 'project_code', 'grand_total', 'lead_value', 'id'])))->toBe([]);
});

it('prefers the Lead address and falls back to company then contact without requiring an address', function (): void {
    $lead = (object) [
        'address' => ['address' => 'Lokasi acara'],
        'person' => (object) [
            'address' => ['city' => 'Bogor'],
            'organization' => (object) ['address' => '{"city":"Bandung"}'],
        ],
    ];
    $service = new LeadQuotePrefillService;
    expect($service->attributes($lead)['billing_address'])->toBe(['address' => 'Lokasi acara']);

    $lead->address = ['address' => '', 'city' => null];
    expect($service->attributes($lead)['billing_address'])->toBe(['city' => 'Bandung']);

    $lead->person->organization->address = [];
    expect($service->attributes($lead)['billing_address'])->toBe(['city' => 'Bogor']);

    $lead->person = null;
    $values = $service->attributes($lead);
    expect($values['billing_address'])->toBe([])
        ->and($values['shipping_address'])->toBe([])
        ->and($values['subject'])->toBe('')
        ->and($values['event_date'])->toBeNull();
});

it('resolves optional EAV fields using the Lead definition rather than a same-code Quote attribute', function (): void {
    $definitions = collect([
        (object) ['id' => 71, 'code' => 'event_date'],
        (object) ['id' => 72, 'code' => 'location'],
    ]);
    $repository = Mockery::mock(AttributeRepository::class);
    $repository->shouldReceive('findWhere')->once()->with(['entity_type' => 'leads'])->andReturn($definitions);
    app()->instance(AttributeRepository::class, $repository);

    $lead = new class extends Model
    {
        protected $table = 'leads';

        public function getCustomAttributeValue($attribute)
        {
            return [71 => '2026-11-20', 72 => 'Hall A'][$attribute->id] ?? null;
        }
    };
    $lead->setRawAttributes(['title' => 'Custom Lead', 'description' => '', 'expected_close_date' => null]);
    $lead->setRelation('person', null);
    $values = (new LeadQuotePrefillService)->attributes($lead);

    expect($values['event_date'])->toBe('2026-11-20')
        ->and($values['location'])->toBe('Hall A')
        ->and($values['billing_address'])->toBe([]);
});
