<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\RelationManagers;

use Carbon\CarbonImmutable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CommerceCustomers\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CommerceCustomers\Models\GroupMembership;

/**
 * Who is in this group, by file reference and nothing else.
 *
 * No name, no email. A merchant looking at a group is looking at an audience,
 * and the reference is enough to reach the file; putting a column of shoppers'
 * email addresses on a screen that exists to answer "how big is this group"
 * spreads identifying data for no reason, and a sortable or searchable one
 * spreads it into the URL.
 *
 * Read-only. Memberships are attached and ended from the file, where the
 * domain's writable-and-belongs-to-this-tenant check has a file to run against.
 * Every ability is closed by name, `canDissociate()` included: this is a
 * `hasMany` and it defaults open.
 */
final class GroupMembersRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customerFile.reference')->label('Customer file'),
                TextColumn::make('joined_at')->label('Joined')->dateTime(),
                TextColumn::make('expires_at')->label('Expires')->dateTime()->placeholder('Never'),
                IconColumn::make('active')
                    ->label('Live')
                    ->boolean()
                    ->state(fn (GroupMembership $record): bool => $record->isActiveAt(CarbonImmutable::now())),
            ])
            ->filters([])
            ->defaultSort('joined_at', 'desc')
            // Unwired because a membership has no page and no view ability is
            // published for one — authorization, not a type error.
            ->recordUrl(null)
            ->recordAction(null)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
