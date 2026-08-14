# Runbook

## Operating

### "A merchant says a customer has disappeared from the list"

Three things make a file stop appearing where somebody expects it, and they are
not the same event.

1. **It was merged away.** Check `commerce_customers_merges` for
   `losing_file_id`. The file is still there and still readable; it is
   superseded, so every write action on it is hidden. Its `person_ref` was
   released to null, which means **looking the old reference up returns
   nothing** — the merges table is where the redirect lives, and this panel does
   not walk it. See the gap below.
2. **The panel is showing a different tenant.** Everything here is scoped by
   `PanelTenant`. A shopper at two merchants has two files.
3. **It was never in this tenant.** A cross-tenant reference does not resolve at
   all: route binding runs through `getEloquentQuery()`, so the page 404s rather
   than refusing.

### "Purchase history says Not available"

That is the Orders seam being unbound, and it is the designed state when Orders
is not composed into the application. Bind `ResolvesPurchaseHistory` (see
`docs/adoption.md` §6). Everything else on the page reads normally meanwhile —
if the *whole page* is failing, the seam is not the cause.

It will never say "0 orders" in that state, deliberately. If you see a zero, a
resolver **is** bound and is reporting that it knows this person and they have
bought nothing.

### "Lifetime value shows two lines"

Correct. It is a currency-to-money map. Two orders in two currencies do not add,
and any single figure would have no unit. If somebody needs one number they need
a conversion, and conversion belongs to whoever owns the rate.

### "A merchant cannot move a file to the state they want"

The select only offers what the domain permits from where the file stands:

| From | To |
|---|---|
| Prospect | Active, Blocked, Closed |
| Active | Dormant, Blocked, Closed |
| Dormant | Active, Blocked, Closed |
| Blocked | Active, Closed |
| Closed | *nothing — terminal* |

Closed is terminal on purpose: reopening a closed file is opening a new one,
which is what keeps a closure meaningful. The action is hidden entirely on a
closed file rather than opening a modal with an empty select.

### "Someone redacted the wrong person"

There is no undo, and there is no way to reconstruct the fields — they are
nulled in place, in every tenant. `commerce_customers_redactions` records who,
when and why, per file. Restore from a backup or accept the loss.

The prevention is upstream: gate the redaction action by role in the host
(`docs/adoption.md` §4). This package makes the reach visible on screen — the
heading, the description and the acknowledgement checkbox all say it spans every
tenant — but it cannot make it a separate permission, because it has no concept
of your permissions.

### "An address is wrong"

Use **Revise**, not a support ticket to edit the row. Revising writes a new
address and supersedes the old one, keeping the chain intact so an order that
shipped to the old one can still say where it went. Direct edits to
`commerce_customers_addresses` break that guarantee silently.

There is no address deletion, in this panel or in the domain. If a subject
access request demands the destination be erased, that is a request this module
refuses: redaction nulls the recipient and the company and keeps the postal
lines, because a street with no name on it is a place rather than a person.

## Gaps that are real

These are things this package does not do, stated rather than hidden.

**No merge redirect.** After a merge, the losing file's `person_ref` is null and
the old reference resolves to nothing. `commerce_customers_merges` holds both
refs and is where a "find the old one, land on the survivor" redirect would come
from. This panel does not walk that table — a merchant who searches an old
reference gets an empty list rather than the survivor. Worth adding if the
merge action turns out to be used often; not worth guessing at now.

**No group rename.** The domain publishes `CreateCustomerGroup` and no update
action, so this package ships no group edit page and answers `canEdit()` false
by name. A typo in a group name is currently unfixable through any published
surface. **This is a gap in the domain package**, not a decision this panel
should paper over with a raw Eloquent write that goes around the tenant check —
it needs an `UpdateCustomerGroup` action there first.

**No tag surface.** The domain publishes `TagCustomerFile`, `UntagCustomerFile`
and `ListFilesTagged`; this panel exposes none of them. Tags were not in this
module's Filament scope for the wave. When they are added, note that a tag is
free text an operator types, so it must not become a searchable or filterable
column without somebody deciding what happens when an operator types a person's
employer into one.

**No consent recording.** The file page *shows* the current consent per channel,
folded by the domain. It offers no way to record a decision, because a merchant
recording consent on a shopper's behalf is a decision about provenance that this
wave did not settle. `RecordConsent` is available to the API and to Customer
Accounts.

**One domain query per row for the lifecycle column.** Marked with a `ponytail:`
note in `CustomerFileResource::table()`. At a panel's page size that is ten
queries. If a merchant ever needs to page a thousand files at once, the fix is a
batched fold published by the domain, not this resource growing its own SQL.

**Global search is on and scoped to the reference.** It searches
`CustomerFile::reference` and `CustomerGroup::name`, through the tenant-scoped
query. If the host turns on `globalSearchResourceOptIn()` these opt in by
default — worth knowing, though neither attribute is identifying.

## Verifying a change

```bash
composer update
vendor/bin/pint --test --config vendor/liberusoftware/package-testbench/pint.json
vendor/bin/phpstan analyse -c vendor/liberusoftware/package-testbench/phpstan.neon -l 1 --memory-limit=1G src
vendor/bin/pest --coverage --min=80
```

The suite must pass with **no Promotions package installed**, and there is a
test that asserts the package requires none and references none of its symbols.
If you find yourself wanting to `require` it, you want the host adapter in
`docs/adoption.md` §5 instead.

The assertions most likely to be broken by a well-intentioned change are in
`tests/Feature/PrivacyTest.php` (nothing identifying searchable, no filters
anywhere) and `tests/Feature/AuthorizationTest.php` (every unpublished ability
false by name). Both fail loudly; neither is a style rule.
