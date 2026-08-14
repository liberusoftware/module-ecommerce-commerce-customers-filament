<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceCustomers\Actions\AddAddress;
use Liberu\Ecommerce\CommerceCustomers\Actions\ReviseAddress;
use Liberu\Ecommerce\CommerceCustomers\Actions\SetDefaultAddress;
use Liberu\Ecommerce\CommerceCustomers\Actions\SupersedeAddress;
use Liberu\Ecommerce\CommerceCustomers\Enums\AddressUsage;
use Liberu\Ecommerce\CommerceCustomers\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileFormData;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerAddress;

/**
 * The addresses on a file, including the ones it has stopped using.
 *
 * An address is immutable once written. Editing one produces a new row and
 * marks the old one superseded, so an order that shipped somewhere can still
 * say where it went — history is the chain, not the diff. That is why this
 * table has a "Revise" action rather than an edit form, and why superseded
 * rows stay on screen instead of disappearing.
 *
 * Every built-in ability is closed by name. `canDissociate()` in particular is
 * live on a `hasMany` and defaults open: dissociating would null
 * `customer_file_id` and leave an address belonging to nobody, which is an
 * append-only table edited through a button no one added.
 */
final class AddressesRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Addresses';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('usage')
                    ->label('Used for')
                    ->badge()
                    ->state(fn (CustomerAddress $record): string => $record->usage->label()),
                TextColumn::make('line_one')
                    ->label('Address')
                    ->description(fn (CustomerAddress $record): string => self::summary($record)),
                TextColumn::make('country_code')->label('Country'),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('superseded_at')
                    ->label('Superseded')
                    ->dateTime()
                    ->placeholder('In use'),
            ])
            // Nothing identifying is searchable or filterable here either: a
            // recipient name and a street are exactly what must not end up in a
            // query string. Superseded rows are shown rather than filtered out,
            // because a filter's state persists into the URL as readily as a
            // search term does.
            ->filters([])
            ->defaultSort('id')
            // No row is clickable. There is no page for an address and no view
            // ability published for one; a clickable row would offer a screen
            // that must not exist.
            ->recordUrl(null)
            ->recordAction(null)
            ->headerActions([
                Action::make('addAddress')
                    ->label('Add an address')
                    ->icon('heroicon-o-plus')
                    ->schema(self::addressSchema())
                    ->action(function (array $data): void {
                        App::make(AddAddress::class)(
                            $this->getOwnerRecord(),
                            PanelTenant::current(),
                            CustomerFileFormData::postalAddress($data),
                            AddressUsage::from((string) $data['usage']),
                            (bool) ($data['is_default'] ?? false),
                        );

                        Notification::make()->title('Address added.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('revise')
                    ->label('Revise')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (CustomerAddress $record): bool => ! $record->isSuperseded())
                    ->modalHeading('Revise this address')
                    ->modalDescription('This does not edit the address. It writes a new one and marks this one superseded, so an order that shipped here can still say where it went.')
                    ->fillForm(fn (CustomerAddress $record): array => self::formState($record))
                    ->schema(self::addressSchema(withDefault: false))
                    ->action(function (CustomerAddress $record, array $data): void {
                        App::make(ReviseAddress::class)(
                            $record,
                            PanelTenant::current(),
                            CustomerFileFormData::postalAddress($data),
                            AddressUsage::from((string) $data['usage']),
                        );

                        Notification::make()->title('Address revised. The previous one is kept and marked superseded.')->success()->send();
                    }),
                Action::make('makeDefault')
                    ->label('Make default')
                    ->icon('heroicon-o-star')
                    ->visible(fn (CustomerAddress $record): bool => ! $record->isSuperseded() && $record->is_default !== true)
                    ->requiresConfirmation()
                    ->action(function (CustomerAddress $record): void {
                        App::make(SetDefaultAddress::class)($record, PanelTenant::current());

                        Notification::make()->title('Default address moved.')->success()->send();
                    }),
                Action::make('supersede')
                    ->label('No longer used')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (CustomerAddress $record): bool => ! $record->isSuperseded())
                    ->requiresConfirmation()
                    ->modalDescription('The row stays. Retiring an address is not deleting it — an order pointing at it must still resolve.')
                    ->action(function (CustomerAddress $record): void {
                        App::make(SupersedeAddress::class)($record, PanelTenant::current());

                        Notification::make()->title('Address retired.')->success()->send();
                    }),
            ]);
    }

    /**
     * The postal fields, with exactly the two the domain refuses to do without
     * marked required.
     *
     * A country is required because an address without one cannot be
     * validated, taxed or shipped to — the host's free-text `city` and `state`
     * with no country is what that omission looks like from a report. There is
     * no `state` field: most countries do not have one, so it is
     * `administrative_area` and it is optional.
     *
     * @return array<Component>
     */
    public static function addressSchema(bool $withDefault = true): array
    {
        $components = [
            Select::make('usage')
                ->label('Used for')
                ->options(self::usageOptions())
                ->default(AddressUsage::Both->value)
                ->required()
                ->native(false),
            TextInput::make('line_one')->label('Address line 1')->required()->maxLength(255),
            TextInput::make('line_two')->label('Address line 2')->maxLength(255),
            TextInput::make('locality')->label('Town or city')->maxLength(255),
            TextInput::make('administrative_area')
                ->label('State, province or region')
                ->maxLength(255)
                ->helperText('Optional. Most countries do not have one.'),
            TextInput::make('postal_code')->label('Postal code')->maxLength(32),
            TextInput::make('country_code')
                ->label('Country')
                ->required()
                ->maxLength(2)
                ->rule('regex:/^[A-Za-z]{2}$/')
                ->helperText('ISO 3166-1 alpha-2.'),
            TextInput::make('recipient_name')->label('Recipient')->maxLength(255),
            TextInput::make('company')->label('Company')->maxLength(255),
        ];

        if ($withDefault) {
            $components[] = Toggle::make('is_default')->label('Make this the default for that usage');
        }

        return $components;
    }

    /** @return array<string, string> */
    public static function usageOptions(): array
    {
        $options = [];

        foreach (AddressUsage::cases() as $usage) {
            $options[$usage->value] = $usage->label();
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public static function formState(CustomerAddress $address): array
    {
        return [
            ...$address->toPostalAddress()->toArray(),
            'usage' => $address->usage->value,
        ];
    }

    public static function summary(CustomerAddress $address): string
    {
        $parts = array_filter([
            $address->line_two,
            $address->locality,
            $address->administrative_area,
            $address->postal_code,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return implode(', ', $parts);
    }

    public function form(Schema $schema): Schema
    {
        // Unused: this relation manager publishes no create or edit ability, so
        // Filament never opens a form of its own. Every write is a named action.
        return $schema->components([]);
    }
}
