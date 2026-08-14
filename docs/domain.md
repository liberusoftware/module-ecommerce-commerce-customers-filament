# What each surface shows, and why it shows it that way

This package holds no domain logic. Every fact on every screen comes from
`liberusoftware/ecommerce-commerce-customers`, and every write goes back through
one of its actions. What is decided here is *what a merchant is shown* and
*what a merchant is allowed to press* — and both of those decisions are where
the host's own customer screens went wrong, so they are documented rather than
assumed.

## The shaping fact

Three different things get called "a customer", and the host had one table
being all three.

1. **A person.** The human. One per deployment. Owned by identity, not by us —
   we hold an opaque `person_ref`.
2. **A file.** One merchant's record about one person. One per
   `(tenant, person_ref)`. **This is what this panel shows.**
3. **An audience.** A derived answer — lifetime value, a segment, a churn
   score. Computed, perishable, and mostly somebody else's.

A shopper who buys from two merchants has **two files and one person
reference**. That is why the customer file list is scoped by tenant and the
redaction action is not.

## `CustomerFileResource`

### The list

Five columns: reference, name, email (hidden by default), lifecycle, opened.

Only **reference** is searchable, and it is the record title, the URL segment
and the global-search term as well. The domain mints it precisely so that a
merchant's search box and a merchant's URL history never carry a shopper's
email. Wave 11 established that both search terms and filter state persist into
the query string; from there they reach browser history, bookmarks, referrer
headers and screenshots.

There are **no filters**. Two reasons, and the second is the interesting one:

- Every filter over identifying data is the search problem again with a
  different name.
- The one non-identifying filter a merchant would want is lifecycle state — and
  there is no `lifecycle_state` column. State is a fold over an append-only
  ledger of decisions, so filtering on it would mean rebuilding that fold in SQL
  here. A panel that recomputes a state the domain already publishes has two
  answers to one question, and they drift. The lifecycle column reads the
  domain's `GetLifecycleState` per row instead — one query per row at a panel's
  page size, marked with a `ponytail:` note about what to do if that ever
  matters.

### The form

Nothing is required except the issuing country of a tax registration, and that
only when a registration has been entered — a rule the domain enforces rather
than one this form invented.

The host's `CustomerResource` marked `first_name`, `last_name`, `email`,
`phone_number`, `address` and `city` required over columns the link migration
had made nullable. The result was a panel that refused records its own API
created. The schema is the authority.

`phone` is a plain text input with no numeric and no `tel` validation. The host
stored a phone number in an `integer` column, which loses a leading zero, loses
a `+44`, and overflows on most international numbers; validating it as a number
here would be the same mistake one layer up. It is stored as given, with a
dialling country beside it, and never normalised to E.164.

`person_ref` is create-only. A file that changes who it is about is a different
file; merging is the action for that.

### The file page

Five sections. Two of them exist because of a specific fault:

**Marketing consent** shows the latest decision per channel, folded by the
domain. A channel nobody has ever asked about reads *Never asked* — deliberately
not *Withdrawn*. Never asked and said no are different facts, and collapsing
them is how a merchant mails somebody who has never heard of them. Consent is
one row per *decision*, not one row per channel; this panel reads the fold and
never assumes a row exists.

**Purchase history** is told to us by Orders through an unbound seam. With
Orders absent it says

> Not available — No purchase-history resolver is bound; Orders is not composed
> into this application.

and it never says zero. Zero orders and no answer are the same number and
opposite facts, and a merchant shown "0 orders, nothing spent" acts on it. When
a resolver *is* bound and reports no purchases, that same section says "0" and
"No purchases recorded in any currency", because a resolver saying "I know this
person and they bought nothing" is a real answer and the only place a zero is
honest.

**Lifetime value is one line per currency.**

> - EUR 12.00
> - GBP 24.99

Never one number. The host's `getTotalSpentAttribute()` summed
`orders.total_amount` with no currency filter, so an order in EUR and an order
in GBP were added together and the result had no unit. If a caller wants one
figure they convert, and conversion is not this module's.

Everything else on the page renders normally with Orders absent. A module that
refuses to show a customer's address because Orders is down is a control with no
reason behind it.

### The three whole-file actions

