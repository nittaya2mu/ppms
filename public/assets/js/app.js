// PPMS front-end helpers (no inline scripts: Content-Security-Policy is script-src 'self').
(function () {
    'use strict';

    // Confirmation dialogs for irreversible actions (NFR-USA-04): <form data-confirm="...">
    let pendingForm = null;
    const modalEl = document.getElementById('confirmModal');
    const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') {
            return;
        }
        event.preventDefault();
        if (!modal) {
            if (window.confirm(form.dataset.confirm)) { form.dataset.confirmed = '1'; form.submit(); }
            return;
        }
        pendingForm = form;
        document.getElementById('confirmText').textContent = form.dataset.confirm;
        modal.show();
    });
    const ok = document.getElementById('confirmOk');
    if (ok) {
        ok.addEventListener('click', function () {
            if (pendingForm) { pendingForm.dataset.confirmed = '1'; pendingForm.submit(); }
            modal.hide();
        });
    }

    // Bar charts from data-chart='{"labels":[...],"values":[...]}'
    if (window.Chart) {
        Chart.defaults.font.family = "'Sarabun', system-ui, sans-serif";
        document.querySelectorAll('canvas.js-bar-chart').forEach(function (canvas) {
            let data;
            try { data = JSON.parse(canvas.dataset.chart); } catch (e) { return; }
            new Chart(canvas, {
                type: 'bar',
                data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: '#12305a', borderRadius: 4 }] },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                    maintainAspectRatio: false
                }
            });
        });
    }
})();
