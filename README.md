# Commerce Customers — Filament

[![Tests](https://github.com/liberusoftware/module-ecommerce-commerce-customers-filament/actions/workflows/tests.yml/badge.svg)](https://github.com/liberusoftware/module-ecommerce-commerce-customers-filament/actions/workflows/tests.yml)

The merchant's panel over
[`liberusoftware/ecommerce-commerce-customers`](https://github.com/liberusoftware/module-ecommerce-commerce-customers):
customer files, addresses, groups, memberships, lifecycle decisions and merges.

It is a **merchant** surface and only a merchant surface. There is no
shopper-facing screen here and there is not going to be one — a person's own
view of the file a merchant holds on them is Customer Accounts' (#846), which
depends on this module's domain package rather than on this one.

## What it puts on a panel

| | |
|---|---|
| **Resources** | `CustomerFileResource`, `CustomerGroupResource` |
| **Pages** | Customer files: list, create, view, edit. Customer groups: list, create, view. |
| **Relation managers** | Addresses, Group memberships, Lifecycle decisions (all on a file); Members (on a group) |
| **Widgets** | None — see below |

There are no widgets. The one thing that looked like a widget is a customer's
purchase history, and it belongs on the file it is about rather than on a
dashboard: it is per-record, it comes from a seam that is usually unbound, and
a dashboard tile that reads "not available" for every merchant is noise. It is
a section of the file's infolist instead.

## Installing

```bash
composer require liberusoftware/ecommerce-commerce-customers-filament
```

The domain package is not on Packagist, so a consuming application adds the VCS
repository itself — Composer honours `repositories` only from the root
manifest. `docs/adoption.md` has the entry to paste.

Nothing boots on install. There is no `extra.laravel.providers`, and the
provider this package ships registers nothing at all: the host's module manager
registers it when the module is named in `MODULES_ENABLED`, and the panel UI
arrives separately, per panel, through a plugin.

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

Attach it to the panels your staff use and to no others. That is the whole
reason it is a plugin.

## The five things worth knowing before you change anything

**Nothing identifying is searchable, sortable or filterable.** A search term
and a filter's state both persist into the query string, and the query string
persists into browser history, bookmarks, referrer headers and the screenshot
somebody pastes into a ticket. The one searchable thing on a customer file is
its `reference` — a non-identifying string the domain mints for exactly this.
There are **no filters at all**, on any table in the package, and there is a
test that says so.

**The form requires nothing.** The host's `CustomerResource` marked six fields
`->required()` over columns its own migration had made nullable, so the panel
refused records the API happily created. A merchant may hold a file on a person
and know nothing about them but that they exist.

**Every write goes through a domain action.** No resource, page or relation
manager writes with Eloquent. That is what keeps the domain's tenant assertion,
redaction check and supersession check on every path, and it is why the
relation managers have named actions instead of Filament's built-in create and
edit.

**Every unpublished ability is forced closed by name.** A missing policy is
permissive; a policy that exists but lacks the method asked about is *also*
permissive. `canAssociate()` and `canDissociate()` in particular are live on a
`hasMany` and default open. See `src/Concerns/`.

**Redaction spans every tenant, and the screen says so.** `RedactPerson` is the
one operation in the module that is not scoped to a merchant, because a
subject-access erasure is about a person rather than about a storefront. The
modal says it in the heading, in the description and on the box you have to
tick, and three tests keep it saying so.

## Documentation

- `docs/domain.md` — what each surface shows and why it shows it that way
- `docs/adoption.md` — installing, the tenant seam, the Promotions adapter the
  host owns, and what to turn off in the host
- `docs/runbook.md` — operating it, and the gaps that are real

## Licence

MIT.
