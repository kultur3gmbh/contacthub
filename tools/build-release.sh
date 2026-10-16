#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Release packages must be built from a Git repository." >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "The zip command is required." >&2
  exit 1
fi

basic_version="$(awk -F': ' '/^ \* Version:/{print $2; exit}' contacthub/contacthub.php)"
basic_requires="$(awk -F': ' '/^ \* Requires at least:/{print $2; exit}' contacthub/contacthub.php)"
basic_requires_php="$(awk -F': ' '/^ \* Requires PHP:/{print $2; exit}' contacthub/contacthub.php)"
basic_tested="${CONTACTHUB_TESTED_WP:-$basic_requires}"
if [[ -z "$basic_version" || -z "$basic_requires" || -z "$basic_requires_php" ]]; then
  echo "Could not read plugin versions." >&2
  exit 1
fi

mkdir -p dist
basic_zip="dist/contacthub-basic-${basic_version}.zip"
git archive --format=zip --prefix=contacthub/ HEAD:contacthub > "$basic_zip"
unzip -tq "$basic_zip" >/dev/null

if command -v shasum >/dev/null 2>&1; then
  basic_sha256="$(shasum -a 256 "$basic_zip" | awk '{print $1}')"
elif command -v sha256sum >/dev/null 2>&1; then
  basic_sha256="$(sha256sum "$basic_zip" | awk '{print $1}')"
else
  echo "A SHA-256 utility is required." >&2
  exit 1
fi

printf '{\n  "slug": "contacthub",\n  "version": "%s",\n  "requires": "%s",\n  "requires_php": "%s",\n  "tested": "%s",\n  "sha256": "%s"\n}\n' \
  "$basic_version" "$basic_requires" "$basic_requires_php" "$basic_tested" "$basic_sha256" \
  > dist/contacthub-update.json

echo "Created $basic_zip and dist/contacthub-update.json"

