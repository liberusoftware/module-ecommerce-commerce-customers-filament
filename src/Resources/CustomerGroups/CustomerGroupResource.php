<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups;

use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CommerceCustomers\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\CreateCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\ListCustomerGroups;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages\ViewCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\RelationManagers\GroupMembersRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerGroup;

/**
 * A declared audience: a name people are attached to.
 *
 * It carries no discount, no free-shipping threshold and no minimum order
 * amount, and this resource offers no field for one. The host put
 * `calculateDiscount()` and `qualifiesForFreeShipping()` on its customer group
 * — a promotion wearing a customer's clothes. The membership is ours; whatever
 * offer hangs off it is Promotions', and the host joins the two with an
 * adapter it owns. See docs/adoption.md.
 *
 * There is no count column either. A count is derived, and nothing derived is
 * stored anywhere in this module.
 *
 * **There is no edit page**, and `canEdit()` is closed by name. The domain
 * publishes `CreateCustomerGroup` and no update action, so a rename here would
 * have to be a plain Eloquent write going around every check the domain
 * performs. That is a gap in the domain package rather than one this panel
 * should paper over; it is recorded in docs/runbook.md.
 */
final class CustomerGroupResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = CustomerGroup::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'customer group';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Customer groups';

    /**
     * No update action exists in the domain, so no edit ability is published.
     * Stated by name rather than left to the absence of an edit page: a
     * missing page hides the button, and a missing ability check leaves the
     * answer to a policy nobody wrote.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** @return Builder<CustomerGroup> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forTenant(PanelTenant::current());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Group')
                ->description('A name and a description. Anything that reduces what a shopper pays belongs to Promotions.')
                ->schema([
                    TextInput::make('name')->label('Name')->required()->maxLength(255),
                    Textarea::make('description')->label('Description')->maxLength(1000),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Group')
                ->columns(2)
                ->schema([
                    TextEntry::make('name')->label('Name'),
                    TextEntry::make('description')->label('Description')->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // A group name is the merchant's own vocabulary, not a fact
                // about a shopper, so it is the one thing in this package that
                // is searchable besides a file reference.
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('description')->label('Description')->limit(60)->placeholder('—'),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            ])
            ->filters([])
            ->defaultSort('name');
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            GroupMembersRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListCustomerGroups::route('/'),
            'create' => CreateCustomerGroup::route('/create'),
            'view' => ViewCustomerGroup::route('/{record}'),
        ];
    }
}
