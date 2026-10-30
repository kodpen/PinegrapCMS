<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the beta build record of an installation (data/update_build.json,
 * includes/fn/update.php): when the file counts, its atomic write, read and
 * delete, the versions.php entry a re-run of the same version starts from,
 * the beta offers with and without the file, and the release tags the
 * integrity check asks GitHub for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A folder of its own under the system temp folder, removed by the caller.
function update_build_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_update_build_' . bin2hex(random_bytes(6));
	mkdir($folder);

	return $folder;
}

function update_build_test_remove($folder)
{
	foreach ((array) glob($folder . '/{,.}*', GLOB_BRACE) as $file) {
		if (is_file($file)) {
			unlink($file);
		}
	}

	@rmdir($folder);
}

// The file counts only for the version the code is; anything else is no
// build at all.
function test_update_build_parse()
{
	$none = array('version' => '', 'build' => '', 'git_ref' => '');
	$file = json_encode(array('version' => '2026.11.0', 'build' => 'bbbbbbb.202611051500', 'git_ref' => 'v2026.11.0-beta.202611051500'));

	pg_assert_same(
		array('version' => '2026.11.0', 'build' => 'bbbbbbb.202611051500', 'git_ref' => 'v2026.11.0-beta.202611051500'),
		pg_update_build_parse($file, '2026.11.0'),
		'the running version'
	);
	pg_assert_same($none, pg_update_build_parse($file, '2026.11.1'), 'another version');
	pg_assert_same($none, pg_update_build_parse('{"version":"2026.11.0","build":', '2026.11.0'), 'broken JSON');
	pg_assert_same($none, pg_update_build_parse('', '2026.11.0'), 'no file');
	pg_assert_same($none, pg_update_build_parse($file, ''), 'no code version');

	pg_assert_same(
		array('version' => '2026.11.0', 'build' => 'bbbbbbb.202611051500', 'git_ref' => ''),
		pg_update_build_parse(json_encode(array('version' => '2026.11.0', 'build' => 'bbbbbbb.202611051500')), '2026.11.0'),
		'no git_ref'
	);

	foreach (array(
		'no build'          => array('version' => '2026.11.0', 'build' => ''),
		'build not a string' => array('version' => '2026.11.0', 'build' => 7),
		'build with a blank' => array('version' => '2026.11.0', 'build' => 'a b'),
		'a list'            => array('2026.11.0', 'b'),
	) as $label => $data) {
		pg_assert_same($none, pg_update_build_parse(json_encode($data), '2026.11.0'), $label);
	}

	// A git_ref that cannot be a tag or a commit is left out, the build kept.
	pg_assert_same('', pg_update_build_parse(json_encode(array('version' => '2026.11.0', 'build' => 'b1', 'git_ref' => '../x')), '2026.11.0')['git_ref']);
}

// Written through a temporary file and a rename, read back, deleted.
function test_update_build_write_read_delete()
{
	$folder = update_build_test_folder();
	$path = $folder . '/update_build.json';

	pg_assert_true(pg_update_build_write('2026.11.0', 'bbbbbbb.202611051500', 'v2026.11.0-beta.202611051500', $path));
	pg_assert_same(
		array('version' => '2026.11.0', 'build' => 'bbbbbbb.202611051500', 'git_ref' => 'v2026.11.0-beta.202611051500'),
		pg_update_build_read($path, '2026.11.0')
	);
	pg_assert_same('', pg_update_build_read($path, '2026.11.1')['build'], 'read by another version');
	pg_assert_same(array('update_build.json'), array_values(array_diff(scandir($folder), array('.', '..'))), 'no temporary file left');

	pg_assert_true(pg_update_build_write('2026.11.0', 'ccccccc.202611061200', '', $path), 'over an older one');
	pg_assert_same('ccccccc.202611061200', pg_update_build_read($path, '2026.11.0')['build']);

	pg_assert_false(pg_update_build_write('2026.11.0', '', '', $path), 'no build is not written');
	pg_assert_false(pg_update_build_write('2026.11.0', 'a b', '', $path), 'nor a build with a blank');

	pg_assert_true(pg_update_build_delete($path));
	pg_assert_false(is_file($path));
	pg_assert_true(pg_update_build_delete($path), 'nothing to delete');
	pg_assert_same('', pg_update_build_read($path, '2026.11.0')['build'], 'no file');

	update_build_test_remove($folder);
}

// After a beta build the file names it; after a stable package it goes.
function test_update_build_for_pin()
{
	$pin = array('version' => '2026.11.0', 'channel' => 'beta', 'build' => 'bbbbbbb.202611051500', 'git_ref' => 'v2026.11.0-beta.202611051500');

	pg_assert_same(array('version' => '2026.11.0', 'build' => 'bbbbbbb.202611051500', 'git_ref' => 'v2026.11.0-beta.202611051500'), pg_update_build_for_pin($pin));

	unset($pin['git_ref']);
	pg_assert_same('', pg_update_build_for_pin($pin)['git_ref'], 'a pin without git_ref');

	pg_assert_same(null, pg_update_build_for_pin(array('version' => '2026.11.0', 'channel' => 'stable', 'build' => 'x1')), 'stable');
	pg_assert_same(null, pg_update_build_for_pin(array('version' => '2026.11.0', 'channel' => 'beta', 'build' => '')), 'beta without a build');
}

