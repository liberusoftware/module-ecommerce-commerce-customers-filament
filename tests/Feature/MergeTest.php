<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCustomers\Data\ProfileDetails;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileActions;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\FileMerge;
use Livewire\Livewire;

/*
 * A merge is destructive in one direction and cannot be undone: the losing file
 * is superseded, its person reference is released to null, and afterwards
 * nothing answers to that reference except the survivor. A bare
 * `requiresConfirmation()` asks "are you sure?" about two files it never names.
 */

it('names both files by reference in the confirmation before it will merge', function () {
    $losing = openFile('person-1');
    $surviving = openFile('person-2');

    $consequence = CustomerFileActions::mergeConsequence($losing, $surviving->id);

    expect($consequence)->toContain($losing->reference)
        ->and($consequence)->toContain($surviving->reference)
        ->and($consequence)->toContain('cannot be undone');
});

it('says which file survives is still unchosen rather than naming a wrong one', function () {
    $losing = openFile('person-1');

    expect(CustomerFileActions::mergeConsequence($losing, null))
        ->toContain('Both files will be named here before you can confirm.');
});

it('offers merge candidates by reference, so two people called John are two entries', function () {
    $losing = openFile('person-1', TestTenant::PRIMARY, new ProfileDetails(givenName: 'John'));
    $johnOne = openFile('person-2', TestTenant::PRIMARY, new ProfileDetails(givenName: 'John'));
    $johnTwo = openFile('person-3', TestTenant::PRIMARY, new ProfileDetails(givenName: 'John'));

    $candidates = CustomerFileActions::mergeCandidates($losing);

    expect($candidates)->toHaveCount(2)
        ->and($candidates[$johnOne->id])->toBe($johnOne->reference)
        ->and($candidates[$johnTwo->id])->toBe($johnTwo->reference)
        ->and(array_values($candidates))->not->toContain('John');
});

it('never offers another merchant file as a merge candidate', function () {
    $mine = openFile('person-1', TestTenant::PRIMARY);
    $theirs = openFile('person-2', TestTenant::OTHER);

    expect(CustomerFileActions::mergeCandidates($mine))->not->toHaveKey($theirs->id);
});

it('refuses to merge until the losing reference has been typed back', function () {
    $losing = openFile('person-1');
    $surviving = openFile('person-2');

    Livewire::test(ViewCustomerFile::class, ['record' => $losing->reference])
        ->callAction('merge', [
            'surviving_file_id' => $surviving->id,
            'confirm_reference' => 'CFNOTTHISONE',
        ])
        ->assertHasActionErrors(['confirm_reference']);

    expect(FileMerge::query()->count())->toBe(0);
});

it('merges, supersedes the losing file and releases its person reference', function () {
    $losing = openFile('person-1');
    $surviving = openFile('person-2');

    Livewire::test(ViewCustomerFile::class, ['record' => $losing->reference])
        ->callAction('merge', [
            'surviving_file_id' => $surviving->id,
            'confirm_reference' => $losing->reference,
        ])
        ->assertHasNoActionErrors();

    $merge = FileMerge::query()->sole();

    expect($merge->losing_file_id)->toBe($losing->id)
        ->and($merge->surviving_file_id)->toBe($surviving->id)
        ->and($merge->losing_person_ref)->toBe('person-1')
        ->and($losing->fresh()->person_ref)->toBeNull()
        ->and($losing->fresh()->isSuperseded())->toBeTrue();
});

/*
 * After a merge, looking up the old person reference finds nothing at all, and
 * `commerce_customers_merges` is where the redirect lives. This panel does not
 * walk that table, and says so in docs/runbook.md rather than pretending the
 * old reference still resolves.
 */
it('hides every write action on a file that has been merged away', function () {
    $losing = openFile('person-1');
    $surviving = openFile('person-2');

    Livewire::test(ViewCustomerFile::class, ['record' => $losing->reference])
        ->callAction('merge', [
            'surviving_file_id' => $surviving->id,
            'confirm_reference' => $losing->reference,
        ]);

    Livewire::test(ViewCustomerFile::class, ['record' => $losing->reference])
        ->assertActionHidden('merge')
        ->assertActionHidden('recordLifecycleDecision');
});
