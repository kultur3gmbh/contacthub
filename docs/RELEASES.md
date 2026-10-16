# Release process

## Before changing a version

Version changes require explicit user approval. Confirm the intended Basic version before changing plugin metadata.

## Release checklist

1. Review the release scope and compatibility requirements.
2. Run `./tools/check.sh`.
3. Test database upgrades from the previous release.
4. Update changelog and translation catalogs.
5. Update plugin headers and version constants.
6. Commit the complete source state.
7. Run `./tools/build-release.sh`; it creates the installable ZIP and the matching SHA-256 update manifest.
8. Inspect the ZIP and manifest, then install the ZIP on a non-production WordPress site.
9. Obtain approval before any live-site deployment.

Generated ZIP and update-manifest files are written to `dist/` and are not committed. Upload both files to the matching published GitHub Release only after the isolated update test passes.

