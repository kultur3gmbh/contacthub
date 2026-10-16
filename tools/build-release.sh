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
if [[ -z "$basic_version" ]]; then
  echo "Could not read plugin versions." >&2
  exit 1
fi

mkdir -p dist
git archive --format=zip --prefix=contacthub/ HEAD:contacthub > "dist/contacthub-basic-${basic_version}.zip"
unzip -tq "dist/contacthub-basic-${basic_version}.zip" >/dev/null

echo "Created dist/contacthub-basic-${basic_version}.zip"

