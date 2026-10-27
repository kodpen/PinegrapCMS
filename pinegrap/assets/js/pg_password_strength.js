/**
 * Pinegrap - Enterprise Website Platform
 *
 * pgPasswordStrength: the panel's copy of validate_password_strength()
 * (includes/fn/auth.php), the rules "Require Strong Password" enforces on the
 * server. A password meter that follows that setting scores with this file,
 * so what it shows as strong is what the server accepts. Change both
 * together; tests/password_strength_test.php runs the same cases on each.
 *
 *   pgPasswordStrength.isStrong(password)   true when every rule is met
 *   pgPasswordStrength.check(password)      each rule: {length, capital, numbers, symbol}
 *   pgPasswordStrength.rules                the numbers the rules use
 *   pgPasswordStrength.score(password)      the meter's score with the setting on
 *   pgPasswordStrength.advisoryScore(pw)    Bootstrap's default score, setting off
 *   pgPasswordStrength.level(score, strict) 'weak' ... 'strong', or null
 *
 * It also drives the Bootstrap 6 Strength meters pg_password_strength_meter()
 * prints ([data-pg-strength]): Strength's own markup and classes, with the
 * level set here rather than by Bootstrap's script, whose scorer cannot be
 * given through markup and which the panel reaches only through pgUI.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (window) {
    'use strict';

    // The numbers get_strong_password_requirements() lists.
    var RULES = { length: 10, capital: 1, numbers: 2, symbol: 1 };

    function count(text, pattern) {
        var found = text.match(pattern);
        return found ? found.length : 0;
    }

    function check(password) {
        var text = (password === null || password === undefined) ? '' : String(password);

        return {
            // mb_strlen() counts characters, not UTF-16 units: an emoji is one.
            length: Array.from(text).length >= RULES.length,
            // ASCII only, as the PHP patterns (no /u): Ç or Ş is not a capital
            // letter but counts as a non-alphanumeric character.
            capital: count(text, /[A-Z]/g) >= RULES.capital,
            numbers: count(text, /[0-9]/g) >= RULES.numbers,
            symbol: count(text, /[^A-Za-z0-9]/g) >= RULES.symbol
        };
    }

    function isStrong(password) {
        var result = check(password);

        return result.length && result.capital && result.numbers && result.symbol;
    }

    // Score boundaries as Bootstrap's Strength reads them: up to the first
    // is weak, then fair, then good, above the last strong.
    var THRESHOLDS = [2, 3, 4];
    var ADVISORY_THRESHOLDS = [2, 4, 6];
    var LEVELS = ['weak', 'fair', 'good', 'strong'];
    // The colour of the message line for each level, as Bootstrap's script
    // gives it.
    var TONES = { weak: 'fg-danger', fair: 'fg-warning', good: 'fg-info', strong: 'fg-success' };

    // With "Require Strong Password" on: one point for typing anything and
    // one per rule met, so the meter reads strong exactly when isStrong() is
    // true, which is when the server accepts the password.
    function score(password) {
        var text = (password === null || password === undefined) ? '' : String(password);
        var result;

        if (text === '') {
            return 0;
        }

        result = check(text);

        return 1 + (result.length ? 1 : 0) + (result.capital ? 1 : 0) + (result.numbers ? 1 : 0) + (result.symbol ? 1 : 0);
    }

    // With the setting off: Bootstrap's default Strength scoring (minimum
    // length 8, every weight 1). It only advises; the server refuses nothing.
    function advisoryScore(password) {
        var text = (password === null || password === undefined) ? '' : String(password);
        var special = /[!@#$%^&*(),.?":{}|<>]/;
        var points = 0;

        if (text === '') {
            return 0;
        }

        points += text.length >= 8 ? 1 : 0;
        points += text.length >= 12 ? 1 : 0;
        points += /[a-z]/.test(text) ? 1 : 0;
        points += /[A-Z]/.test(text) ? 1 : 0;
        points += /\d/.test(text) ? 1 : 0;
        points += special.test(text) ? 1 : 0;
        points += /[!@#$%^&*(),.?":{}|<>].*[!@#$%^&*(),.?":{}|<>]/.test(text) ? 1 : 0;
        points += text.length >= 16 ? 1 : 0;

        return points;
    }

    function level(points, strict) {
        var bounds = strict ? THRESHOLDS : ADVISORY_THRESHOLDS;
        var i;

        if (!points) {
            return null;
        }

        for (i = 0; i < bounds.length; i++) {
            if (points <= bounds[i]) {
                return LEVELS[i];
            }
        }

        return LEVELS[LEVELS.length - 1];
    }

    function meter(root) {
        var input = document.querySelector(root.getAttribute('data-pg-strength-input'));
        var strict = root.getAttribute('data-pg-strength-strict') === '1';
        var segments = root.querySelectorAll('.strength-segment');
        var text = root.parentElement ? root.parentElement.querySelector('.strength-text') : null;
        var messages = {};

        if (!input || root.pgStrength) {
            return;
        }

        root.pgStrength = true;

        try {
            messages = JSON.parse(root.getAttribute('data-pg-strength-messages') || '{}') || {};
        } catch (e) {
            messages = {};
        }

        function update() {
            var value = input.value;
            var current = level(strict ? score(value) : advisoryScore(value), strict);
            var index = current ? LEVELS.indexOf(current) : -1;
            var i;

            if (current) {
                root.setAttribute('data-bs-strength', current);
            } else {
                root.removeAttribute('data-bs-strength');
            }

            for (i = 0; i < segments.length; i++) {
                segments[i].classList.toggle('active', i <= index);
            }

            if (text) {
                for (i = 0; i < LEVELS.length; i++) {
                    text.classList.remove(TONES[LEVELS[i]]);
                }
                text.textContent = current ? (messages[current] || '') : '';
                if (current) {
                    text.classList.add(TONES[current]);
                }
            }
        }

        input.addEventListener('input', update);
        input.addEventListener('change', update);
        update();
    }

    function start() {
        var roots = document.querySelectorAll('[data-pg-strength]');
        var i;

        for (i = 0; i < roots.length; i++) {
            meter(roots[i]);
        }
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }

    window.pgPasswordStrength = {
        check: check,
        isStrong: isStrong,
        rules: RULES,
        score: score,
        advisoryScore: advisoryScore,
        level: level,
        thresholds: THRESHOLDS
    };
})(window);
