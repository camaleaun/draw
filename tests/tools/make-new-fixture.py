#!/usr/bin/env python3
"""Writes tests/golden/markup/<name>.new.html from tests/golden/<name>.json by the table of docs/block-markup.md (the oracle of the format: it does not use the package)."""
import json, sys

BLOCKS = {'text': 'draw/text', 'rect': 'draw/rect', 'ellipse': 'draw/ellipse', 'line': 'draw/line', 'image': 'draw/image', 'group': 'draw/group', 'verification': 'events-certificates/certificate-verification'}
H = {'start': 'left', 'center': 'center', 'end': 'right', 'between': 'space-between'}
V = {'start': 'top', 'center': 'center', 'end': 'bottom', 'between': 'space-between'}

def num(n):
    return str(int(n)) if float(n) == int(n) else repr(float(n))

def pt(v):
    return num(v) + 'pt' if isinstance(v, (int, float)) and not isinstance(v, bool) else v

def style(out, *path_value):
    *path, value = path_value
    node = out.setdefault('style', {})
    for key in path[:-1]:
        node = node.setdefault(key, {})
    node[path[-1]] = value

def attrs(el):
    out = {}
    t = el['type']
    for key, value in el.items():
        if key in ('type', 'children', 'runs', 'variants') or key.startswith('_'):
            continue
        if key == 'id':
            if not (t == 'group' or '-' in value and value.rsplit('-', 1)[1].isdigit() and value.split('-')[0] in BLOCKS):
                out['anchor'] = value
        elif key == 'name':
            out.setdefault('metadata', {})['name'] = value
        elif key == 'hidden':
            out.setdefault('metadata', {})['blockVisibility'] = False
        elif key == 'locked':
            out['lock'] = {'move': True, 'remove': True}
        elif key == 'lock':
            style(out, 'dimensions', 'aspectRatio', num(value))
        elif key in ('x', 'y', 'w', 'h', 'x1', 'y1', 'x2', 'y2'):
            out[{'w': 'width', 'h': 'height'}.get(key, key)] = pt(value)
        elif key == 'layout':
            out['layout'] = {{'h': 'horizontal', 'v': 'vertical', 'ref': 'reference'}.get(k, k): v for k, v in value.items()}
        elif key == 'font':
            out['fontFamily'] = value.lower().replace(' ', '-')
        elif key == 'weight':
            style(out, 'typography', 'fontWeight', str(value))
        elif key == 'size':
            style(out, 'typography', 'fontSize', pt(value))
        elif key == 'lh':
            style(out, 'typography', 'lineHeight', num(value) if isinstance(value, (int, float)) else value)
        elif key == 'ls':
            style(out, 'typography', 'letterSpacing', pt(value))
        elif key == 'align':
            if t == 'group':
                continue
            style(out, 'typography', 'textAlign', value)
        elif key == 'upper':
            style(out, 'typography', 'textTransform', 'uppercase')
        elif key == 'color':
            style(out, 'color', 'text', value)
        elif key == 'fit' and t == 'text':
            out['fitText'] = True
        elif key == 'fit' and t == 'image':
            out['scale'] = value
        elif key == 'min':
            out['minFontSize'] = pt(value)
        elif key == 'fill':
            style(out, 'color', 'background', value)
        elif key == 'stroke':
            style(out, 'border', 'color', value)
        elif key == 'strokeWidth':
            style(out, 'border', 'width', pt(value))
        elif key == 'dash':
            style(out, 'border', 'style', value)
        elif key == 'radius':
            if len(set(map(str, value))) == 1:
                style(out, 'border', 'radius', pt(value[0]))
            else:
                style(out, 'border', 'radius', dict(zip(['topLeft', 'topRight', 'bottomRight', 'bottomLeft'], [pt(v) if v != 0 else '0pt' for v in value])))
        elif key == 'arrange':
            if t == 'group':
                a = {'type': 'grid' if value == 'grid' else 'flex'}
                if value in ('row', 'stack'):
                    a['orientation'] = 'horizontal' if value == 'row' else 'vertical'
                out['arrange'] = a
            else:
                out['arrange'] = value
        elif key in ('justify',):
            pass
        elif key == 'gap':
            style(out, 'spacing', 'blockGap', pt(value))
        elif key == 'padding':
            style(out, 'spacing', 'padding', pt(value))
        elif key == 'src':
            out['url'] = value
        elif key == 'attachment':
            out['id'] = value
        elif key == 'ratio':
            out['aspectRatio'] = value
        elif key == 'qr':
            out['qr'] = pt(value)
        elif key in ('code', 'hint'):
            o = {'typography': {}, 'color': {}}
            for k, v in value.items():
                if k == 'size': o['typography']['fontSize'] = pt(v)
                elif k == 'weight': o['typography']['fontWeight'] = str(v)
                elif k == 'lh': o['typography']['lineHeight'] = num(v)
                elif k == 'color': o['color']['text'] = v
            out[key] = o
        else:
            out[key] = value
    if t == 'text':
        out['text'] = el['_text']
        if el.get('variants'):
            out['textVariants'] = [el['_variants']]
    if t == 'group':
        j, a = el.get('justify'), el.get('align')
        arr = out['arrange']
        if arr['type'] == 'flex' and arr.get('orientation') == 'horizontal':
            if j: arr['justifyContent'] = H[j]
            if a: arr['verticalAlignment'] = V[a]
        elif arr['type'] == 'flex':
            if a: arr['justifyContent'] = H[a]
            if j: arr['verticalAlignment'] = V[j]
        else:
            if el.get('columns'): arr['columnCount'] = el['columns']
    return out

def text_of(runs):
    s = ''
    for r in runs:
        piece = '[[' + r['field'] + ']]' if 'field' in r else r['text']
        if r.get('b'): piece = '**' + piece + '**'
        if r.get('i'): piece = '*' + piece + '*'
        s += piece
    return s

def prepare(el):
    el = dict(el)
    if 'runs' in el:
        el['_text'] = text_of(el['runs'])
    if el.get('variants'):
        el['_variants'] = {k: text_of(v) for k, v in el['variants'].items()}
    return el

def block(el, depth):
    pad = '\t' * depth
    el = prepare(el)
    name = BLOCKS[el['type']]
    a = json.dumps(attrs(el), separators=(',', ':'), ensure_ascii=False)
    if el['type'] == 'group':
        inner = '\n'.join(block(c, depth + 1) for c in el['children'])
        return f'{pad}<!-- wp:{name} {a} -->\n{inner}\n{pad}<!-- /wp:{name} -->'
    return f'{pad}<!-- wp:{name} {a} /-->'

name = sys.argv[1]
design = json.load(open(f'tests/golden/{name}.json'))
root = {k: v for k, v in design.items() if k != 'elements'}
body = '\n'.join(block(e, 1) for e in design['elements'])
open(f'tests/golden/markup/{name}.new.html', 'w').write(
    '<!-- wp:events-certificates/certificate ' + json.dumps(root, separators=(',', ':'), ensure_ascii=False) + ' -->\n' + body + '\n<!-- /wp:events-certificates/certificate -->\n')
