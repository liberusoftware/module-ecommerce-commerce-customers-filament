<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCustomers\Actions\RecordLifecycleDecision;
use Liberu\Ecommerce\CommerceCustomers\Enums\LifecycleState;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileActions;
use Liberu\Ecommerce\CommerceCustomers\Queries\GetLifecycleState;
use Livewire\Livewire;

/*
 * A select listing all five states and letting `InvalidLifecycleTransition`
 * come back is a form that offers what it cannot do: the merchant discovers
 * which moves exist by being refused, one at a time.
 */
it('offers only the transitions the domain permits, from wherever the file stands', function () {
    $file = openFile();

    expect(array_keys(CustomerFileActions::transitionOptions($file)))
        ->toBe(['active', 'blocked', 'closed']);

    app(RecordLifecycleDecision::class)($file, 'tenant-a', LifecycleState::Active, 'Bought something.', 'actor:test');

    expect(array_keys(CustomerFileActions::transitionOptions($file->fresh())))
        ->toBe(['dormant', 'blocked', 'closed']);
});

it('offers no move at all out of a closed file', function () {
    $file = openFile();

    app(RecordLifecycleDecision::class)($file, 'tenant-a', LifecycleState::Closed, 'Asked us to.', 'actor:test');

    expect(CustomerFileActions::transitionOptions($file->fresh()))->toBe([]);
});

it('hides the lifecycle action entirely once a file is closed', function () {
    $file = openFile();

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertActionVisible('recordLifecycleDecision');

    app(RecordLifecycleDecision::class)($file, 'tenant-a', LifecycleState::Closed, 'Asked us to.', 'actor:test');

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->assertActionHidden('recordLifecycleDecision');
});

it('records a lifecycle move as a decision with a reason and an actor', function () {
    $file = openFile();

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('recordLifecycleDecision', [
            'to_state' => LifecycleState::Blocked->value,
            'reason' => 'Chargeback fraud.',
        ])
        ->assertHasNoActionErrors();

    $decision = $file->lifecycleDecisions()->orderByDesc('id')->firstOrFail();

    expect(app(GetLifecycleState::class)($file->fresh()))->toBe(LifecycleState::Blocked)
        ->and($decision->from_state)->toBe(LifecycleState::Prospect)
        ->and($decision->reason)->toBe('Chargeback fraud.')
        ->and($decision->actor_ref)->toBe('panel:unauthenticated');
});

it('insists on a reason for a lifecycle move', function () {
    $file = openFile();

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('recordLifecycleDecision', ['to_state' => LifecycleState::Active->value, 'reason' => ''])
        ->assertHasActionErrors(['reason']);
});

/*
 * Nothing in this module moves a file on a schedule. Dormant is a merchant
 * decision, reachable only through this action; a derived "has not ordered in
 * 180 days" is an audience and belongs to Attribution and Analytics.
 */
it('reaches Dormant only through a merchant decision', function () {
    $file = openFile();

    app(RecordLifecycleDecision::class)($file, 'tenant-a', LifecycleState::Active, 'Bought something.', 'actor:test');

    Livewire::test(ViewCustomerFile::class, ['record' => $file->reference])
        ->callAction('recordLifecycleDecision', [
            'to_state' => LifecycleState::Dormant->value,
            'reason' => 'Quiet for two years; merchant decision.',
        ])
        ->assertHasNoActionErrors();

    expect(app(GetLifecycleState::class)($file->fresh()))->toBe(LifecycleState::Dormant);
});
