# Tests

Use sanitized fixtures only. Production customer data must never be copied here. Run `./tools/check.sh` before handoff.

With PHP CLI, the check runs `tests/trash.php` for both record types, soft deletion and restoration, preserved relationships and source tables, permanent deletion restricted to Trash, administrator/mode/confirmation guards, malformed requests, transactional rollback, non-default table prefixes, and retained event/activity records.

Without PHP, the script runs its structural fallback and explicitly reports the skipped behavior test. Structural validation is not PHP execution or a WordPress integration test. The next test milestone remains a disposable WordPress environment covering installation, standalone and Groundhogg data-source modes, and optional dependencies both absent and active.

