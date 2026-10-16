# Product decisions

This document records stable decisions that must survive individual conversations and cloud tasks.

## Product identity

- Product name: ContactHub.
- Developer: Kultur3 GmbH.
- Basic display positioning: User-Friendly CRM for WordPress.
- Basic and Plus are separate installable plugins maintained in separate repositories.

## Data ownership

- Administrators choose Groundhogg or the ContactHub database as the active contact data source when Groundhogg is present.
- Without Groundhogg, ContactHub creates and uses its own tables.
- WooCommerce remains the order source.
- German Market remains the invoice source.
- Historical orders and invoices are never duplicated or altered by ContactHub.

## Migrations

- Groundhogg, WooCommerce, and Newsletter plugin migrations are non-destructive.
- Source records remain unchanged.
- Repeated imports merge by stable identifiers and email addresses as appropriate.
- Imported historical broadcasts are read-only archives, not editable email templates.

## Releases and deployment

- New files or release versions are produced only after an explicit go from the user.
- Installation on live sites requires a separate explicit approval.

