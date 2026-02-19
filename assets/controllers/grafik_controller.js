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
        this._cursor = null;
        this._kbdCursorCell = null;
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

    autoPlan() {
        if (!this.autoPlanUrlValue) return;
        this._showAutoPlanModal();
    }

    _showAutoPlanModal() {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';

        const modal = document.createElement('div');
        modal.className = 'modal modal-blur fade show';
        modal.style.display = 'block';
        modal.setAttribute('tabindex', '-1');
        modal.innerHTML = `
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-status bg-warning"></div>
                    <div class="modal-body text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2 text-warning" width="40" height="40"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M12 8l0 4l2 2"/>
                            <path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/>
                        </svg>
                        <h3>AUTO Plan</h3>
                        <div class="text-secondary">
                            Czy na pewno chcesz automatycznie wypełnić grafik dla <strong>wszystkich pracowników</strong> w tym miesiącu?
                            <br><br>
                            <span class="text-warning">Istniejące wpisy zostaną nadpisane.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="w-100 d-flex gap-2">
                            <button type="button" class="btn flex-fill" data-action="cancel">Anuluj</button>
                            <button type="button" class="btn btn-warning flex-fill" data-action="confirm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16"
                                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 12l5 5l10 -10"/>
                                </svg>
                                Generuj plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);
        document.body.classList.add('modal-open');

        const close = () => {
            modal.remove();
            backdrop.remove();
            document.body.classList.remove('modal-open');
        };

        modal.querySelector('[data-action="cancel"]').addEventListener('click', close);
        backdrop.addEventListener('click', close);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });

        modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
            const btn = modal.querySelector('[data-action="confirm"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generowanie...';
            modal.querySelector('[data-action="cancel"]').disabled = true;
            this._executeAutoPlan(modal, backdrop);
        });
    }

    async _executeAutoPlan(modal, backdrop) {
        const close = () => {
            modal.remove();
            backdrop.remove();
            document.body.classList.remove('modal-open');
        };

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
            if (data.error) {
                close();
                this._showAlertModal('Błąd', data.error, 'danger');
                return;
            }
            if (data.success) {
                close();
                window.location.reload();
            }
        } catch (e) {
            console.error('Auto plan error:', e);
            close();
            this._showAlertModal('Błąd', 'Wystąpił błąd podczas generowania planu.', 'danger');
        }
    }

    _showAlertModal(title, message, status) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';

        const modal = document.createElement('div');
        modal.className = 'modal modal-blur fade show';
        modal.style.display = 'block';
        modal.setAttribute('tabindex', '-1');

        const iconColor = status === 'danger' ? 'text-danger' : 'text-success';
        const iconPath = status === 'danger'
            ? '<path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/>'
            : '<path d="M5 12l5 5l10 -10"/>';

        modal.innerHTML = `
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-status bg-${status}"></div>
                    <div class="modal-body text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg mb-2 ${iconColor}" width="40" height="40"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>${iconPath}
                        </svg>
                        <h3>${this._esc(title)}</h3>
                        <div class="text-secondary">${this._esc(message)}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn w-100" data-action="close">OK</button>
                    </div>
                </div>
            </div>`;

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);
        document.body.classList.add('modal-open');

        const close = () => {
            modal.remove();
            backdrop.remove();
            document.body.classList.remove('modal-open');
        };

        modal.querySelector('[data-action="close"]').addEventListener('click', close);
        backdrop.addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    }

    disconnect() {
        document.removeEventListener('click', this._onDocClick);
        document.removeEventListener('keydown', this._onKeyDown);
        document.removeEventListener('mousemove', this._onMouseMove);
        document.removeEventListener('mouseup', this._onMouseUp);
        this._removeDropdown();
    }

    async _handleKeyDown(e) {
        if (e.key === 'Escape') {
            this._deselectAll();
            this._clearKbdCursor();
            this._cursor = null;
            return;
        }

        // Ignore if user is typing in an input/textarea/select
        const tag = e.target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

        if (this.canEditValue !== 'true') return;

        // Arrow keys → navigate grid
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) !== -1) {
            e.preventDefault();
            this._navigateArrow(e);
            return;
        }

        // From here, need at least one selected cell
        if (this._selected.size === 0) return;

        // Delete key → clear selected
        if (e.key === 'Delete') {
            e.preventDefault();
            this._clearSelected();
            this._removeDropdown();
            return;
        }

        // Build combo string from the event
        const parts = [];
        if (e.ctrlKey || e.metaKey) parts.push('Ctrl');
        if (e.shiftKey) parts.push('Shift');
        if (e.altKey) parts.push('Alt');
        if (['Control', 'Shift', 'Alt', 'Meta'].indexOf(e.key) !== -1) return;
        const keyName = e.key.length === 1 ? e.key.toUpperCase() : e.key;
        parts.push(keyName);
        const combo = parts.join('+');

        // Find matching shift type
        const typ = this.typyZmianValue.find(t => t.skrotKlawiaturowy === combo);
        if (typ) {
            // Block główny-only types for non-główny users
            if (typ.tylkoGlowny) {
                const selUids = new Set();
                this._selected.forEach(c => selUids.add(parseInt(c.dataset.userId)));
                const glIds = new Set(this.glownyUserIdsValue || []);
                if (![...selUids].every(uid => glIds.has(uid))) return;
            }
            e.preventDefault();
            const shouldAdvance = this._cursor && this._selected.size <= 1;
            // Fire the apply (async), then immediately advance cursor
            this._applyToSelected(typ.id, false);
            this._removeDropdown();
            if (shouldAdvance) {
                this._deselectAllSilent();
                this._clearKbdCursor();
                this._moveArrow(0, 1);
                const newCell = this._cellMap[this._cursor.uid + '-' + this._cursor.day];
                if (newCell) {
                    this._selectCell(newCell);
                    this._setKbdCursor(newCell);
                    this._scrollIntoView(newCell);
                }
            } else {
                this._deselectAllSilent();
                this._clearKbdCursor();
                this._cursor = null;
            }
        }
    }

    _navigateArrow(e) {
        const userIds = this._getOrderedUserIds();
        if (!userIds.length) return;

        // Determine current cursor position
        if (!this._cursor) {
            if (this._selected.size > 0) {
                const first = [...this._selected][0];
                this._cursor = { uid: first.dataset.userId, day: parseInt(first.dataset.day) };
            } else {
                const firstDay = this._getMinDay();
                this._cursor = { uid: userIds[0], day: firstDay };
            }
        }

        let dRow = 0, dCol = 0;
        switch (e.key) {
            case 'ArrowUp':    dRow = -1; break;
            case 'ArrowDown':  dRow = 1;  break;
            case 'ArrowLeft':  dCol = -1; break;
            case 'ArrowRight': dCol = 1;  break;
        }

        // Shift+Arrow → extend selection (blue), move cursor (orange)
        if (e.shiftKey) {
            this._clearKbdCursor();
            this._moveArrow(dRow, dCol);
            const cell = this._cellMap[this._cursor.uid + '-' + this._cursor.day];
            if (cell) {
                this._selectCell(cell);
                this._setKbdCursor(cell);
                this._scrollIntoView(cell);
            }
            return;
        }

        this._moveArrow(dRow, dCol);

        // Single select at new position with keyboard cursor
        const cell = this._cellMap[this._cursor.uid + '-' + this._cursor.day];
        if (cell) {
            this._removeDropdown();
            this._deselectAllSilent();
            this._clearKbdCursor();
            this._selectCell(cell);
            this._setKbdCursor(cell);
            this._lastClicked = cell;
            this._scrollIntoView(cell);
        }
    }

    _moveArrow(dRow, dCol) {
        const userIds = this._getOrderedUserIds();
        let rowIdx = userIds.indexOf(this._cursor.uid);
        let day = this._cursor.day;

        const minDay = this._getMinDay();
        const maxDay = this._getMaxDay();

        if (dRow !== 0) {
            rowIdx = Math.max(0, Math.min(userIds.length - 1, rowIdx + dRow));
        }
        if (dCol !== 0) {
            day = day + dCol;
            // Wrap to next/prev row
            if (day > maxDay) {
                day = minDay;
                rowIdx = Math.min(userIds.length - 1, rowIdx + 1);
            } else if (day < minDay) {
                day = maxDay;
                rowIdx = Math.max(0, rowIdx - 1);
            }
        }

        this._cursor = { uid: userIds[rowIdx], day };
    }

    _getMinDay() {
        if (this._minDay) return this._minDay;
        let min = 999;
        for (const key of Object.keys(this._cellMap)) {
            const d = parseInt(key.split('-')[1]);
            if (d < min) min = d;
        }
        this._minDay = min;
        return min;
    }

    _getMaxDay() {
        if (this._maxDay) return this._maxDay;
        let max = 0;
        for (const key of Object.keys(this._cellMap)) {
            const d = parseInt(key.split('-')[1]);
            if (d > max) max = d;
        }
        this._maxDay = max;
        return max;
    }

    _scrollIntoView(cell) {
        const wrap = this.element.querySelector('.grafik-wrap');
        if (!wrap) return;
        const cellRect = cell.getBoundingClientRect();
        const wrapRect = wrap.getBoundingClientRect();
        // Horizontal scroll
        if (cellRect.right > wrapRect.right) {
            wrap.scrollLeft += cellRect.right - wrapRect.right + 10;
        } else if (cellRect.left < wrapRect.left + 110) { // 110 = sticky col width
            wrap.scrollLeft -= (wrapRect.left + 110) - cellRect.left + 10;
        }
    }

    cellTargetConnected(cell) {
        const isEditor = this.canEditValue === 'true';
        const cellSkrot = cell.textContent.trim();
        const cellTyp = cellSkrot ? this.typyZmianValue.find(t => t.skrot === cellSkrot) : null;
        const isOwnPodanieCell = !isEditor
            && this.hasCurrentUserIdValue
            && parseInt(cell.dataset.userId) === this.currentUserIdValue
            && cellTyp && (cellTyp.szablonId || cellTyp.szablonPodania);

        if (!isEditor && !isOwnPodanieCell) return;

        const key = cell.dataset.userId + '-' + cell.dataset.day;
        this._cellMap[key] = cell;

        cell.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return; // only left button
            e.preventDefault();
            e.stopPropagation();

            if (isOwnPodanieCell || (!isEditor && !this._isEditorMode())) {
                // Limited mode: single select only, show podanie dropdown
                this._removeDropdown();
                this._deselectAllSilent();
                this._selectCell(cell);
                this._lastClicked = cell;
                this._openUserDropdown(cell);
                return;
            }

            this._handleMouseDown(cell, e);
        });

        cell.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    _isEditorMode() {
        return this.canEditValue === 'true';
    }

    // ─── Mouse drag logic ─────────────────────────────────────

    _handleMouseDown(cell, e) {
        this._clearKbdCursor();
        this._cursor = null;

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

    _setKbdCursor(cell) {
        if (this._kbdCursorCell && this._kbdCursorCell !== cell) {
            this._kbdCursorCell.classList.remove('kbd-cursor');
        }
        cell.classList.add('kbd-cursor');
        this._kbdCursorCell = cell;
    }

    _clearKbdCursor() {
        if (this._kbdCursorCell) {
            this._kbdCursorCell.classList.remove('kbd-cursor');
            this._kbdCursorCell = null;
        }
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

        // Check if all selected users have this dept as główny
        const selectedUserIds = new Set();
        this._selected.forEach(c => selectedUserIds.add(parseInt(c.dataset.userId)));
        const glownyIds = new Set(this.glownyUserIdsValue || []);
        const allGlowny = [...selectedUserIds].every(uid => glownyIds.has(uid));

        // Shift types
        this.typyZmianValue.forEach((typ) => {
            if (typ.tylkoGlowny && !allGlowny) return; // hide główny-only types for non-główny users

            const item = document.createElement('div');
            item.className = 'grafik-dropdown-item';
            const kbdHtml = typ.skrotKlawiaturowy ? ` <kbd style="font-size:.6rem;margin-left:4px;opacity:.7">${this._esc(typ.skrotKlawiaturowy)}</kbd>` : '';
            item.innerHTML = `<span class="color-dot" style="background:${this._esc(typ.kolor)}"></span>` +
                `<span><strong>${this._esc(typ.skrot)}</strong> ${this._esc(typ.nazwa)}` +
                `${typ.godzinyOd ? ' ' + this._esc(typ.godzinyOd) + '-' + this._esc(typ.godzinyDo) : ''}${kbdHtml}</span>`;
            item.addEventListener('click', () => {
                this._applyToSelected(typ.id);
                this._removeDropdown();
            });
            dropdown.appendChild(item);
        });

        // "Złóż podanie/wniosek" / "Pobierz podanie" — show for types with szablonPodania
        if (this.podanieUrlValue) {
            const uniqueUsers = [...selectedUserIds];
            if (uniqueUsers.length === 1) {
                // Group selected cells by their type's szablonPodania
                const podanieTypes = new Map(); // szablonPodania → {typ, cells}
                for (const c of this._selected) {
                    const skrot = c.textContent.trim();
                    const typ = skrot ? this.typyZmianValue.find(t => t.skrot === skrot) : null;
                    if (typ && (typ.szablonId || typ.szablonPodania)) {
                        const szKey = typ.szablonId ? ('id_' + typ.szablonId) : typ.szablonPodania;
                        if (!podanieTypes.has(szKey)) {
                            podanieTypes.set(szKey, { typ, cells: [] });
                        }
                        podanieTypes.get(szKey).cells.push(c);
                    }
                }

                if (podanieTypes.size > 0) {
                    dropdown.appendChild(this._makeSep());

                    for (const [szKey, { typ, cells: podanieCells }] of podanieTypes) {
                        const podanieId = podanieCells.map(c => c.dataset.podanieId).find(id => id);
                        const label = (typ.szablonPodania === 'urlop' && !typ.szablonId) ? 'podanie' : 'wniosek';

                        const podanieItem = document.createElement('div');
                        podanieItem.className = 'grafik-dropdown-item';
                        podanieItem.style.fontWeight = '600';

                        if (podanieId && this.podaniePdfUrlValue) {
                            podanieItem.style.color = '#2fb344';
                            podanieItem.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 17v-14"/><path d="M9 14l3 3l3 -3"/><path d="M5 21h14"/></svg> <span>Pobierz ' + this._esc(label) + ' (' + this._esc(typ.skrot) + ')</span>';
                            podanieItem.addEventListener('click', () => {
                                window.location.href = this.podaniePdfUrlValue.replace('__PID__', podanieId);
                            });
                        } else {
                            podanieItem.style.color = '#206bc4';
                            podanieItem.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M12 17v-6"/><path d="M9.5 14.5l2.5 2.5l2.5 -2.5"/></svg> <span>Złóż ' + this._esc(label) + ' (' + this._esc(typ.skrot) + ')</span>';
                            podanieItem.addEventListener('click', () => {
                                const uid = uniqueUsers[0];
                                const group = this._findTypeGroup(uid, typ.skrot, [...this._selected]);
                                if (!group.length) return;
                                const dataOd = group[0].date;
                                const dataDo = group[group.length - 1].date;
                                const url = this.podanieUrlValue.replace('__UID__', uid).replace('__TZID__', typ.id)
                                    + '&dataOd=' + encodeURIComponent(dataOd)
                                    + '&dataDo=' + encodeURIComponent(dataDo);
                                window.location.href = url;
                            });
                        }

                        dropdown.appendChild(podanieItem);
                    }
                }
            }
        }

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

    _openUserDropdown(anchorCell) {
        this._removeDropdown();

        const dropdown = document.createElement('div');
        dropdown.className = 'grafik-dropdown';
        dropdown.addEventListener('click', (e) => e.stopPropagation());

        const uid = anchorCell.dataset.userId;
        const podanieId = anchorCell.dataset.podanieId;
        const cellSkrot = anchorCell.textContent.trim();
        const cellTyp = cellSkrot ? this.typyZmianValue.find(t => t.skrot === cellSkrot) : null;
        const hasSzablon = cellTyp?.szablonId || cellTyp?.szablonPodania;
        const label = (cellTyp?.szablonPodania === 'urlop' && !cellTyp?.szablonId) ? 'podanie' : 'wniosek';

        if (podanieId && this.podaniePdfUrlValue) {
            const pdfItem = document.createElement('div');
            pdfItem.className = 'grafik-dropdown-item';
            pdfItem.style.fontWeight = '600';
            pdfItem.style.color = '#2fb344';
            pdfItem.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 17v-14"/><path d="M9 14l3 3l3 -3"/><path d="M5 21h14"/></svg> <span>Pobierz ' + this._esc(label) + '</span>';
            pdfItem.addEventListener('click', () => {
                window.location.href = this.podaniePdfUrlValue.replace('__PID__', podanieId);
            });
            dropdown.appendChild(pdfItem);
        }

        if (this.podanieUrlValue && cellTyp) {
            const formItem = document.createElement('div');
            formItem.className = 'grafik-dropdown-item';
            formItem.style.fontWeight = '600';
            formItem.style.color = '#206bc4';
            formItem.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M12 17v-6"/><path d="M9.5 14.5l2.5 2.5l2.5 -2.5"/></svg> <span>Złóż ' + this._esc(label) + '</span>';
            formItem.addEventListener('click', () => {
                const group = this._findTypeGroup(uid, cellSkrot, [anchorCell]);
                if (!group.length) return;
                const dataOd = group[0].date;
                const dataDo = group[group.length - 1].date;
                const url = this.podanieUrlValue.replace('__UID__', uid).replace('__TZID__', cellTyp.id)
                    + '&dataOd=' + encodeURIComponent(dataOd)
                    + '&dataDo=' + encodeURIComponent(dataDo);
                window.location.href = url;
            });
            dropdown.appendChild(formItem);
        }

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

    async _applyToSelected(typZmianyId, deselect = true) {
        const typ = this.typyZmianValue.find(t => t.id === typZmianyId);
        const isUrlop = typ && typ.skrot === 'U';
        const wolneSet = new Set(this.dniWolneValue || []);
        const typW = isUrlop ? this.typyZmianValue.find(t => t.skrot === 'W') : null;

        const cells = [...this._selected];

        if (cells.length === 1) {
            const c = cells[0];
            const day = parseInt(c.dataset.day);
            const effectiveId = (isUrlop && wolneSet.has(day) && typW) ? typW.id : typZmianyId;
            await this._upsert(c, c.dataset.userId, c.dataset.date, effectiveId);
            if (deselect) this._deselectAll();
            return;
        }

        // Split cells: working days get U, free days get W
        const wpisyU = [];
        const wpisyW = [];
        for (const c of cells) {
            const day = parseInt(c.dataset.day);
            const entry = { userId: parseInt(c.dataset.userId), data: c.dataset.date };
            if (isUrlop && wolneSet.has(day) && typW) {
                wpisyW.push(entry);
            } else {
                wpisyU.push(entry);
            }
        }

        // Batch for main type
        if (wpisyU.length > 0) {
            await this._batchApply(typZmianyId, wpisyU);
        }
        // Batch for W on free days
        if (wpisyW.length > 0) {
            await this._batchApply(typW.id, wpisyW);
        }

        if (deselect) this._deselectAll();
    }

    async _batchApply(typZmianyId, wpisy) {
        try {
            const res = await fetch(this.batchUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    departamentId: this.departamentIdValue,
                    typZmianyId,
                    wpisy,
                }),
            });
            const data = await res.json();
            if (data.success) {
                this._applyResults(data.results);
            }
        } catch (e) {
            console.error('Batch apply error:', e);
        }
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
                cell.style.color = this._contrastColor(r.kolor);
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
            if (data.error) {
                alert(data.error);
                return;
            }
            if (data.success) {
                cell.textContent = data.skrot;
                cell.style.backgroundColor = data.kolor;
                cell.style.color = this._contrastColor(data.kolor);
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

    _contrastColor(hex) {
        if (!hex) return '#ffffff';
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.55 ? '#1e293b' : '#ffffff';
    }

    _esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    /**
     * Groups cells of a given type (skrot) for a user into separate periods.
     * Two blocks belong to the same period if every day between them
     * is either a W-cell or a weekend/holiday (from dniWolne).
     * Returns the group that contains the clicked/selected cell.
     */
    _findTypeGroup(uid, skrot, selectedCells) {
        const typeDays = [];
        const bridgeDays = new Set(this.dniWolneValue || []);

        for (const [key, cell] of Object.entries(this._cellMap)) {
            if (!key.startsWith(uid + '-')) continue;
            const day = parseInt(key.split('-')[1]);
            const text = cell.textContent.trim();
            if (text === skrot) {
                typeDays.push({ day, date: cell.dataset.date });
            } else if (text === 'W') {
                bridgeDays.add(day);
            }
        }

        typeDays.sort((a, b) => a.day - b.day);
        if (!typeDays.length) return [];

        // Build groups: consecutive days bridged only by W/weekend/holiday
        const groups = [[typeDays[0]]];
        for (let i = 1; i < typeDays.length; i++) {
            const prev = typeDays[i - 1].day;
            const curr = typeDays[i].day;
            let bridged = true;
            for (let d = prev + 1; d < curr; d++) {
                if (!bridgeDays.has(d)) { bridged = false; break; }
            }
            if (bridged) {
                groups[groups.length - 1].push(typeDays[i]);
            } else {
                groups.push([typeDays[i]]);
            }
        }

        // Find group containing clicked cell's day
        const clickedDay = parseInt(selectedCells[0]?.dataset?.day);
        return groups.find(g => g.some(u => u.day === clickedDay)) || groups[0];
    }
}
