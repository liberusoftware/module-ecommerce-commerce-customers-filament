<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Support;

use Filament\Facades\Filament;

/**
 * Who the domain records as having taken a decision.
 *
 * Every lifecycle decision, membership and redaction in Commerce Customers
 * carries an `actor_ref`, because "who decided this" is half of what an
 * append-only ledger is for. The domain types it as an opaque string and takes
 * no view of what a user is; this is the panel's answer to that question, and
 * it is one line so that no resource invents its own.
 *
 * A panel with no authenticated user still writes something rather than an
 * empty string — an unattributed decision should read as unattributed, not as
 * a blank that looks like a bug.
 */
final class PanelActor
{
    public static function current(): string
    {
        $id = Filament::auth()->user()?->getAuthIdentifier();

        return match (true) {
            is_string($id), is_int($id) => 'user:'.$id,
            default => 'panel:unauthenticated',
        };
    }
}
