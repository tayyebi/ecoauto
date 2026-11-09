#!/usr/bin/env python3
"""Report packages whose installed version violates a dependency constraint.

DECISION (infra): a green `composer install` is the only proof the lock file is
correct, and Composer is not reachable from every environment this project is
built in. This is the next best check: walk every installed manifest and verify
that each declared requirement is actually met by the version on disk.

Exits non-zero on the first violation, so it can gate a build.
"""
import json
import re
import sys
from pathlib import Path

VENDOR = Path('vendor')

def ver_tuple(v):
    v = v.lstrip('v').split('-')[0]
    parts = []
    for p in v.split('.'):
        try: parts.append(int(p))
        except ValueError: parts.append(0)
    while len(parts) < 3: parts.append(0)
    return tuple(parts[:3])

def satisfies(version, constraint):
    """Very small subset of Composer's constraint language, enough to audit."""
    constraint = constraint.strip()
    if constraint in ('*', '@dev', '@stable', ''):
        return True
    # strip stability flags and "as" aliases
    constraint = re.sub(r'@\w+', '', constraint).strip()
    if ' || ' in constraint or '|' in constraint:
        return any(satisfies(version, c) for c in re.split(r'\s*\|\|?\s*', constraint))
    v = ver_tuple(version)
    for part in constraint.split(','):
        part = part.strip()
        if not part: continue
        m = re.match(r'^(\^|~|>=|<=|>|<|=)?\s*v?([\d.]+)$', part)
        if not m:
            # things like "dev-master" or complex ranges: skip
            continue
        op, num = m.group(1) or '=', m.group(2)
        t = ver_tuple(num)
        if op == '^':
            # ^0.14.2 means >=0.14.2 <0.15.0 ; ^1.0 means >=1.0 <2.0
            if t[0] > 0:   upper = (t[0]+1, 0, 0)
            elif t[1] > 0: upper = (0, t[1]+1, 0)
            else:          upper = (0, 0, t[2]+1)
            if not (v >= t and v < upper): return False
        elif op == '~':
            upper = (t[0], t[1]+1, 0) if len(num.split('.')) > 1 else (t[0]+1, 0, 0)
            if not (v >= t and v < upper): return False
        elif op == '>=':
            if not v >= t: return False
        elif op == '<=':
            if not v <= t: return False
        elif op == '>':
            if not v > t: return False
        elif op == '<':
            if not v < t: return False
        else:
            if not v == t: return False
    return True

installed = {}
for path in VENDOR.glob('*/*/composer.json'):
    d = json.loads(path.read_text())
    if d.get('name'):
        installed[d['name']] = d

print(f"auditing {len(installed)} packages\n")
bad = []
for name, d in sorted(installed.items()):
    version = d.get('version', '0.0.0')
    for dep, constraint in (d.get('require') or {}).items():
        if dep.startswith(('ext-', 'php', 'lib-', 'composer-')): continue
        if dep not in installed: continue
        depv = installed[dep].get('version', '0.0.0')
        if not satisfies(depv, constraint):
            bad.append((name, version, dep, depv, constraint))

if not bad:
    print("no violations found")
for b in bad:
    print(f"  {b[0]} {b[1]} requires {b[2]} {b[4]}  -> have {b[3]}")
print(f"\n{len(bad)} violations")
sys.exit(1 if bad else 0)
