import { Controller } from '@hotwired/stimulus';
import * as fabric from 'fabric';

const CANVAS_W = 794;
const CANVAS_H = 1123;
const PX_TO_PT = 0.75; // 96 DPI → 72 DPI

export default class extends Controller {
    static values = {
        canvasJson: String,
        placeholders: Array,
        previewUrl: String,
        previewNewUrl: String,
        szablonId: Number,
    };

    static targets = [
        'canvas',
        'trescHtml',
        'canvasJson',
        'toolbar',
        'propPanel',
        'fontSize',
        'fontColor',
        'propX',
        'propY',
        'propW',
        'propH',
        'placeholderSelect',
    ];

    connect() {
        this._initCanvas();
        this._loadState();
        this._bindCanvasEvents();
        this._bindKeyboard();
    }

    disconnect() {
        if (this._fc) {
            this._fc.dispose();
        }
    }

    // ── Canvas init ──────────────────────────────────────────────

    _initCanvas() {
        this._fc = new fabric.Canvas(this.canvasTarget, {
            width: CANVAS_W,
            height: CANVAS_H,
            backgroundColor: '#ffffff',
            selection: true,
        });

        // Page border (visual only, not exported)
        const border = new fabric.Rect({
            left: 0,
            top: 0,
            width: CANVAS_W,
            height: CANVAS_H,
            fill: 'transparent',
            stroke: '#dee2e6',
            strokeWidth: 1,
            selectable: false,
            evented: false,
            excludeFromExport: false,
        });
        border._isPageBorder = true;
        this._fc.add(border);
        this._fc.sendObjectToBack(border);
    }

    _loadState() {
        const json = this.canvasJsonValue;
        if (json) {
            try {
                const parsed = JSON.parse(json);
                this._fc.loadFromJSON(parsed).then(() => {
                    // Re-tag page border
                    this._fc.getObjects().forEach(obj => {
                        if (obj.type === 'rect' && obj.width === CANVAS_W && obj.height === CANVAS_H && obj.left === 0 && obj.top === 0) {
                            obj._isPageBorder = true;
                            obj.selectable = false;
                            obj.evented = false;
                        }
                    });
                    this._fc.renderAll();
                });
            } catch (e) {
                console.warn('Failed to load canvas JSON:', e);
            }
        }
    }

    _bindCanvasEvents() {
        const events = ['object:modified', 'object:added', 'object:removed', 'text:changed'];
        events.forEach(evt => {
            this._fc.on(evt, () => this._syncState());
        });

        this._fc.on('selection:created', (e) => this._onSelect(e));
        this._fc.on('selection:updated', (e) => this._onSelect(e));
        this._fc.on('selection:cleared', () => this._onDeselect());
    }

