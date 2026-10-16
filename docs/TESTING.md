# Testing strategy

## Required checks

Every change must pass:

- PHP syntax validation for ContactHub Basic.
- JavaScript syntax validation for ContactHub Basic.
- plugin header and version-constant consistency checks.
- Basic/Plus compatibility checks.
- installation and database-upgrade smoke tests for schema changes.

## Test modes

Features that read or write contacts must be tested in both modes:

1. ContactHub standalone database.
2. Groundhogg as the selected data source.

Optional integrations must also be tested with their dependency inactive.

## Sanitized fixtures

Future integration fixtures should represent:

- WooCommerce customers, guest orders, refunds, and multiple billing addresses;
- German Market invoice references without real invoice documents;
- Groundhogg contacts, companies, tags, broadcasts, and opt-in states;
- Newsletter plugin subscribers, mailings, recipients, opens, and clicks;
- Event Tickets attendees matched by normalized email address;
- duplicate contacts and merge conflicts.

Fixtures must use fictional names, domains such as `example.test`, and artificial identifiers.

## Manual site matrix

- Clean WordPress installation without Groundhogg or WooCommerce.
- WordPress plus WooCommerce.
- WordPress plus Groundhogg and Companies.
- WordPress plus The Events Calendar and Event Tickets Plus.
- WordPress plus Newsletter plugin with historical mailings.

