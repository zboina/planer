import { Controller } from '@hotwired/stimulus';

/**
 * Signature Pad Controller
 * Provides a canvas-based handwritten signature option as an alternative to text input.
 * The signature is exported as a base64 PNG data URL and stored in a hidden input.
 */
export default class extends Controller {
    static targets = [
        'canvas',        // <canvas> element for drawing
        'hiddenInput',   // hidden <input name="podpis"> that holds the value (text or base64)
        'textInput',     // visible text <input> for typed signature
        'padContainer',  // container wrapping the canvas + controls
        'textContainer', // container wrapping the text input
        'toggleBtn',     // button to switch modes
        'clearBtn',      // button to clear canvas
    ];

    static values = {
        lineWidth: { type: Number, default: 2 },
        lineColor: { type: String, default: '#000000' },
    };

    connect() {
        this._isDrawing = false;
        this._hasDrawn = false;
        this._mode = 'text'; // 'text' or 'draw'
        this._ctx = null;

        // Start in text mode (default)
        this._updateView();
    }

    // ─── Mode toggle ───────────────────────────────────────────

    toggle() {
        this._mode = this._mode === 'text' ? 'draw' : 'text';
        this._updateView();

        if (this._mode === 'draw') {
            this._initCanvas();
        } else {
            // Switching back to text — sync text input value to hidden input
            this.hiddenInputTarget.value = this.textInputTarget.value;
        }
    }

    _updateView() {
        if (this._mode === 'draw') {
            this.textContainerTarget.style.display = 'none';
            this.padContainerTarget.style.display = 'block';
            this.toggleBtnTarget.innerHTML = '<i class="ti ti-keyboard me-1"></i>Wpisz tekstem';
        } else {
            this.textContainerTarget.style.display = 'block';
            this.padContainerTarget.style.display = 'none';
            this.toggleBtnTarget.innerHTML = '<i class="ti ti-signature me-1"></i>Podpisz odręcznie';
        }
    }

    // ─── Canvas init ───────────────────────────────────────────

    _initCanvas() {
        const canvas = this.canvasTarget;
        // Make canvas sharp on retina
        const rect = canvas.parentElement.getBoundingClientRect();
        const w = Math.floor(rect.width) || 400;
        const h = 120;
        const dpr = window.devicePixelRatio || 1;

        canvas.width = w * dpr;
        canvas.height = h * dpr;
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';

        this._ctx = canvas.getContext('2d');
        this._ctx.scale(dpr, dpr);
        this._ctx.lineCap = 'round';
        this._ctx.lineJoin = 'round';
        this._ctx.strokeStyle = this.lineColorValue;
        this._ctx.lineWidth = this.lineWidthValue;

        this._hasDrawn = false;
        this._drawGuide();
    }

    _drawGuide() {
        const canvas = this.canvasTarget;
        const dpr = window.devicePixelRatio || 1;
        const w = canvas.width / dpr;
        const h = canvas.height / dpr;
        const ctx = this._ctx;

        // Dotted guide line at ~75% height
        ctx.save();
        ctx.setLineDash([4, 4]);
        ctx.strokeStyle = '#ccc';
        ctx.lineWidth = 1;
        ctx.beginPath();
        const y = Math.round(h * 0.75);
        ctx.moveTo(20, y);
        ctx.lineTo(w - 20, y);
        ctx.stroke();
        ctx.restore();

        // Restore drawing style
        ctx.strokeStyle = this.lineColorValue;
        ctx.lineWidth = this.lineWidthValue;
    }

    // ─── Drawing events ────────────────────────────────────────

    startDraw(e) {
        e.preventDefault();
        this._isDrawing = true;
        const pos = this._getPos(e);
        this._ctx.beginPath();
        this._ctx.moveTo(pos.x, pos.y);
    }

    draw(e) {
        if (!this._isDrawing) return;
        e.preventDefault();
        const pos = this._getPos(e);
        this._ctx.lineTo(pos.x, pos.y);
        this._ctx.stroke();
        this._hasDrawn = true;
    }

    endDraw(e) {
        if (!this._isDrawing) return;
        e.preventDefault();
        this._isDrawing = false;
        this._syncToHidden();
    }

    _getPos(e) {
        const canvas = this.canvasTarget;
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top,
        };
    }

    // ─── Actions ───────────────────────────────────────────────

    clear() {
        if (!this._ctx) return;
        const canvas = this.canvasTarget;
        const dpr = window.devicePixelRatio || 1;
        this._ctx.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr);
        this._hasDrawn = false;
        this._drawGuide();
        this.hiddenInputTarget.value = '';
    }

    syncText() {
        // Called on text input change — update hidden input
        this.hiddenInputTarget.value = this.textInputTarget.value;
    }

    // ─── Export ─────────────────────────────────────────────────

    _syncToHidden() {
        if (!this._hasDrawn) {
            this.hiddenInputTarget.value = '';
            return;
        }

        // Export canvas to base64 PNG, trimmed to content
        const dataUrl = this._exportTrimmed();
        this.hiddenInputTarget.value = dataUrl;
    }

    _exportTrimmed() {
        const canvas = this.canvasTarget;
        const dpr = window.devicePixelRatio || 1;
        const w = canvas.width;
        const h = canvas.height;
        const ctx = this._ctx;

        // Get image data at full resolution
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = w;
        tempCanvas.height = h;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.drawImage(canvas, 0, 0);

        const imageData = tempCtx.getImageData(0, 0, w, h);
        const data = imageData.data;

        // Find bounding box of non-transparent pixels
        let minX = w, minY = h, maxX = 0, maxY = 0;
        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                const alpha = data[(y * w + x) * 4 + 3];
                if (alpha > 10) {
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                }
            }
        }

        if (maxX <= minX || maxY <= minY) {
            // Nothing drawn
            return canvas.toDataURL('image/png');
        }

        // Add padding
        const pad = 10 * dpr;
        minX = Math.max(0, minX - pad);
        minY = Math.max(0, minY - pad);
        maxX = Math.min(w - 1, maxX + pad);
        maxY = Math.min(h - 1, maxY + pad);

        const cropW = maxX - minX + 1;
        const cropH = maxY - minY + 1;

        const cropCanvas = document.createElement('canvas');
        cropCanvas.width = cropW;
        cropCanvas.height = cropH;
        const cropCtx = cropCanvas.getContext('2d');
        cropCtx.drawImage(tempCanvas, minX, minY, cropW, cropH, 0, 0, cropW, cropH);

        return cropCanvas.toDataURL('image/png');
    }
}
