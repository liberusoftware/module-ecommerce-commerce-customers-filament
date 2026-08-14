<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Liberu\Ecommerce\CommerceCustomers\Data\ProfileDetails;
use Liberu\Ecommerce\CommerceCustomers\Enums\LifecycleState;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\CreateCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\EditCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ListCustomerFiles;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Queries\GetLifecycleState;
use Livewire\Livewire;

it('lists only the files of the merchant the panel is showing', function () {
    $mine = openFile('person-1', TestTenant::PRIMARY);
    $theirs = openFile('person-2', TestTenant::OTHER);

    Livewire::test(ListCustomerFiles::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

/*
 * The host's `CustomerResource` marked six fields required over columns its own
 * migration had made nullable, so the panel refused records the API created.
 * The schema is the authority, and the schema requires none of these.
 */
it('opens a file with nothing filled in but a person reference', function () {
    Livewire::test(CreateCustomerFile::class)
        ->fillForm(['person_ref' => 'person-77'])
        ->call('create')
        ->assertHasNoFormErrors();

    $file = CustomerFile::query()->where('person_ref', 'person-77')->sole();

    expect($file->tenant_id)->toBe(TestTenant::PRIMARY)
        ->and($file->given_name)->toBeNull()
        ->and($file->email)->toBeNull()
        ->and($file->reference)->toStartWith('CF');
});

it('opens a guest file with no person reference at all', function () {
    Livewire::test(CreateCustomerFile::class)
        ->fillForm(['given_name' => 'Guest'])
        ->call('create')
        ->assertHasNoFormErrors();

    $file = CustomerFile::query()->where('given_name', 'Guest')->sole();

    expect($file->person_ref)->toBeNull();
});

it('marks no profile field required on the form', function () {
    $schema = CustomerFileResource::form(
        Schema::make(Livewire::test(CreateCustomerFile::class)->instance()),
    );

    $required = [];

    foreach ($schema->getFlatComponents() as $component) {
        if ($component instanceof TextInput && $component->isRequired()) {
            $required[] = $component->getName();
        }
    }

    // Nothing is unconditionally required. The issuing country is required
    // only *with* a registration id, which is a rule the domain enforces
    // rather than one this form invented — see the test below that proves it.
    expect($required)->toBe([]);
});

it('writes the first lifecycle decision when a file is opened, so the fold is total', function () {
    Livewire::test(CreateCustomerFile::class)
        ->fillForm(['person_ref' => 'person-9'])
        ->call('create')
        ->assertHasNoFormErrors();

    $file = CustomerFile::query()->where('person_ref', 'person-9')->sole();

    expect(app(GetLifecycleState::class)($file))->toBe(LifecycleState::Prospect)
        ->and($file->lifecycleDecisions()->count())->toBe(1);
});

/*
 * The profile DTO replaces the whole block, so clearing a field clears it.
 * "Null means leave it alone" gives no way to erase a phone number entered
 * wrongly, and every surface then invents its own sentinel for "actually none".
 */
it('clears a profile field that the merchant empties', function () {
    $file = openFile('person-3', TestTenant::PRIMARY, new ProfileDetails(
        givenName: 'Ada', familyName: 'Lovelace', email: 'ada@example.test', phone: '0161 496 0000',
    ));

    Livewire::test(EditCustomerFile::class, ['record' => $file->reference])
        ->fillForm(['phone' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($file->fresh()->phone)->toBeNull();
});

it('stores a phone number as a string, leading zero and plus sign intact', function () {
    $file = openFile('person-4');

    Livewire::test(EditCustomerFile::class, ['record' => $file->reference])
        ->fillForm(['phone' => '+44 (0)161 496 0000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($file->fresh()->phone)->toBe('+44 (0)161 496 0000');
});

it('refuses a tax registration with no issuing country, because the domain does', function () {
    $file = openFile('person-5');

    Livewire::test(EditCustomerFile::class, ['record' => $file->reference])
        ->fillForm(['tax_registration_id' => 'GB123456789', 'tax_registration_country' => ''])
        ->call('save')
        ->assertHasFormErrors(['tax_registration_country']);
});

it('records a tax status the domain accepts', function () {
    $file = openFile('person-6');

    Livewire::test(EditCustomerFile::class, ['record' => $file->reference])
        ->fillForm([
            'tax_exempt' => true,
            'tax_registration_id' => 'GB123456789',
            'tax_registration_country' => 'GB',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($file->fresh()->tax_exempt)->toBeTrue()
        ->and($file->fresh()->tax_registration_country)->toBe('GB');
});

it('renders a file with no name, no email and no person reference without inventing any', function () {
    $file = openFile(null, TestTenant::PRIMARY, new ProfileDetails());

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertSuccessful()
        ->assertSee($file->reference);
});

/*
 * Route binding is Eloquent and tenant-scoped in `getEloquentQuery()`, so a
 * reference belonging to another merchant does not resolve to a record at all —
 * it is not a record this panel is allowed to refuse, it is a record this panel
 * cannot see.
 */
it('will not resolve another merchant file through the URL', function () {
    $theirs = openFile('person-7', TestTenant::OTHER);

    Livewire::test(ViewCustomerFile::class, ['record' => $theirs->reference]);
})->throws(ModelNotFoundException::class);
