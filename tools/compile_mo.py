#!/usr/bin/env python3
"""Compile a gettext PO catalog to MO using only the Python standard library."""

import ast
import re
import struct
import sys
from pathlib import Path


def parse_po(path):
    entries = []
    entry = {}
    field = None
    fuzzy = False

    def flush():
        nonlocal entry, field, fuzzy
        if entry and not fuzzy and "msgid" in entry:
            key = entry["msgid"]
            if "msgid_plural" in entry:
                key += "\0" + entry["msgid_plural"]
                values = []
                index = 0
                while f"msgstr[{index}]" in entry:
                    values.append(entry[f"msgstr[{index}]"])
                    index += 1
                value = "\0".join(values)
            else:
                value = entry.get("msgstr", "")
            if "msgctxt" in entry:
                key = entry["msgctxt"] + "\x04" + key
            entries.append((key, value))
        entry = {}
        field = None
        fuzzy = False

    for raw_line in Path(path).read_text(encoding="utf-8").splitlines() + [""]:
        line = raw_line.strip()
        if not line:
            flush()
            continue
        if line.startswith("#,") and "fuzzy" in line:
            fuzzy = True
            continue
        if line.startswith("#"):
            continue
        match = re.match(
            r'(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+(".*")$',
            line,
        )
        if match:
            field = match.group(1)
            entry[field] = ast.literal_eval(match.group(2))
            continue
        if line.startswith('"') and field:
            entry[field] += ast.literal_eval(line)

    return dict(entries)


def compile_catalog(po_path, mo_path):
    messages = parse_po(po_path)
    keys = sorted(messages)
    identifiers = b""
    translations = b""
    offsets = []

    for key in keys:
        encoded_key = key.encode("utf-8")
        encoded_value = messages[key].encode("utf-8")
        offsets.append(
            (
                len(encoded_key),
                len(identifiers),
                len(encoded_value),
                len(translations),
            )
        )
        identifiers += encoded_key + b"\0"
        translations += encoded_value + b"\0"

    count = len(keys)
    key_start = 7 * 4 + 16 * count
    value_start = key_start + len(identifiers)
    output = struct.pack("<7I", 0x950412DE, 0, count, 7 * 4, 7 * 4 + 8 * count, 0, 0)
    output += b"".join(
        struct.pack("<2I", length, key_start + offset)
        for length, offset, _, _ in offsets
    )
    output += b"".join(
        struct.pack("<2I", length, value_start + offset)
        for _, _, length, offset in offsets
    )
    output += identifiers + translations
    Path(mo_path).write_bytes(output)
    print(f"{po_path}: {count} messages -> {mo_path}")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("Usage: compile_mo.py INPUT.po OUTPUT.mo")
    compile_catalog(sys.argv[1], sys.argv[2])
