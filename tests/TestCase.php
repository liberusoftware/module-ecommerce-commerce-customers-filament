<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Liberu\Ecommerce\CommerceCustomers\CommerceCustomersServiceProvider;
use Liberu\Ecommerce\CommerceCustomers\Filament\CommerceCustomersFilamentServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;
use Livewire\LivewireServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends PackageTestCase
{
    /**
     * The providers a panel needs, in an order that is not decorative.
     *
     * **Filament's providers register before Livewire's, and must.**
     * `filament/support` re-`bind()`s Livewire's `DataStore`, and a `bind()`
     * drops whatever instance was already registered under that key. Boot
     * Livewire first and every component lookup gets a fresh empty store, which
     * surfaces during render as
     * `ViewErrorBag::put(): $bag must be MessageBag, null given` — an error
     * about validation bags, thrown nowhere near the ordering that caused it.
     * Do not tidy this list into alphabetical order.
     *
     * The domain provider is named here rather than by putting the domain
     * package in `require-dev` as well as `require`. Both work — the base test
     * case boots the manifest provider of any dev-required sibling — but a
     * package listed twice is what `composer validate` warns about, and
     * `Install` runs `composer validate`.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,

            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,

            LivewireServiceProvider::class,

            CommerceCustomersServiceProvider::class,
            CommerceCustomersFilamentServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    /**
     * `app.debug` is true deliberately.
     *
     * A `TypeError` raised inside a Filament schema closure is turned by
     * Livewire into a bare `419 Page Expired` unless debug is on. The symptom
     * is that `Livewire::test(...)->call('save')` leaves `instance()` null,
     * `errors()` empty, nothing written and nothing thrown — every signature
     * mistake in a closure looks like a session problem instead.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', true);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
