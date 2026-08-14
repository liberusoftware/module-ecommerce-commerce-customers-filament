<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCustomers\Actions\CreateCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\AddressesRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\LifecycleDecisionsRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\MembershipsRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\CustomerGroupResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\RelationManagers\GroupMembersRelationManager;

/*
 * A missing policy is permissive. A policy that exists but lacks the method
 * asked about is also permissive, because Filament's authorization helper falls
 * through to allow when the method is absent. So every ability this package
 * does not publish is answered here by name, and asserted here by name — the
 * assertion is the only thing that notices when somebody deletes an override
 * during a refactor and nothing else changes.
 */

it('refuses every destructive resource ability by name', function (string $ability) {
    $file = openFile();

    $answer = match ($ability) {
        'canDeleteAny', 'canForceDeleteAny', 'canReorder', 'canRestoreAny' => CustomerFileResource::{$ability}(),
        default => CustomerFileResource::{$ability}($file),
    };

    expect($answer)->toBeFalse();
})->with([
    ['canDelete'],
    ['canDeleteAny'],
    ['canForceDelete'],
    ['canForceDeleteAny'],
    ['canReorder'],
    ['canReplicate'],
    ['canRestore'],
    ['canRestoreAny'],
]);

it('publishes exactly the abilities its pages need on a customer file', function () {
    $file = openFile();

    expect(CustomerFileResource::canViewAny())->toBeTrue()
        ->and(CustomerFileResource::canCreate())->toBeTrue()
        ->and(CustomerFileResource::canView($file))->toBeTrue()
        ->and(CustomerFileResource::canEdit($file))->toBeTrue();
});

it('refuses edit on a customer group, because the domain publishes no update', function () {
    $group = app(CreateCustomerGroup::class)('tenant-a', 'Trade');

    expect(CustomerGroupResource::canEdit($group))->toBeFalse()
        ->and(CustomerGroupResource::canDelete($group))->toBeFalse()
        ->and(CustomerGroupResource::canViewAny())->toBeTrue()
        ->and(CustomerGroupResource::canCreate())->toBeTrue();
});

/*
 * `canAssociate` and `canDissociate` are the two that matter most here: both
 * are live on a `hasMany` and both default open. Dissociating a membership
 * would null its foreign key and leave a declared fact belonging to nobody.
 */
it('refuses every relation-manager ability by name, on every relation manager', function (string $manager) {
    $file = openFile();
    $instance = new $manager();

    foreach ([
        'canAssociate', 'canAttach', 'canCreate', 'canDeleteAny', 'canDetachAny',
        'canDissociateAny', 'canForceDeleteAny', 'canReorder', 'canRestoreAny',
    ] as $ability) {
        expect($instance->{$ability}())->toBeFalse("{$manager}::{$ability}() must be false");
    }

    foreach ([
        'canDelete', 'canDetach', 'canDissociate', 'canEdit', 'canForceDelete',
        'canReplicate', 'canRestore', 'canView',
    ] as $ability) {
        expect($instance->{$ability}($file))->toBeFalse("{$manager}::{$ability}() must be false");
    }

    expect($instance->canViewAny())->toBeTrue();
})->with([
    [AddressesRelationManager::class],
    [MembershipsRelationManager::class],
    [LifecycleDecisionsRelationManager::class],
    [GroupMembersRelationManager::class],
]);
