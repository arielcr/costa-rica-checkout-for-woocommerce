#!/usr/bin/env python3
"""
Builds data/cr-locations.json from the official source:

  División Territorial Administrativa de la República de Costa Rica 2025,
  Decreto Ejecutivo N.° 44882-MGP, Alcance N.° 12 a La Gaceta N.° 17 del 28 de enero de 2025.
  https://www.imprentanacional.go.cr/pub/2025/01/28/ALCA12_28_01_2025.pdf

Usage (needs pdftotext from poppler):

  curl -o /tmp/alcance12.pdf https://www.imprentanacional.go.cr/pub/2025/01/28/ALCA12_28_01_2025.pdf
  pdftotext -layout /tmp/alcance12.pdf /tmp/alcance12.txt
  python3 bin/extract-dta-2025.py /tmp/alcance12.txt > data/cr-locations.json

Development tool only; it is not shipped in the plugin zip.
"""
import json
import re
import sys

SOURCE = (
    'División Territorial Administrativa de la República de Costa Rica 2025, '
    'Decreto Ejecutivo N.° 44882-MGP, Alcance N.° 12 a La Gaceta N.° 17 del 28 de enero de 2025'
)
SOURCE_URL = 'https://www.imprentanacional.go.cr/pub/2025/01/28/ALCA12_28_01_2025.pdf'

# WooCommerce state codes for Costa Rica (WC()->countries->get_states( 'CR' )).
WC_STATES = {'1': 'CR-SJ', '2': 'CR-A', '3': 'CR-C', '4': 'CR-H', '5': 'CR-G', '6': 'CR-P', '7': 'CR-L'}

PARTICLES = {'de', 'del', 'la', 'las', 'los', 'el', 'y', 'e', 'o'}
# Names where the article is part of a proper name.
EXCEPTIONS = {
    'SAN ISIDRO DE EL GENERAL': 'San Isidro de El General',
    'VALLE LA ESTRELLA': 'Valle La Estrella',
}

PROVINCE_RE = re.compile(r'^\s*PROVINCIA DE\s+(.+?)\s*$')
CANTON_RE = re.compile(r'^\s*CANT[ÓO]N\s+(\d)(\d{2})\s*:\s*(.+?)\s*$')
# "101 01 CARMEN: …". The source has one typo ("205 06: SAN JOSÉ:"), hence the optional colon.
DISTRICT_RE = re.compile(r'^\s*(\d)(\d{2})\s+(\d{2}):?\s+([^:]+?):')


def proper_case(name: str) -> str:
    name = ' '.join(name.split())
    if name in EXCEPTIONS:
        return EXCEPTIONS[name]
    words = name.split(' ')
    out = []
    for i, word in enumerate(words):
        lower = word.lower()
        out.append(lower if i > 0 and lower in PARTICLES else word[:1].upper() + word[1:].lower())
    return ' '.join(out)


def main(path: str) -> None:
    lines = open(path, encoding='utf-8').read().split('\n')
    start = next(i for i, line in enumerate(lines) if 'N° 44882' in line)
    end = next(i for i, line in enumerate(lines) if 'D44882-' in line)

    provinces: dict = {}
    current = None
    for line in lines[start:end]:
        match = PROVINCE_RE.match(line)
        if match:
            current = match.group(1)
            continue
        match = CANTON_RE.match(line)
        if match:
            province, canton, name = match.groups()
            provinces.setdefault(province, {'name': current, 'cantons': {}})
            provinces[province]['cantons'][canton] = {'name': name, 'districts': {}}
            continue
        match = DISTRICT_RE.match(line)
        if match:
            province, canton, district, name = match.groups()
            provinces[province]['cantons'][canton]['districts'][district] = name

    data = {
        'source': SOURCE,
        'source_url': SOURCE_URL,
        'postcode': 'Five digits: province (1) + canton (2) + district (2), e.g. 10101 for Carmen, San José.',
        'counts': {
            'provinces': len(provinces),
            'cantons': sum(len(p['cantons']) for p in provinces.values()),
            'districts': sum(len(c['districts']) for p in provinces.values() for c in p['cantons'].values()),
        },
        'provinces': [
            {
                'code': p_code,
                'wc_state': WC_STATES[p_code],
                'name': proper_case(p['name']),
                'cantons': [
                    {
                        'code': c_code,
                        'name': proper_case(c['name']),
                        'districts': [
                            {'code': d_code, 'name': proper_case(d_name)}
                            for d_code, d_name in sorted(c['districts'].items())
                        ],
                    }
                    for c_code, c in sorted(p['cantons'].items())
                ],
            }
            for p_code, p in sorted(provinces.items())
        ],
    }
    json.dump(data, sys.stdout, ensure_ascii=False, indent='\t')
    sys.stdout.write('\n')


if __name__ == '__main__':
    main(sys.argv[1])
