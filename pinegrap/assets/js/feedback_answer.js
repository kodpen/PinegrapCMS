/**
 * Pinegrap - Enterprise Website Platform
 *
 * The answer page of a satisfaction survey (feedback.php). Opening a score
 * link writes nothing on the server, so a mail scanner's visit never counts;
 * this script, run by the person's browser, posts the score the link chose.
 * The comment and the extra answers go with the form's own Send button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var form = document.getElementById('fb_form');

    if (!form || (form.getAttribute('data-pg-feedback-autosave') !== '1') || !window.fetch || !window.FormData) {
        return;
    }

    var chosen = form.querySelector('input[name="score"]:checked');

    if (!chosen) {
        return;
    }

    var data = new FormData();
    data.append('action', 'score');
    data.append('token', form.elements.token.value);
    data.append('t', form.elements.t.value);
    data.append('score', chosen.value);

    // Quiet: a failed write leaves the Send button to save the answer.
    window.fetch(form.getAttribute('action'), {method: 'POST', body: data, credentials: 'same-origin'}).catch(function () {});
}());
