# GitHub updates during the test phase

Basic uses `kultur3gmbh/contacthub` (public, no token). Plus uses `kultur3gmbh/contacthub-plus` (private). Only the latest **published, non-draft, non-prerelease GitHub Release** is considered. Branches, GitHub source archives and `main` are never installed. GitHub's `/releases/latest` determines the release; do not republish an older version as latest.

## Bootstrap and per-site configuration

Install this Basic code before this Plus code. This first installation still needs the normal trusted deployment process because previously installed versions have no updater. Basic exposes `ContactHub_GitHub_Updater`; Plus registers through that public contract at `plugins_loaded` and does not copy Basic implementation. An older Basic without this contract shows a safe updater dependency notice. Existing Plus functionality remains subject to its original Basic minimum.

Create a **separate, expiring fine-grained personal access token per test website**, restricted to the private `contacthub-plus` repository. Grant only **Contents: Read-only** (Metadata: Read-only is implicit). Do not grant write or administration access. Select the appropriate resource owner; organization approval may be necessary. Set an expiry covering only the test period.

In the website's `wp-config.php`, before the line loading WordPress, use a placeholder as follows; substitute the real secret only in that site's private configuration:

```php
define( 'CONTACTHUB_PLUS_GITHUB_TOKEN', '<PLUS-READ-ONLY-TOKEN-PLACEHOLDER>' );
```

Never put the real token into plugin files, repository history, ZIPs, screenshots, issue reports, logs, database options or administration forms. The updater has no token field, token status dump or token logging. Do not enable third-party HTTP request debugging that records Authorization headers on a token-bearing test site. Access errors expose only fixed messages, never raw HTTP responses or exception payloads.

Missing, revoked, expired or unauthorized tokens affect only update access; they never disable Plus modules. After replacing a token, click **Plugins → Check for updates** on the relevant plugin to clear its cached result. Checks are cached network-wide: success for 30 minutes, failure for 5 minutes. An installation always revalidates the published release and runtime requirements.

Authenticated requests go only to locally constructed `https://api.github.com/repos/kultur3gmbh/contacthub-plus/releases/...` endpoints. Automatic HTTP redirects are disabled. Only HTTPS redirects to exact GitHub release CDN hosts (`release-assets.githubusercontent.com`, `objects.githubusercontent.com`, `github-releases.githubusercontent.com`) are followed, and **Authorization is removed before every CDN request**. Other hosts, embedded credentials, fragments, insecure schemes and non-443 ports are rejected. No global HTTP authentication filter is installed. Signed CDN URLs remain in memory only.

## WordPress behavior

Update offers appear in the normal Plugins/Updates administration. **Manual updates are the default.** Enable automatic updates explicitly for each plugin in WordPress's Plugins screen if desired. The updater also respects upstream vetoes and requires the plugin to appear in WordPress's `auto_update_plugins` option; a generic “auto-update everything” filter alone does not opt these plugins in.

WordPress and PHP minima are checked before offering an installable package and immediately before downloading. Plus additionally requires active Basic at the release's `requires_basic` version. Basic is read from the installed file, so Basic-then-Plus bulk updates can recognize the newly installed Basic version in the same request. Incompatible updates are visible with no installable package; update dependencies first, then check again. Plus is never automatically installed while incompatible.

Downloads are streamed to temporary files, size-checked and SHA-256 verified before extraction. The extracted package must have exactly the existing `contacthub/` or `contacthub-plus/` directory and the expected main PHP file. Version, WordPress/PHP and Plus's Basic minimum in the plugin header must match release metadata. WordPress replaces the same plugin directory. No uninstall, option reset or settings deletion is run.

## Publishing a release (only when explicitly authorized)

No release or version is created by installing this updater. For a later, authorized release:

1. Choose a strictly increasing normal numeric version (`MAJOR.MINOR.PATCH`), update plugin header/constants consistently, and run checks. Plus must declare its Basic minimum consistently in `Requires ContactHub:` and `CONTACTHUB_PLUS_MIN_BASIC_VERSION`.
2. Build from the reviewed commit using the existing `tools/build-release.sh`. Expected filenames are `contacthub-basic-VERSION.zip` and `contacthub-plus-VERSION.zip`. Their root directories remain `contacthub/` and `contacthub-plus/`; do not upload source archives as plugin packages. Exclude private configuration and tokens.
3. Compute the ZIP's SHA-256 and byte size. Create a separate `contacthub-update.json` or `contacthub-plus-update.json` asset. All version fields must contain numeric versions, not ranges. Example below uses placeholders and is intentionally not publishable.
4. Attach both the ZIP and its manifest to the same GitHub Release, with tag `vVERSION` (or `VERSION`). Publish only after checking all assets; drafts and prereleases are ignored. The updater checks GitHub's ZIP digest too when present.
5. Test on the isolated test website before enabling any automatic update.

```json
{
  "slug": "<contacthub-OR-contacthub-plus>",
  "version": "<RELEASE-VERSION>",
  "requires": "<MINIMUM-WORDPRESS-VERSION>",
  "requires_php": "<MINIMUM-PHP-VERSION>",
  "tested": "<TESTED-WORDPRESS-VERSION>",
  "requires_basic": "<PLUS-MINIMUM-BASIC-VERSION>",
  "sha256": "<64-LOWERCASE-HEX-CHARACTERS>"
}
```

Omit `requires_basic` for Basic. `requires`, `requires_php`, and (for Plus) `requires_basic` must exactly match the packaged plugin's headers. Release notes are not treated as executable metadata or rendered as trusted HTML. Temporary download failures do not trigger installation of a different source or branch.

## End of test phase

Basic's GitHub channel can be disabled per site:

```php
define( 'CONTACTHUB_GITHUB_UPDATES', false );
```

The updater stops GitHub checks and discards its cached WordPress GitHub offer. It does not remove a WordPress.org offer. Basic deliberately has no custom `Update URI` header: after WordPress.org accepts the same `contacthub` slug, the ordinary WordPress.org channel can take over. Keep `contacthub/contacthub.php`, the same options, and monotonically increasing versions. Run WordPress's update check after switching. Test this handover before removing GitHub support from a later release.

Plus can separately disable the test channel with `CONTACTHUB_PLUS_GITHUB_UPDATES` set to `false`. Its custom `Update URI` prevents accidental WordPress.org substitution. A future customer updater must use a protected update service with revocable site-scoped access, release metadata and signed short-lived download URLs. GitHub PATs must not be distributed to customer sites. That service is an integration boundary, not implemented here; keep the plugin basename and version sequence when replacing the channel.

At test completion remove `CONTACTHUB_PLUS_GITHUB_TOKEN` from every test site's configuration **and revoke that site's token in GitHub**. Remove any token-bearing private backups under the site's normal retention policy. Clear update caches with Check for updates before disabling the channel; never print secrets while doing so.

## Primary references

- https://docs.github.com/en/rest/releases/releases#get-the-latest-release
- https://docs.github.com/en/rest/releases/assets#get-a-release-asset
- https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens
- https://developer.wordpress.org/reference/hooks/upgrader_pre_download/
- https://developer.wordpress.org/reference/hooks/upgrader_source_selection/
- https://developer.wordpress.org/reference/hooks/auto_update_type/
