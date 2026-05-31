<?php
/**
 * =============================================================
 * JEEM MALL — Shared Footer Partial
 * =============================================================
 * Include at the bottom of every protected page.
 * =============================================================
 */
?>

<!-- ── Footer ─────────────────────────────────────────────────── -->
<footer style="
    text-align:center;
    padding: 1.5rem;
    color: var(--text-muted);
    font-size: 0.8rem;
    border-top: 1px solid var(--border-subtle);
    margin-top: auto;
">
    &copy; <?= date('Y') ?> JEEM MALL. All rights reserved.
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- JEEM MALL custom scripts -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<!-- ── Theme Engine ──────────────────────────────────────────── -->
<script>
/**
 * jeemToggleTheme()
 * Called by the toggle button in the navbar.
 * Switches the data-theme attribute on <html>, saves to
 * localStorage so the preference survives page navigation,
 * and updates the button icon + label instantly.
 */
function jeemToggleTheme() {
    var html    = document.getElementById('html-root');
    var current = html.getAttribute('data-theme') || 'dark';
    var next    = current === 'dark' ? 'light' : 'dark';

    // 1. Apply new theme
    html.setAttribute('data-theme', next);

    // 2. Persist across pages
    localStorage.setItem('jeemTheme', next);

    // 3. Update toggle button text + icon
    jeemUpdateToggleUI(next);
}

/**
 * jeemUpdateToggleUI(theme)
 * Syncs the toggle button icon and label to the current theme.
 */
function jeemUpdateToggleUI(theme) {
    var iconEl  = document.querySelector('#theme-toggle .toggle-icon');
    var labelEl = document.querySelector('#theme-toggle .toggle-label');
    if (!iconEl || !labelEl) return;

    if (theme === 'light') {
        iconEl.textContent  = '🌙';
        labelEl.textContent = 'Dark Mode';
    } else {
        iconEl.textContent  = '☀️';
        labelEl.textContent = 'Light Mode';
    }
}

// On every page load, sync the button UI to the saved theme
// (the data-theme was already applied in <head> — this just
// fixes the button text to match).
(function() {
    var saved = localStorage.getItem('jeemTheme') || 'dark';
    jeemUpdateToggleUI(saved);
})();
</script>
</body>
</html>

