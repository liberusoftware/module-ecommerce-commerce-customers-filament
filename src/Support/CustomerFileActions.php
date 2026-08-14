<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceCustomers\Actions\MergeCustomerFiles;
use Liberu\Ecommerce\CommerceCustomers\Actions\RecordLifecycleDecision;
use Liberu\Ecommerce\CommerceCustomers\Actions\RedactPerson;
use Liberu\Ecommerce\CommerceCustomers\Enums\LifecycleState;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Queries\GetLifecycleState;

/**
 * The three decisions a merchant takes about a whole file, as page actions.
 *
 * They live here rather than on a page because the view page and the edit page
 * both offer them, and a second copy is how two screens end up disagreeing
 * about what a confirmation says.
 *
 * None of them is a Filament built-in. Every one calls a domain action, so the
 * tenant assertion, the redaction check and the supersession check run on
 * every path — a panel cannot reach the database around them.
 */
final class CustomerFileActions
{
    /**
     * A lifecycle move, offering only the moves the domain will accept.
     *
     * The select is built from `allowedTransitions()`. A select listing all
     * five states and letting `InvalidLifecycleTransition` come back is a form
     * that offers what it cannot do — and the merchant learns which is which
     * by being refused, one at a time.
     */
    public static function recordLifecycleDecision(): Action
    {
        return Action::make('recordLifecycleDecision')
            ->label('Record a lifecycle decision')
            ->icon('heroicon-o-arrows-right-left')
            ->visible(fn (CustomerFile $record): bool => self::isWritable($record) && ! self::state($record)->isTerminal())
            ->modalHeading(fn (CustomerFile $record): string => 'Move customer file '.$record->reference)
            ->modalDescription('A lifecycle move is a decision with a reason and an actor behind it, recorded when it happened. Nothing in this module moves a file on a schedule.')
            ->schema([
                Select::make('to_state')
                    ->label('Move to')
                    ->options(fn (CustomerFile $record): array => self::transitionOptions($record))
                    ->required()
                    ->native(false),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(500)
                    ->helperText('Recorded on the decision. It is the only explanation anyone reading the ledger later will have.'),
            ])
            ->action(function (CustomerFile $record, array $data): void {
                App::make(RecordLifecycleDecision::class)(
                    $record,
                    PanelTenant::current(),
                    LifecycleState::from((string) $data['to_state']),
                    (string) $data['reason'],
                    PanelActor::current(),
                );

                Notification::make()->title('Lifecycle decision recorded.')->success()->send();
            });
    }

