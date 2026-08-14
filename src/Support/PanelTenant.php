<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Support;

use Closure;
use Filament\Facades\Filament;
use RuntimeException;

/**
 * Where the panel gets its tenant id from.
 *
 * Every query and every action in the domain package takes the tenant as an
 * argument and never reads one from ambient state — deliberately, because the
 * host's own group accessor reached other merchants' rows through something
 * that read as a filter. That argument has to come from somewhere at the edge,
 * and this is that somewhere: one place, stated out loud, rather than
 * `Filament::getTenant()` sprinkled through nine resources where one of them
 * can quietly forget.
 *
 * By default it is Filament's own panel tenant. A host whose tenancy is not
 * Filament's — a team resolved from the request, a store resolved from the
 * host header — hands us a closure instead. There is no fallback to "no
 * tenant": an unresolvable tenant throws rather than returning null, because
 * `where('tenant_id', null)` compiles to `is null` and lists precisely the
 * orphan rows a scope was meant to hide.
 */
final class PanelTenant
{
    /**
     * ponytail: one process-global resolver, because one deployment has one
     * way of naming its merchants. If a host ever needs a different rule per
     * panel, move this onto the plugin and read it from the current panel.
     */
    private static ?Closure $resolver = null;

    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function current(): string
    {
        $tenant = self::$resolver !== null
            ? (self::$resolver)()
            : Filament::getTenant()?->getKey();

        if (! is_string($tenant) && ! is_int($tenant)) {
            throw new RuntimeException(
                'Commerce Customers could not resolve a tenant for this panel. '
                .'Set one with PanelTenant::resolveUsing(...) in the panel provider.'
            );
        }

        $tenant = (string) $tenant;

        if ($tenant === '') {
            throw new RuntimeException('Commerce Customers resolved an empty tenant id, which would match orphan rows.');
        }

        return $tenant;
    }
}
