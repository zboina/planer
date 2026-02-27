import { Controller } from '@hotwired/stimulus';
import * as fabric from 'fabric';

const CANVAS_W = 794;
const CANVAS_H = 1123;
const PX_TO_PT = 0.75; // 96 DPI → 72 DPI
const PT_TO_PX = 1 / PX_TO_PT; // 72 DPI → 96 DPI

export default class extends Controller {
    static values = {
        canvasJson: String,
        placeholders: Array,
        previewUrl: String,
        previewNewUrl: String,
        szablonId: Number,
        legacy: String,
    };

    static targets = [
        'canvas', 'trescHtml', 'canvasJson', 'sidebar',
        'placeholderSelect', 'fontSize', 'fontColor', 'fillColor',
        'propPanel', 'propX', 'propY', 'propW', 'propH', 'propAngle', 'propOpacity',
        'legacyMode', 'canvasMode', 'legacyTextarea',
        'pdfFile', 'bgOpacity', 'bgOpacityValue', 'bgControls',
        'fullscreenContainer',
    ];

    connect() {
        this._isLegacyMode = this.legacyValue === '1';

        if (!this._isLegacyMode) {
            this._initCanvas();
            this._loadState();
            this._bindCanvasEvents();
            this._bindKeyboard();
        }

        const form = this.element.closest('form');
        if (form) {
            form.addEventListener('submit', () => {
                if (this._isLegacyMode && this.hasLegacyTextareaTarget && this.hasTrescHtmlTarget) {
                    this.trescHtmlTarget.value = this.legacyTextareaTarget.value;
                }
            });
        }
    }

    disconnect() {
        if (this._fc) this._fc.dispose();
    }

    // ── Canvas init ──────────────────────────────────────────────

    _initCanvas() {
        this._fc = new fabric.Canvas(this.canvasTarget, {
            width: CANVAS_W, height: CANVAS_H,
            backgroundColor: '#ffffff', selection: true,
        });

        const border = new fabric.Rect({
            left: 0, top: 0, width: CANVAS_W, height: CANVAS_H,
            fill: 'transparent', stroke: '#dee2e6', strokeWidth: 1,
            selectable: false, evented: false,
        });
        border._isPageBorder = true;
        this._fc.add(border);
        this._fc.sendObjectToBack(border);
    }

    _loadState() {
        const json = this.canvasJsonValue;
        if (!json) return;
        try {
            this._fc.loadFromJSON(JSON.parse(json)).then(() => {
                this._fc.getObjects().forEach(obj => {
                    if (obj.type === 'rect' && obj.width === CANVAS_W && obj.height === CANVAS_H && obj.left === 0 && obj.top === 0) {
                        obj._isPageBorder = true;
                        obj.selectable = false;
                        obj.evented = false;
                    }
                });
                this._fc.renderAll();
            });
        } catch (e) { console.warn('Failed to load canvas JSON:', e); }
    }

    _bindCanvasEvents() {
        ['object:modified', 'object:added', 'object:removed', 'text:changed'].forEach(evt => {
            this._fc.on(evt, () => this._syncState());
        });
        this._fc.on('selection:created', () => this._onSelect());
        this._fc.on('selection:updated', () => this._onSelect());
        this._fc.on('selection:cleared', () => this._onDeselect());
    }

    _bindKeyboard() {
        document.addEventListener('keydown', (e) => {
            if (!this._fc) return;
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

            if (e.key === 'Delete' || e.key === 'Backspace') {
                const active = this._fc.getActiveObject();
                if (active && active.isEditing) return;
                e.preventDefault();
                this.deleteSelected();
            }
            if (e.ctrlKey && e.key === 'b') { e.preventDefault(); this.toggleBold(); }
            if (e.ctrlKey && e.key === 'i') { e.preventDefault(); this.toggleItalic(); }
        });
    }

    // ── Prevent Enter from submitting form ───────────────────────

