# Codex Cloud setup

## Repository

Use a private GitHub repository for the complete ContactHub monorepo. Cloud tasks should start from a branch and return a reviewable diff or pull request.

The cloud repository contains source code, documentation, sanitized tests, and build scripts. It must not contain live customer data, database dumps, production credentials, or manually downloaded copies of third-party plugins.

## Environment

The Codex Cloud environment should provide:

- PHP 7.4 or newer for syntax checks; the validation workflow currently uses PHP 8.1.
- Node.js for JavaScript syntax checks.
- `zip`, `git`, and GNU-compatible shell tools for release packaging.
- Optional Composer tooling when WordPress Coding Standards and PHPUnit are added.

Run this after checkout:

```sh
chmod +x tools/*.sh
./tools/check.sh
```

## Access policy

- Keep general outbound internet access restricted to dependency sources required by the build.
- Do not add WordPress administrator passwords or database credentials as environment variables.
- Do not connect production sites to the first cloud environment.
- Diagnose and approve live-site changes through the existing local WordPress MCP connections.

## Working model

1. Create a focused branch for each correction or feature.
2. Let the cloud task implement and run repository checks.
3. Review the diff locally.
4. Test the generated plugin on a dedicated WordPress test installation.
5. Create a release ZIP only after explicit approval.

