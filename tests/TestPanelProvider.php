<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Tests;

use Filament\Panel;
use Filament\PanelProvider;
use Liberu\Ecommerce\CommerceCustomers\Filament\CommerceCustomersPlugin;

/**
 * A merchant panel with this module's plugin attached, and nothing else.
 *
 * This is the whole of what a host has to write, which is the point of
 * shipping a plugin rather than registering resources from a service provider:
 * the host decides which panels the merchant's customer files appear on, and a
 * shopper-facing panel simply does not attach this.
 */
final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(
                CommerceCustomersPlugin::make()
                    ->tenantUsing(fn (): string => TestTenant::current()),
            );
    }
}
