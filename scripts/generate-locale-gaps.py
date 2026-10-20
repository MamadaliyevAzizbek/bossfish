#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
text = (ROOT / 'resources/js/i18n.js').read_text(encoding='utf-8')

def parse_block(name):
    m = re.search(rf'{name}: \{{', text)
    start = m.end()
    depth = 1
    i = start
    while i < len(text) and depth:
        if text[i] == '{':
            depth += 1
        elif text[i] == '}':
            depth -= 1
        i += 1
    block = text[start:i - 1]
    pairs = {}
    for m in re.finditer(r"^\s+(\w+):\s*'((?:\\'|[^'])*)'\s*,?\s*$", block, re.M):
        pairs[m.group(1)] = m.group(2).replace("\\'", "'")
    for m in re.finditer(r'^\s+(\w+):\s*"((?:\\"|[^"])*)"\s*,?\s*$', block, re.M):
        pairs[m.group(1)] = m.group(2).replace('\\"', '"')
    return pairs

uz = parse_block('uz')
ru = parse_block('ru')
cy = parse_block('uz_cyrl')
missing_ru = sorted(set(uz) - set(ru))

REPLACEMENTS = [
    ("O\u2018", "\u040e"), ("o\u2018", "\u045e"),
    ("G\u2018", "\u0403"), ("g\u2018", "\u0493"),
    ("Sh", "\u0428"), ("sh", "\u0448"),
    ("Ch", "\u0427"), ("ch", "\u0447"),
]

LATIN_LOWER = {
    'a': 'а', 'b': 'б', 'd': 'д', 'e': 'е', 'f': 'ф', 'g': 'г', 'h': 'ҳ',
    'i': 'и', 'j': 'ж', 'k': 'к', 'l': 'л', 'm': 'м', 'n': 'н', 'o': 'о',
    'p': 'п', 'q': 'қ', 'r': 'р', 's': 'с', 't': 'т', 'u': 'у', 'v': 'в',
    'x': 'х', 'y': 'й', 'z': 'з',
}
LATIN_UPPER = {k.upper(): v.upper() for k, v in LATIN_LOWER.items()}


def to_cyrl(value):
    if not value:
        return value
    text = value.replace('\u2018', "'").replace('\u2019', "'")
    out = []
    i = 0
    while i < len(text):
        ch = text[i]
        if ch == '{':
            j = text.find('}', i)
            if j == -1:
                out.append(text[i:])
                break
            out.append(text[i:j + 1])
            i = j + 1
            continue
        if i + 1 < len(text):
            pair = text[i:i + 2]
            low = pair.lower()
            if low == 'sh':
                out.append('Ш' if pair[0].isupper() else 'ш')
                i += 2
                continue
            if low == 'ch':
                out.append('Ч' if pair[0].isupper() else 'ч')
                i += 2
                continue
        if ch in 'oOgG' and i + 1 < len(text) and text[i + 1] == "'":
            out.append('Ў' if ch == 'O' else ('ў' if ch == 'o' else ('Ғ' if ch == 'G' else 'ғ')))
            i += 2
            continue
        if ch in LATIN_UPPER:
            out.append(LATIN_UPPER[ch])
        elif ch in LATIN_LOWER:
            out.append(LATIN_LOWER[ch])
        else:
            out.append(ch)
        i += 1
    return ''.join(out)


def is_cyrillic_heavy(value):
    cyr = len(re.findall(r'[\u0400-\u04FF\u0500-\u052F]', value))
    latin = len(re.findall(r'[a-zA-Z]', value))
    return cyr >= 5 and cyr > latin

# Load manual ru translations from JSON if present
ru_manual_path = ROOT / 'scripts/i18n-ru-gaps.json'
ru_manual = {}
if ru_manual_path.exists():
    import json
    ru_manual = json.loads(ru_manual_path.read_text(encoding='utf-8'))

CY_OVERRIDES = {
    'warehouseEquipmentExample': 'Масалан: Аератор, музлатгич',
    'email': 'Email',
    'owner': 'BOSS',
    'fisheryManagement': 'BOSS',
    'ownerCabinet': 'BOSS кабинети',
    'fullControl': 'BOSS назорати',
    'uzbekLatin': 'O‘zbek',
    'printTotal': 'Жами',
    'phLevel': 'pH',
    'homeContactPhone': '+998 77 444 2525',
    'homeContactHours': '24/7',
    'lossFreeBadge': '100%+',
    'printTotal': 'Жами',
}

LATIN_KEEP = re.compile(
    r'^(BOSS|Email|pH|24/7|\+998[\d\s]+|\d+%\+?|←|→|[\d\s+\-/():.,%$#@&*!?]+)$',
    re.I,
)

def is_latin_heavy(value):
    if not value or LATIN_KEEP.match(value.strip()):
        return False
    latin = len(re.findall(r'[a-zA-Z]', value))
    cyr = len(re.findall(r'[\u0400-\u04FF\u0500-\u052F]', value))
    han = len(re.findall(r'[\u4e00-\u9fff]', value))
    if han > latin:
        return False
    return latin >= 4 and latin > cyr

ru_gaps = {key: ru_manual.get(key, ru.get(key, uz[key])) for key in missing_ru}

cy_gaps = {}
for key in sorted(uz):
    existing = cy.get(key, '')
    if is_cyrillic_heavy(existing):
        continue
    if key not in cy or existing == uz[key] or is_latin_heavy(existing):
        cy_gaps[key] = CY_OVERRIDES.get(key, to_cyrl(uz[key]))

zh_path = ROOT / 'scripts/i18n-zh.json'
zh_manual = {}
if zh_path.exists():
    import json
    zh_manual = json.loads(zh_path.read_text(encoding='utf-8'))

zh = {key: zh_manual.get(key, uz[key]) for key in uz}

def js_str(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"

lines = ['// Auto-generated — run: python3 scripts/generate-locale-gaps.py', 'export const ruGaps = {']
for key in sorted(ru_gaps):
    lines.append(f'  {key}: {js_str(ru_gaps[key])},')
lines.append('};')
lines.append('')
lines.append('export const uzCyrlGaps = {')
for key in sorted(cy_gaps):
    lines.append(f'  {key}: {js_str(cy_gaps[key])},')
lines.append('};')
lines.append('')
lines.append('export const zhMessages = {')
for key in sorted(zh):
    lines.append(f'  {key}: {js_str(zh[key])},')
lines.append('};')

out = ROOT / 'resources/js/i18n/locale-gaps.js'
out.parent.mkdir(parents=True, exist_ok=True)
out.write_text('\n'.join(lines) + '\n', encoding='utf-8')
print(f'Wrote {out} — ru:{len(ru_gaps)} cy:{len(cy_gaps)} zh:{len(zh)}')
