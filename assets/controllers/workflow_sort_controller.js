import { Controller } from '@hotwired/stimulus'
import Sortable from 'sortablejs'

export default class extends Controller {
    static targets = ['body', 'chain']
    static values = { url: String }

    connect() {
        this.sortable = Sortable.create(this.bodyTarget, {
            handle: '.drag-handle',
            animation: 200,
            ghostClass: 'bg-blue-lt',
            onEnd: () => this.save(),
        })
    }

    disconnect() {
        if (this.sortable) {
            this.sortable.destroy()
        }
    }

    async save() {
        const rows = this.bodyTarget.querySelectorAll('tr[data-id]')
        const order = []

        rows.forEach((row, index) => {
            const id = parseInt(row.dataset.id)
            order.push({ id, kolejnosc: index + 1 })

            // Update the displayed number
            const lpCell = row.querySelector('.lp')
            if (lpCell) lpCell.textContent = index + 1
        })

        // Update the visual chain
        this.updateChain(rows)

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order }),
            })

            if (!response.ok) {
                throw new Error('Save failed')
            }
        } catch (e) {
            console.error('Failed to save order:', e)
        }
    }

    updateChain(rows) {
        if (!this.hasChainTarget) return

        const chain = this.chainTarget
        let html = '<span class="badge bg-yellow-lt py-2 px-3">Złożony</span>'
        const arrow = `<svg xmlns="http://www.w3.org/2000/svg" class="icon text-secondary" width="20" height="20" viewBox="0 0 24 24"
             stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M5 12l14 0"/>
            <path d="M15 16l4 -4"/>
            <path d="M15 8l4 4"/>
        </svg>`

        rows.forEach(row => {
            const label = row.dataset.label
            const type = row.dataset.type
            const badgeClass = type === 'department' ? 'bg-blue-lt' : 'bg-purple-lt'
            const typeLabel = type === 'department' ? 'dział' : 'rola'
            html += arrow
            html += `<span class="badge ${badgeClass} py-2 px-3">${label} <span class="ms-1 text-secondary small">(${typeLabel})</span></span>`
        })

        html += arrow
        html += '<span class="badge bg-green-lt py-2 px-3">Zatwierdzony</span>'

        chain.innerHTML = html
    }
}
