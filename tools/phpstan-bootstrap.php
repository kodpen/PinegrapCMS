<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Loaded by PHPStan (tools/phpstan.neon, bootstrapFiles) before it analyses
 * the product; nothing else loads it.
 *
 * The product's scripts run with pinegrap/ as the working directory, and a few
 * files include others relative to it (includes/fn/auth.php includes
 * "liveform.class.php", includes/panel/commerce.php
 * "includes/iyzipay-php/IyzipayBootstrap.php"). PHPStan looks for a relative
 * include in its own working directory, the include path and the including
 * file's folder, so pinegrap/ goes on the include path: the check then runs
 * from the repository root, where its file names are the ones GitHub needs to
 * annotate a pull request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pinegrap');
