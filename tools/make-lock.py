#!/usr/bin/env python3
"""Regenerate composer.lock from the resolved vendor tree.

DECISION (infra): the committed lock file must be one Composer can actually use.
An earlier version was built from the resolver's *internal* summary, which only
knew package names and versions - every `require`, `autoload` and file list was
empty. `composer install` reads exactly those fields to build its class maps, so
that lock could never have installed anything.

DECISION (infra): dist/source URLs are not built from the package name either.
A Composer name is not a GitHub repository path - `league/*` lives in
`thephpleague/*`, `psr/*` in `php-fig/*`, `guzzlehttp/*` in `guzzle/*` - and the
`v` prefix is not consistent across releases (guzzle tags `7.16.1`). Guessing
`github.com/{name}.git` + `zipball/v{version}` produced a lock whose archives
404 on every clean install. The repo map is imported from resolve-deps.py, the
one place that map exists, and the tag is probed on codeload; when no tag
answers, this script exits non-zero instead of writing a lock that cannot be
installed.

This script reads each package's own composer.json, which is authoritative, and
emits a lock whose `packages` entries match Composer's schema closely enough for
`composer install` to work from the repository's git sources.

It cannot produce `content-hash` the way Composer does (that needs Composer), so
it emits a stable hash of the root requirements instead; Composer treats a
mismatch as "lock is stale" and refreshes it on the first install.
"""

from __future__ import annotations

import hashlib
import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
VENDOR = ROOT / "vendor"
LOCK = ROOT / "composer.lock"

# Fields Composer copies verbatim from a package manifest into the lock.
KEEP = [
    "name", "version", "require", "require-dev", "conflict", "provide",
    "replace", "suggest", "type", "extra", "autoload", "autoload-dev",
    "notification-url", "license", "authors", "description", "homepage",
    "keywords", "support", "funding", "time",
]


def load_repo_map() -> dict[str, str]:
    """The Composer-name -> GitHub-repo map the resolver used.

    DECISION (infra): imported, not copied. Two copies of this map eventually
    disagree, and the disagreement is what shipped a lock whose archives 404'd.
    resolve-deps.py guards its `main()`, so importing it has no side effects
    even though its hyphenated filename needs importlib.
    """
    import importlib.util

    spec = importlib.util.spec_from_file_location(
        "resolve_deps", ROOT / "tools" / "resolve-deps.py"
    )
    module = importlib.util.module_from_spec(spec)
    assert spec and spec.loader
    spec.loader.exec_module(module)
    return module.REPOS


def tag_for(repo: str, version: str) -> str:
    """The exact tag string GitHub serves for `version`.

    Tags are not uniformly prefixed: guzzle releases as `7.16.1`, symfony as
    `v8.1.11`. Probe codeload for both spellings and return the one that
    exists; when neither does, stop rather than write an uninstallable lock.
    """
    if version.startswith("dev-"):
        return version

    for tag in (f"v{version}", version):
        url = f"https://codeload.github.com/{repo}/tar.gz/refs/tags/{tag}"
        result = subprocess.run(
            ["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}",
             "--max-time", "20", "-I", url],
            capture_output=True, text=True, timeout=30,
        )
        if result.stdout.strip() in ("200", "301", "302"):
            return tag

    raise SystemExit(
        f"no reachable tag for {repo} at version {version} (tried {version} with and without a v prefix)"
    )


def normalise_version(raw: str) -> tuple[str, str]:
    """Composer wants both a display version and a normalised one."""
    version = str(raw).lstrip("v")
    parts = version.split(".")
    padded = (parts + ["0", "0", "0", "0"])[:4]
    return version, ".".join(padded)


def main() -> int:
    repos = load_repo_map()
    root_manifest = json.loads((ROOT / "composer.json").read_text())
    packages = []

    for manifest_path in sorted(VENDOR.glob("*/*/composer.json")):
        data = json.loads(manifest_path.read_text())
        name = data.get("name")

        if not name:
            continue

        entry = {key: data[key] for key in KEEP if key in data}
        version, normalised = normalise_version(data.get("version", "0.0.0"))
        entry["version"] = version
        entry["version_normalized"] = normalised

        # Composer needs an install source. The tree came from release tags on
        # GitHub, so point the dist at the same tarball - at the repository
        # that actually exists and the tag that actually answers, never at a
        # guess built from the Composer name.
        repo = repos.get(name, name)
        tag = tag_for(repo, version) if not version.startswith("dev-") else version
        entry["source"] = {
            "type": "git",
            "url": f"https://github.com/{repo}.git",
            "reference": tag,
        }
        entry["dist"] = {
            "type": "zip",
            "url": f"https://api.github.com/repos/{repo}/zipball/{tag}",
            "reference": tag,
        }
        entry.setdefault("type", "library")

        packages.append(entry)

    if not packages:
        print("error: no packages found under vendor/, run tools/resolve-deps.py first", file=sys.stderr)
        return 1

    # Composer hashes the root requirements to detect a stale lock.
    relevant = {
        "name": root_manifest.get("name"),
        "require": root_manifest.get("require", {}),
        "require-dev": root_manifest.get("require-dev", {}),
        "minimum-stability": root_manifest.get("minimum-stability", "stable"),
        "prefer-stable": root_manifest.get("prefer-stable", True),
    }
    digest = hashlib.md5(
        json.dumps(relevant, sort_keys=True).encode()
    ).hexdigest()

    lock = {
        "_readme": [
            "This file locks the dependencies of your project to a known state.",
            "Generated by tools/make-lock.py from the resolved vendor tree.",
            "Run `composer update` once Packagist is reachable to re-lock properly.",
        ],
        "content-hash": digest,
        "packages": sorted(packages, key=lambda p: p["name"]),
        "packages-dev": [],
        "aliases": [],
        "minimum-stability": root_manifest.get("minimum-stability", "stable"),
        "stability-flags": {},
        "prefer-stable": root_manifest.get("prefer-stable", True),
        "prefer-lowest": False,
        "platform": root_manifest.get("require", {}),
        "platform-dev": {},
        "plugin-api-version": "2.6.0",
    }

    LOCK.write_text(json.dumps(lock, indent=4, ensure_ascii=False) + "\n")

    print(f"wrote {LOCK.relative_to(ROOT)} with {len(packages)} packages")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
