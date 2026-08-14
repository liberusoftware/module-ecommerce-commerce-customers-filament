<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileActions;

final class ViewCustomerFile extends ViewRecord
{
    protected static string $resource = CustomerFileResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            CustomerFileActions::recordLifecycleDecision(),
            CustomerFileActions::merge(),
            CustomerFileActions::redact(),
        ];
    }
}
