<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Webkul\Admin\Services\CrmReadOnlyArchivePolicyService;
use Webkul\Admin\Services\QuoteDeletionService;
use Webkul\Quote\Repositories\QuoteRepository;

uses(TestCase::class);

class QuoteDeletionFixture extends Model
{
    protected $table = 'quotes';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function (): void {
    $this->previousConnection = DB::getDefaultConnection();
    config(['database.connections.quote_delete_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('quote_delete_test');
    Schema::clearResolvedInstance('db.schema');
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Only an in-memory test database may be used.');
    }
    Event::fake(['quote.delete.before', 'quote.delete.after']);
    Schema::create('quotes', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('user_id');
        $table->dateTime('expired_at')->nullable();
    });
    Schema::create('invoices', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('quote_id');
    });
    Schema::create('quote_items', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('quote_id');
        $table->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
    });
    DB::table('quotes')->insert([
        ['id' => 1, 'user_id' => 7, 'expired_at' => '2099-01-01'],
        ['id' => 2, 'user_id' => 8, 'expired_at' => '2099-01-01'],
    ]);
    DB::table('quote_items')->insert([['quote_id' => 1], ['quote_id' => 2]]);
    $this->repository = Mockery::mock(QuoteRepository::class);
    $this->repository->shouldReceive('getModel')->andReturn(new QuoteDeletionFixture);
    $this->service = new QuoteDeletionService($this->repository, new CrmReadOnlyArchivePolicyService);
});

afterEach(function (): void {
    DB::purge('quote_delete_test');
    DB::setDefaultConnection($this->previousConnection);
    Schema::clearResolvedInstance('db.schema');
});

it('deletes an eligible quote and its items', function (): void {
    $this->repository->shouldReceive('delete')->once()->with(1)->andReturnUsing(fn ($id) => QuoteDeletionFixture::findOrFail($id)->delete());
    $this->service->delete([1], fn ($quote) => expect($quote->user_id)->toBe(7));
    expect(DB::table('quotes')->pluck('id')->all())->toBe([2])
        ->and(DB::table('quote_items')->pluck('quote_id')->all())->toBe([2]);
    Event::assertDispatched('quote.delete.after');
});

it('explains archive restrictions and leaves the whole selection untouched', function (string $reason): void {
    if ($reason === 'invoice') {
        DB::table('invoices')->insert(['quote_id' => 2]);
    } else {
        DB::table('quotes')->where('id', 2)->update(['expired_at' => '2000-01-01']);
    }
    $this->repository->shouldNotReceive('delete');
    try {
        $this->service->delete([1, 2], fn () => null);
        $this->fail('An archived quote must not be deleted.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['quotes'][0])->toContain('Quotation #2')
            ->toContain($reason === 'invoice' ? 'Invoice' : 'kedaluwarsa');
    }
    expect(DB::table('quotes')->count())->toBe(2)
        ->and(DB::table('quote_items')->count())->toBe(2);
    Event::assertNotDispatched('quote.delete.before');
})->with(['invoice', 'expired']);

it('rolls back earlier deletes when a later deletion fails', function (): void {
    $this->repository->shouldReceive('delete')->with(1)->andReturnUsing(fn ($id) => QuoteDeletionFixture::findOrFail($id)->delete());
    $this->repository->shouldReceive('delete')->with(2)->andThrow(new RuntimeException('Simulated failure'));
    try {
        $this->service->delete([1, 2], fn () => null);
        $this->fail('Expected deletion failure.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated failure');
    }
    expect(DB::table('quotes')->count())->toBe(2)
        ->and(DB::table('quote_items')->count())->toBe(2);
});

it('does not silently skip a quote owned by another user', function (): void {
    $this->repository->shouldNotReceive('delete');
    try {
        $this->service->delete([1, 2], function ($quote): void {
            if ($quote->user_id !== 7) {
                throw new HttpException(403);
            }
        });
        $this->fail('Expected authorization failure.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
    expect(DB::table('quotes')->count())->toBe(2);
    Event::assertNotDispatched('quote.delete.before');
});
