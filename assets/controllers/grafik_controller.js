import { Controller } from '@hotwired/stimulus';

/**
 * Grafik pracy controller.
 *
 * Interactions:
 *  - Click cell         → select it (single)
 *  - Mouse drag         → select range by dragging across cells
 *  - Shift+click        → select range (same row, from last click)
 *  - Ctrl/Cmd+click     → toggle cell in selection (multi, any row)
 *  - After selecting     → dropdown appears; pick type → applies to ALL selected
 *  - Dropdown has:
 *      • Shift types
 *      • "Powtórz poprzedni" — copies the entry from previous day (day-1) for each selected cell
 *      • "Wyczyść" — clears all selected
 *  - Esc / click outside → deselect all
 */
export default class extends Controller {
    static values = {
        upsertUrl: String,
        deleteUrl: String,
        batchUrl: String,
        typyZmian: Array,
        canEdit: String,
        departamentId: Number,
        glownyUserIds: Array,
        dniWolne: Array,
        podanieUrl: String,
        podaniePdfUrl: String,
        currentUserId: Number,
        autoPlanUrl: String,
        rok: Number,
        miesiac: Number,
    };

    static targets = ['cell', 'dwCell'];

    initialize() {
        this._dropdown = null;
        this._selected = new Set();
        this._lastClicked = null;
        this._cellMap = {};
        this._dragging = false;
        this._dragStart = null;
        this._dragCurrent = null;
    }

