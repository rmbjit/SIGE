#!/usr/bin/env python3
"""
SIGE SoftGenial - Normalizador Óptico de Ícones (build-time)

Resolve a inconsistência de tamanho aparente dos ícones SVG: cada ficheiro
era desenhado preenchendo uma fracção diferente do viewBox, pelo que
pareciam de tamanhos diferentes mesmo à mesma caixa em px (dispersão de
diagonal medida: 8.2px no original).

Este utilitário mede a bounding box REAL de cada ícone (via render) e
reescala/centra o conteúdo num <g transform> para que TODOS tenham a mesma
DIAGONAL aparente (~21 de 24), com stroke-width compensado pela escala para
manter a espessura óptica constante. A tag <svg> raiz fica com atributos
canónicos (24x24, fill none, stroke currentColor).

Requer: cairosvg, pillow, numpy.
Uso:
  python3 tools/normalizar-icones.py            (simula, mostra dispersão)
  python3 tools/normalizar-icones.py --apply     (grava os SVG normalizados)

Idempotente: se já normalizados, reaplica o mesmo resultado.
"""
import cairosvg, glob, os, io, re, sys
from PIL import Image
import numpy as np

VB = 24.0
RENDER = 240
DIAG_ALVO = 21.0   # diagonal-alvo do desenho em unidades de viewBox

def inner(svg):
    m = re.search(r'<svg[^>]*>(.*)</svg>', svg, re.S)
    return m.group(1).strip() if m else ''

def bbox(svg_text):
    svg = svg_text.replace('currentColor', '#000000')
    png = cairosvg.svg2png(bytestring=svg.encode(), output_width=RENDER, output_height=RENDER, background_color='white')
    arr = np.array(Image.open(io.BytesIO(png)).convert('L'))
    mask = arr < 200
    if not mask.any():
        return None
    ys, xs = mask.nonzero()
    f = RENDER / VB
    return xs.min()/f, ys.min()/f, xs.max()/f, ys.max()/f

def diagonal_render(svg_text):
    bb = bbox(svg_text)
    if not bb:
        return None
    x0, y0, x1, y1 = bb
    return ((x1-x0)**2 + (y1-y0)**2) ** 0.5

def main():
    aplicar = '--apply' in sys.argv
    raiz = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    pasta = os.path.join(raiz, 'assets/icons/sg')
    diags = {}
    for f in sorted(glob.glob(f'{pasta}/*.svg')):
        nome = os.path.basename(f)[:-4]
        txt = open(f).read()
        bb = bbox(txt)
        if not bb:
            print(f"  {nome}: bbox vazia, ignorado")
            continue
        x0, y0, x1, y1 = bb
        w = x1-x0; h = y1-y0
        diag = (w*w + h*h) ** 0.5
        if diag <= 0:
            continue
        escala = DIAG_ALVO / diag
        cx = (x0+x1)/2; cy = (y0+y1)/2
        tx = VB/2 - cx*escala
        ty = VB/2 - cy*escala
        sw = round(2.0/escala, 3)
        g = (f'<g transform="translate({tx:.4f} {ty:.4f}) scale({escala:.5f})" '
             f'stroke-width="{sw}">{inner(txt)}</g>')
        novo = (f'<svg class="sg-svg-icon sg-svg-icon-{nome}" xmlns="http://www.w3.org/2000/svg" '
                f'width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
                f'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{g}</svg>')
        if aplicar:
            open(f, 'w').write(novo)
        # medir resultado
        d2 = diagonal_render(novo) if aplicar else diag*escala
        diags[nome] = round(d2, 2)

    vals = sorted(diags.values())
    disp = round(max(vals)-min(vals), 2) if vals else 0
    print(f"{'APLICADO' if aplicar else 'SIMULAÇÃO'}: {len(diags)} ícones | diagonal min {min(vals)} máx {max(vals)} dispersão {disp}px")
    return 0

if __name__ == '__main__':
    sys.exit(main())
