<?php

declare(strict_types=1);

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Liberu\Ecommerce\CommerceCustomers\Filament\CommerceCustomersPlugin;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\CustomerGroupResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;

/** @return array<string, string> */
function packageSources(): array
{
    $sources = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src')) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
        }
    }

    return $sources;
}

/*
 * Three modules hold a piece of "is this shopper eligible?" and none may reach
 * into another. Promotions published `ResolvesCustomerEligibility` and left it
 * unbound; we publish `ListGroupsForPerson`; the host binds one to the other
 * with an adapter it owns. A module that implements another module's contract
 * has made two modules that must be installed together.
 */
it('references no Promotions symbol anywhere in src', function () {
    foreach (packageSources() as $path => $source) {
        // Prose about the boundary is welcome and is most of why this package
        // reads the way it does; a symbol is what would couple the two.
        expect($source)->not->toContain('Liberu\Ecommerce\Promotions', "[{$path}]");
        expect($source)->not->toContain('ResolvesCustomerEligibility', "[{$path}]");
        expect($source)->not->toContain('isCustomerIn', "[{$path}]");
    }
});

it('requires no Promotions package', function () {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect(array_keys($composer['require']))->not->toContain('liberusoftware/ecommerce-promotions')
        ->and(array_keys($composer['require-dev']))->not->toContain('liberusoftware/ecommerce-promotions');
});

it('runs its whole suite with no Promotions package installed', function () {
    expect(class_exists('Liberu\Ecommerce\Promotions\PromotionsServiceProvider'))->toBeFalse()
        ->and(interface_exists('Liberu\Ecommerce\Promotions\Contracts\ResolvesCustomerEligibility'))->toBeFalse();
});

/*
 * `config()`, `app()`, `auth()` and `now()` are framework-*foundation* helpers,
 * not illuminate/support. They pass every CI job here because the testbench
 * drags the whole framework in, while being a lying constraint for a consumer
 * who installed only what this package declares.
 */
it('reaches for facades rather than foundation helpers in src', function () {
    foreach (packageSources() as $path => $source) {
        $stripped = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source) ?? $source;

        foreach (['config(', 'app(', 'auth(', 'now(', 'resolve(', 'Str::ulid('] as $helper) {
            expect($stripped)->not->toContain(' '.$helper, "[{$path}] uses the {$helper}) helper");
        }
    }
});

it('holds no float and computes no money', function () {
    foreach (packageSources() as $path => $source) {
        expect($source)->not->toContain('float ')
            ->and($source)->not->toContain('(float)');
    }
});

it('declares plugin classes in module.json that exist and are Filament plugins', function () {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/module.json'), true);

    expect($manifest['category'])->toBe('presentation')
        ->and($manifest['presentation']['filament'])->not->toBeEmpty();

    foreach ($manifest['presentation']['filament'] as $panel => $plugins) {
        expect($plugins)->not->toBeEmpty();

        foreach ($plugins as $plugin) {
            expect(class_exists($plugin))->toBeTrue()
                ->and(is_subclass_of($plugin, Plugin::class))->toBeTrue();
        }
    }
});

it('registers exactly the two resources the plugin says it does, and nothing globally', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getPlugin('ecommerce-commerce-customers'))->toBeInstanceOf(CommerceCustomersPlugin::class)
        ->and(array_values($panel->getResources()))->toEqualCanonicalizing([
            CustomerFileResource::class,
            CustomerGroupResource::class,
        ]);
});

/*
 * `where('tenant_id', null)` compiles to `is null`, so an unresolvable tenant
 * would list precisely the orphan rows a scope was meant to hide. It throws
 * instead.
 */
it('refuses to resolve an empty tenant rather than matching orphan rows', function () {
    PanelTenant::resolveUsing(fn (): string => '');

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);

    PanelTenant::resolveUsing(fn (): ?string => null);

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);
});
