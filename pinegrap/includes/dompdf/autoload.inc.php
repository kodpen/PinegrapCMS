<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Autoloader for the vendored dompdf distribution (HTML/CSS to PDF) and its
 * dependencies. The upstream release archive ships a Composer-generated
 * vendor/autoload.php; Pinegrap does not use Composer, so this file provides
 * the equivalent PSR-4 mapping by hand. The vendor/ tree below keeps the same
 * layout as the official dompdf-<version>.zip so a future upgrade is a
 * directory swap. Bundled libraries (versions in each composer.json):
 *
 *   vendor/dompdf/dompdf              Dompdf\           (LGPL-2.1)
 *   vendor/dompdf/php-font-lib        FontLib\          (LGPL-2.1)
 *   vendor/dompdf/php-svg-lib         Svg\              (LGPL-3.0)
 *   vendor/masterminds/html5          Masterminds\      (MIT)
 *   vendor/sabberworm/php-css-parser  Sabberworm\CSS\   (MIT)
 *
 * Usage: require_once PG_FUNCTIONS_DIR . '/includes/dompdf/autoload.inc.php';
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY')) {
	exit;
}

if (defined('PG_DOMPDF_AUTOLOADED')) {
	return;
}

define('PG_DOMPDF_AUTOLOADED', true);

spl_autoload_register(function ($class) {

	static $prefixes = null;

	if ($prefixes === null) {

		$vendor = __DIR__ . '/vendor/';

		// Longest prefix first so "Sabberworm\CSS\" wins over a shorter match.
		$prefixes = array(
			'Sabberworm\\CSS\\' => $vendor . 'sabberworm/php-css-parser/src/',
			'Masterminds\\'     => $vendor . 'masterminds/html5/src/',
			'FontLib\\'         => $vendor . 'dompdf/php-font-lib/src/FontLib/',
			'Dompdf\\'          => $vendor . 'dompdf/dompdf/src/',
			'Svg\\'             => $vendor . 'dompdf/php-svg-lib/src/Svg/',
		);

	}

	// Cpdf lives outside the PSR-4 tree; Composer maps it through a classmap.
	if ($class === 'Dompdf\\Cpdf') {
		require __DIR__ . '/vendor/dompdf/dompdf/lib/Cpdf.php';
		return;
	}

	foreach ($prefixes as $prefix => $base_dir) {

		if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
			continue;
		}

		$file = $base_dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

		if (is_file($file)) {
			require $file;
		}

		return;

	}

});
