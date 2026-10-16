# Safe lifecycle of people and companies

The existing `deleted_at` columns implement the Trash state. No schema or version change is needed.

Administrators in standalone mode can open **ContactHub → Trash**, choose People or Companies, and switch between active records and Trash. Existing people/company lists also link to this page for an individual record. Select one or multiple records (maximum 50 per request), choose the operation and check the explicit confirmation box before applying. The select-all control affects only the displayed page; the form works without JavaScript through individual checkboxes.

- **Move to Trash:** sets `deleted_at` and `updated_at`, without removing relationships or metadata. Existing active lookups, exports and company/contact maps omit trashed entities.
- **Restore:** clears `deleted_at`; original IDs and relationships are preserved. Unique person identities remain reserved while in Trash. Import and registration lookups do not recreate a trashed company; repeated Groundhogg/WooCommerce imports skip trashed people and companies.
- **Delete permanently:** only accepts an already trashed entity. It removes the ContactHub core record and its owned local metadata/assignments. A company's people and shared tags are never deleted. Activities, including imported event/order references, are retained and only the affected local entity reference is detached. Plus/third-party tables are not queried or deleted.

All mutations require `manage_options`, standalone mode, POST, a valid action nonce, an allowlisted entity/operation, explicit confirmation and positive IDs. GET links only select a record for review. Server-side checks apply equally to individual and bulk actions. Unauthorized access and Groundhogg mode fail before any write.

Permanent deletion checks that every involved ContactHub table uses InnoDB, opens a transaction, locks the trashed entity, and rolls back local cleanup on any failure. Nontransactional or inaccessible engines fail closed. Bulk actions process each entity separately and report changed and failed/no-longer-in-state counts; they do not silently claim every selection succeeded.

No WordPress user deletion, WooCommerce mutation, invoice API, event deletion, Groundhogg deletion, external source-system API or destructive extension hook is called. All table names come from the existing ContactHub schema allowlist with the active database prefix. A later import may recreate a **permanently** deleted local record because its external original intentionally remains; merely trashed records remain reserved until restored or purged.

`tests/trash.php` supplies sanitized state and failure fixtures. Real WordPress/Groundhogg/standalone verification remains part of `docs/isolated-update-test.md`; the current environment has no PHP runtime or disposable WordPress installation.

