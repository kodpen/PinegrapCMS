/**
 * Pinegrap - Enterprise Website Platform
 *
 * The scoring screen of "My reviews" (my_reviews.php?review=): saves the
 * scores and notes as they are typed (a post with autosave=1, answered in
 * JSON), keeps the weighted total under the criteria current, and opens the
 * reason field of "I know this applicant". Sending the review, Previous and
 * Next are the form's own posts.
 *
 * Loaded with a plain <script src> by the screen; no .min twin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var form = document.getElementById('mr_form');
    if (!form) {
        return;
    }

    var status = form.querySelector('[data-pg-mr-saved]');
    var totalOut = form.querySelector('[data-pg-mr-total]');
    var timer = null;
    // The words of a failed save come from the screen, translated.
    var failed = status.getAttribute('data-pg-mr-failed');

    // The weighted total as the server works it out: every criterion scored,
    // each score times its weight, over 100.
    function updateTotal() {
        var sum = 0;
        var complete = true;

        form.querySelectorAll('[data-pg-mr-criterion]').forEach(function (row) {
            var checked = row.querySelector('input[type="radio"]:checked');
            if (!checked) {
                complete = false;
                return;
            }
            sum += parseInt(checked.value, 10) * parseInt(row.getAttribute('data-weight'), 10);
        });

        totalOut.textContent = complete ? (sum / 100).toFixed(2) : '\u2014';
    }

    function save() {
        var data = new FormData(form);
        data.append('autosave', '1');
        status.textContent = '';

        fetch(form.getAttribute('action'), {method: 'POST', body: data, credentials: 'same-origin'})
            .then(function (response) {
                return response.json().catch(function () {
                    return {ok: false, message: failed};
                });
            })
            .then(function (answer) {
                status.textContent = String(answer.message || '');
            })
            .catch(function () {
                status.textContent = failed;
            });
    }

    form.addEventListener('change', function (event) {
        if (event.target.type === 'radio') {
            updateTotal();
        }
        if (event.target.name && !event.target.disabled && event.target.name !== 'conflict_reason') {
            window.clearTimeout(timer);
            save();
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.tagName === 'TEXTAREA' && event.target.name !== 'conflict_reason') {
            window.clearTimeout(timer);
            timer = window.setTimeout(save, 1200);
        }
    });

    var toggle = form.querySelector('[data-pg-mr-conflict-toggle]');
    var panel = document.getElementById('mr_conflict');

    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            var open = panel.hidden;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                panel.querySelector('textarea').focus();
            }
        });
    }
}());
