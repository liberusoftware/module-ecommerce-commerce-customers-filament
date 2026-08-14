<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceCustomers\Actions\OpenCustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Actions\SetTaxStatus;
use Liberu\Ecommerce\CommerceCustomers\Filament\Resources\CustomerFiles\CustomerFileResource;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\CustomerFileFormData;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelActor;
use Liberu\Ecommerce\CommerceCustomers\Filament\Support\PanelTenant;

final class CreateCustomerFile extends CreateRecord
{
    protected static string $resource = CustomerFileResource::class;

    /**
     * The record is opened by the domain action, never by Eloquent.
     *
     * `OpenCustomerFile` mints the reference, writes the first lifecycle
     * decision so the fold is total, and refuses a second file for a person
     * this merchant already knows. A `create()` here would produce a file with
     * no reference, no lifecycle and no uniqueness check, and every one of
     * those failures would surface somewhere else.
     *
     * No idempotency key is minted, and that is a decision rather than an
     * omission: `(tenant_id, person_ref)` is a server-supplied natural key, so
     * a double submit is already refused by the database. A key the client
     * holds is a key the client can change, and one layered over a natural key
     * is strictly weaker than the constraint already there.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = PanelTenant::current();

        $file = App::make(OpenCustomerFile::class)(
            $tenant,
            CustomerFileFormData::text($data, 'person_ref'),
            PanelActor::current(),
            CustomerFileFormData::profile($data),
        );

        return App::make(SetTaxStatus::class)($file, $tenant, CustomerFileFormData::taxStatus($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
