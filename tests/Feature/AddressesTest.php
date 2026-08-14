<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Liberu\Ecommerce\CommerceCustomers\Actions\AddAddress;
use Liberu\Ecommerce\CommerceCustomers\Actions\SupersedeAddress;
use Liberu\Ecommerce\CommerceCustomers\Data\PostalAddress;
use Liberu\Ecommerce\CommerceCustomers\Enums\AddressUsage;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\AddressesRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerAddress;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function addressManager(CustomerFile $file): Testable
{
    return Livewire::test(AddressesRelationManager::class, [
        'ownerRecord' => $file,
        'pageClass' => ViewCustomerFile::class,
    ]);
}

it('adds an address through the domain action, country required', function () {
    $file = openFile();

    addressManager($file)
        ->callAction(TestAction::make('addAddress')->table(), [
            'usage' => AddressUsage::Shipping->value,
            'line_one' => '1 Deansgate',
            'locality' => 'Manchester',
            'postal_code' => 'M3 1AA',
            'country_code' => 'GB',
        ])
        ->assertHasNoActionErrors();

    $address = CustomerAddress::query()->sole();

    expect($address->country_code)->toBe('GB')
        ->and($address->usage)->toBe(AddressUsage::Shipping)
        ->and($address->tenant_id)->toBe(TestTenant::PRIMARY);
});

/*
 * A country is required because an address without one cannot be validated,
 * taxed or shipped to. The host's free-text `city` and `state` with no country
 * is what that omission looks like from a report.
 */
it('refuses an address with no country', function () {
    $file = openFile();

    addressManager($file)
        ->callAction(TestAction::make('addAddress')->table(), [
            'usage' => AddressUsage::Both->value,
            'line_one' => '1 Deansgate',
            'country_code' => '',
        ])
        ->assertHasActionErrors(['country_code']);

    expect(CustomerAddress::query()->count())->toBe(0);
});

/*
 * An address is immutable once written: revising produces a new row and marks
 * the old one superseded, so an order that shipped there can still say where it
 * went. This is the reason there is a "Revise" action and not an edit form.
 */
it('revises an address by writing a new one and superseding the old', function () {
    $file = openFile();
    $original = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(
        lineOne: '1 Deansgate', countryCode: 'GB', locality: 'Manchester',
    ), AddressUsage::Both, true);

    addressManager($file)
        ->callAction(
            TestAction::make('revise')->table($original),
            [
                'usage' => AddressUsage::Both->value,
                'line_one' => '2 Deansgate',
                'locality' => 'Manchester',
                'country_code' => 'GB',
            ],
        )
        ->assertHasNoActionErrors();

    expect(CustomerAddress::query()->count())->toBe(2)
        ->and($original->fresh()->isSuperseded())->toBeTrue()
        ->and($original->fresh()->line_one)->toBe('1 Deansgate');

    $replacement = CustomerAddress::query()->whereKeyNot($original->id)->sole();

    expect($replacement->line_one)->toBe('2 Deansgate')
        ->and($replacement->is_default)->toBeTrue()
        ->and($original->fresh()->superseded_by_address_id)->toBe($replacement->id);
});

it('retires an address without deleting it', function () {
    $file = openFile();
    $address = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(
        lineOne: '1 Deansgate', countryCode: 'GB',
    ));

    addressManager($file)
        ->callAction(TestAction::make('supersede')->table($address))
        ->assertHasNoActionErrors();

    expect(CustomerAddress::query()->whereKey($address->id)->exists())->toBeTrue()
        ->and($address->fresh()->isSuperseded())->toBeTrue();
});

it('moves the default slot rather than letting two addresses claim it', function () {
    $file = openFile();
    $first = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(lineOne: '1 Deansgate', countryCode: 'GB'), AddressUsage::Both, true);
    $second = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(lineOne: '2 Deansgate', countryCode: 'GB'), AddressUsage::Both);

    addressManager($file)
        ->callAction(TestAction::make('makeDefault')->table($second))
        ->assertHasNoActionErrors();

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeNull();
});

it('offers no revision, default or retirement on an address already superseded', function () {
    $file = openFile();
    $address = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(lineOne: '1 Deansgate', countryCode: 'GB'));

    app(SupersedeAddress::class)($address, TestTenant::PRIMARY);

    addressManager($file)
        ->assertActionHidden(TestAction::make('revise')->table($address))
        ->assertActionHidden(TestAction::make('supersede')->table($address))
        ->assertActionHidden(TestAction::make('makeDefault')->table($address));
});

/*
 * Superseded rows stay on screen. Hiding them behind a toggle would be a
 * filter, and a filter's state persists into the query string exactly as a
 * search term does.
 */
it('keeps superseded addresses visible rather than filtering them away', function () {
    $file = openFile();
    $address = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(lineOne: '1 Deansgate', countryCode: 'GB'));

    app(SupersedeAddress::class)($address, TestTenant::PRIMARY);

    addressManager($file)->assertCanSeeTableRecords([$address->fresh()]);
});

it('does not make a row clickable, because an address has no page', function () {
    $file = openFile();

    $table = addressManager($file)->instance()->getTable();

    $address = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(lineOne: '1 Deansgate', countryCode: 'GB'));

    expect($table->getRecordUrl($address))->toBeNull()
        ->and($table->getRecordAction($address))->toBeNull();
});
