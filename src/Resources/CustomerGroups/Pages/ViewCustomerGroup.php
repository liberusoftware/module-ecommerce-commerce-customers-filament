<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\CustomerGroupResource;

final class ViewCustomerGroup extends ViewRecord
{
    protected static string $resource = CustomerGroupResource::class;

    /**
     * No edit, no delete. The domain publishes neither, so neither is offered.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
