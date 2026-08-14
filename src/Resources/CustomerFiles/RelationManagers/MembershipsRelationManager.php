<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\RelationManagers;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceCustomers\Actions\JoinCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Actions\LeaveCustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelActor;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerGroup;
use Liberu\Ecommerce\CommerceCustomers\Models\GroupMembership;

/**
 * The groups this file has been attached to, live and expired.
 *
 * A group membership is a *declared* fact — an operator attached it, with a
 * date and an actor. A derived audience is a segment and belongs to Attribution
 * and Analytics; nothing here is recomputed on a schedule.
 *
 * Leaving dates the expiry and leaves the row. A promotion applied while
 * somebody was in a group needs that to stay true, and a deleted row makes a
 * past decision unexplainable — which is why `canDelete()` and
 * `canDissociate()` are both closed by name rather than left to a policy that
 * does not exist.
 *
 * Whether a membership is live is read from the model's own `isActiveAt()`,
 * never restated here. The host restated that window three times, ungrouped,
 * and all three copies returned other customers' rows.
 */
final class MembershipsRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Group memberships';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group.name')->label('Group'),
                TextColumn::make('joined_at')->label('Joined')->dateTime(),
                TextColumn::make('expires_at')->label('Expires')->dateTime()->placeholder('Never'),
                IconColumn::make('active')
                    ->label('Live')
                    ->boolean()
                    ->state(fn (GroupMembership $record): bool => $record->isActiveAt(CarbonImmutable::now())),
                TextColumn::make('actor_ref')->label('Attached by'),
            ])
            ->filters([])
            ->defaultSort('joined_at', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->headerActions([
                Action::make('join')
                    ->label('Attach to a group')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Select::make('group_id')
                            ->label('Group')
                            ->options(fn (): array => self::groupOptions())
                            ->required()
                            ->searchable()
                            ->native(false),
                        DateTimePicker::make('expires_at')
                            ->label('Expires')
                            ->helperText('Optional. Leave empty for a membership that does not expire.'),
                    ])
                    ->action(function (array $data): void {
                        $tenant = PanelTenant::current();

                        $group = CustomerGroup::query()
                            ->where('tenant_id', $tenant)
                            ->whereKey($data['group_id'])
                            ->firstOrFail();

                        App::make(JoinCustomerGroup::class)(
                            $this->getOwnerRecord(),
                            $group,
                            $tenant,
                            PanelActor::current(),
                            self::instant($data['expires_at'] ?? null),
                        );

                        Notification::make()->title('Attached to '.$group->name.'.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('leave')
                    ->label('End membership')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    ->visible(fn (GroupMembership $record): bool => $record->isActiveAt(CarbonImmutable::now()))
                    ->requiresConfirmation()
                    ->modalDescription('The row stays and its expiry is dated now. A promotion applied while this person was in the group needs the evidence to survive.')
                    ->action(function (GroupMembership $record): void {
                        App::make(LeaveCustomerGroup::class)(
                            $this->getOwnerRecord(),
                            $record->group,
                            PanelTenant::current(),
                            PanelActor::current(),
                        );

                        Notification::make()->title('Membership ended.')->success()->send();
                    }),
            ]);
    }

    /** @return array<int, string> */
    public static function groupOptions(): array
    {
        return CustomerGroup::query()
            ->where('tenant_id', PanelTenant::current())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function instant(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }
}