**Record a lifecycle decision.** The select is built from the domain's
`allowedTransitions()` for wherever the file currently stands — three options
from `Prospect`, three from `Active`, two from `Blocked`, and the action is
hidden entirely once a file is `Closed`. A select listing all five states and
letting `InvalidLifecycleTransition` come back is a form that offers what it
cannot do, and the merchant learns which is which by being refused one at a
time. `Dormant` is reachable only here: no scheduler in this module writes it,
because "has not ordered in 180 days" is a derived audience and belongs to
Attribution and Analytics (#821).

**Merge into another file.** A merge is destructive in one direction and
irreversible: the losing file is superseded, its `person_ref` is released to
null, and afterwards nothing answers to that reference but the survivor. So the
confirmation **names both files by reference** — the losing one in the modal
heading, the surviving one in a live line that updates as soon as it is chosen —
and will not submit until the merchant has typed the losing reference back.
`->requiresConfirmation()` on its own asks "are you sure?" about two files it
never names.

The surviving file is chosen from a select keyed on **reference**. Two host
resources pick a customer with `Customer::pluck('first_name', 'id')`, where two
people called John are one entry repeated — and where a shopper's first name
ends up in a dropdown that never needed it.

**Redact this person.** See below.

## Redaction

`RedactPerson` takes no tenant. It finds every file held on this person by every
merchant on the deployment and redacts all of them. That is correct — a
subject-access erasure is about a person rather than about a storefront, and one
that clears a single merchant's row is a wrong answer rather than a partial one
— and it is *completely invisible* from a panel that is otherwise scoped to one
merchant. A merchant clicks a button on their customer's file and changes
another business's records.

So the modal says so three times, and three tests keep it saying so:

- the heading: *Redact this person across every tenant*
- the description: *…every merchant on this deployment — not only this one, and
  not only the tenant this panel is showing…*
- a checkbox that must be ticked: *I understand this affects every tenant on
  this deployment, not only this one.*

It redacts; it does not delete. The file survives with its identifying fields
nulled and a redaction record beside it, and the memberships, consents and
lifecycle decisions survive with their references intact. Deleting the row would
take the orders' foreign key with it, and an order that cannot say who placed it
is a worse privacy outcome than one that says "redacted".

On addresses it nulls the recipient and the company and **keeps the postal
lines**. A street with no name on it is a place rather than a person, and an
order that shipped somewhere must still be able to say where it went. **This
module offers no address deletion at all**; a host that needs the destination
gone is asking for something we refuse.

## Addresses

Immutable once written. There is a **Revise** action rather than an edit form:
revising writes a new row and marks the old one superseded, with
`superseded_by_address_id` linking the chain. History is the chain, not the
diff.

Superseded rows stay on screen with a "Superseded" column rather than being
hidden behind a toggle — a toggle is a filter, and a filter's state persists
into the URL as readily as a search term does.

A country is required. An address without one cannot be validated, taxed or
shipped to, and the host's free-text `city` and `state` columns with no country
are what that omission looks like from a report that buckets "London" and
"london, UK" separately. There is no `state` field: most countries do not have
one, so it is `administrative_area` and it is optional.

Two identical addresses on one file are a legitimate second row — a person can
genuinely have things delivered to the same place under two labels — so nothing
here deduplicates and no idempotency key is minted. See "Idempotency" below.

## Groups and memberships

A group is a **declared** membership: an operator attaches it, with a
`joined_at`, an optional `expires_at` and an actor. A segment is a **derived**
one, recomputed on a schedule from facts owned elsewhere, and it belongs to
Attribution and Analytics. The host gave both the same word.

A group carries **no discount, no free-shipping threshold and no minimum order
amount**, and the form offers no field for one. The host put
`calculateDiscount()` and `qualifiesForFreeShipping()` on its customer group — a
promotion wearing a customer's clothes. The membership is ours; the offer
attached to it is Promotions'. See `docs/adoption.md` for how the host joins the
two.

There is no member count column, because a count is derived and nothing derived
is stored anywhere in this module.

Ending a membership dates its expiry and **leaves the row**. A promotion applied
while somebody was in a group needs that to stay true. Whether a membership is
live is read from the model's own `isActiveAt()` and is never restated in this
package — the host restated that window three times, ungrouped, and all three
copies returned every never-expiring membership on the deployment because `AND`
binds tighter than `OR`. There is a test named for it.

The members table on a group shows the **file reference** and nothing else about
the person. A screen that exists to answer "how big is this group" does not need
a column of email addresses.

## Idempotency

None, anywhere, and that is a decision rather than an omission.

- **Opening a file**: `(tenant_id, person_ref)` is a server-supplied natural key
  and the database already refuses a second file. A key the client holds is a
  key the client can change, so one layered over a natural key is strictly
  weaker than the constraint already there.
- **Adding an address**: a second identical address is a legitimate second row,
  so there is nothing for a key to protect. Decided on its own merits, and the
  opposite way round from the file.
- **Everything else** is a merchant pressing a button in a panel, not a shopper
  retrying a payment. Every action either appends to a ledger, where a duplicate
  is visible and correctable, or is guarded by the domain (`AlreadyInGroup`,
  `CustomerFileIsSuperseded`, `InvalidLifecycleTransition`).

## Authorization

Every ability this package does not publish is answered **false by name**, in
`src/Concerns/DeniesUnpublishedResourceAbilities.php` and
`src/Concerns/DeniesUnpublishedRelationAbilities.php`, and asserted by name in
`tests/Feature/AuthorizationTest.php`.

Naming them is the point. A missing policy is permissive. A policy that exists
but lacks the method asked about is *also* permissive, because Filament's
`get_authorization_response()` falls through to allow when the method is absent.
"We did not write a delete policy" is not "delete is off" — it is "delete is on
and nobody decided".

Every relation manager is a reader. `canAssociate()` and `canDissociate()` are
live on a `hasMany` and default open; dissociating a membership would null its
foreign key and leave a declared fact belonging to nobody.

The abilities that *are* published — `viewAny`, `create`, `view`, `edit` on a
file; `viewAny` and `create` on a group — fall through to Filament's policy
lookup, so a host that ships a gate policy for `CustomerFile` still governs who
may read and write. With no policy at all they are open, which is Filament's
default for any resource and is the host's decision to make, not ours. See
`docs/adoption.md`.

## Row clicks

`recordUrl` and `recordAction` are unwired on every relation-manager table. Not
because anything would raise — these records are Eloquent models, so Filament's
`Model` type hints are satisfied and nothing breaks — but because a clickable
row offers a page for an address, a membership or a lifecycle decision, and
there is no such page and no view ability published for one. **The reason is
authorization, not a type error.**
