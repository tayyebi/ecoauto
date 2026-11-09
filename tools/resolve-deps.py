#!/usr/bin/env python3
"""Resolve Laravel's dependency tree from GitHub tarballs.

Packagist is not reachable from this sandbox, so this script does what Composer
would do for the purposes of *running and testing* the application: it walks
`require` blocks, downloads each package's release tarball, and writes a
PSR-4 autoloader plus a minimal installed.json that Laravel's PackageManifest
can read.

It is a verification tool only. The project itself depends on Composer.
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import tarfile
import urllib.request
from pathlib import Path

REPOS = {
    "brick/math": "brick/math",
    "carbonphp/carbon-doctrine-types": "CarbonPHP/carbon-doctrine-types",
    "dflydev/dot-access-data": "dflydev/dflydev-dot-access-data",
    "doctrine/inflector": "doctrine/inflector",
    "doctrine/lexer": "doctrine/lexer",
    "dragonmantank/cron-expression": "dragonmantank/cron-expression",
    "egulias/email-validator": "egulias/EmailValidator",
    "fruitcake/php-cors": "fruitcake/php-cors",
    "graham-campbell/result-type": "GrahamCampbell/Result-Type",
    "guzzlehttp/guzzle": "guzzle/guzzle",
    "guzzlehttp/promises": "guzzle/promises",
    "guzzlehttp/psr7": "guzzle/psr7",
    "guzzlehttp/uri-template": "guzzle/uri-template",
    "laravel/framework": "laravel/framework",
    "laravel/prompts": "laravel/prompts",
    "laravel/serializable-closure": "laravel/serializable-closure",
    "league/commonmark": "thephpleague/commonmark",
    "league/config": "thephpleague/config",
    "league/flysystem": "thephpleague/flysystem",
    "league/flysystem-local": "thephpleague/flysystem-local",
    "league/mime-type-detection": "thephpleague/mime-type-detection",
    "league/uri": "thephpleague/uri",
    "league/uri-interfaces": "thephpleague/uri-interfaces",
    "masterminds/html5": "Masterminds/html5-php",
    "monolog/monolog": "Seldaek/monolog",
    "nesbot/carbon": "briannesbitt/Carbon",
    "nette/schema": "nette/schema",
    "nette/utils": "nette/utils",
    "nunomaduro/termwind": "nunomaduro/termwind",
    "phpoption/phpoption": "schmittjoh/php-option",
    "psr/clock": "php-fig/clock",
    "psr/container": "php-fig/container",
    "psr/event-dispatcher": "php-fig/event-dispatcher",
    "psr/http-client": "php-fig/http-client",
    "psr/http-factory": "php-fig/http-factory",
    "psr/http-message": "php-fig/http-message",
    "psr/log": "php-fig/log",
    "psr/simple-cache": "php-fig/simple-cache",
    "ralouphie/getallheaders": "ralouphie/getallheaders",
    "ramsey/collection": "ramsey/collection",
    "ramsey/uuid": "ramsey/uuid",
    "symfony/clock": "symfony/clock",
    "symfony/console": "symfony/console",
    "symfony/css-selector": "symfony/css-selector",
    "symfony/deprecation-contracts": "symfony/deprecation-contracts",
    "symfony/error-handler": "symfony/error-handler",
    "symfony/event-dispatcher": "symfony/event-dispatcher",
    "symfony/event-dispatcher-contracts": "symfony/event-dispatcher-contracts",
    "symfony/finder": "symfony/finder",
    "symfony/http-foundation": "symfony/http-foundation",
    "symfony/http-kernel": "symfony/http-kernel",
    "symfony/mailer": "symfony/mailer",
    "symfony/mime": "symfony/mime",
    "symfony/polyfill-ctype": "symfony/polyfill-ctype",
    "symfony/polyfill-intl-grapheme": "symfony/polyfill-intl-grapheme",
    "symfony/polyfill-intl-idn": "symfony/polyfill-intl-idn",
    "symfony/polyfill-intl-normalizer": "symfony/polyfill-intl-normalizer",
    "symfony/polyfill-mbstring": "symfony/polyfill-mbstring",
    "symfony/polyfill-php80": "symfony/polyfill-php80",
    "symfony/polyfill-php82": "symfony/polyfill-php82",
    "symfony/polyfill-time": "symfony/polyfill-time",
    "symfony/polyfill-php83": "symfony/polyfill-php83",
    "symfony/polyfill-php84": "symfony/polyfill-php84",
    "symfony/polyfill-php85": "symfony/polyfill-php85",
    "symfony/polyfill-php86": "symfony/polyfill-php86",
    "symfony/polyfill-uuid": "symfony/polyfill-uuid",
    "symfony/process": "symfony/process",
    "symfony/routing": "symfony/routing",
    "symfony/service-contracts": "symfony/service-contracts",
    "symfony/string": "symfony/string",
    "symfony/translation": "symfony/translation",
    "symfony/translation-contracts": "symfony/translation-contracts",
    "symfony/uid": "symfony/uid",
    "symfony/var-dumper": "symfony/var-dumper",
    "tijsverkoyen/css-to-inline-styles": "tijsverkoyen/CssToInlineStyles",
    "vlucas/phpdotenv": "vlucas/phpdotenv",
    "voku/portable-ascii": "voku/portable-ascii",
}

SKIP = re.compile(r"^(php|php-64bit|hhvm|ext-[a-z0-9_]+|lib-[a-z0-9_]+|composer|composer-runtime-api|composer-plugin-api)$")


def gh_json(url: str):
    path = url.replace("https://api.github.com/", "")
    result = subprocess.run(
        ["gh", "api", "-H", "Accept: application/vnd.github+json", path],
        capture_output=True, text=True, timeout=120,
    )
    if result.returncode != 0:
        raise OSError(result.stderr.strip())
    return json.loads(result.stdout)


def version_key(tag: str):
    numbers = re.findall(r"\d+", tag)
    return tuple(int(n) for n in numbers[:4]) if numbers else (0,)


PINNED = {"laravel/framework": os.environ.get("PIN_LARAVEL")}


def latest_tag(repo: str, pinned: str | None = None) -> str:
    if pinned:
        return pinned
    tags = gh_json(f"https://api.github.com/repos/{repo}/tags?per_page=100")
    candidates = [
        t["name"] for t in tags
        if re.match(r"^v?\d+\.\d+(\.\d+)?$", t["name"])
    ]
    if not candidates:
        raise SystemExit(f"no release tag for {repo}")
    return max(candidates, key=version_key)


def download(repo: str, tag: str, target: Path) -> None:
    url = f"https://codeload.github.com/{repo}/tar.gz/refs/tags/{tag}"
    archive = target.parent / f"{repo.replace('/', '_')}.tgz"
    subprocess.run(["curl", "-sSL", url, "-o", str(archive)], check=True, timeout=300)
    with tarfile.open(archive) as tar:
        members = tar.getmembers()
        root = members[0].name.split("/")[0]
        for member in members:
            parts = member.name.split("/", 1)
            if len(parts) < 2 or parts[1] == "":
                continue
            member.name = parts[1]
            tar.extract(member, target)
    archive.unlink()


def main() -> int:
    vendor = Path(sys.argv[1]).resolve()
    vendor.mkdir(parents=True, exist_ok=True)

    queue = ["laravel/framework"]
    seen: dict[str, dict] = {}

    while queue:
        name = queue.pop(0)
        if name in seen:
            continue
        repo = REPOS.get(name)
        if repo is None:
            print(f"  !! no repo mapping for {name}", file=sys.stderr)
            continue
        try:
            tag = latest_tag(repo, PINNED.get(name))
        except (OSError, SystemExit) as error:
            print(f"  ?? cannot resolve {name} ({repo}): {error}", file=sys.stderr)
            seen[name] = {"name": name, "version": "0.0.0"}
            continue
        target = vendor / name
        if target.exists():
            manifest = target / "composer.json"
            data = json.loads(manifest.read_text()) if manifest.exists() else {}
        else:
            target.parent.mkdir(parents=True, exist_ok=True)
            download(repo, tag, target)
            manifest = target / "composer.json"
            data = json.loads(manifest.read_text()) if manifest.exists() else {}
            data.setdefault("name", name)
            data["version"] = tag.lstrip("v")
            (manifest).write_text(json.dumps(data, indent=2))
        seen[name] = data
        for dep in data.get("require", {}) or {}:
            if SKIP.match(dep):
                continue
            print(f"     -> {dep}", flush=True)
            if dep in seen or dep in queue:
                continue
            queue.append(dep)

    print(f"  total {len(seen)}")

    # ---------------------------------------------------------- autoloading
    psr4: dict[str, list[str]] = {}
    files: list[str] = []

    for name, data in seen.items():
        base = vendor / name
        autoload = (data.get("autoload") or {})
        for prefix, paths in (autoload.get("psr-4") or {}).items():
            paths = paths if isinstance(paths, list) else [paths]
            psr4.setdefault(prefix, []).extend(str(base / p) for p in paths)
        for entry in autoload.get("files") or []:
            files.append(str(base / entry))

    manifest_dir = vendor / "composer"
    manifest_dir.mkdir(exist_ok=True)

    (manifest_dir / "autoload_psr4.php").write_text(
        "<?php\nreturn " + php_array(psr4) + ";\n"
    )
    (manifest_dir / "autoload_files.php").write_text(
        "<?php\nreturn " + php_array({("f" + str(i)): v for i, v in enumerate(files)}) + ";\n"
    )

    installed = {"packages": [
        {
            "name": name,
            "version": str(data.get("version", "0.0.0")).lstrip("v"),
            "version_normalized": str(data.get("version", "0.0.0")).lstrip("v"),
            "type": data.get("type", "library"),
            "extra": data.get("extra", {}),
            "install-path": "../" + name,
        }
        for name, data in seen.items()
    ]}
    (manifest_dir / "installed.json").write_text(json.dumps(installed, indent=2))
    (manifest_dir / "installed.php").write_text(
        "<?php return " + php_array({
            "root" : {"name": "store/store", "version": "1.0.0"},
            "versions": {n: {"version": d.get("version", "0.0.0")} for n, d in seen.items()},
        }) + ";\n"
    )

    print(f"resolved {len(seen)} packages")
    return 0


def php_array(data, depth: int = 0) -> str:
    if isinstance(data, str):
        return "'" + data.replace("\\", "\\\\").replace("'", "\\'") + "'"
    if isinstance(data, int):
        return str(data)
    if isinstance(data, list):
        return "[" + ", ".join(php_array(v, depth + 1) for v in data) + "]"
    if isinstance(data, dict):
        inner = ", ".join(f"{php_array(str(k))} => {php_array(v, depth + 1)}" for k, v in data.items())
        return "[" + inner + "]"
    return "null"


if __name__ == "__main__":
    raise SystemExit(main())
