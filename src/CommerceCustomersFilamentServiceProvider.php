<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament;

use Illuminate\Support\ServiceProvider;

/**
 * The module's provider, and it registers nothing.
 *
 * That is not an omission. A presentation package contributes panel UI, and a
 * panel is composed by the host: it decides which panels exist, and which of
 * them a merchant's customer files belong on. Registering a resource globally
 * from a provider would put this module's navigation into every panel the
 * application has, including a shopper-facing one — and the shopper-facing
 * surface over a customer file belongs to Customer Accounts, not here.
 *
 * So everything this package offers arrives through `CommerceCustomersPlugin`,
 * which the host attaches to exactly the panels it means to. The provider
 * exists because the module manifest must name one and the boundary suite
 * boots it; there is nothing for it to do.
 */
class CommerceCustomersFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
