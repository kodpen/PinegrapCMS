/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Visitors overview (visitors.php): the period bar sends its form when a
 * choice is made, and every [data-pg-chart] canvas is drawn with Chart.js
 * from the JSON next to it (pg_visitors_chart(), includes/visitors/shell.php),
 * again when the theme changes. Colours are the theme's ink tokens, resolved
 * through a probe element: Chart.js cannot read light-dark() text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var charts = [];

    // ── Period bar ────────────────────────────────────────────────────────

    function periodBar() {
        var form = document.getElementById('pg_visitors_period');

        if (!form) {
            return;
        }

        var preset = document.getElementById('pg_visitors_p');
        var range = document.getElementById('pg_visitors_range');
        var from = document.getElementById('pg_visitors_from');
        var to = document.getElementById('pg_visitors_to');
        var compare = document.getElementById('pg_visitors_compare');
        var apply = form.querySelector('.pg-visitors-apply');

        // With the script running, a choice sends the form itself.
        if (apply) {
            apply.hidden = true;
        }

        var send = function () {
            if (form.requestSubmit) {
                form.requestSubmit();
            } else {
                form.submit();
            }
        };

        preset.addEventListener('change', function () {
            if (preset.value === 'custom') {
                range.hidden = false;
                from.focus();
                return;
            }

            // The range fields are not sent for a preset.
            from.disabled = true;
            to.disabled = true;
            send();
        });

        var rangeChanged = function () {
            if ((preset.value === 'custom') && from.value && to.value) {
                send();
            }
        };

        from.addEventListener('change', rangeChanged);
        to.addEventListener('change', rangeChanged);

        compare.addEventListener('change', function () {
            if (preset.value !== 'custom') {
                from.disabled = true;
                to.disabled = true;
            }

            send();
        });
    }

    // ── Charts ────────────────────────────────────────────────────────────

    function inks() {
        var probe = document.createElement('span');
        var names = ['--pg-primary-ink', '--pg-secondary-ink', '--pg-success-ink', '--pg-warning-ink', '--pg-danger-ink', '--pg-info-ink'];
        var resolve = function (name) {
            probe.style.color = 'var(' + name + ')';
            return getComputedStyle(probe).color;
        };

        document.body.appendChild(probe);

        var colours = {
            text: resolve('--pg-fg-2'),
            grid: resolve('--pg-border-color-translucent'),
            series: names.map(resolve)
        };

        probe.remove();

        return colours;
    }

    function draw() {
        if (typeof Chart === 'undefined') {
            return;
        }

        charts.forEach(function (chart) {
            chart.destroy();
        });
        charts = [];

        var colours = inks();
        var locale = document.documentElement.lang || undefined;
        var format = function (value) {
            return Number(value).toLocaleString(locale, { maximumFractionDigits: 1 });
        };

        document.querySelectorAll('canvas[data-pg-chart]').forEach(function (canvas) {
            var source = document.getElementById(canvas.getAttribute('data-pg-chart'));
            var data;

            try {
                data = JSON.parse(source ? source.textContent : '');
            } catch (e) {
                return;
            }

            var right = false;
            var datasets = data.series.map(function (series, index) {
                var colour = colours.series[index % colours.series.length];

                right = right || (series.axis === 'right');

                return {
                    label: series.label,
                    data: series.data,
                    yAxisID: series.axis,
                    borderColor: colour,
                    backgroundColor: colour,
                    borderDash: series.dashed ? [6, 4] : [],
                    pointStyle: series.dashed ? 'rectRot' : 'circle',
                    pointRadius: 2,
                    tension: 0.3,
                    spanGaps: false
                };
            });

            var axis = function (position, shown) {
                return {
                    position: position,
                    display: shown,
                    beginAtZero: true,
                    grid: { color: colours.grid, drawOnChartArea: position === 'left' },
                    ticks: { color: colours.text, callback: function (value) { return format(value); } }
                };
            };

            charts.push(new Chart(canvas, {
                type: data.type,
                data: { labels: data.labels, datasets: datasets },
                options: {
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: datasets.length > 1, position: 'bottom', labels: { color: colours.text, usePointStyle: true } },
                        tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + format(ctx.parsed.y); } } }
                    },
                    scales: {
                        x: { ticks: { color: colours.text }, grid: { color: colours.grid } },
                        left: axis('left', true),
                        right: axis('right', right)
                    }
                }
            }));
        });
    }

    function watchTheme() {
        var pending = null;
        var redraw = function () {
            clearTimeout(pending);
            pending = setTimeout(draw, 50);
        };

        new MutationObserver(redraw).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

        if (window.matchMedia) {
            var media = window.matchMedia('(prefers-color-scheme: dark)');

            if (media.addEventListener) {
                media.addEventListener('change', redraw);
            }
        }
    }

    function start() {
        periodBar();

        if (document.querySelector('canvas[data-pg-chart]')) {
            draw();
            watchTheme();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