    /**
     * Merging one file into another, confirmed by naming both by reference.
     *
     * A merge is destructive in one direction and irreversible: the losing
     * file is superseded, its person reference is released to null, and after
     * it nothing answers to that reference except the survivor. A bare
     * `requiresConfirmation()` would ask "are you sure?" about two files it
     * never names — so this modal names the losing file in its heading, names
     * the surviving one as soon as it is chosen, and will not submit until the
     * merchant has typed the losing reference back.
     *
     * The surviving file is picked on its **reference**. Two host resources
     * pick a customer with `pluck('first_name', 'id')`, where two people
     * called John are one entry repeated.
     */
    public static function merge(): Action
    {
        return Action::make('merge')
            ->label('Merge into another file')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->visible(fn (CustomerFile $record): bool => self::isWritable($record))
            ->modalHeading(fn (CustomerFile $record): string => 'Merge customer file '.$record->reference.' into another')
            ->modalSubmitActionLabel('Merge permanently')
            ->schema([
                Select::make('surviving_file_id')
                    ->label('Surviving file')
                    ->options(fn (CustomerFile $record): array => self::mergeCandidates($record))
                    ->searchable()
                    ->required()
                    ->live()
                    ->native(false)
                    ->helperText('Listed by reference. A name would put two people called John under one entry, and put a shopper\'s name in a select nobody needed it in.'),
                Placeholder::make('consequence')
                    ->label('What this does')
                    ->content(fn (Get $get, CustomerFile $record): string => self::mergeConsequence($record, $get('surviving_file_id'))),
                TextInput::make('confirm_reference')
                    ->label(fn (CustomerFile $record): string => 'Type '.$record->reference.' to confirm')
                    ->required()
                    ->rule(fn (CustomerFile $record): string => 'in:'.$record->reference)
                    ->validationMessages(['in' => 'That is not the reference of the file being merged away.']),
            ])
            ->action(function (CustomerFile $record, array $data): void {
                $tenant = PanelTenant::current();

                $surviving = CustomerFile::query()
                    ->where('tenant_id', $tenant)
                    ->whereKey($data['surviving_file_id'])
                    ->firstOrFail();

                App::make(MergeCustomerFiles::class)($record, $surviving, $tenant, PanelActor::current());

                Notification::make()
                    ->title('File '.$record->reference.' merged into '.$surviving->reference.'.')
                    ->body('The merged file keeps its rows and its history. Its person reference has been released, so nothing answers to it any more.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Erasure — the most dangerous thing in this package, and the only
     * operation in the whole module that crosses tenants.
     *
     * `RedactPerson` takes no tenant. It finds every file on this person held
     * by every merchant on the deployment and redacts all of them, because a
     * subject-access erasure is about a person rather than about a storefront
     * and one that clears a single merchant's row is a wrong answer rather than
     * a partial one. That is correct, and it is also completely invisible from
     * a panel that is otherwise scoped to one merchant — so the modal says it,
     * in the heading, in the description and again on the box the merchant has
     * to tick. It is asserted in three places for the same reason.
     */
    public static function redact(): Action
    {
        return Action::make('redact')
            ->label('Redact this person')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->visible(fn (CustomerFile $record): bool => $record->person_ref !== null && ! $record->isRedacted())
            ->modalHeading('Redact this person across every tenant')
            ->modalDescription(self::REDACTION_WARNING)
            ->modalSubmitActionLabel('Redact everywhere')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(500),
                Checkbox::make('acknowledged')
                    ->label('I understand this affects every tenant on this deployment, not only this one.')
                    ->accepted()
                    ->required(),
            ])
            ->action(function (CustomerFile $record, array $data): void {
                $ids = App::make(RedactPerson::class)(
                    (string) $record->person_ref,
                    (string) $data['reason'],
                    PanelActor::current(),
                );

                Notification::make()
                    ->title('Redacted '.count($ids).' customer file(s) across all tenants.')
                    ->body('Identifying fields are cleared and a redaction record is beside each file. Postal lines survive so an order can still say where it shipped.')
                    ->success()
                    ->send();
            });
    }

    public const REDACTION_WARNING = 'This erases the identifying fields from every customer file held on this person by every merchant on this deployment — not only this one, and not only the tenant this panel is showing. It cannot be undone. Addresses keep their postal lines and lose the recipient and company: an order must still be able to say where it shipped.';

    /** @return array<string, string> */
    public static function transitionOptions(CustomerFile $file): array
    {
        $options = [];

        foreach (self::state($file)->allowedTransitions() as $state) {
            $options[$state->value] = $state->label();
        }

        return $options;
    }

    /**
     * Every other current file of this merchant, labelled by reference.
     *
     * Superseded files are excluded because merging into one would build a
     * chain nothing walks, and redacted ones because the domain refuses a
     * write to them — offering either would be a button that only ever throws.
     *
     * @return array<int, string>
     */
    public static function mergeCandidates(CustomerFile $file): array
    {
        return CustomerFile::query()
            ->where('tenant_id', $file->tenant_id)
            ->whereKeyNot($file->id)
            ->whereDoesntHave('mergedAway')
            ->whereDoesntHave('redaction')
            ->orderBy('reference')
            ->pluck('reference', 'id')
            ->all();
    }

    public static function mergeConsequence(CustomerFile $losing, mixed $survivingId): string
    {
        $surviving = is_numeric($survivingId)
            ? CustomerFile::query()->where('tenant_id', $losing->tenant_id)->whereKey($survivingId)->value('reference')
            : null;

        if (! is_string($surviving)) {
            return 'Choose the file that survives. Both files will be named here before you can confirm.';
        }

        return 'File '.$losing->reference.' will be superseded and its person reference released, and file '.$surviving
            .' will survive as the only file answering to that person. '.$losing->reference
            .' keeps every row it has — this is not a deletion — but it can never be written to again, and the merge cannot be undone.';
    }

    private static function state(CustomerFile $file): LifecycleState
    {
        return (new GetLifecycleState())($file);
    }

    private static function isWritable(CustomerFile $file): bool
    {
        return ! $file->isRedacted() && ! $file->isSuperseded();
    }
}
