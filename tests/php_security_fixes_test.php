<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the System Status reading of whether this PHP has the PHP2Shell
 * fixes (CVE-2026-109618, CVE-2026-109619): pg_php2shell_status() decides
 * from the version and the build date, pg_php_build_time_from_info() reads
 * the build date out of phpinfo(), and the Docker image turns off the
 * session upload progress the second flaw goes through.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The state and the release to update to, for one version and build time.
function _test_php_security_fixes_state($version, $build_time = null)
{
	$status = pg_php2shell_status($version, $build_time);

	return $status['state'] . ' ' . $status['fixed_in'];
}

// A release of its line that carries the fixes, or a later line, has them
// whenever it was built.
function test_php_security_fixes_release_with_the_fix()
{
	pg_assert_same('fixed 8.2.35', _test_php_security_fixes_state('8.2.35'));
	pg_assert_same('fixed 8.3.36', _test_php_security_fixes_state('8.3.36'));
	pg_assert_same('fixed 8.3.36', _test_php_security_fixes_state('8.3.40', gmmktime(0, 0, 0, 1, 5, 2027)));
	pg_assert_same('fixed 8.4.27', _test_php_security_fixes_state('8.4.27'));
	pg_assert_same('fixed 8.5.12', _test_php_security_fixes_state('8.5.12', gmmktime(22, 54, 11, 10, 21, 2026)));
	pg_assert_same('fixed 8.5.12', _test_php_security_fixes_state('8.5.13-1+ubuntu24.04.1+deb.sury.org+1'));
	pg_assert_same('fixed 8.2.35', _test_php_security_fixes_state('8.6.0'));
	pg_assert_same('fixed 8.2.35', _test_php_security_fixes_state('9.0.1', gmmktime(0, 0, 0, 3, 1, 2028)));
}

// Built before the fixes could have existed: nothing in it carries them,
// whatever the number says about a distribution's package.
function test_php_security_fixes_built_before_the_fix()
{
	$september = gmmktime(12, 0, 0, 9, 15, 2026);

	pg_assert_same('missing 8.3.36', _test_php_security_fixes_state('8.3.30', $september));
	pg_assert_same('missing 8.5.12', _test_php_security_fixes_state('8.5.11', $september));
	pg_assert_same('missing 8.2.35', _test_php_security_fixes_state('8.1.2-1ubuntu2.27', $september));
	pg_assert_same('missing 8.2.35', _test_php_security_fixes_state('7.1.33', gmmktime(18, 22, 12, 11, 22, 2019)));
	pg_assert_same('missing 8.3.36', _test_php_security_fixes_state('8.3.36-dev', gmmktime(0, 0, 0, 9, 30, 2026)));
	pg_assert_same('missing 8.2.35', _test_php_security_fixes_state('8.6.0-dev', $september), 'a pre-release of a later line built before the fix');
}

// An older number built after the fixes were published is what a
// distribution's patched package looks like (Ubuntu 24.04 keeps 8.3.6): the
// number cannot tell, so it is not reported as missing.
function test_php_security_fixes_older_number_built_after_the_fix()
{
	pg_assert_same('likely_fixed 8.3.36', _test_php_security_fixes_state('8.3.6', gmmktime(9, 0, 0, 10, 22, 2026)));
	pg_assert_same('likely_fixed 8.2.35', _test_php_security_fixes_state('8.1.2-1ubuntu2.28', gmmktime(12, 0, 0, 10, 21, 2026)));
	pg_assert_same('likely_fixed 8.2.35', _test_php_security_fixes_state('7.4.3-4ubuntu2.29+esm5', gmmktime(0, 0, 0, 11, 2, 2026)));
}

// No build date, or one inside the weeks distributions may have had the
// fixes under embargo: the answer is the host's.
function test_php_security_fixes_cannot_tell()
{
	pg_assert_same('unknown 8.3.36', _test_php_security_fixes_state('8.3.6'));
	pg_assert_same('unknown 8.3.36', _test_php_security_fixes_state('8.3.6', gmmktime(18, 6, 38, 10, 7, 2026)));
	pg_assert_same('unknown 8.2.35', _test_php_security_fixes_state('8.0.30'));
	pg_assert_same('unknown 8.2.35', _test_php_security_fixes_state(''));
	pg_assert_same('unknown 8.2.35', _test_php_security_fixes_state('not a version', gmmktime(0, 0, 0, 1, 1, 2020)));
}

// The build date as phpinfo() prints it: "=>" on the command line, a table
// cell on a web page, the day padded with a space when it has one digit.
function test_php_security_fixes_build_time_from_phpinfo()
{
	pg_assert_same(gmmktime(18, 6, 38, 10, 7, 2026), pg_php_build_time_from_info("System => Linux\nBuild Date => Oct  7 2026 18:06:38\nBuild System => Linux"));
	pg_assert_same(gmmktime(22, 54, 11, 10, 21, 2026), pg_php_build_time_from_info('<tr><td class="e">Build Date </td><td class="v">Oct 21 2026 22:54:11 </td></tr>'));
	pg_assert_same(null, pg_php_build_time_from_info(''));
	pg_assert_same(null, pg_php_build_time_from_info('Build Date => unknown'));
	pg_assert_same(null, pg_php_build_time_from_info('Build Date => Foo 99 2026 18:06:38'));
}

// The Docker image runs a PHP the fixes reach on every rebuild, and turns off
// the session upload progress Pinegrap never uses: one of the ways in on a
// PHP without them.
function test_php_security_fixes_docker_turns_off_upload_progress()
{
	$settings = (string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/docker/php-settings.conf');

	pg_assert_same(1, preg_match('/^session\.upload_progress\.enabled\s*=\s*Off\s*$/m', $settings), 'docker/php-settings.conf turns off session.upload_progress.enabled');
}
