<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Every built-in relation-manager ability, forced closed by name.
 *
 * A relation manager has its own authorization surface, separate from the
 * resource's, and two of its methods are live on a `hasMany` and default
 * open: `canAssociate()` and `canDissociate()`. Dissociating a membership row
 * would null its foreign key and leave a group membership belonging to nobody
 * — an append-only ledger silently edited through a button nobody added.
 *
 * Every relation manager in this package is therefore a *reader*. Nothing in
 * these tables is created, edited or removed by Filament's own machinery.
 * Every write is a named action that calls a domain action, so the domain's
 * tenant assertion, redaction check and supersession check run on every path,
 * and the panel cannot invent a write the domain does not publish.
 *
 * `canViewAny()` is the one ability published, and it is stated rather than
 * inherited: inheriting it would mean the table's visibility rested on a
 * policy method nobody wrote, which is the same permissive default this trait
 * exists to close.
 *
 * These are declared public where the parent declares them protected. Widening
 * is legal, narrowing is a fatal at class load, and public is what lets a test
 * assert the answer by name instead of through reflection.
 */
trait DeniesUnpublishedRelationAbilities
{
    public function canViewAny(): bool
    {
        return true;
    }

    public function canAssociate(): bool
    {
        return false;
    }

    public function canAttach(): bool
    {
        return false;
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }

    public function canDeleteAny(): bool
    {
        return false;
    }

    public function canDetach(Model $record): bool
    {
        return false;
    }

    public function canDetachAny(): bool
    {
        return false;
    }

    public function canDissociate(Model $record): bool
    {
        return false;
    }

    public function canDissociateAny(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canForceDelete(Model $record): bool
    {
        return false;
    }

    public function canForceDeleteAny(): bool
    {
        return false;
    }

    public function canReorder(): bool
    {
        return false;
    }

    public function canReplicate(Model $record): bool
    {
        return false;
    }

    public function canRestore(Model $record): bool
    {
        return false;
    }

    public function canRestoreAny(): bool
    {
        return false;
    }

    public function canView(Model $record): bool
    {
        return false;
    }
}
