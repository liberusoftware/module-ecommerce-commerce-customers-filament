<?php

declare(strict_types=1);

use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Liberu\Ecommerce\CommerceCustomers\Actions\CreateCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ListCustomerFiles;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\AddressesRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\LifecycleDecisionsRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\MembershipsRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\CustomerGroupResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\ListCustomerGroups;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\ViewCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\RelationManagers\GroupMembersRelationManager;
use Livewire\Livewire;

/*
 * A search term and a filter's state both end up in the query string, and the
 * query string ends up in browser history, bookmarks, referrer headers and
 * screenshots. So a shopper's email is not searchable anywhere in this package,
 * and there is nothing filterable at all.
 *
 * These assertions are the load-bearing ones. Adding `->searchable()` to an
 * email column is a one-word change that reads like an improvement.
 */

function searchableColumnsOf(Table $table): array
{
    return array_values(array_map(
        static fn (Column $column): string => $column->getName(),
        array_filter($table->getColumns(), static fn (Column $column): bool => $column->isSearchable()),
    ));
}

it('makes only the file reference searchable on the customer file table', function () {
    $table = Livewire::test(ListCustomerFiles::class)->instance()->getTable();

    expect(searchableColumnsOf($table))->toBe(['reference']);
});

it('offers no filter anywhere, on any table in the package', function (string $component, array $mountArgs) {
    $table = Livewire::test($component, $mountArgs)->instance()->getTable();

    expect($table->getFilters())->toBe([]);
})->with(function () {
    return [
        'customer files' => [ListCustomerFiles::class, []],
        'customer groups' => [ListCustomerGroups::class, []],
    ];
});

it('offers no filter on any relation manager', function (string $manager) {
    $file = openFile();

    $table = Livewire::test($manager, [
        'ownerRecord' => $file,
        'pageClass' => ViewCustomerFile::class,
    ])->instance()->getTable();

    expect($table->getFilters())->toBe([])
        ->and(searchableColumnsOf($table))->toBe([]);
})->with([
    [AddressesRelationManager::class],
    [MembershipsRelationManager::class],
    [LifecycleDecisionsRelationManager::class],
]);

it('shows a group member by file reference and never by name or email', function () {
    $group = app(CreateCustomerGroup::class)('tenant-a', 'Trade');

    $table = Livewire::test(GroupMembersRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => ViewCustomerGroup::class,
    ])->instance()->getTable();

    $names = array_map(static fn (Column $column): string => $column->getName(), $table->getColumns());

    expect($names)->toContain('customerFile.reference')
        ->and($names)->not->toContain('customerFile.email')
        ->and($names)->not->toContain('customerFile.given_name');
});

it('searches globally on the reference and on nothing else', function () {
    expect(CustomerFileResource::getGloballySearchableAttributes())->toBe(['reference']);
});

/*
 * A group name is the merchant's own vocabulary rather than a fact about a
 * shopper, which is why it is the one other searchable thing in the package.
 */
it('searches a customer group on its name', function () {
    $table = Livewire::test(ListCustomerGroups::class)->instance()->getTable();

    expect(searchableColumnsOf($table))->toBe(['name'])
        ->and(CustomerGroupResource::getGloballySearchableAttributes())->toBe(['name']);
});
