<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceCustomers\Actions\SetTaxStatus;
use Liberu\Ecommerce\CommerceCustomers\Actions\UpdateCustomerProfile;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileActions;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileFormData;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;

final class EditCustomerFile extends EditRecord
{
    protected static string $resource = CustomerFileResource::class;

    /**
     * There is no delete action here and there is nowhere in this package that
     * there is one. A customer file is superseded by a merge and redacted by
     * an erasure; deleting the row would take the orders' foreign key with it,
     * and an order that cannot say who placed it is a worse privacy outcome
     * than one that says "redacted".
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            CustomerFileActions::recordLifecycleDecision(),
            CustomerFileActions::merge(),
            CustomerFileActions::redact(),
        ];
    }

    /**
     * The profile block is replaced whole, because the DTO is a whole-block
     * replacement: a null clears rather than being ignored, so a phone number
     * entered wrongly can be erased instead of every surface inventing its own
     * sentinel for "actually, none".
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof CustomerFile);

        $tenant = PanelTenant::current();

        $file = App::make(UpdateCustomerProfile::class)($record, $tenant, CustomerFileFormData::profile($data));

        return App::make(SetTaxStatus::class)($file, $tenant, CustomerFileFormData::taxStatus($data));
    }
}
