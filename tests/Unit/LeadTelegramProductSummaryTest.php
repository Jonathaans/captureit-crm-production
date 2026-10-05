<?php

use Tests\TestCase;
use Webkul\Automation\Helpers\Entity\Lead;
use Webkul\Automation\Repositories\WebhookRepository;
use Webkul\Automation\Services\LeadProductSummary;
use Webkul\Automation\Services\WebhookService;

uses(TestCase::class);

class TelegramLeadHelperFixture extends Lead
{
    public array $fixtureAttributes = [];

    public function __construct() {}

    public function getAttributes(string $entityType, array $skipAttributes = ['textarea', 'image', 'file', 'address']): array
    {
        return $this->fixtureAttributes;
    }

    public function setWebhookDependencies(WebhookRepository $repository, WebhookService $service): void
    {
        $this->webhookRepository = $repository;
        $this->webhookService = $service;
    }
}

it('formats pcs and day products with quantity price and subtotal', function (): void {
    $summary = (new LeadProductSummary)->format([
        ['name' => 'Photobooth', 'quantity' => 2, 'unit' => 'day', 'day' => 7, 'price' => 5000000],
        ['name' => 'Print', 'quantity' => 3, 'unit' => 'pcs', 'price' => 10000],
    ]);
    expect($summary)->toContain('1. <b>Photobooth</b>', '2 day × Rp 5.000.000,00 = <b>Rp 10.000.000,00</b>')
        ->toContain('2. <b>Print</b>', '3 pcs × Rp 10.000,00 = <b>Rp 30.000,00</b>');
});

it('preserves historical day times quantity pricing', function (): void {
    $summary = (new LeadProductSummary)->format([
        ['name' => 'Legacy', 'quantity' => 2, 'day' => 3, 'unit' => null, 'price' => 100],
    ]);
    expect($summary)->toContain('6 day × Rp 100,00 = <b>Rp 600,00</b>');
});

it('escapes product text and provides an explicit empty state', function (): void {
    $summary = (new LeadProductSummary)->format([
        ['name' => 'Booth "A" & <B>', 'quantity' => 1, 'unit' => 'pcs', 'price' => 25.5],
    ]);
    expect($summary)->toContain('Booth &quot;A&quot; &amp; &lt;B&gt;', 'Rp 25,50')
        ->and((new LeadProductSummary)->format([]))->toContain('Belum ada produk');
});

it('limits long product lists without breaking HTML tags', function (): void {
    $summary = (new LeadProductSummary)->format(array_fill(0, 100, [
        'name' => str_repeat('Produk & ', 30), 'quantity' => 2, 'unit' => 'pcs', 'price' => 100,
    ]));
    expect(mb_strlen(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8')))->toBeLessThan(2400)
        ->and(substr_count($summary, '<b>'))->toBe(substr_count($summary, '</b>'))
        ->and($summary)->toContain('produk lainnya');
});

it('resolves the placeholder from lead product rows even without a contact', function (): void {
    $row = new class
    {
        public object $product;

        public function __construct()
        {
            $this->product = (object) ['name' => 'Camera'];
        }

        public function getAttributes(): array
        {
            return ['quantity' => 2, 'unit' => 'pcs', 'price' => 100];
        }
    };
    $query = Mockery::mock();
    $query->shouldReceive('with')->with('product')->andReturnSelf();
    $query->shouldReceive('orderBy')->with('id')->andReturnSelf();
    $query->shouldReceive('get')->andReturn(collect([$row]));
    $lead = new class($query)
    {
        public $person = null;

        public function __construct(public $query) {}

        public function products()
        {
            return $this->query;
        }
    };
    $helper = new TelegramLeadHelperFixture;
    $result = $helper->replacePlaceholders($lead, '{%leads.products_detail%} / {% leads.products_detail %}');
    expect($result)->toContain('Camera', '2 pcs')->not->toContain('{%');
    expect($helper->getEmailTemplatePlaceholders(['name' => 'Leads'])['menu'])->toContain([
        'text' => 'Detail Produk Lead (Telegram HTML)', 'value' => '{%leads.products_detail%}',
    ]);
});

it('keeps webhook JSON valid when placeholder data contains quotes and newlines', function (): void {
    $helper = new TelegramLeadHelperFixture;
    $helper->fixtureAttributes = [['id' => 'title', 'type' => 'text', 'name' => 'Title']];
    $lead = (object) ['title' => "Event \"A\"\nDay 2 \\ Booth", 'person' => null];
    $webhook = (object) [
        'method' => 'POST', 'end_point' => 'https://example.invalid/webhook',
        'query_params' => [['key' => 'title', 'value' => '{%leads.title%}']],
        'headers' => [['key' => 'Content-Type', 'value' => 'application/x-www-form-urlencoded']],
        'payload' => [['key' => 'text', 'value' => '{%leads.title%}'], ['key' => 'enabled', 'value' => false]],
    ];
    $repository = Mockery::mock(WebhookRepository::class);
    $repository->shouldReceive('findOrFail')->once()->with(9)->andReturn($webhook);
    $service = Mockery::mock(WebhookService::class);
    $service->shouldReceive('triggerWebhook')->once()->withArgs(function ($payload) use ($lead): bool {
        $decoded = json_decode($payload['payload'], true, flags: JSON_THROW_ON_ERROR);
        expect($decoded[0]['value'])->toBe($lead->title)
            ->and($decoded[1]['value'])->toBeFalse()
            ->and(json_decode($payload['query_params'], true)[0]['value'])->toBe($lead->title);

        return true;
    })->andReturn(['status' => 'success']);
    $helper->setWebhookDependencies($repository, $service);
    $helper->triggerWebhook(9, $lead);
});
