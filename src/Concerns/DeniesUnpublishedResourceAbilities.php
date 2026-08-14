<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * The abilities no resource in this package publishes, forced closed by name.
 *
 * Naming them is the whole point. A missing policy is permissive; a policy
 * that exists but lacks the method asked about is *also* permissive, because
 * Filament's `get_authorization_response()` falls through to allow when the
 * method is absent. So "we did not write a delete policy" is not "delete is
 * off" — it is "delete is on and nobody decided". These eight say no in a
 * place no host policy can accidentally reopen.
 *
 * They are all destructive-or-mutating operations over an append-only domain:
 * nothing in Commerce Customers is deleted, restored, force-deleted,
 * replicated or reordered. A customer file is superseded by a merge and
 * redacted by an erasure, and both leave the row and its evidence behind.
 *
 * Abilities a resource *does* publish are deliberately not listed here: they
 * fall through to Filament's policy lookup so a host that ships a gate policy
 * for `CustomerFile` still governs who may read and write. See docs/adoption.md.
 */
trait DeniesUnpublishedResourceAbilities
{
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }
}
