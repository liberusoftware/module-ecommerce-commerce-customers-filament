<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceCustomers\Actions\CreateCustomerGroup as CreateCustomerGroupAction;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerGroups\CustomerGroupResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileFormData;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;

final class CreateCustomerGroup extends CreateRecord
{
    protected static string $resource = CustomerGroupResource::class;

    /**
     * The tenant comes from the panel, never from the form.
     *
     * A tenant a client can name is a tenant a client can change, which is how
     * one storefront's operator files rows into another business.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return App::make(CreateCustomerGroupAction::class)(
            PanelTenant::current(),
            (string) $data['name'],
            CustomerFileFormData::text($data, 'description'),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
