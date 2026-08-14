<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\CommerceCustomers\Enums\LifecycleState;
use Liberu\Ecommerce\CommerceCustomers\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\CreateCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\EditCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ListCustomerFiles;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages\ViewCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\AddressesRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\LifecycleDecisionsRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers\MembershipsRelationManager;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFilePresenter;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Queries\GetLifecycleState;

/**
 * A merchant's file on a person.
 *
 * Two things about this resource are load-bearing and easy to undo by accident.
 *
 * **Nothing identifying is searchable, sortable or filterable.** A search term
 * and a filter's state both persist into the query string, and the query
 * string persists into browser history, bookmarks, referrer headers and the
 * screenshot somebody pastes into a ticket. The one searchable column is the
 * file's `reference`, which is what the domain minted it for: it identifies
 * the file and identifies nobody. There are no filters at all — see
 * `table()` for why the obvious one is also the wrong one.
 *
 * **The form requires nothing, because the schema requires nothing.** The
 * host's `CustomerResource` marked six fields `->required()` over columns its
 * own migration had made nullable, so the panel refused records the API
 * created — a constraint that was true nowhere but on that form. A merchant
 * may hold a file on a person and know only that they exist.
 */
final class CustomerFileResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = CustomerFile::class;

    /**
     * Filament's own tenancy is off: this module's tenant is an argument to
     * every domain query, resolved once in PanelTenant, and applied below. Two
     * scoping mechanisms over one table is how a merchant ends up seeing rows
     * that belong to a different merchant when only one of them is wired.
     */
    protected static bool $isScopedToTenant = false;

    /**
     * The record title, the global-search term and the URL segment are all
     * this, and all three are places a shopper's email must never reach.
     */
    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $recordRouteKeyName = 'reference';

    protected static ?string $modelLabel = 'customer file';

    protected static ?string $pluralModelLabel = 'customer files';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Customer files';

    /** @return Builder<CustomerFile> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forTenant(PanelTenant::current());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Person')
                ->description('An opaque reference to whatever identity system the host runs. It is never a login and never a credential, and a file may have none at all — a guest who ordered once still has a file.')
                ->schema([
                    TextInput::make('person_ref')
                        ->label('Person reference')
                        ->maxLength(255)
                        ->helperText('Leave empty for a guest. It cannot be changed afterwards: a file that changes who it is about is a different file, and merging is the action for that.')
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(),
                ]),

            Section::make('Profile')
                ->description('Nothing here is required. The schema permits every one of these to be empty, and a form that says otherwise refuses records the API accepts.')
                ->columns(2)
                ->schema([
                    TextInput::make('given_name')->label('Given name')->maxLength(255),
                    TextInput::make('family_name')->label('Family name')->maxLength(255),
                    TextInput::make('email')->label('Email')->email()->maxLength(255),
                    // A phone number is a string, not arithmetic. The host stored
                    // it in an `integer` column, which loses a leading zero, loses
                    // a `+44`, and overflows on most international numbers.
                    TextInput::make('phone')->label('Phone')->maxLength(64),
                    TextInput::make('phone_country')
                        ->label('Phone dialling country')
                        ->maxLength(2)
                        ->helperText('ISO 3166-1 alpha-2. Kept beside the number rather than folded into it; we never normalise to E.164.'),
                    TextInput::make('locale')->label('Locale')->maxLength(16),
                    TextInput::make('preferred_currency')->label('Preferred currency')->maxLength(3),
                    TextInput::make('timezone')->label('Timezone')->maxLength(64),
                ]),

            Section::make('Tax status')
                ->description('Recorded and published, never verified. Whether a registration is live is the host\'s lookup; what an exemption does to a price is the Tax module\'s decision.')
                ->columns(2)
                ->schema([
                    Toggle::make('tax_exempt')->label('Tax exempt')->default(false),
                    TextInput::make('tax_registration_id')
                        ->label('Tax registration')
                        ->maxLength(64)
                        ->rule('regex:/^[A-Za-z0-9 .\-]{4,64}$/'),
                    // Not a lying constraint: the domain refuses a registration
                    // with no issuing country, so the form says so rather than
                    // letting the merchant find out by exception.
                    TextInput::make('tax_registration_country')
                        ->label('Issued by')
                        ->maxLength(2)
                        ->requiredWith('tax_registration_id')
                        ->rule('regex:/^[A-Z]{2}$/'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('File')
                ->columns(2)
                ->schema([
                    TextEntry::make('reference')->label('Reference')->copyable(),
                    TextEntry::make('lifecycle')
                        ->label('Lifecycle')
                        ->badge()
                        ->state(fn (CustomerFile $record): string => self::lifecycleState($record)->label()),
                    TextEntry::make('name')
                        ->label('Name')
                        ->state(fn (CustomerFile $record): string => CustomerFilePresenter::displayName($record)),
                    TextEntry::make('email')->label('Email')->placeholder('—'),
                    TextEntry::make('phone')->label('Phone')->placeholder('—'),
                    TextEntry::make('person_ref')->label('Person reference')->placeholder('Guest — no person reference'),
                ]),

            Section::make('Marketing consent')
                ->description('The latest decision per channel, folded over the consent ledger. A channel nobody has ever asked about reads "Never asked", which is not the same fact as "Withdrawn".')
                ->schema([
                    TextEntry::make('consent')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->state(fn (CustomerFile $record): array => CustomerFilePresenter::consentLines($record)),
                ]),

            Section::make('Purchase history')
                ->description('Told to this module by Orders, never computed here. With Orders absent this section says it cannot say, rather than saying zero.')
                ->columns(2)
                ->schema([
                    TextEntry::make('order_count')
                        ->label('Orders')
                        ->state(fn (CustomerFile $record): string => CustomerFilePresenter::orderCountLine(
                            CustomerFilePresenter::purchaseHistory($record),
                        )),
                    TextEntry::make('lifetime_value')
                        ->label('Lifetime value')
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->helperText('One line per currency. Two currencies do not add, and a single total would have no unit.')
                        ->state(fn (CustomerFile $record): array => CustomerFilePresenter::lifetimeValueLines(
                            CustomerFilePresenter::purchaseHistory($record),
                        )),
                    TextEntry::make('first_purchase_at')
                        ->label('First purchase')
                        ->state(fn (CustomerFile $record): string => CustomerFilePresenter::purchaseInstantLine(
                            CustomerFilePresenter::purchaseHistory($record), true,
                        )),
                    TextEntry::make('last_purchase_at')
                        ->label('Last purchase')
                        ->state(fn (CustomerFile $record): string => CustomerFilePresenter::purchaseInstantLine(
                            CustomerFilePresenter::purchaseHistory($record), false,
                        )),
                ]),

            Section::make('Tax status')
                ->columns(3)
                ->schema([
                    TextEntry::make('tax_exempt')->label('Exempt')->state(fn (CustomerFile $record): string => $record->tax_exempt ? 'Yes' : 'No'),
                    TextEntry::make('tax_registration_id')->label('Registration')->placeholder('—'),
                    TextEntry::make('tax_registration_country')->label('Issued by')->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // The only searchable column in the package. See the class docblock.
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (CustomerFile $record): string => CustomerFilePresenter::displayName($record)),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                // ponytail: one domain query per row for the lifecycle fold. At a
                // panel's page size that is ten queries; if a merchant ever pages
                // a thousand files at once, the domain should publish a batched
                // fold rather than this resource growing its own SQL.
                TextColumn::make('lifecycle')
                    ->label('Lifecycle')
                    ->badge()
                    ->state(fn (CustomerFile $record): string => self::lifecycleState($record)->label()),
                TextColumn::make('created_at')->label('Opened')->dateTime()->sortable(),
            ])
            // No filters, deliberately, and the empty array is the statement.
            //
            // Every filter this resource could offer is either identifying — a
            // name, an email, a tag somebody typed a person's employer into — or
            // is lifecycle state, which has no column: it is a fold over an
            // append-only ledger, and a filter would have to rebuild that fold in
            // SQL here. A panel that recomputes a state the domain already
            // publishes has two answers to one question, and they drift.
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
            MembershipsRelationManager::class,
            LifecycleDecisionsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListCustomerFiles::route('/'),
            'create' => CreateCustomerFile::route('/create'),
            'view' => ViewCustomerFile::route('/{record}'),
            'edit' => EditCustomerFile::route('/{record}/edit'),
        ];
    }

    public static function lifecycleState(CustomerFile $file): LifecycleState
    {
        return (new GetLifecycleState())($file);
    }
}