// Another build of the running version runs the upgrade again from the
// versions.php entry before it.
function test_update_build_rerun_and_previous_version()
{
	$versions = array('2026.4.9', '2026.4.10', '2026.11.0');

	pg_assert_same('2026.4.10', pg_upgrade_previous_version('2026.11.0', $versions));
	pg_assert_same('2026.4.9', pg_upgrade_previous_version('2026.4.10', $versions));
	pg_assert_same('', pg_upgrade_previous_version('2026.4.9', $versions), 'the first entry');
	pg_assert_same('', pg_upgrade_previous_version('2026.12.0', $versions), 'not listed');
	pg_assert_same('2026.4.10', pg_upgrade_previous_version('2026.11.0'), 'this installation\'s list');

	$pin = array('version' => '2026.11.0', 'channel' => 'beta', 'build' => 'ccccccc.202611061200');

	pg_assert_true(pg_vendor_update_rerun($pin, '2026.11.0', 'bbbbbbb.202611051500'), 'another build');
	pg_assert_true(pg_vendor_update_rerun($pin, '2026.11.0', ''), 'a build over the release');
	pg_assert_false(pg_vendor_update_rerun($pin, '2026.11.0', 'ccccccc.202611061200'), 'the same build');
	pg_assert_false(pg_vendor_update_rerun($pin, '2026.4.10', ''), 'a newer version upgrades anyway');
}

// Q1 (#3204): the beta channel offers another build of the running version
// only over a build file naming a beta build of that version.
function test_update_build_beta_offer_matrix()
{
	$release = array(
		'version' => '2026.11.0', 'channel' => 'beta', 'build' => 'ccccccc.202611061200',
		'update' => array('url' => 'https://api.kodpen.com/v1/p.zip', 'size' => 10, 'sha256' => str_repeat('a', 64)),
		'manifest_sha256' => str_repeat('b', 64), 'sig_key_id' => 'k1',
		'manifest_url' => 'https://api.kodpen.com/v1/m.json', 'signature_url' => 'https://api.kodpen.com/v1/m.sig',
	);
	$found = array('status' => 200, 'code' => '', 'release' => $release);
	$file = json_encode(array('version' => '2026.11.0', 'build' => 'bbbbbbb.202611051500'));
	$offer = function ($file, $installed, $mode = 'update') use ($found) {
		return pg_vendor_release_offer($found, $installed, pg_update_build_parse($file, $installed)['build'], 'beta', $mode);
	};

	pg_assert_same('update', $offer($file, '2026.11.0')['outcome'], 'a file of the running version');
	pg_assert_same('no_update', $offer('', '2026.11.0')['outcome'], 'no file');
	pg_assert_same('no_update', $offer(json_encode(array('version' => '2026.4.10', 'build' => 'b')), '2026.11.0')['outcome'], 'a file of another version');
	pg_assert_same('no_update', $offer(json_encode(array('version' => '2026.11.0', 'build' => 'ccccccc.202611061200')), '2026.11.0')['outcome'], 'the same build');
	pg_assert_same('update', $offer('', '2026.4.10')['outcome'], 'the next version without a file');

	// Repair installs the pointer's build and names it.
	$repair = $offer('', '2026.11.0', 'repair');
	pg_assert_same('repair', $repair['outcome']);
	pg_assert_same('ccccccc.202611061200', $repair['pin']['build']);
	pg_assert_contains('ccccccc.202611061200', pg_vendor_release_message($repair, '2026.11.0'));
}

// On beta the integrity check asks for the build's own ref first, then the
// release tags; one cache entry per ref.
function test_update_build_integrity_tags()
{
	pg_assert_same(array('v2026.11.0-beta.202611051500', 'v2026.11.0', 'v2026.11.0-beta'), pg_integrity_release_tags('2026.11.0', 'beta', 'v2026.11.0-beta.202611051500'));
	pg_assert_same(array('v2026.11.0', 'v2026.11.0-beta'), pg_integrity_release_tags('2026.11.0', 'beta', ''));
	pg_assert_same(array('v2026.11.0', 'v2026.11.0-beta'), pg_integrity_release_tags('2026.11.0', 'stable', 'v2026.11.0-beta.202611051500'), 'stable');

	pg_assert_same('2026.11.0', pg_integrity_reference_key('2026.11.0', ''));
	pg_assert_same('2026.11.0@v2026.11.0-beta.202611051500', pg_integrity_reference_key('2026.11.0', 'v2026.11.0-beta.202611051500'));
}
