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

    // Per-button confirmation (forms with several submit buttons): <button data-confirm-button="...">
    document.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-confirm-button]');
        if (btn && !window.confirm(btn.dataset.confirmButton)) {
            event.preventDefault();
        }
    });

    // Print / back buttons on print pages (no inline JavaScript allowed by the CSP).
    document.querySelectorAll('.js-print').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
    document.querySelectorAll('.js-back').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); history.back(); }); });

    // Client-side filter for long tables: <input class="js-table-filter" data-target="#tableId">
    document.querySelectorAll('.js-table-filter').forEach(function (input) {
        const table = document.querySelector(input.dataset.target);
        if (!table) { return; }
        input.addEventListener('input', function () {
            const q = input.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                tr.style.display = q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        });
    });

    // Rubric editor: live sum of active weights.
    const sumEl = document.getElementById('weightSum');
    if (sumEl) {
        const recalc = function () {
            let sum = 0;
            document.querySelectorAll('.js-weight').forEach(function (w) {
                const row = w.closest('tr');
                const active = row.querySelector('.js-weight-active');
                if (active && active.checked && w.value !== '' && row.querySelector('input[name$="[name_th]"]').value.trim() !== '') {
                    sum += parseFloat(w.value) || 0;
                }
            });
            sumEl.textContent = sum.toFixed(2);
            sumEl.classList.toggle('text-danger', Math.abs(sum - 100) > 0.001);
            sumEl.classList.toggle('text-success', Math.abs(sum - 100) <= 0.001);
        };
        document.addEventListener('input', recalc);
        document.addEventListener('change', recalc);
        recalc();
    }

    // Evaluation form: live criterion scores and total (display only; the server recalculates).
    const totalEl = document.getElementById('evalTotal');
    if (totalEl) {
        const recalcEval = function () {
            let total = 0;
            let complete = true;
            document.querySelectorAll('#evalForm tbody tr').forEach(function (tr) {
                const checked = tr.querySelector('.js-rating:checked');
                const cell = tr.querySelector('.js-criterion-score');
                if (!checked) { complete = false; cell.textContent = '-'; return; }
                const s = Math.round(parseInt(checked.value, 10) * parseFloat(checked.dataset.weight) * 20) / 100;
                cell.textContent = s.toFixed(2);
                total += s;
            });
            totalEl.textContent = complete ? total.toFixed(2) : total.toFixed(2) + ' *';
        };
        document.addEventListener('change', function (e) { if (e.target.classList.contains('js-rating')) { recalcEval(); } });
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
