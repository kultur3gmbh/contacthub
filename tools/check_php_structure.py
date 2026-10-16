#!/usr/bin/env python3
"""Offline structural PHP check used only when the PHP CLI is unavailable."""

from pathlib import Path
import sys


def check(path: Path) -> str:
    source = path.read_text(encoding="utf-8")
    stack = []
    pairs = {")": "(", "]": "[", "}": "{"}
    state = "code"
    quote = ""
    line = 1
    start_line = 1
    index = 0

    while index < len(source):
        char = source[index]
        following = source[index + 1] if index + 1 < len(source) else ""
        if char == "\n":
            line += 1

        if state == "code":
            if char in "'\"":
                state, quote, start_line = "string", char, line
            elif char == "/" and following == "/":
                state, index = "line_comment", index + 1
            elif char == "#":
                state = "line_comment"
            elif char == "/" and following == "*":
                state, start_line, index = "block_comment", line, index + 1
            elif char in "([{":
                stack.append((char, line))
            elif char in ")]}" :
                if not stack or stack[-1][0] != pairs[char]:
                    return f"{path}:{line}: unmatched {char}"
                stack.pop()
            elif char == "\\" and following == "\\":
                return f"{path}:{line}: duplicated PHP namespace separator"
        elif state == "string":
            if char == "\\":
                index += 1
            elif char == quote:
                state = "code"
        elif state == "line_comment":
            if char == "\n":
                state = "code"
        elif state == "block_comment" and char == "*" and following == "/":
            state, index = "code", index + 1
        index += 1

    if state in {"string", "block_comment"}:
        return f"{path}:{start_line}: unterminated {state}"
    if stack:
        char, open_line = stack[-1]
        return f"{path}:{open_line}: unclosed {char}"
    return ""


errors = []
for argument in sys.argv[1:]:
    candidate = Path(argument)
    paths = candidate.rglob("*.php") if candidate.is_dir() else [candidate]
    for php_file in paths:
        error = check(php_file)
        if error:
            errors.append(error)

if errors:
    print("\n".join(errors))
    raise SystemExit(1)

print("PHP structure check passed")

