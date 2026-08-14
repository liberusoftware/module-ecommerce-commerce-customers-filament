<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CommerceCustomers\Actions\RecordConsent;
use Liberu\Ecommerce\CommerceCustomers\Contracts\ResolvesPurchaseHistory;
use Liberu\Ecommerce\CommerceCustomers\Data\LifetimeValue;
use Liberu\Ecommerce\CommerceCustomers\Data\Money;
use Liberu\Ecommerce\CommerceCustomers\Data\PurchaseHistory;
use Liberu\Ecommerce\CommerceCustomers\Enums\ConsentChannel;
use Liberu\Ecommerce\CommerceCustomers\Enums\ConsentState;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFilePresenter;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestTenant;
use Livewire\Livewire;

/*
 * Orders is unbound by default and that fails exactly one read.
 *
 * "Not available" rather than "0 orders, 0.00 spent": zero and no answer are
 * the same number and opposite facts, and a merchant shown a zero acts on it —
 * refuses a refund, closes a file, drops somebody from a campaign.
 */

it('renders an unavailable purchase history rather than a zero, with Orders absent', function () {
    $file = openFile();

    $result = CustomerFilePresenter::purchaseHistory($file);

    expect($result->available)->toBeFalse()
        ->and(CustomerFilePresenter::orderCountLine($result))->toStartWith('Not available')
        ->and(CustomerFilePresenter::orderCountLine($result))->not->toBe('0')
        ->and(CustomerFilePresenter::lifetimeValueLines($result))->toBe([
            'Not available — No purchase-history resolver is bound; Orders is not composed into this application.',
        ]);
});

it('shows the unavailability on the file page itself', function () {
    $file = openFile();

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertSee('Not available')
        ->assertDontSee('0 orders');
});

/*
 * Nothing else fails. A module that 503s a customer's address because Orders is
 * absent has misread the rule.
 */
it('reads the profile, the consent and the lifecycle normally with Orders absent', function () {
    $file = openFile();

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertSuccessful()
        ->assertSee($file->reference)
        ->assertSee('Prospect');
});

/*
 * A lifetime value is a map of currency to amount. Two currencies do not add.
 * The host's `sum('total_amount')` over orders in every currency is the fault
 * this rule exists to prevent, and folding the map into one figure here would
 * rebuild it in the one place a merchant actually reads.
 */
it('renders a lifetime value as one line per currency and never as one number', function () {
    $file = openFile();

    app()->bind(ResolvesPurchaseHistory::class, fn (): ResolvesPurchaseHistory => new class() implements ResolvesPurchaseHistory
    {
        public function purchaseHistoryFor(string $tenantId, string $personRef): ?PurchaseHistory
        {
            return new PurchaseHistory(
                orderCount: 3,
                firstPurchaseAt: CarbonImmutable::parse('2026-01-02 10:00:00'),
                lastPurchaseAt: CarbonImmutable::parse('2026-08-01 10:00:00'),
                lifetimeValue: LifetimeValue::of(
                    new Money(1999, 'GBP'),
                    new Money(1200, 'EUR'),
                    new Money(500, 'GBP'),
                ),
            );
        }
    });

    $result = CustomerFilePresenter::purchaseHistory($file);

    expect($result->available)->toBeTrue()
        ->and(CustomerFilePresenter::lifetimeValueLines($result))->toBe(['EUR 12.00', 'GBP 24.99'])
        ->and(CustomerFilePresenter::orderCountLine($result))->toBe('3');

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertSee('GBP 24.99')
        ->assertSee('EUR 12.00');
});

it('says nothing was bought rather than showing an empty list, when the resolver knows of no purchases', function () {
    $file = openFile();

    app()->bind(ResolvesPurchaseHistory::class, fn (): ResolvesPurchaseHistory => new class() implements ResolvesPurchaseHistory
    {
        public function purchaseHistoryFor(string $tenantId, string $personRef): ?PurchaseHistory
        {
            return new PurchaseHistory(0, null, null, LifetimeValue::none());
        }
    });

    $result = CustomerFilePresenter::purchaseHistory($file);

    expect(CustomerFilePresenter::lifetimeValueLines($result))->toBe(['No purchases recorded in any currency.'])
        ->and(CustomerFilePresenter::orderCountLine($result))->toBe('0')
        ->and(CustomerFilePresenter::purchaseInstantLine($result, true))->toBe('Never');
});

it('asks Orders about nobody when the file is a guest file', function () {
    $file = openFile(null);

    $result = CustomerFilePresenter::purchaseHistory($file);

    expect($result->available)->toBeFalse()
        ->and($result->reason)->toContain('guest file');
});

/*
 * A profile edit that fails because Orders is down would be a control with no
 * reason behind it. Neither seam is reachable from a write path.
 */
it('never consults a seam from a write path', function () {
    $sources = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src')) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
        }
    }

    $seamReaders = array_keys(array_filter(
        $sources,
        static fn (string $source): bool => str_contains($source, 'GetPurchaseHistory') || str_contains($source, 'GetPersonIdentity'),
    ));

    expect(array_map(basename(...), $seamReaders))->toBe(['CustomerFilePresenter.php']);
});

it('folds consent per channel, with an unasked channel reading as never asked', function () {
    $file = openFile();

    app(RecordConsent::class)($file, TestTenant::PRIMARY, ConsentChannel::Email, ConsentState::Granted, 'actor:test');
    app(RecordConsent::class)($file, TestTenant::PRIMARY, ConsentChannel::Email, ConsentState::Withdrawn, 'actor:test');

    expect(CustomerFilePresenter::consentLines($file))->toBe([
        'Email: Withdrawn',
        'SMS: Never asked',
        'Post: Never asked',
    ]);
});
