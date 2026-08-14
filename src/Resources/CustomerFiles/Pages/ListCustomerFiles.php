<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;

/**
 * @property-read class-string<CustomerFileResource> $resource
 */
final class ListCustomerFiles extends ListRecords
{
    protected static string $resource = CustomerFileResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Open a customer file'),
        ];
    }
}
