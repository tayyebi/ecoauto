#!/usr/bin/env python3
"""Import the legacy Hugo content tree into the store seed snapshot.

The legacy site (ecoauto.ir) was a Hugo static site. This script converts its
`content/` directory into the JSON snapshot consumed by the tenant seeder, so the
original data is reproducible without keeping Hugo around.

Usage:
    python3 tools/import-hugo.py --source /path/to/hugo/checkout \
        --out database/seeders/data/ecoauto

The legacy tree lives at git commit 876b6f2 (`git archive 876b6f2 content | tar -x`).
Requires: pyyaml.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

try:
    import yaml
except ImportError:  # pragma: no cover
    sys.exit("pyyaml is required: pip install pyyaml")


FRONT_MATTER = re.compile(r"^---\s*\n(.*?)\n---\s*\n?(.*)$", re.S)


def read_markdown(path: Path) -> tuple[dict, str]:
    raw = path.read_text(encoding="utf-8")
    match = FRONT_MATTER.match(raw)
    if not match:
        return {}, raw
    data = yaml.safe_load(match.group(1)) or {}
    if not isinstance(data, dict):
        data = {}
    return data, match.group(2)


def slug_of(path: Path) -> str:
    return path.stem


def as_list(value) -> list[str]:
    if value is None:
        return []
    if isinstance(value, str):
        return [value] if value.strip() else []
    return [str(item) for item in value if str(item).strip()]


def as_int(value):
    """Extract an integer price, tolerating '1,959,000' or '19590000 ریال'."""
    if value is None:
        return None
    if isinstance(value, (int, float)):
        return int(value)
    digits = re.sub(r"[^0-9]", "", str(value))
    return int(digits) if digits else None


def cleaned(value):
    """Normalise the empty-ish placeholders Hugo content used."""
    if value is None:
        return None
    text = str(value).strip()
    if text in {"", "-", "—", "N/A"}:
        return None
    return text


def looks_like_html(body: str) -> bool:
    return bool(re.search(r"<(p|h[1-6]|ul|ol|li|div|span|hr|table|strong)\b", body))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", required=True, help="Hugo project root")
    parser.add_argument("--out", required=True, help="Seed data output directory")
    args = parser.parse_args()

    source = Path(args.source).resolve()
    content = source / "content"
    out = Path(args.out).resolve()
    if not content.is_dir():
        return sys.exit(f"no content/ directory under {source}")

    out.mkdir(parents=True, exist_ok=True)

    # ---------------------------------------------------------------- categories
    categories = []
    for path in sorted((content / "categories").glob("*/_index.md")):
        data, _ = read_markdown(path)
        categories.append(
            {
                "slug": path.parent.name,
                "title": data.get("title") or path.parent.name,
                "description": cleaned(data.get("description")),
                "parent": cleaned(data.get("parent")),
                "icon": cleaned(data.get("icon")),
            }
        )

    known_categories = {category["slug"] for category in categories}

    # ------------------------------------------------------------------- vendors
    vendors = []
    for path in sorted((content / "vendors").glob("*.md")):
        if path.name.startswith("_"):
            continue
        data, body = read_markdown(path)
        body = body.strip()
        vendors.append(
            {
                "slug": slug_of(path),
                "title": (data.get("title") or slug_of(path)).strip(),
                # Vendor bodies were plain text blobs, not markup.
                "description": cleaned(data.get("description")),
                "body": body or None,
                "body_is_html": looks_like_html(body),
                "email": cleaned(data.get("email")),
                "phone": cleaned(data.get("phone")),
                "website": cleaned(data.get("website")),
            }
        )

    known_vendors = {vendor["slug"] for vendor in vendors}

    # ------------------------------------------------------------------ products
    products = []
    missing_vendors: set[str] = set()
    missing_categories: set[str] = set()
    for path in sorted((content / "products").glob("*.md")):
        if path.name.startswith("_"):
            continue
        data, body = read_markdown(path)

        product_vendors = [v for v in as_list(data.get("vendors")) if v]
        product_categories = [c for c in as_list(data.get("categories")) if c]
        missing_vendors.update(v for v in product_vendors if v not in known_vendors)
        missing_categories.update(c for c in product_categories if c not in known_categories)

        amount = as_int(data.get("price_amount"))
        if not amount:
            # Older entries stored the price as a plain `price:` string.
            amount = as_int(data.get("price"))
        if amount is not None and amount <= 0:
            amount = None

        products.append(
            {
                "slug": slug_of(path),
                "title": (data.get("title") or slug_of(path)).strip(),
                "description": cleaned(data.get("description")),
                "body": body.strip() or None,
                "sku": cleaned(data.get("sku")),
                "brand": cleaned(data.get("brand")),
                "category_label": cleaned(data.get("category")),
                "country_of_origin": cleaned(data.get("country_of_origin")),
                "material": cleaned(data.get("material")),
                "material_en": cleaned(data.get("material_en")),
                "gtin": cleaned(data.get("gtin")),
                "price": amount,
                "currency": cleaned(data.get("price_currency")) or "IRR",
                "image": cleaned(data.get("image")),
                "categories": product_categories,
                "vendors": product_vendors,
            }
        )

    # ---------------------------------------------------------------- blog posts
    posts = []
    missing_post_categories: set[str] = set()
    for path in sorted((content / "blog").glob("*.md")):
        if path.name.startswith("_"):
            continue
        data, body = read_markdown(path)
        post_categories = [c for c in as_list(data.get("categories")) if c]
        missing_post_categories.update(c for c in post_categories if c not in known_categories)

        published = data.get("date")
        posts.append(
            {
                "slug": slug_of(path),
                "title": (data.get("title") or slug_of(path)).strip(),
                "description": cleaned(data.get("description")),
                "body": body.strip() or None,
                "published_at": published.isoformat() if hasattr(published, "isoformat") else cleaned(published),
                "categories": post_categories,
            }
        )

    # --------------------------------------------------------------------- pages
    pages = []
    for name, slug in (("about.md", "about"), ("contact.md", "contact"), ("sitemap.md", "sitemap")):
        path = content / name
        if not path.exists():
            continue
        data, body = read_markdown(path)
        pages.append(
            {
                "slug": slug,
                "title": (data.get("title") or slug).strip(),
                "description": cleaned(data.get("description")),
                "body": body.strip() or None,
            }
        )

    home_data, _ = read_markdown(content / "_index.md") if (content / "_index.md").exists() else ({}, "")
    section_titles = {}
    for section in ("blog", "products", "vendors", "categories"):
        index = content / section / "_index.md"
        if index.exists():
            data, _ = read_markdown(index)
            section_titles[section] = {
                "title": data.get("title"),
                "description": cleaned(data.get("description")),
            }

    snapshot = {
        "meta": {
            "source": "legacy Hugo site",
            "legacy_commit": "876b6f2",
            "counts": {
                "categories": len(categories),
                "vendors": len(vendors),
                "products": len(products),
                "posts": len(posts),
                "pages": len(pages),
            },
        },
        "site": {"title": home_data.get("title"), "description": cleaned(home_data.get("description"))},
        "sections": section_titles,
        "categories": categories,
        "vendors": vendors,
        "products": products,
        "posts": posts,
        "pages": pages,
    }

    (out / "content.json").write_text(
        json.dumps(snapshot, ensure_ascii=False, indent=2, sort_keys=False) + "\n", encoding="utf-8"
    )

    if missing_vendors:
        print(f"warn: {len(missing_vendors)} product→vendor references unresolved", file=sys.stderr)
    if missing_categories:
        print(f"warn: {len(missing_categories)} product→category references unresolved", file=sys.stderr)
    if missing_post_categories:
        print(f"warn: {len(missing_post_categories)} post→category references unresolved", file=sys.stderr)

    print(f"wrote {out / 'content.json'}")
    print(json.dumps(snapshot["meta"]["counts"], indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
