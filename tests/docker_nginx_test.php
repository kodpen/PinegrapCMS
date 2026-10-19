<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * docker/nginx.conf carries the rules the installer writes for nginx
 * (pg_server_config_default('nginx')), so that the Docker image protects the
 * same folders a host does. A rule added to includes/server_config.php fails
 * here until it is in the image's server block too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The directive lines of a configuration: comments and blank lines dropped,
// indentation removed.
function docker_nginx_test_directives($text)
{
	$lines = array();

	foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
		$line = trim($line);

		if (($line === '') || ($line[0] === '#')) {
			continue;
		}

		$lines[] = $line;
	}

	return $lines;
}

function docker_nginx_test_conf()
{
	$path = dirname(PG_FUNCTIONS_DIR) . '/docker/nginx.conf';

	return is_file($path) ? (string) file_get_contents($path) : '';
}

// Every directive of the installer's nginx sample is in the image's server
// block, in the same order (a location's order decides which one answers).
function test_docker_nginx_carries_installer_rules()
{
	$conf = docker_nginx_test_conf();
	pg_assert_true($conf !== '', 'docker/nginx.conf exists');

	$sample = docker_nginx_test_directives(pg_server_config_default('nginx'));
	pg_assert_true(count($sample) > 5, 'the sample has rules');

	// The sample is for a site at / in the folder pinegrap, as in the image.
	pg_assert_contains('location / {', $sample);

	$directives = docker_nginx_test_directives($conf);
	$position = -1;

	foreach ($sample as $line) {
		$found = -1;

		for ($i = $position + 1; $i < count($directives); $i++) {
			if ($directives[$i] === $line) {
				$found = $i;
				break;
			}
		}

		pg_assert_true($found >= 0, 'in order: ' . $line);

		if ($found >= 0) {
			$position = $found;
		}
	}
}

// PHP is reached only after the deny rules: the first regular expression
// location that matches answers, so data/ and includes/ never run.
function test_docker_nginx_php_after_deny_rules()
{
	$directives = docker_nginx_test_directives(docker_nginx_test_conf());

	$deny = array_search('location ~ ^/(pinegrap/)?(data|pinegrap/includes)/ {', $directives, true);
	$php = array_search('location ~ \.php$ {', $directives, true);

	pg_assert_true(is_int($deny), 'deny rule present');
	pg_assert_true(is_int($php), 'PHP location present');
	pg_assert_true(is_int($deny) && is_int($php) && ($deny < $php), 'deny rule before the PHP location');
	pg_assert_contains('set $pinegrap_app app:9000;', $directives);
	pg_assert_contains('fastcgi_pass $pinegrap_app;', $directives);
}
