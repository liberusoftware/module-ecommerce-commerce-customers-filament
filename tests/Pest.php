<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\CommerceCustomers\Actions\OpenCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Data\ProfileDetails;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestCase;
use Liberu\Ecommerce\CommerceCustomers\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => TestTenant::reset())
    ->in(__DIR__.'/Feature', __DIR__.'/Unit');

/**
 * A file opened the way the domain opens one, so every test starts from a
 * record that has a reference and a lifecycle rather than from a raw insert.
 */
function openFile(?string $personRef = 'person-1', string $tenantId = TestTenant::PRIMARY, ?ProfileDetails $profile = null): CustomerFile
{
    return app(OpenCustomerFile::class)(
        $tenantId,
        $personRef,
        'actor:test',
        $profile ?? new ProfileDetails(givenName: 'Ada', familyName: 'Lovelace', email: 'ada@example.test'),
    );
}
