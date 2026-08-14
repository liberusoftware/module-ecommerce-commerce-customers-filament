<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\CustomerGroupResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;

/**
 * What the host attaches to a merchant panel.
 *
 * Nothing here registers globally. A host with an admin panel and a shopper
 * panel attaches this to the first and not the second, because every surface
 * in this package is a merchant surface: a shopper's own view of their file is
 * Customer Accounts' and is deliberately absent from here.
 *
 *     use Liberu\Ecommerce\CommerceCustomers\Filament\CommerceCustomersPlugin;
 *
 *     $panel->plugin(
 *         CommerceCustomersPlugin::make()
 *             ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey()),
 *     );
 */
final class CommerceCustomersPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'ecommerce-commerce-customers';
    }

    /**
     * How this panel names the merchant whose files it is showing.
     *
     * Optional: without it the panel's own Filament tenant is used. A host
     * whose tenancy is not Filament's — a team resolved from the request, a
     * store resolved from the host header — says so here rather than leaving
     * every query to guess.
     */
    public function tenantUsing(?Closure $resolver): self
    {
        PanelTenant::resolveUsing($resolver);

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            CustomerFileResource::class,
            CustomerGroupResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