    _bindKeyboard() {
        document.addEventListener('keydown', (e) => {
            if (!this._fc) return;
            const tag = e.target.tagName;
            // Don't intercept when typing in inputs
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

            if (e.key === 'Delete' || e.key === 'Backspace') {
                // Don't delete if editing text
                const active = this._fc.getActiveObject();
                if (active && active.isEditing) return;
                e.preventDefault();
                this.deleteSelected();
            }
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                this.toggleBold();
            }
            if (e.ctrlKey && e.key === 'i') {
                e.preventDefault();
                this.toggleItalic();
            }
        });
    }

    // ── Selection / Property panel ───────────────────────────────

    _onSelect(e) {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        this._showProps(obj);
    }

    _onDeselect() {
        if (this.hasPropPanelTarget) {
            this.propPanelTarget.classList.add('d-none');
        }
    }

    _showProps(obj) {
        if (!this.hasPropPanelTarget) return;
        this.propPanelTarget.classList.remove('d-none');

        if (this.hasFontSizeTarget && obj.fontSize !== undefined) {
            this.fontSizeTarget.value = Math.round(obj.fontSize);
        }
        if (this.hasFontColorTarget && obj.fill !== undefined) {
            this.fontColorTarget.value = obj.fill || '#000000';
        }
        if (this.hasPropXTarget) this.propXTarget.value = Math.round(obj.left || 0);
        if (this.hasPropYTarget) this.propYTarget.value = Math.round(obj.top || 0);
        if (this.hasPropWTarget) this.propWTarget.value = Math.round((obj.width || 0) * (obj.scaleX || 1));
        if (this.hasPropHTarget) this.propHTarget.value = Math.round((obj.height || 0) * (obj.scaleY || 1));
    }

    // ── Toolbar actions ──────────────────────────────────────────

    addText() {
        const text = new fabric.Textbox('Wpisz tekst...', {
            left: 50,
            top: 50,
            width: 300,
            fontSize: 12,
            fontFamily: 'DejaVu Sans',
            fill: '#000000',
            editable: true,
        });
        this._fc.add(text);
        this._fc.setActiveObject(text);
        this._fc.renderAll();
    }

    addPlaceholder() {
        if (!this.hasPlaceholderSelectTarget) return;
        const val = this.placeholderSelectTarget.value;
        if (!val) return;

        const text = new fabric.Textbox(val, {
            left: 50,
            top: 50,
            width: 350,
            fontSize: 12,
            fontFamily: 'DejaVu Sans',
            fill: '#1971c2',
            editable: true,
        });
        text.isPlaceholder = true;
        this._fc.add(text);
        this._fc.setActiveObject(text);
        this._fc.renderAll();
    }

    addLine() {
        const line = new fabric.Line([50, 200, 550, 200], {
            stroke: '#000000',
            strokeWidth: 1,
        });
        this._fc.add(line);
        this._fc.setActiveObject(line);
        this._fc.renderAll();
    }

    addRect() {
        const rect = new fabric.Rect({
            left: 50,
            top: 50,
            width: 200,
            height: 100,
            fill: 'transparent',
            stroke: '#000000',
            strokeWidth: 1,
        });
        this._fc.add(rect);
        this._fc.setActiveObject(rect);
        this._fc.renderAll();
    }

    deleteSelected() {
        const active = this._fc.getActiveObjects();
        if (!active.length) return;
        active.forEach(obj => {
            if (!obj._isPageBorder) {
                this._fc.remove(obj);
            }
        });
        this._fc.discardActiveObject();
        this._fc.renderAll();
    }

    toggleBold() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('fontWeight', obj.fontWeight === 'bold' ? 'normal' : 'bold');
        this._fc.renderAll();
        this._syncState();
    }

    toggleItalic() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('fontStyle', obj.fontStyle === 'italic' ? 'normal' : 'italic');
        this._fc.renderAll();
        this._syncState();
    }

    updateFontSize() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.fontSize === undefined) return;
        const size = parseInt(this.fontSizeTarget.value, 10);
        if (size > 0 && size <= 200) {
            obj.set('fontSize', size);
            this._fc.renderAll();
            this._syncState();
        }
    }

    updateFontColor() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        const color = this.fontColorTarget.value;
        if (obj.type === 'textbox') {
            obj.set('fill', color);
        } else if (obj.type === 'line' || obj.type === 'rect') {
            obj.set('stroke', color);
        }
        this._fc.renderAll();
        this._syncState();
    }

    updatePropX() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        obj.set('left', parseInt(this.propXTarget.value, 10) || 0);
        obj.setCoords();
        this._fc.renderAll();
        this._syncState();
    }

    updatePropY() {
        const obj = this._fc.getActiveObject();
        if (!obj) return;
        obj.set('top', parseInt(this.propYTarget.value, 10) || 0);
        obj.setCoords();
        this._fc.renderAll();
        this._syncState();
    }

    alignLeft() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('textAlign', 'left');
        this._fc.renderAll();
        this._syncState();
    }

    alignCenter() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('textAlign', 'center');
        this._fc.renderAll();
        this._syncState();
    }

    alignRight() {
        const obj = this._fc.getActiveObject();
        if (!obj || obj.type !== 'textbox') return;
        obj.set('textAlign', 'right');
        this._fc.renderAll();
        this._syncState();
    }

    previewPdf() {
        const html = this.trescHtmlTarget.value;
        if (!html.trim()) {
            alert('Treść HTML jest pusta. Dodaj elementy na kanwę.');
            return;
        }

        const url = this.szablonIdValue
            ? this.previewUrlValue
            : this.previewNewUrlValue;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.target = '_blank';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'tresc_html';
        input.value = html;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    // ── Sync & Export ────────────────────────────────────────────

    _syncState() {
        const objects = this._fc.getObjects().filter(o => !o._isPageBorder);

        // Save canvas JSON for re-editing
        if (this.hasCanvasJsonTarget) {
            const json = JSON.stringify(this._fc.toJSON(['isPlaceholder', '_isPageBorder']));
            this.canvasJsonTarget.value = json;
        }

        // Only overwrite trescHtml if canvas has real objects
        if (objects.length > 0 && this.hasTrescHtmlTarget) {
            this.trescHtmlTarget.value = this._exportToHtml();
        }
    }

    _exportToHtml() {
        const objects = this._fc.getObjects().filter(o => !o._isPageBorder);
        let bodyParts = [];

        objects.forEach(obj => {
            const left = ((obj.left || 0) * PX_TO_PT).toFixed(1);
            const top = ((obj.top || 0) * PX_TO_PT).toFixed(1);

            if (obj.type === 'textbox') {
                const scaleX = obj.scaleX || 1;
                const scaleY = obj.scaleY || 1;
                const w = ((obj.width || 200) * scaleX * PX_TO_PT).toFixed(1);
                const fontSize = ((obj.fontSize || 12) * scaleY * PX_TO_PT).toFixed(1);
                const weight = obj.fontWeight === 'bold' ? 'font-weight:bold;' : '';
                const style = obj.fontStyle === 'italic' ? 'font-style:italic;' : '';
                const align = obj.textAlign && obj.textAlign !== 'left' ? `text-align:${obj.textAlign};` : '';
                const color = obj.fill && obj.fill !== '#000000' ? `color:${obj.fill};` : '';
                const text = this._escapeHtml(obj.text || '');
                // Convert newlines to <br>
                const htmlText = text.replace(/\n/g, '<br>');

                bodyParts.push(
                    `<div style="position:absolute;left:${left}pt;top:${top}pt;width:${w}pt;` +
                    `font-size:${fontSize}pt;font-family:'DejaVu Sans';${weight}${style}${align}${color}">${htmlText}</div>`
                );
            } else if (obj.type === 'line') {
                const x1 = (obj.x1 || 0) + (obj.left || 0);
                const x2 = (obj.x2 || 0) + (obj.left || 0);
                const lineW = (Math.abs(x2 - x1) * (obj.scaleX || 1) * PX_TO_PT).toFixed(1);
                const strokeW = ((obj.strokeWidth || 1) * PX_TO_PT).toFixed(1);
                const strokeColor = obj.stroke || '#000000';

                bodyParts.push(
                    `<div style="position:absolute;left:${left}pt;top:${top}pt;width:${lineW}pt;` +
                    `border-bottom:${strokeW}pt solid ${strokeColor};"></div>`
                );
            } else if (obj.type === 'rect' && !obj._isPageBorder) {
                const scaleX = obj.scaleX || 1;
                const scaleY = obj.scaleY || 1;
                const w = ((obj.width || 100) * scaleX * PX_TO_PT).toFixed(1);
                const h = ((obj.height || 50) * scaleY * PX_TO_PT).toFixed(1);
                const strokeW = ((obj.strokeWidth || 1) * PX_TO_PT).toFixed(1);
                const strokeColor = obj.stroke || '#000000';
                const fillColor = obj.fill && obj.fill !== 'transparent' ? `background:${obj.fill};` : '';

                bodyParts.push(
                    `<div style="position:absolute;left:${left}pt;top:${top}pt;width:${w}pt;height:${h}pt;` +
                    `border:${strokeW}pt solid ${strokeColor};${fillColor}"></div>`
                );
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
${bodyParts.join('\n')}
</body>
</html>`;
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}
