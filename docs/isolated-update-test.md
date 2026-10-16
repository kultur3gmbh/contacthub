# Isolated WordPress update acceptance test

## Execution status of this change

**Not executed in this workspace.** No PHP, Node CLI, Docker or WordPress installation is available. JavaScript syntax was separately verified for all seven plugin scripts through the available JavaScript execution service. Shell DNS cannot resolve `api.github.com`. `tools/check.sh` therefore uses structural fallback and marks PHP behavioral tests skipped. No test is claimed as a real installation test. No production website, release ZIP, published release or version number was changed.

The offline PHP suites cover HTTP/WordPress adapters with sanitized fixtures. They do not substitute for the real `Plugin_Upgrader` acceptance test below. This test is required before deployment.

## Prepare a disposable test site

Use a fresh disposable WordPress installation and database, with outbound HTTPS, PHP ZIP support and a writable plugin directory. Do not use a production copy with customer data. Disable outbound mail and indexing. Record the tested WordPress and PHP versions. Install the approved Basic/Plus baseline containing these updater changes through the usual trusted deployment path, with a strictly older installed version than an already authorized published test release. Do not invent a version or publish a release merely to satisfy this checklist without the user's separate release authorization.

Configure a site-specific short-lived, read-only Plus token in `wp-config.php` as documented in `github-updates.md`. Do not paste it into command lines, screenshots or reports. Both existing repositories must have valid published release assets and manifests. If they do not, report the live release test blocked until an authorized release is available.

Set harmless sentinel options for Basic and Plus, create one sanitized person/company and record their IDs. Record the exact installed plugin basenames and activation states.

## Executable installation runner

After preparing the disposable site, set `WP_ENVIRONMENT_TYPE` to `local` in its private configuration and create an empty `.contacthub-isolated-test` marker at that WordPress root. The Basic repository provides a runner that consumes existing authorized published releases and invokes the **real** WordPress `Plugin_Upgrader` for Basic then Plus:

```sh
CONTACTHUB_TEST_WP_ROOT=/path/to/disposable/wordpress php tests/wordpress-update-integration.php
```

The runner preflights a newer published offer for **both** plugins before installation, uses a local administrator, performs actual ZIP installation, verifies the expected installed versions, stable basenames, activation state and preserved sentinel options, then removes its sentinels. It never creates/releases a ZIP or changes source version numbers. It fails closed without the marker, local environment or updater-enabled Basic. Missing releases/access report BLOCKED rather than success. The broader failure matrix and contact data checks below remain separate acceptance cases. This runner is deliberately **not** executed by `tools/check.sh`.

## Real GitHub detection and installation

1. In Plugins, click each plugin's **Check for updates**. Confirm correct published versions appear in the normal WordPress Updates screen. Confirm no token or signed CDN URL appears in page source, plugin info or update transients.
2. Confirm neither plugin has automatic updates enabled. Run the automatic update checker and verify no install takes place. Explicitly opt in on the Plugins screen only when testing that behavior; turn it off again afterward.
3. Manually update Basic, then Plus, using the WordPress update buttons. This must perform real ZIP download, extraction and `Plugin_Upgrader` installation. Record before/after versions and the result. Check that only `contacthub/` and `contacthub-plus/` exist, activation states persist and sentinel options/person/company remain unchanged. Confirm no order/invoice/event/source record changed.
4. Test a Basic-then-Plus bulk update with a compatible pair. Check runtime requirements and database access after both complete.
5. Repeat with optional integrations absent and with sanitized local WooCommerce/Groundhogg fixtures active. Verify both ContactHub standalone and Groundhogg views remain usable; update code must not depend on those integrations.

## Failure and compatibility matrix

On the disposable site only, restore the baseline between scenarios:

| Scenario | Required result |
| --- | --- |
| Plus constant missing or empty | Fixed readable notice; no authenticated request; Plus still usable |
| Invalid/revoked/expired placeholder access | Fixed access notice; no raw error body/token in output; Plus still usable |
| Network/DNS failure, timeout, GitHub 429/5xx | Short error cache; no update install and no fallback to `main` |
| GitHub draft/prerelease/missing manifest/wrong ZIP | No update offer from that source |
| Redirect to unrelated host, HTTP URL, credentials or spoofed CDN suffix | Rejected before issuing a request to that host |
| Allowed CDN redirect | No Authorization header forwarded; ZIP checksum verified |
| Unsupported PHP/WordPress | No installable package and no automatic install |
| Missing/inactive/older Basic than Plus manifest requires | No installable Plus package; manual/automatic install blocked |
| Basic updated in same request before Plus | Read installed Basic header; allow Plus only if minimum now met |
| Tampered bytes, mismatched header/version/root directory | Abort before replacing installed plugin files |
| Release withdrawn/replaced after detection | Revalidate on download; refuse stale asset |
| Disable Basic GitHub channel | Stale GitHub offer removed; normal WordPress.org offer retained |
| Remove token and revoke it after test | Plus remains usable; GitHub update access fails cleanly |

For manipulated HTTP responses use a local test HTTP transport/mock in the disposable installation, not a production integration. Ensure the test transport never persists actual request headers. Use placeholders in all recorded reports.

## Papierkorb acceptance

As administrator in standalone mode, trash and restore a person and a company, individually and in bulk. Confirm active lists/exports omit trashed records, relationships return after restoration, and repeat imports do not recreate trashed identities. Attempt purge of an active record, missing nonce, omitted confirmation and shop-manager access: all must fail without changes. Purge trashed records with confirmation; only local owned details/relationships may disappear, while activities/event references remain detached. Groundhogg mode must expose no destructive control or endpoint access. Test a simulated database error and non-InnoDB table: purge must fail without partial cleanup.

Record actual passes, failures and blocked scenarios. Never mark an unexecuted matrix as passed.
