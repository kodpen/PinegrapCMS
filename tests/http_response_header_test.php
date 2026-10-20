<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that no product file reads PHP's $http_response_header. PHP 8.5
 * deprecates that variable and reports the read when the file is compiled,
 * even when the line never runs, so one read printed "Deprecated" at the top
 * of every panel page that loaded the file. Response headers come from the
 * stream instead (stream_get_meta_data()['wrapper_data']), which works on
 * every supported version.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Embedded libraries keep their own code: dompdf assigns the variable before
// it reads it, which PHP 8.5 accepts.
function test_http_response_header_is_not_read()
{
	$skip = array('/includes/dompdf/', '/assets/lib/', '/data/');
	$found = array();
	$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($directory as $file) {
		if (substr($file->getFilename(), -4) !== '.php') {
			continue;
		}

		$path = str_replace('\\', '/', substr($file->getPathname(), strlen(PG_FUNCTIONS_DIR)));

		foreach ($skip as $prefix) {
			if (strpos($path, $prefix) === 0) {
				continue 2;
			}
		}

		$source = (string) file_get_contents($file->getPathname());

		if (strpos($source, 'http_response_header') === false) {
			continue;
		}

		// Tokens, so that a comment or a string naming the variable is no read.
		foreach (token_get_all($source) as $token) {
			if (is_array($token) && ($token[0] === T_VARIABLE) && ($token[1] === '$http_response_header')) {
				$found[] = ltrim($path, '/') . ':' . $token[2];
			}
		}
	}

	pg_assert_same(array(), $found, 'reads of $http_response_header');
}
