/**
 * =============================================================
 * JEEM MALL — Global JavaScript
 * Minimal, dependency-free. Progressive enhancement only.
 * =============================================================
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Auto-dismiss alerts after 6 seconds ──────────────────
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 6000);
    });

    // ── Quantity input: prevent going below 1 ────────────────
    document.querySelectorAll('input[type="number"][name="quantity"]').forEach(input => {
        input.addEventListener('change', () => {
            if (parseInt(input.value, 10) < 1 || isNaN(parseInt(input.value, 10))) {
                input.value = 1;
            }
        });
    });

    // ── Confirm dangerous actions (delete, cancel) ────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            const msg = this.dataset.confirm || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

});
