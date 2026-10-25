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

    window.pgPasswordStrength = { check: check, isStrong: isStrong, rules: RULES };
})(window);
