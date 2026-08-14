# Adopting this module

## 1. Composer

The domain package is not on Packagist, and Composer honours `repositories`
**only from the root manifest** — so this package declaring it is enough for its
own CI and does nothing for you. Add both entries to the application's own
`composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-commerce-customers" },
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-commerce-customers-filament" }
  ],
  "require": {
    "liberusoftware/ecommerce-commerce-customers-filament": "^0.1"
  }
}
```

## 2. Enable the module

Nothing boots on install. This package ships no `extra.laravel.providers`, so
Composer registers nothing; the host's `ModuleManagerServiceProvider` globs
`config('modules.paths')` for `*/module.json` and registers only what
`MODULES_ENABLED` names.

```dotenv
MODULES_ENABLED="…,ecommerce-commerce-customers,ecommerce-commerce-customers-filament"
```

Both. The presentation package renders the domain package's records and its
migrations live in the domain package.

## 3. Attach the plugin to the panels you mean

```php
use Filament\Facades\Filament;
use Liberu\Ecommerce\CommerceCustomers\Filament\CommerceCustomersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->plugin(
            CommerceCustomersPlugin::make()
                ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey()),
        );
}
```

Attach it to staff panels only. Every screen in this package is a merchant
screen; a shopper's own view of their file is Customer Accounts' (#846).

### The tenant

Every domain query and every domain action takes the tenant as an **argument**,
never from ambient state — that is what stops a scope that reads like a filter
from reaching another merchant's rows. The panel has to supply that argument
from somewhere, and `tenantUsing()` is the only place it comes from.

Omit it and Filament's own panel tenant is used. If your tenancy is not
Filament's — a team resolved from the request, a store resolved from the host
header — say so:

```php
CommerceCustomersPlugin::make()
    ->tenantUsing(fn (): string => app(StoreContext::class)->currentStoreId());
```

There is no fallback to "no tenant". An unresolvable or empty tenant throws,
because `where('tenant_id', null)` compiles to `is null` and would list exactly
the orphan rows a scope was meant to hide.

## 4. Authorization

This package forces every ability it does not publish to false by name. The
abilities it *does* publish fall through to Laravel's policy lookup, and with no
policy registered they are open — which is Filament's default for any resource,
and is your decision rather than ours, because this module has no idea who your
staff are.

Register a policy if staff roles differ:

```php
// app/Policies/CustomerFilePolicy.php
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;

class CustomerFilePolicy
{
    public function viewAny(User $user): bool { return $user->can('customers.read'); }
    public function view(User $user, CustomerFile $file): bool { return $user->can('customers.read'); }
    public function create(User $user): bool { return $user->can('customers.write'); }
    public function update(User $user, CustomerFile $file): bool { return $user->can('customers.write'); }
}
```

Do **not** add `delete`, `restore`, `forceDelete`, `replicate` or `reorder`
methods expecting them to matter. This package answers all of those false before
your policy is consulted, and nothing in the domain deletes.

**Redaction deserves its own permission.** It is the one operation in the module
that crosses tenants, and a token or a role that can correct a phone number must
not be able to erase a person. This package makes the reach visible on screen;
it cannot make it a separate permission, because it has no concept of one. If
you gate the panel by role, gate the redaction action too.

## 5. The Promotions adapter — yours, not ours

Three modules hold a piece of *is this shopper eligible?*:

- **Promotions** (#895) published `ResolvesCustomerEligibility` and left it
  unbound, failing per-offer when it is.
- **We** publish `ListGroupsForPerson(tenant, person_ref)`.
- **You** bind the first to the second.

This package ships no dependency on Promotions and no implementation of its
contract, because a module that implements another module's contract has made
two modules that must be installed together.

The adapter is **not four lines, and cannot be**. The published contract is

```php
public function isCustomerIn(string $customerRef, string $groupRef): bool;
```

with **no tenant argument**. So the adapter has to obtain the tenant from your
request context itself before it can call `ListGroupsForPerson`. An adapter that
forgets answers across every merchant on the deployment — which is exactly the
fault this module was built to stop.

```php
// app/Adapters/CustomerGroupEligibility.php
namespace App\Adapters;

use Liberu\Ecommerce\CommerceCustomers\Queries\ListGroupsForPerson;
use Liberu\Ecommerce\Promotions\Contracts\ResolvesCustomerEligibility;

final class CustomerGroupEligibility implements ResolvesCustomerEligibility
{
    public function __construct(
        private readonly ListGroupsForPerson $groups,
        private readonly StoreContext $stores,   // yours
    ) {}

    public function isCustomerIn(string $customerRef, string $groupRef): bool
    {
        // The tenant cannot come from either module. Promotions has no concept
        // of one at this seam, and Commerce Customers cannot read your request
        // context. It has to be resolved here, and an adapter that skips this
        // line answers across every merchant on the deployment.
        $tenantId = $this->stores->currentStoreId();

        if ($tenantId === null) {
            return false;
        }

        return in_array($groupRef, ($this->groups)($tenantId, $customerRef), strict: true);
    }
}
```

Bind it in a service provider:

```php
$this->app->bind(ResolvesCustomerEligibility::class, CustomerGroupEligibility::class);
```

**One half of that contract has no owner after this wave.** `$groupRef` is
documented as "an opaque group *or segment* reference", and segments belong to
Attribution and Analytics (#821), which is not built — so an offer configured
against a segment is answered `false` by this adapter. That fails closed, which
is the right direction for an eligibility control, but it is silent. #821 has
been notified.

## 6. The Orders seam

Purchase history is unbound by default, and the file page then says "Not
available" rather than zero. Bind it when Orders is composed in:

```php
$this->app->bind(ResolvesPurchaseHistory::class, OrdersPurchaseHistory::class);
```

Your implementation must return **lifetime value as a currency-to-money map**,
not a total. The panel renders one line per currency and will happily render a
map with one entry; what it will never do is add two currencies together.

## 7. What to turn off in the host

Once this module owns the merchant's file on a person, the host's own customer
screens are a second answer to the same question.

- **`App\Filament\Resources\CustomerResource`** — superseded. Its form marks six
  fields required over nullable columns.
- **`App\Models\CustomerGroup::calculateDiscount()` and
  `qualifiesForFreeShipping()`** — Promotions'. Migrate them there; the
  membership half is this module's.
- **`Customer::getActiveGroupsAttribute()`,
  `CustomerGroup::getActiveCustomersCount()` and `scopeWithActiveMembers()`** —
  all three return other customers' rows. Delete rather than fix: the answer is
  `ListActiveGroupMemberships` and `ListGroupsForPerson`.
- **`Customer::getTotalSpentAttribute()` / `getLifetimeValueAttribute()`** — sum
  across currencies and stores. The answer is the purchase-history seam.
- **`CustomerMetric` and `CustomerSegment`** — derived audiences, and #821's.
- **`customers.loyalty_tier_id`** — Loyalty's (#866).
- **`is_wholesale` / `wholesale_status` / `business_name`** — B2B Self-Service's
  (#824). `tax_id` and tax status are ours.

There is **no data migration in this package**, and there is no `Schema::hasTable()`
guard anywhere: the domain package invents its own nine tables, all prefixed
`commerce_customers_`, and adopts none of the host's. The host's `customers`
table cannot be adopted, because its globally-unique `user_id` and its
store scope contradict each other — that is the fault the `(tenant, person_ref)`
key exists to resolve, and it cannot be resolved on the same row.
