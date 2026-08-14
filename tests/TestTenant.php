<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Tests;

/**
 * The merchant the test panel is currently showing.
 *
 * A mutable holder rather than a constant, because half of what is worth
 * proving about this package is that switching merchants changes what the
 * panel can see — and the interesting failures are the ones where it does not.
 */
final class TestTenant
{
    public const PRIMARY = 'tenant-a';

    public const OTHER = 'tenant-b';

    private static string $current = self::PRIMARY;

    public static function current(): string
    {
        return self::$current;
    }

    public static function use(string $tenantId): void
    {
        self::$current = $tenantId;
    }

    public static function reset(): void
    {
        self::$current = self::PRIMARY;
    }
}
