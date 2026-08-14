<?php

declare(strict_types=1);

use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Schema;
use Liberu\Ecommerce\CommerceCustomers\Actions\AddAddress;
use Liberu\Ecommerce\CommerceCustomers\Data\PostalAddress;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;
use Livewire\Livewire;

/*
 * Redaction is the only operation in the whole module that crosses tenants, and
 * that is correct: a subject-access erasure is about a person rather than about
 * a storefront, and one that clears a single merchant's row is a wrong answer
 * rather than a partial one.
 *
 * It is also completely invisible from a panel that is otherwise scoped to one
 * merchant — the merchant clicks a button on *their* customer's file and
 * changes another business's records. So the modal says so, and these three
 * assertions are why it keeps saying so.
 */

it('says on screen that redaction spans every tenant', function () {
    $file = openFile();

    $action = Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->instance()
        ->getAction('redact');

    expect($action->getModalHeading())->toContain('every tenant')
        ->and((string) $action->getModalDescription())->toContain('every merchant on this deployment')
        ->and((string) $action->getModalDescription())->toContain('not only this one');
});

it('makes the merchant tick a box that repeats the cross-tenant reach', function () {
    $file = openFile();
    $page = Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])->instance();

    $labels = [];

    foreach ($page->getAction('redact')->getSchema(Schema::make($page))?->getFlatComponents() ?? [] as $component) {
        if ($component instanceof Checkbox) {
            $labels[] = (string) $component->getLabel();
        }
    }

    expect($labels)->toHaveCount(1)
        ->and($labels[0])->toContain('every tenant on this deployment');
});

it('redacts the person from every merchant file, not only this panel\'s', function () {
    $here = openFile('person-1', TestTenant::PRIMARY);
    $elsewhere = openFile('person-1', TestTenant::OTHER);

    Livewire::test(ViewCustomerFile::class, ['record' => $here->reference])
        ->callAction('redact', ['reason' => 'Erasure request.', 'acknowledged' => true])
        ->assertHasNoActionErrors();

    expect($here->fresh()->email)->toBeNull()
        ->and($here->fresh()->given_name)->toBeNull()
        ->and($elsewhere->fresh()->email)->toBeNull()
        ->and($elsewhere->fresh()->isRedacted())->toBeTrue();
});

it('will not redact without the acknowledgement or a reason', function () {
    $file = openFile();

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('redact', ['reason' => 'Erasure request.', 'acknowledged' => false])
        ->assertHasActionErrors(['acknowledged']);

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('redact', ['reason' => '', 'acknowledged' => true])
        ->assertHasActionErrors(['reason']);

    expect($file->fresh()->isRedacted())->toBeFalse();
});

/*
 * The postal lines survive and the recipient and company do not. A street with
 * no name on it is a place rather than a person, and an order that shipped
 * somewhere must still be able to say where it went. This module offers no
 * address deletion at all; a host that wants the destination gone is asking for
 * something we refuse.
 */
it('keeps the postal lines and drops the recipient when it redacts', function () {
    $file = openFile('person-1');

    $address = app(AddAddress::class)($file, TestTenant::PRIMARY, new PostalAddress(
        lineOne: '1 Deansgate',
        countryCode: 'GB',
        locality: 'Manchester',
        postalCode: 'M3 1AA',
        recipientName: 'Ada Lovelace',
        company: 'Analytical Engines Ltd',
    ));

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('redact', ['reason' => 'Erasure request.', 'acknowledged' => true]);

    expect($address->fresh()->line_one)->toBe('1 Deansgate')
        ->and($address->fresh()->postal_code)->toBe('M3 1AA')
        ->and($address->fresh()->recipient_name)->toBeNull()
        ->and($address->fresh()->company)->toBeNull();
});

it('offers no redaction on a guest file, which has no person to erase', function () {
    $file = openFile(null);

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertActionHidden('redact');
});

it('offers no second redaction once a file is redacted', function () {
    $file = openFile('person-1');

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('redact', ['reason' => 'Erasure request.', 'acknowledged' => true]);

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertActionHidden('redact');
});

it('keeps the file rather than deleting it, so an order can still name its buyer', function () {
    $file = openFile('person-1');

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('redact', ['reason' => 'Erasure request.', 'acknowledged' => true]);

    expect(CustomerFile::query()->whereKey($file->id)->exists())->toBeTrue()
        ->and($file->fresh()->lifecycleDecisions()->count())->toBe(1);
});
