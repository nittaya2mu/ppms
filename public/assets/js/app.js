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

    // Presentation form (แบบฟอร์ม 2): live item points, total and letter grade (display only; the server recalculates).
    const presTotal = document.getElementById('presTotal');
    if (presTotal) {
        const scale = [[80, 'A'], [75, 'B+'], [70, 'B'], [65, 'C+'], [60, 'C'], [55, 'D+'], [50, 'D'], [0, 'F']];
        const recalcPres = function () {
            let total = 0;
            let complete = true;
            document.querySelectorAll('.js-grade').forEach(function (sel) {
                const cell = sel.closest('tr').querySelector('.js-grade-points');
                const opt = sel.options[sel.selectedIndex];
                if (!opt || opt.value === '') { complete = false; cell.textContent = '-'; return; }
                const pts = Math.round(parseFloat(opt.dataset.point) / 4 * parseFloat(sel.dataset.max) * 100) / 100;
                cell.textContent = pts.toFixed(2);
                total += pts;
            });
            total = Math.round(total * 100) / 100;
            presTotal.textContent = total.toFixed(2) + (complete ? '' : ' *');
            const letter = scale.find(function (s) { return total >= s[0]; });
            document.getElementById('presLetter').textContent = complete && letter ? letter[1] : '-';
        };
        document.addEventListener('change', function (e) { if (e.target.classList.contains('js-grade')) { recalcPres(); } });
        recalcPres();
    }

    // Scope form (แบบฟอร์ม 7): live weight total.
    const scopeTotal = document.getElementById('scopeTotal');
    if (scopeTotal) {
        const recalcScope = function () {
            let sum = 0;
            document.querySelectorAll('.js-scope-weight').forEach(function (i) { sum += parseFloat(i.value) || 0; });
            sum = Math.round(sum * 100) / 100;
            scopeTotal.textContent = sum + '%';
            scopeTotal.classList.toggle('text-danger', sum !== 100);
            scopeTotal.classList.toggle('text-success', sum === 100);
        };
        document.addEventListener('input', function (e) { if (e.target.classList.contains('js-scope-weight')) { recalcScope(); } });
        recalcScope();
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