    preventSubmit(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.target.blur();
        }
    }

    // ── Selection / Property panel ───────────────────────────────

    _onSelect() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        this._showProps(obj);
    }

    _onDeselect() {
        if (this.hasPropPanelTarget) this.propPanelTarget.classList.add('d-none');
    }

    _showProps(obj) {
        if (!this.hasPropPanelTarget) return;
        this.propPanelTarget.classList.remove('d-none');

        const sx = obj.scaleX || 1, sy = obj.scaleY || 1;

        if (this.hasFontSizeTarget && obj.fontSize !== undefined) this.fontSizeTarget.value = Math.round(obj.fontSize);
        if (this.hasFontColorTarget) this.fontColorTarget.value = obj.fill || '#000000';
        if (this.hasFillColorTarget) {
            this.fillColorTarget.value = (obj.type === 'rect' && obj.fill && obj.fill !== 'transparent') ? obj.fill : '#ffffff';
        }
        if (this.hasPropXTarget) this.propXTarget.value = Math.round(obj.left || 0);
        if (this.hasPropYTarget) this.propYTarget.value = Math.round(obj.top || 0);
        if (this.hasPropWTarget) this.propWTarget.value = Math.round((obj.width || 0) * sx);
        if (this.hasPropHTarget) this.propHTarget.value = Math.round((obj.height || 0) * sy);
        if (this.hasPropAngleTarget) this.propAngleTarget.value = Math.round(obj.angle || 0);
        if (this.hasPropOpacityTarget) this.propOpacityTarget.value = Math.round((obj.opacity ?? 1) * 100);
    }

    // ── Toolbar actions: Add objects ─────────────────────────────

    addText() {
        const t = new fabric.Textbox('Wpisz tekst...', {
            left: 50, top: 50, width: 300, fontSize: 12,
            fontFamily: 'DejaVu Sans', fill: '#000000', editable: true,
        });
        this._fc.add(t);
        this._fc.setActiveObject(t);
        this._fc.renderAll();
    }

    addPlaceholder() {
        if (!this.hasPlaceholderSelectTarget) return;
        const val = this.placeholderSelectTarget.value;
        if (!val) return;
        const t = new fabric.Textbox(val, {
            left: 50, top: 50, width: 350, fontSize: 12,
            fontFamily: 'DejaVu Sans', fill: '#1971c2', editable: true,
        });
        t.isPlaceholder = true;
        this._fc.add(t);
        this._fc.setActiveObject(t);
        this._fc.renderAll();
    }

    addLine() {
        const l = new fabric.Line([50, 200, 550, 200], { stroke: '#000000', strokeWidth: 1 });
        this._fc.add(l);
        this._fc.setActiveObject(l);
        this._fc.renderAll();
    }

    addRect() {
        const r = new fabric.Rect({
            left: 50, top: 50, width: 200, height: 100,
            fill: 'transparent', stroke: '#000000', strokeWidth: 1,
        });
        this._fc.add(r);
        this._fc.setActiveObject(r);
        this._fc.renderAll();
    }

    deleteSelected() {
        const active = this._fc.getActiveObjects();
        if (!active.length) return;
        active.forEach(obj => { if (!obj._isPageBorder) this._fc.remove(obj); });
        this._fc.discardActiveObject();
        this._fc.renderAll();
    }

    // ── Toolbar actions: Formatting ──────────────────────────────

    toggleBold() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('fontWeight', obj.fontWeight === 'bold' ? 'normal' : 'bold');
        this._fc.renderAll(); this._syncState();
    }

    toggleItalic() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('fontStyle', obj.fontStyle === 'italic' ? 'normal' : 'italic');
        this._fc.renderAll(); this._syncState();
    }

    updateFontSize() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.fontSize === undefined) return;
        const size = parseInt(this.fontSizeTarget.value, 10);
        if (size > 0 && size <= 200) {
            obj.set('fontSize', size);
            this._fc.renderAll(); this._syncState();
        }
    }

    updateFontColor() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        const color = this.fontColorTarget.value;
        if (obj.type === 'textbox') obj.set('fill', color);
        else obj.set('stroke', color);
        this._fc.renderAll(); this._syncState();
    }

    updateFillColor() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        const color = this.fillColorTarget.value;
        if (obj.type === 'rect') {
            obj.set('fill', color === '#ffffff' ? 'transparent' : color);
        } else if (obj.type === 'textbox') {
            obj.set('backgroundColor', color === '#ffffff' ? '' : color);
        }
        this._fc.renderAll(); this._syncState();
    }

    alignLeft() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('textAlign', 'left'); this._fc.renderAll(); this._syncState();
    }
    alignCenter() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('textAlign', 'center'); this._fc.renderAll(); this._syncState();
    }
    alignRight() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('textAlign', 'right'); this._fc.renderAll(); this._syncState();
    }

    // ── Property panel actions ───────────────────────────────────

    updatePropX() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        obj.set('left', parseInt(this.propXTarget.value, 10) || 0);
        obj.setCoords(); this._fc.renderAll(); this._syncState();
    }
    updatePropY() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        obj.set('top', parseInt(this.propYTarget.value, 10) || 0);
        obj.setCoords(); this._fc.renderAll(); this._syncState();
    }
    updatePropW() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        const w = parseInt(this.propWTarget.value, 10);
        if (w > 0) {
            obj.set('width', w / (obj.scaleX || 1));
            obj.setCoords(); this._fc.renderAll(); this._syncState();
        }
    }
    updatePropH() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        const h = parseInt(this.propHTarget.value, 10);
        if (h > 0) {
            obj.set('height', h / (obj.scaleY || 1));
            obj.setCoords(); this._fc.renderAll(); this._syncState();
        }
    }
    updatePropAngle() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        obj.set('angle', parseFloat(this.propAngleTarget.value) || 0);
        obj.setCoords(); this._fc.renderAll(); this._syncState();
    }
    updatePropOpacity() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        obj.set('opacity', parseInt(this.propOpacityTarget.value, 10) / 100);
        this._fc.renderAll(); this._syncState();
    }

    // ── Legacy / Canvas mode switching ───────────────────────────

    switchToCanvas() {
        if (!this._fc) {
            this._initCanvas();
            this._bindCanvasEvents();
            this._bindKeyboard();
        }
        if (this.hasLegacyTextareaTarget && this.hasTrescHtmlTarget)
            this.trescHtmlTarget.value = this.legacyTextareaTarget.value;
        this._isLegacyMode = false;
        if (this.hasLegacyModeTarget) this.legacyModeTarget.classList.add('d-none');
        if (this.hasCanvasModeTarget) this.canvasModeTarget.classList.remove('d-none');
    }

    switchToLegacy() {
        this._isLegacyMode = true;
        if (this.hasLegacyTextareaTarget && this.hasTrescHtmlTarget)
            this.legacyTextareaTarget.value = this.trescHtmlTarget.value;
        if (this.hasCanvasJsonTarget) this.canvasJsonTarget.value = '';
        if (this.hasCanvasModeTarget) this.canvasModeTarget.classList.add('d-none');
        if (this.hasLegacyModeTarget) this.legacyModeTarget.classList.remove('d-none');
    }

    insertPlaceholderLegacy(e) {
        const ph = e.currentTarget.dataset.placeholder;
        if (!this.hasLegacyTextareaTarget || !ph) return;
        const ta = this.legacyTextareaTarget;
        const start = ta.selectionStart, end = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + ph + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + ph.length;
        ta.focus();
    }

    // ── HTML Import ──────────────────────────────────────────────

    importHtml() {
        if (!this.hasTrescHtmlTarget) return;
        const html = this.trescHtmlTarget.value;
        if (!html.trim()) { alert('Brak treści HTML do zaimportowania.'); return; }

        const existing = this._fc.getObjects().filter(o => !o._isPageBorder);
        if (existing.length > 0) {
            if (!confirm('Kanwa zawiera obiekty. Import zastąpi je. Kontynuować?')) return;
            existing.forEach(obj => this._fc.remove(obj));
        }

        const doc = new DOMParser().parseFromString(html, 'text/html');
        const divs = doc.querySelectorAll('div[style*="position:absolute"], div[style*="position: absolute"]');
        let imported = 0;
        divs.forEach(div => {
            const obj = this._parseHtmlDiv(div, div.getAttribute('style') || '');
            if (obj) { this._fc.add(obj); imported++; }
        });
        this._fc.renderAll(); this._syncState();
        alert(`Zaimportowano ${imported} elementów.`);
    }

    _parseHtmlDiv(div, style) {
        const left = this._parsePt(style, /left:\s*([\d.]+)pt/) * PT_TO_PX;
        const top = this._parsePt(style, /top:\s*([\d.]+)pt/) * PT_TO_PX;
        const width = this._parsePt(style, /(?<!border-)width:\s*([\d.]+)pt/) * PT_TO_PX;
        const height = this._parsePt(style, /(?<!border-)height:\s*([\d.]+)pt/) * PT_TO_PX;

        const borderBottomMatch = style.match(/border-bottom:\s*([\d.]+)pt\s+solid\s+([^;"]+)/);
        if (borderBottomMatch && !height) {
            return new fabric.Line([0, 0, width || 200, 0], {
                left, top, stroke: borderBottomMatch[2].trim(),
                strokeWidth: Math.max(1, parseFloat(borderBottomMatch[1]) * PT_TO_PX),
            });
        }

        const borderMatch = style.match(/border:\s*([\d.]+)pt\s+solid\s+([^;"]+)/);
        if (borderMatch && height) {
            const bgMatch = style.match(/background:\s*([^;"]+)/);
            return new fabric.Rect({
                left, top, width: width || 100, height,
                fill: bgMatch ? bgMatch[1].trim() : 'transparent',
                stroke: borderMatch[2].trim(),
                strokeWidth: Math.max(1, parseFloat(borderMatch[1]) * PT_TO_PX),
            });
        }

        const fontSize = this._parsePt(style, /font-size:\s*([\d.]+)pt/) * PT_TO_PX || 12;
        const isBold = /font-weight:\s*bold/.test(style);
        const isItalic = /font-style:\s*italic/.test(style);
        const alignMatch = style.match(/text-align:\s*(center|right|left)/);
        const colorMatch = style.match(/(?<!ground-)color:\s*([^;"]+)/);
        const fill = colorMatch ? colorMatch[1].trim() : '#000000';

        let text = div.innerHTML.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '')
            .replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"').replace(/&#039;/g, "'");
        if (!text.trim()) return null;

        const isPlaceholder = /\[\[.+\]\]/.test(text);
        const textbox = new fabric.Textbox(text, {
            left, top, width: width || 300, fontSize,
            fontFamily: 'DejaVu Sans', fill,
            fontWeight: isBold ? 'bold' : 'normal',
            fontStyle: isItalic ? 'italic' : 'normal',
            textAlign: alignMatch ? alignMatch[1] : 'left', editable: true,
        });
        if (isPlaceholder) {
            textbox.isPlaceholder = true;
            if (fill === '#000000') textbox.set('fill', '#1971c2');
        }
        return textbox;
    }

    _parsePt(style, regex) { const m = style.match(regex); return m ? parseFloat(m[1]) : 0; }

    // ── Fullscreen ───────────────────────────────────────────────

    toggleFullscreen() {
        if (!this.hasFullscreenContainerTarget) return;
        const container = this.fullscreenContainerTarget;
        if (!document.fullscreenElement) {
            container.requestFullscreen().then(() => this._applyFullscreenStyle());
        } else {
            document.exitFullscreen();
        }
    }

    _applyFullscreenStyle() {
        const container = this.fullscreenContainerTarget;
        const onFsChange = () => {
            if (document.fullscreenElement === container) {
                container.style.cssText = 'background:#e9ecef; overflow:auto; display:flex; flex-direction:column;';
                this._scaleCanvasToFit();
                this._fsResizeHandler = () => this._scaleCanvasToFit();
                window.addEventListener('resize', this._fsResizeHandler);
            } else {
                container.style.cssText = '';
                this._resetCanvasScale();
                if (this._fsResizeHandler) {
                    window.removeEventListener('resize', this._fsResizeHandler);
                    this._fsResizeHandler = null;
                }
                document.removeEventListener('fullscreenchange', onFsChange);
            }
        };
        document.addEventListener('fullscreenchange', onFsChange);
    }

    _scaleCanvasToFit() {
        if (!this._fc) return;
        const container = this.fullscreenContainerTarget;
        const availH = container.clientHeight - 160;
        const availW = container.clientWidth - 60;
        const scale = Math.min(availH / CANVAS_H, availW / CANVAS_W, 1);
        const wrapper = this.canvasTarget.parentElement.parentElement;
        wrapper.style.transform = `scale(${scale})`;
        wrapper.style.transformOrigin = 'top center';
    }

    _resetCanvasScale() {
        const wrapper = this.canvasTarget.parentElement.parentElement;
        wrapper.style.transform = '';
        wrapper.style.transformOrigin = '';
    }

    // ── PDF background ───────────────────────────────────────────

    async loadPdfBackground() {
        if (!this.hasPdfFileTarget) return;
        const file = this.pdfFileTarget.files[0];
        if (!file) return;
        const pdfjsLib = await this._loadPdfJs();
        try {
            const pdf = await pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
            const page = await pdf.getPage(1);
            const scale = CANVAS_W / page.getViewport({ scale: 1 }).width;
            const viewport = page.getViewport({ scale });

            const tmpCanvas = document.createElement('canvas');
            tmpCanvas.width = CANVAS_W; tmpCanvas.height = CANVAS_H;
            const ctx = tmpCanvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);
            await page.render({ canvasContext: ctx, viewport }).promise;

            const opacity = this.hasBgOpacityTarget ? parseInt(this.bgOpacityTarget.value, 10) / 100 : 0.3;
            const img = await fabric.FabricImage.fromURL(tmpCanvas.toDataURL('image/png'));
            img.set({ scaleX: CANVAS_W / img.width, scaleY: CANVAS_H / img.height, opacity });
            this._fc.backgroundImage = img;
            this._fc.renderAll();

            if (this.hasBgControlsTarget) this.bgControlsTarget.classList.remove('d-none');
        } catch (err) {
            console.error('PDF load error:', err);
            alert('Nie udało się wczytać PDF: ' + err.message);
        }
    }

    updateBgOpacity() {
        if (!this._fc || !this._fc.backgroundImage) return;
        const opacity = parseInt(this.bgOpacityTarget.value, 10) / 100;
        this._fc.backgroundImage.set('opacity', opacity);
        if (this.hasBgOpacityValueTarget) this.bgOpacityValueTarget.textContent = this.bgOpacityTarget.value + '%';
        this._fc.renderAll();
    }

    removePdfBackground() {
        if (!this._fc) return;
        this._fc.backgroundImage = null;
        this._fc.renderAll();
        if (this.hasBgControlsTarget) this.bgControlsTarget.classList.add('d-none');
        if (this.hasPdfFileTarget) this.pdfFileTarget.value = '';
    }

    _loadPdfJs() {
        return new Promise((resolve, reject) => {
            if (window.pdfjsLib) { resolve(window.pdfjsLib); return; }
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
            script.onload = () => {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                resolve(window.pdfjsLib);
            };
            script.onerror = () => reject(new Error('Nie udało się załadować PDF.js'));
            document.head.appendChild(script);
        });
    }

    // ── Preview PDF ──────────────────────────────────────────────

    previewPdf() {
        if (this._isLegacyMode && this.hasLegacyTextareaTarget && this.hasTrescHtmlTarget)
            this.trescHtmlTarget.value = this.legacyTextareaTarget.value;

        const html = this.trescHtmlTarget.value;
        if (!html.trim()) { alert('Treść HTML jest pusta.'); return; }

        const url = this.szablonIdValue ? this.previewUrlValue : this.previewNewUrlValue;
        const form = document.createElement('form');
        form.method = 'POST'; form.action = url; form.target = '_blank';
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'tresc_html'; input.value = html;
        form.appendChild(input);
        document.body.appendChild(form); form.submit(); form.remove();
    }

    // ── Sync & Export ────────────────────────────────────────────

    _syncState() {
        const objects = this._fc.getObjects().filter(o => !o._isPageBorder);

        if (this.hasCanvasJsonTarget) {
            this.canvasJsonTarget.value = JSON.stringify(this._fc.toJSON(['isPlaceholder', '_isPageBorder']));
        }
        if (objects.length > 0 && this.hasTrescHtmlTarget) {
            this.trescHtmlTarget.value = this._exportToHtml();
        }
    }

    _exportToHtml() {
        const objects = this._fc.getObjects().filter(o => !o._isPageBorder);
        let parts = [];

        objects.forEach(obj => {
            const left = ((obj.left || 0) * PX_TO_PT).toFixed(1);
            const top = ((obj.top || 0) * PX_TO_PT).toFixed(1);

            if (obj.type === 'textbox') {
                const sx = obj.scaleX || 1, sy = obj.scaleY || 1;
                const w = ((obj.width || 200) * sx * PX_TO_PT).toFixed(1);
                const fs = ((obj.fontSize || 12) * sy * PX_TO_PT).toFixed(1);
                const weight = obj.fontWeight === 'bold' ? 'font-weight:bold;' : '';
                const style = obj.fontStyle === 'italic' ? 'font-style:italic;' : '';
                const align = obj.textAlign && obj.textAlign !== 'left' ? `text-align:${obj.textAlign};` : '';
                const color = obj.fill && obj.fill !== '#000000' ? `color:${obj.fill};` : '';
                const htmlText = this._escapeHtml(obj.text || '').replace(/\n/g, '<br>');
                parts.push(`<div style="position:absolute;left:${left}pt;top:${top}pt;width:${w}pt;font-size:${fs}pt;font-family:'DejaVu Sans';${weight}${style}${align}${color}">${htmlText}</div>`);
            } else if (obj.type === 'line') {
                const x1 = (obj.x1 || 0) + (obj.left || 0), x2 = (obj.x2 || 0) + (obj.left || 0);
                const lineW = (Math.abs(x2 - x1) * (obj.scaleX || 1) * PX_TO_PT).toFixed(1);
                const sw = ((obj.strokeWidth || 1) * PX_TO_PT).toFixed(1);
                parts.push(`<div style="position:absolute;left:${left}pt;top:${top}pt;width:${lineW}pt;border-bottom:${sw}pt solid ${obj.stroke || '#000000'};"></div>`);
            } else if (obj.type === 'rect' && !obj._isPageBorder) {
                const sx = obj.scaleX || 1, sy = obj.scaleY || 1;
                const w = ((obj.width || 100) * sx * PX_TO_PT).toFixed(1);
                const h = ((obj.height || 50) * sy * PX_TO_PT).toFixed(1);
                const sw = ((obj.strokeWidth || 1) * PX_TO_PT).toFixed(1);
                const fill = obj.fill && obj.fill !== 'transparent' ? `background:${obj.fill};` : '';
                parts.push(`<div style="position:absolute;left:${left}pt;top:${top}pt;width:${w}pt;height:${h}pt;border:${sw}pt solid ${obj.stroke || '#000000'};${fill}"></div>`);
            }
        });

        return `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 0; size: A4; }
body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; position: relative; width: 595.5pt; height: 842.25pt; }
</style>
</head>
<body>
${parts.join('\n')}
</body>
</html>`;
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}
