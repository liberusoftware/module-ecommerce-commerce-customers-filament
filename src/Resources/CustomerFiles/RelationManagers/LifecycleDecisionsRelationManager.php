<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CommerceCustomers\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CommerceCustomers\Models\LifecycleDecision;

/**
 * The ledger a customer file's state is folded from — read, and only read.
 *
 * There is no `lifecycle_state` column anywhere in this module, deliberately:
 * a stored status and the evidence that justifies it drift, and the one
 * anybody reads is the stored one. So this table is the state, and nothing in
 * it may be edited after the fact. A move is made from the file's own "Record
 * a lifecycle decision" action, which appends.
 *
 * `occurred_at` and `created_at` are both shown and they are different
 * questions: a transition recorded a day late happened when it happened.
 *
 * The row is not clickable. `recordUrl` and `recordAction` are unwired here
 * not because anything would raise — these records are Eloquent models and
 * satisfy Filament's `Model` type hints — but because a clickable row offers a
 * page for a decision, and there is no such page and no view ability published
 * for one. The reason is authorization, not a type error.
 */
final class LifecycleDecisionsRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'lifecycleDecisions';

    protected static ?string $title = 'Lifecycle decisions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_state')
                    ->label('From')
                    ->placeholder('Opened')
                    ->state(fn (LifecycleDecision $record): ?string => $record->from_state?->label()),
                TextColumn::make('to_state')
                    ->label('To')
                    ->badge()
                    ->state(fn (LifecycleDecision $record): string => $record->to_state->label()),
                TextColumn::make('reason')->label('Reason')->wrap(),
                TextColumn::make('actor_ref')->label('Decided by'),
                TextColumn::make('occurred_at')->label('Happened')->dateTime()->sortable(),
                TextColumn::make('created_at')->label('Recorded')->dateTime(),
            ])
            ->filters([])
            ->defaultSort('occurred_at', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
