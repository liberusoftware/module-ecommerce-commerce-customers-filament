# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this package
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-14

### Added

- `CommerceCustomersPlugin`, attached per panel by the host. Nothing registers
  globally, so a shopper-facing panel simply does not attach it.
- `CustomerFileResource` with list, create, view and edit pages, tenant-scoped
  in `getEloquentQuery()` and routed on the file's non-identifying reference.
- `CustomerGroupResource` with list, create and view pages. No edit page: the
  domain publishes no group update, and a rename here would have to go around
  every check the domain performs.
- Relation managers for addresses, group memberships and lifecycle decisions on
  a file, and for members on a group.
- Page actions for the three whole-file decisions: recording a lifecycle
  transition, merging into another file, and redacting a person.
- Purchase history rendered as an explicit unavailability when Orders is not
  composed in, and lifetime value rendered one line per currency.

### Security

- Every Filament ability this package does not publish is forced false by name,
  on every resource and on every relation manager, `canAssociate()` and
  `canDissociate()` included — both are live on a `hasMany` and default open.
- Nothing identifying is searchable, sortable or filterable. The package ships
  no table filter at all.
- The redaction modal states in three places that erasure spans every tenant on
  the deployment.

[0.1.0]: https://github.com/liberusoftware/module-ecommerce-commerce-customers-filament/releases/tag/0.1.0
