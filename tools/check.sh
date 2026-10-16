#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

status=0
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! php -l "$file" >/dev/null; then
      status=1
    fi
  done < <(find contacthub -type f -name '*.php' -print0)
elif command -v python3 >/dev/null 2>&1; then
  echo "PHP CLI is unavailable; running the offline structural fallback."
  python3 tools/check_php_structure.py contacthub || status=1
else
  echo "PHP or Python 3 is required for source validation." >&2
  status=1
fi

if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! node --check "$file" >/dev/null; then
      status=1
    fi
  done < <(find contacthub -type f -name '*.js' -print0)
fi

basic_header="$(awk -F': ' '/^ \* Version:/{print $2; exit}' contacthub/contacthub.php)"
basic_constant="$(sed -n "s/^define( 'CONTACTHUB_VERSION', '\([^']*\)' );/\1/p" contacthub/contacthub.php | head -1)"

if [[ -z "$basic_header" || "$basic_header" != "$basic_constant" ]]; then
  echo "ContactHub Basic header and version constant do not match." >&2
  status=1
fi

if ! grep -q "Text Domain: contacthub$" contacthub/contacthub.php; then
  echo "ContactHub Basic text domain is missing or unexpected." >&2
  status=1
fi

if [[ "$status" -ne 0 ]]; then
  exit "$status"
fi

echo "ContactHub Basic checks passed."


