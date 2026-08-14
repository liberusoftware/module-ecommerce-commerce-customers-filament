<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Liberu\Ecommerce\CommerceCustomers\Actions\CreateCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Actions\JoinCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\MembershipsRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\CreateCustomerGroup as CreateCustomerGroupPage;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\ListCustomerGroups;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\ViewCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\RelationManagers\GroupMembersRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Models\GroupMembership;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function membershipManager(CustomerFile $file): Testable
{
    return Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $file,
        'pageClass' => ViewCustomerFile::class,
    ]);
}

it('creates a group with a name and a description and nothing that reduces a price', function () {
    Livewire::test(CreateCustomerGroupPage::class)
        ->fillForm(['name' => 'Trade', 'description' => 'Wholesale buyers.'])
        ->call('create')
        ->assertHasNoFormErrors();

    $group = CustomerGroup::query()->sole();

    expect($group->tenant_id)->toBe(TestTenant::PRIMARY)
        ->and($group->name)->toBe('Trade')
        ->and($group->getAttributes())->not->toHaveKey('discount_percentage')
        ->and($group->getAttributes())->not->toHaveKey('free_shipping_threshold')
        ->and($group->getAttributes())->not->toHaveKey('customer_count');
});

it('lists only this merchant groups', function () {
    $mine = app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade');
    $theirs = app(CreateCustomerGroup::class)(TestTenant::OTHER, 'Their trade');

    Livewire::test(ListCustomerGroups::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('attaches a file to a group with a joined date and an actor', function () {
    $file = openFile();
    $group = app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade');

    membershipManager($file)
        ->callAction(TestAction::make('join')->table(), ['group_id' => $group->id])
        ->assertHasNoActionErrors();

    $membership = GroupMembership::query()->sole();

    expect($membership->group_id)->toBe($group->id)
        ->and($membership->tenant_id)->toBe(TestTenant::PRIMARY)
        ->and($membership->expires_at)->toBeNull()
        ->and($membership->actor_ref)->toBe('panel:unauthenticated');
});

it('never offers another merchant group to attach to', function () {
    app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade');
    $theirs = app(CreateCustomerGroup::class)(TestTenant::OTHER, 'Their trade');

    expect(MembershipsRelationManager::groupOptions())->not->toHaveKey($theirs->id);
});

/*
 * Ending a membership dates its expiry and leaves the row. A promotion applied
 * while somebody was in the group needs the evidence to survive; a deleted row
 * makes a past decision unexplainable.
 */
it('ends a membership by dating it, never by deleting the row', function () {
    $file = openFile();
    $group = app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade');
    $membership = app(JoinCustomerGroup::class)($file, $group, TestTenant::PRIMARY, 'actor:test');

    membershipManager($file)
        ->callAction(TestAction::make('leave')->table($membership))
        ->assertHasNoActionErrors();

    expect(GroupMembership::query()->whereKey($membership->id)->exists())->toBeTrue()
        ->and($membership->fresh()->expires_at)->not->toBeNull()
        ->and($membership->fresh()->isActiveAt(CarbonImmutable::now()))->toBeFalse();
});

it('offers no way to end a membership that has already expired', function () {
    $file = openFile();
    $group = app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade');
    $membership = app(JoinCustomerGroup::class)(
        $file, $group, TestTenant::PRIMARY, 'actor:test', CarbonImmutable::now()->subDay(),
        CarbonImmutable::now()->subWeek(),
    );

    membershipManager($file)->assertActionHidden(TestAction::make('leave')->table($membership));
});

/*
 * The pin on the host's `getActiveGroupsAttribute()`. Written flat as
 * `->where('expires_at', '>', now())->orWhereNull('expires_at')` on a builder
 * already carrying `customer_file_id = X`, SQL reads it as
 * `(customer_file_id = X AND expires_at > now()) OR expires_at IS NULL` —
 * every never-expiring membership on the deployment, whoever it belongs to.
 */
it('never shows another customer never-expiring membership on this file', function () {
    $mine = openFile('person-1', TestTenant::PRIMARY);
    $someoneElse = openFile('person-2', TestTenant::PRIMARY);

    $group = app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade');
    $theirMembership = app(JoinCustomerGroup::class)($someoneElse, $group, TestTenant::PRIMARY, 'actor:test');

    membershipManager($mine)
        ->assertCanNotSeeTableRecords([$theirMembership])
        ->assertCountTableRecords(0);
});

it('shows the members of a group by reference, and only this merchant members', function () {
    $mine = openFile('person-1', TestTenant::PRIMARY);
    $group = app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade');
    $membership = app(JoinCustomerGroup::class)($mine, $group, TestTenant::PRIMARY, 'actor:test');

    Livewire::test(GroupMembersRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => ViewCustomerGroup::class,
    ])
        ->assertCanSeeTableRecords([$membership])
        ->assertSee($mine->reference)
        ->assertDontSee('ada@example.test');
});

it('resolves the tenant from the panel rather than from any form field', function () {
    TestTenant::use(TestTenant::OTHER);

    expect(PanelTenant::current())->toBe(TestTenant::OTHER);

    Livewire::test(CreateCustomerGroupPage::class)
        ->fillForm(['name' => 'Trade'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CustomerGroup::query()->sole()->tenant_id)->toBe(TestTenant::OTHER);
});

it('shows a group with no edit and no delete, because the domain publishes neither', function () {
    $group = app(CreateCustomerGroup::class)(TestTenant::PRIMARY, 'Trade', 'Wholesale buyers.');

    Livewire::test(ViewCustomerGroup::class, ['record' => $group->getKey()])
        ->assertSuccessful()
        ->assertSee('Trade')
        ->assertSee('Wholesale buyers.')
        ->assertActionDoesNotExist('edit')
        ->assertActionDoesNotExist('delete');
});