    connect() {
        this._onDocClick = (e) => this._onOutsideClick(e);
        this._onKeyDown = (e) => this._handleKeyDown(e);
        this._onMouseMove = (e) => this._handleMouseMove(e);
        this._onMouseUp = (e) => this._handleMouseUp(e);
        document.addEventListener('click', this._onDocClick);
        document.addEventListener('keydown', this._onKeyDown);
        document.addEventListener('mousemove', this._onMouseMove);
        document.addEventListener('mouseup', this._onMouseUp);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocClick);
        document.removeEventListener('keydown', this._onKeyDown);
        document.removeEventListener('mousemove', this._onMouseMove);
        document.removeEventListener('mouseup', this._onMouseUp);
        this._removeDropdown();
    }

    cellTargetConnected(cell) {
        if (this.canEditValue !== 'true') return;

        const key = cell.dataset.userId + '-' + cell.dataset.day;
        this._cellMap[key] = cell;

        cell.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return; // only left button
            e.preventDefault();
            e.stopPropagation();
            this._handleMouseDown(cell, e);
        });

        cell.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // ─── Mouse drag logic ─────────────────────────────────────

    _handleMouseDown(cell, e) {
        const isShift = e.shiftKey;
        const isCtrl = e.ctrlKey || e.metaKey;

        if (isShift && this._lastClicked) {
            this._selectRange(this._lastClicked, cell);
            this._lastClicked = cell;
            if (this._selected.size > 0) this._openDropdown(cell);
            return;
        }

        if (isCtrl) {
            this._toggleCell(cell);
            this._lastClicked = cell;
            if (this._selected.size > 0) this._openDropdown(cell);
            return;
        }

        // Start drag
        this._removeDropdown();
        this._deselectAllSilent();
        this._dragging = true;
        this._dragStart = cell;
        this._dragCurrent = cell;
        this._selectCell(cell);
        this.element.querySelector('.grafik-table')?.classList.add('is-dragging');
    }

    _handleMouseMove(e) {
        if (!this._dragging || !this._dragStart) return;

        // Find which cell the mouse is over
        const el = document.elementFromPoint(e.clientX, e.clientY);
        if (!el) return;
        const cell = el.closest('[data-grafik-target="cell"]');
        if (!cell || cell === this._dragCurrent) return;

        this._dragCurrent = cell;

        // Clear previous drag selection, re-select the range
        this._deselectAllSilent();
        this._selectDragRange(this._dragStart, cell);
    }

    _handleMouseUp(e) {
        if (!this._dragging) return;
        this._dragging = false;
        this.element.querySelector('.grafik-table')?.classList.remove('is-dragging');

        const anchor = this._dragCurrent || this._dragStart;
        this._lastClicked = anchor;

        if (this._selected.size > 0) {
            this._openDropdown(anchor);
        }

        this._dragStart = null;
        this._dragCurrent = null;

        // After mouseup the browser fires a click event that would hit
        // _onOutsideClick and destroy the selection. Suppress it.
        this._suppressClick = true;
        setTimeout(() => { this._suppressClick = false; }, 0);
    }

    _selectDragRange(fromCell, toCell) {
        const uidFrom = fromCell.dataset.userId;
        const uidTo = toCell.dataset.userId;
        const dayFrom = parseInt(fromCell.dataset.day);
        const dayTo = parseInt(toCell.dataset.day);
        const loDay = Math.min(dayFrom, dayTo);
        const hiDay = Math.max(dayFrom, dayTo);

        // Get all user IDs in row order between the two rows
        const allRows = this._getOrderedUserIds();
        const idxFrom = allRows.indexOf(uidFrom);
        const idxTo = allRows.indexOf(uidTo);
        if (idxFrom === -1 || idxTo === -1) return;
        const loRow = Math.min(idxFrom, idxTo);
        const hiRow = Math.max(idxFrom, idxTo);

        for (let r = loRow; r <= hiRow; r++) {
            const uid = allRows[r];
            for (let d = loDay; d <= hiDay; d++) {
                const c = this._cellMap[uid + '-' + d];
                if (c) this._selectCell(c);
            }
        }
    }

    _getOrderedUserIds() {
        if (this._orderedUserIds) return this._orderedUserIds;
        const seen = new Set();
        const ids = [];
        // Iterate targets in DOM order
        for (const cell of this.cellTargets) {
            const uid = cell.dataset.userId;
            if (!seen.has(uid)) {
                seen.add(uid);
                ids.push(uid);
            }
        }
        this._orderedUserIds = ids;
        return ids;
    }

    _deselectAllSilent() {
        this._selected.forEach(c => c.classList.remove('selected'));
        this._selected.clear();
    }

    _selectCell(cell) {
        this._selected.add(cell);
        cell.classList.add('selected');
    }

    _deselectCell(cell) {
        this._selected.delete(cell);
        cell.classList.remove('selected');
    }

    _toggleCell(cell) {
        if (this._selected.has(cell)) {
            this._deselectCell(cell);
        } else {
            this._selectCell(cell);
        }
        this._removeDropdown();
    }

    _deselectAll() {
        this._selected.forEach(c => c.classList.remove('selected'));
        this._selected.clear();
        this._removeDropdown();
    }

    _selectRange(fromCell, toCell) {
        // Only works for same user row
        const uid = fromCell.dataset.userId;
        if (toCell.dataset.userId !== uid) {
            // Different rows — just select the target
            this._deselectAll();
            this._selectCell(toCell);
            return;
        }

        const dayFrom = parseInt(fromCell.dataset.day);
        const dayTo = parseInt(toCell.dataset.day);
        const lo = Math.min(dayFrom, dayTo);
        const hi = Math.max(dayFrom, dayTo);

        // Don't clear previous Ctrl-selections, just add range
        for (let d = lo; d <= hi; d++) {
            const key = uid + '-' + d;
            const c = this._cellMap[key];
            if (c) this._selectCell(c);
        }
    }

    // ─── Dropdown ───────────────────────────────────────────────

    _openDropdown(anchorCell) {
        this._removeDropdown();

        const selCount = this._selected.size;
        const dropdown = document.createElement('div');
        dropdown.className = 'grafik-dropdown';
        dropdown.addEventListener('click', (e) => e.stopPropagation());

        // Header showing count if multi
        if (selCount > 1) {
            const header = document.createElement('div');
            header.className = 'grafik-dropdown-header';
            header.textContent = `Zaznaczono: ${selCount} dni`;
            dropdown.appendChild(header);
            dropdown.appendChild(this._makeSep());
        }

        // Shift types
        this.typyZmianValue.forEach((typ) => {
            const item = document.createElement('div');
            item.className = 'grafik-dropdown-item';
            item.innerHTML = `<span class="color-dot" style="background:${this._esc(typ.kolor)}"></span>` +
                `<span><strong>${this._esc(typ.skrot)}</strong> ${this._esc(typ.nazwa)}` +
                `${typ.godzinyOd ? ' ' + this._esc(typ.godzinyOd) + '-' + this._esc(typ.godzinyDo) : ''}</span>`;
            item.addEventListener('click', () => {
                this._applyToSelected(typ.id);
                this._removeDropdown();
            });
            dropdown.appendChild(item);
        });

        dropdown.appendChild(this._makeSep());

        // "Powtórz poprzedni" option
        const repeatItem = document.createElement('div');
        repeatItem.className = 'grafik-dropdown-item repeat';
        repeatItem.innerHTML = '<span class="repeat-icon">&#8634;</span> <span>Powtórz poprzedni</span>';
        repeatItem.addEventListener('click', () => {
            this._repeatPrevious();
            this._removeDropdown();
        });
        dropdown.appendChild(repeatItem);

        dropdown.appendChild(this._makeSep());

        // Clear
        const clearItem = document.createElement('div');
        clearItem.className = 'grafik-dropdown-item clear';
        clearItem.textContent = 'Wyczyść';
        clearItem.addEventListener('click', () => {
            this._clearSelected();
            this._removeDropdown();
        });
        dropdown.appendChild(clearItem);

        // Position near anchor
        const rect = anchorCell.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.left = rect.left + 'px';
        dropdown.style.top = (rect.bottom + 2) + 'px';

        document.body.appendChild(dropdown);
        this._dropdown = dropdown;

        requestAnimationFrame(() => {
            const dr = dropdown.getBoundingClientRect();
            if (dr.right > window.innerWidth) {
                dropdown.style.left = (window.innerWidth - dr.width - 8) + 'px';
            }
            if (dr.bottom > window.innerHeight) {
                dropdown.style.top = (rect.top - dr.height - 2) + 'px';
            }
        });
    }

    _makeSep() {
        const s = document.createElement('div');
        s.className = 'grafik-dropdown-separator';
        return s;
    }

    _onOutsideClick(e) {
        if (this._suppressClick) return;
        if (this._dropdown && !this._dropdown.contains(e.target)) {
            this._deselectAll();
        }
    }

    _removeDropdown() {
        if (this._dropdown) {
            this._dropdown.remove();
            this._dropdown = null;
        }
    }

    // ─── Actions ────────────────────────────────────────────────

    async _applyToSelected(typZmianyId) {
        const cells = [...this._selected];
        if (cells.length === 1) {
            // Single — use fast single endpoint
            const c = cells[0];
            await this._upsert(c, c.dataset.userId, c.dataset.date, typZmianyId);
            this._deselectAll();
            return;
        }

        // Batch
        const wpisy = cells.map(c => ({ userId: parseInt(c.dataset.userId), data: c.dataset.date }));
        try {
            const res = await fetch(this.batchUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    departamentId: this.departamentIdValue,
                    typZmianyId: typZmianyId,
                    wpisy,
                }),
            });
            const data = await res.json();
            if (data.success) {
                this._applyResults(data.results);
            }
        } catch (e) {
            console.error('Batch upsert error:', e);
        }
        this._deselectAll();
    }

    async _clearSelected() {
        const cells = [...this._selected];
        if (cells.length === 1) {
            const c = cells[0];
            await this._delete(c, c.dataset.userId, c.dataset.date);
            this._deselectAll();
            return;
        }

        const wpisy = cells.map(c => ({ userId: parseInt(c.dataset.userId), data: c.dataset.date }));
        try {
            const res = await fetch(this.batchUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    departamentId: this.departamentIdValue,
                    typZmianyId: null,
                    wpisy,
                }),
            });
            const data = await res.json();
            if (data.success) {
                this._applyResults(data.results);
            }
        } catch (e) {
            console.error('Batch clear error:', e);
        }
        this._deselectAll();
    }

    /**
     * "Powtórz poprzedni" — for each selected cell, look at day-1 for the same user.
     * If day-1 has a shift type, apply the same type to this cell.
     * Groups by type and sends batch requests.
     */
    async _repeatPrevious() {
        const cells = [...this._selected];
        // Group by typZmianyId to batch
        const byType = {};  // typId → [{userId, data}]
        const skipped = [];

        for (const cell of cells) {
            const uid = cell.dataset.userId;
            const day = parseInt(cell.dataset.day);
            const prevKey = uid + '-' + (day - 1);
            const prevCell = this._cellMap[prevKey];

            if (prevCell && prevCell.textContent.trim()) {
                // Find the type from the prev cell's text
                const prevSkrot = prevCell.textContent.trim();
                const typ = this.typyZmianValue.find(t => t.skrot === prevSkrot);
                if (typ) {
                    if (!byType[typ.id]) byType[typ.id] = { typ, wpisy: [] };
                    byType[typ.id].wpisy.push({ userId: parseInt(uid), data: cell.dataset.date });
                } else {
                    skipped.push(cell);
                }
            } else {
                skipped.push(cell);
            }
        }

        // Send batch per type
        for (const entry of Object.values(byType)) {
            try {
                const res = await fetch(this.batchUrlValue, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        departamentId: this.departamentIdValue,
                        typZmianyId: entry.typ.id,
                        wpisy: entry.wpisy,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    this._applyResults(data.results);
                }
            } catch (e) {
                console.error('Repeat previous error:', e);
            }
        }

        this._deselectAll();
    }

    // ─── Cell updates ───────────────────────────────────────────

    _applyResults(results) {
        const affectedUsers = new Set();
        for (const r of results) {
            const key = r.userId + '-' + this._dayFromDate(r.data);
            const cell = this._cellMap[key];
            if (!cell) continue;

            affectedUsers.add(String(r.userId));

            if (r.skrot) {
                cell.textContent = r.skrot;
                cell.style.backgroundColor = r.kolor;
                cell.style.color = '#fff';
                cell.classList.remove('gc-empty');
            } else {
                cell.textContent = '';
                cell.style.backgroundColor = '';
                cell.style.color = '';
                cell.classList.add('gc-empty');
            }
        }
        affectedUsers.forEach(uid => this._recalcDW(uid));
    }

    _dayFromDate(dateStr) {
        // "2026-02-05" → 5
        return parseInt(dateStr.split('-')[2], 10);
    }

    async _upsert(cell, userId, date, typZmianyId) {
        try {
            const res = await fetch(this.upsertUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    userId: parseInt(userId),
                    departamentId: this.departamentIdValue,
                    data: date,
                    typZmianyId,
                }),
            });
            const data = await res.json();
            if (data.success) {
                cell.textContent = data.skrot;
                cell.style.backgroundColor = data.kolor;
                cell.style.color = '#fff';
                cell.classList.remove('gc-empty');
                this._recalcDW(userId);
            }
        } catch (e) {
            console.error('Grafik upsert error:', e);
        }
    }

    async _delete(cell, userId, date) {
        try {
            const res = await fetch(this.deleteUrlValue, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    userId: parseInt(userId),
                    departamentId: this.departamentIdValue,
                    data: date,
                }),
            });
            const data = await res.json();
            if (data.success) {
                cell.textContent = '';
                cell.style.backgroundColor = '';
                cell.style.color = '';
                cell.classList.add('gc-empty');
                this._recalcDW(userId);
            }
        } catch (e) {
            console.error('Grafik delete error:', e);
        }
    }

    _recalcDW(userId) {
        const uid = String(userId);
        let count = 0;
        for (const [key, cell] of Object.entries(this._cellMap)) {
            if (key.startsWith(uid + '-') && cell.textContent.trim() === 'W') {
                count++;
            }
        }
        const dwCell = this.dwCellTargets.find(c => c.dataset.userId === uid);
        if (dwCell) {
            dwCell.textContent = count;
        }
    }

    // ─── Keyboard shortcuts ────────────────────────────────────

    _handleKeyDown(e) {
        if (e.key === 'Escape') {
            this._deselectAll();
            return;
        }

        // Apply shift type via keyboard shortcut when cells are selected
        if (this._selected.size === 0) return;
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

        const key = e.key.toUpperCase();
        const typ = this.typyZmianValue.find(t => t.skrotKlawiaturowy && t.skrotKlawiaturowy.toUpperCase() === key);
        if (typ) {
            e.preventDefault();
            this._applyToSelected(typ.id);
            this._removeDropdown();
        }

        if (e.key === 'Delete' || e.key === 'Backspace') {
            e.preventDefault();
            this._clearSelected();
            this._removeDropdown();
        }
    }

    // ─── Auto Plan ───────────────────────────────────────────────

    async autoPlan() {
        if (!confirm('Automatycznie uzupełnić grafik na podstawie szablonów? Istniejące wpisy nie zostaną nadpisane.')) return;

        try {
            const res = await fetch(this.autoPlanUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    departamentId: this.departamentIdValue,
                    rok: this.rokValue,
                    miesiac: this.miesiacValue,
                }),
            });
            const data = await res.json();
            if (data.success) {
                // Reload page to show results
                window.location.reload();
            } else {
                alert(data.error || 'Wystąpił błąd podczas automatycznego planowania.');
            }
        } catch (e) {
            console.error('Auto plan error:', e);
            alert('Wystąpił błąd podczas automatycznego planowania.');
        }
    }

    _esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }
}
