<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Kodpen API's release check (includes/fn/update.php): how a
 * GET /releases/latest answer is read into the flat release the vendor's
 * vectors use, and what the check step makes of it, against every case of
 * tests/fixtures/vendor_api/update_steps.json: the cases that stop at the
 * check stop there with the vectors' outcome and reason, every other case
 * passes it and pins the release.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function vendor_release_test_vectors()
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/vendor_api/update_steps.json'), true);
}

function vendor_release_test_set(&$array, $path, $value)
{
	$node = &$array;

	foreach (explode('.', $path) as $key) {
		$node = &$node[$key];
	}

	$node = $value;
	unset($node);
}

// What pg_vendor_release_read() gives for a vector state's answer.
function vendor_release_test_found($state)
{
	return array('status' => $state['answer_status'], 'code' => (string) $state['answer_code'], 'release' => $state['answer']);
}

// The pin the check step makes of a flat release.
function vendor_release_test_pin($release, $mode)
{
	return array(
		'mode'            => $mode,
		'version'         => $release['version'],
		'channel'         => $release['channel'],
		'build'           => $release['build'],
		'package_url'     => $release['update']['url'],
		'size'            => $release['update']['size'],
		'sha256'          => $release['update']['sha256'],
		'manifest_sha256' => $release['manifest_sha256'],
		'sig_key_id'      => $release['sig_key_id'],
		'manifest_url'    => $release['manifest_url'],
		'signature_url'   => $release['signature_url'],
	);
}

// The answer of the API itself: the flat release's update package under
// release.artifacts, beside a package of another kind.
function vendor_release_test_body($release)
{
	$api = $release;
	unset($api['update']);
	$api['artifacts'] = array(
		'update' => $release['update'],
		'full'   => array('url' => 'https://api.kodpen.com/dl/full.zip', 'size' => 52000000, 'sha256' => str_repeat('ab', 32)),
	);

	return json_encode(array('release' => $api));
}

function vendor_release_test_answer($body, $status = 200, $content_type = 'application/json; charset=utf-8')
{
	return array('ok' => ($status === 200), 'status' => $status, 'content_type' => $content_type, 'body' => $body, 'errno' => 0, 'error' => '');
}

// Every case of the vectors at the check step. Three cases describe the site
// after the check (its channel changed, it reached the version or the build
// another way): at the check it still had the baseline's value of those keys.
function test_vendor_release_check_step_follows_the_vectors()
{
	$vectors = vendor_release_test_vectors();
	$after_check = array(
		'site_channel_changed_since_check'     => array('update_channel'),
		'zip_version_not_newer_than_installed' => array('installed_version'),
		'beta_build_already_installed'         => array('installed_version', 'installed_build'),
	);

	pg_assert_true(count($vectors['cases']) >= 30, 'the vectors are read');

	foreach ($vectors['cases'] as $case) {
		$state = $vectors['baseline'];

		foreach ($case['set'] as $path => $value) {
			if (!isset($after_check[$case['id']]) || !in_array($path, $after_check[$case['id']], true)) {
				vendor_release_test_set($state, $path, $value);
			}
		}

		$decision = pg_vendor_release_offer(vendor_release_test_found($state), $state['installed_version'], $state['installed_build'], $state['update_channel'], 'update');

		if ($case['expect']['stopped_at'] === 'check') {
			pg_assert_same($case['expect']['outcome'], $decision['outcome'], $case['id']);
			pg_assert_same($case['expect']['reason'], $decision['reason'], $case['id'] . ' reason');
			pg_assert_same(null, $decision['pin'], $case['id'] . ' pins nothing');
		} else {
			pg_assert_same('update', $decision['outcome'], $case['id'] . ' passes the check');
			pg_assert_same(vendor_release_test_pin($state['answer'], 'update'), $decision['pin'], $case['id'] . ' pin');
		}
	}
}

// The API's own shape reads into the vectors' flat release.
function test_vendor_release_read_real_shaped_bodies()
{
	$vectors = vendor_release_test_vectors();
	$release = $vectors['baseline']['answer'];

	pg_assert_same(array('status' => 200, 'code' => '', 'release' => $release), pg_vendor_release_read(vendor_release_test_answer(vendor_release_test_body($release))));

	$blocked = $release;
	$blocked['version'] = '2027.3.0';
	$blocked['min_from_version'] = '2026.12.0';
	$blocked['blocked'] = array('reason' => 'min_from_version', 'stepping_stone' => '2026.12.4');

	pg_assert_same($blocked, pg_vendor_release_read(vendor_release_test_answer(vendor_release_test_body($blocked)))['release'], 'blocked');

	// A blocked release needs no package: nothing of it is pinned.
	$bare = json_encode(array('release' => array('version' => '2027.3.0', 'channel' => 'stable', 'blocked' => array('reason' => 'future_reason', 'stepping_stone' => '2026.12.4'))));
	$found = pg_vendor_release_read(vendor_release_test_answer($bare));

	pg_assert_same(array('reason' => 'future_reason', 'stepping_stone' => '2026.12.4'), $found['release']['blocked'], 'a bare blocked release');
	pg_assert_same('blocked', pg_vendor_release_offer($found, '2026.11.0', '', 'stable', 'update')['outcome']);

	// A stable release may leave build out; upper-case hex is kept lower-case.
	$api = json_decode(vendor_release_test_body($release), true);
	unset($api['release']['build']);
	$api['release']['manifest_sha256'] = strtoupper($api['release']['manifest_sha256']);
	$read = pg_vendor_release_read(vendor_release_test_answer(json_encode($api)))['release'];

	pg_assert_same('', $read['build'], 'no build');
	pg_assert_same($release['manifest_sha256'], $read['manifest_sha256'], 'lower-case sha256');
}

// A 200 that is not a whole release is no release: the check calls it
// unusable rather than reading half of it.
function test_vendor_release_read_refuses_what_is_not_a_release()
{
	$release = vendor_release_test_vectors()['baseline']['answer'];
	$broken = array(
		'version missing'         => array('version', null),
		'version not a release'   => array('version', '2026.11.1-beta'),
		'unknown channel'         => array('channel', 'nightly'),
		'build with a blank'      => array('build', 'a b'),
		'build not a string'      => array('build', 7),
		'manifest url missing'    => array('manifest_url', null),
		'signature url empty'     => array('signature_url', ''),
		'short manifest sha256'   => array('manifest_sha256', 'abc'),
		'key id in capitals'      => array('sig_key_id', 'KP-2026A'),
		'key id too long'         => array('sig_key_id', 'kp-2026a-0123456789'),
		'size as text'            => array('update.size', '31000000'),
		'size zero'               => array('update.size', 0),
		'package url missing'     => array('update.url', null),
		'package sha256 not hex'  => array('update.sha256', str_repeat('g', 64)),
		'blocked without a stone' => array('blocked', array('reason' => 'min_from_version')),
		'stone not a version'     => array('blocked', array('reason' => 'min_from_version', 'stepping_stone' => 'soon')),
		'blocked not an object'   => array('blocked', true),
	);

	foreach ($broken as $label => $change) {
		$copy = $release;
		vendor_release_test_set($copy, $change[0], $change[1]);

		pg_assert_same(null, pg_vendor_release_read(vendor_release_test_answer(vendor_release_test_body($copy)))['release'], $label);
	}

	$body = vendor_release_test_body($release);

	pg_assert_same(null, pg_vendor_release_read(vendor_release_test_answer($body, 200, 'text/html'))['release'], 'not JSON');
	pg_assert_same(null, pg_vendor_release_read(vendor_release_test_answer(''))['release'], 'empty');
	pg_assert_same(null, pg_vendor_release_read(vendor_release_test_answer('[]'))['release'], 'a JSON array');
	pg_assert_same(null, pg_vendor_release_read(vendor_release_test_answer('{"release":"2026.11.1"}'))['release'], 'release not an object');
	pg_assert_same(null, pg_vendor_release_read(vendor_release_test_answer(json_encode($release)))['release'], 'the flat shape is not the API\'s');

	$api = json_decode($body, true);
	unset($api['release']['artifacts']['update']);

	pg_assert_same(null, pg_vendor_release_read(vendor_release_test_answer(json_encode($api)))['release'], 'no update package');
}

// Only a 404 release_not_found and a 403 access_blocked are answers without
// a release; every other status or problem is unusable.
function test_vendor_release_answers_without_a_release()
{
	$problem = function ($status, $code) {
		return vendor_release_test_answer(json_encode(array('type' => 'https://api.kodpen.com/problems/' . $code, 'title' => 'Title', 'status' => $status, 'code' => $code)), $status, 'application/problem+json');
	};

	$cases = array(
		array($problem(404, 'release_not_found'), 'no_update', 'release_not_found'),
		array($problem(403, 'access_blocked'), 'no_update', 'access_blocked'),
		array($problem(404, 'not_found'), 'unusable', ''),
		array($problem(403, 'seats_full'), 'unusable', ''),
		array($problem(503, 'not_live'), 'unusable', ''),
		array($problem(429, 'rate_limited'), 'unusable', ''),
		array(vendor_release_test_answer('<html></html>', 502, 'text/html'), 'unusable', ''),
		array(vendor_release_test_answer('', 0, ''), 'unusable', ''),
		array(vendor_release_test_answer('{"release":"x"}', 404, 'application/json'), 'unusable', ''),
	);

	foreach ($cases as $index => $case) {
		$decision = pg_vendor_release_offer(pg_vendor_release_read($case[0]), '2026.11.0', '', 'stable', 'update');

		pg_assert_same($case[1], $decision['outcome'], 'case ' . $index);
		pg_assert_same($case[2], $decision['reason'], 'case ' . $index . ' reason');
		pg_assert_same(null, $decision['pin'], 'case ' . $index . ' pins nothing');
	}
}

// A beta site is offered another build of its own version only when it
// records a build of it (decided in #3204); a newer version whatever the
// builds.
function test_vendor_release_beta_builds()
{
	$release = vendor_release_test_vectors()['baseline']['answer'];
	$release['channel'] = 'beta';
	$release['build'] = 'bbbbbbb.202611051500';
	$found = array('status' => 200, 'code' => '', 'release' => $release);

	pg_assert_same('no_update', pg_vendor_release_offer($found, '2026.11.1', '', 'beta', 'update')['outcome'], 'no build recorded');
	pg_assert_same('up_to_date', pg_vendor_release_offer($found, '2026.11.1', '', 'beta', 'update')['reason']);
	pg_assert_same('update', pg_vendor_release_offer($found, '2026.11.1', 'aaaaaaa.202611010900', 'beta', 'update')['outcome'], 'another build');
	pg_assert_same('no_update', pg_vendor_release_offer($found, '2026.11.1', 'bbbbbbb.202611051500', 'beta', 'update')['outcome'], 'the same build');
	pg_assert_same('update', pg_vendor_release_offer($found, '2026.11.0', '', 'beta', 'update')['outcome'], 'a newer version');
	pg_assert_same('no_update', pg_vendor_release_offer($found, '2026.11.2', 'aaaaaaa.202611010900', 'beta', 'update')['outcome'], 'an older version');

	$release['channel'] = 'stable';
	$release['build'] = '';

	pg_assert_same('no_update', pg_vendor_release_offer(array('status' => 200, 'code' => '', 'release' => $release), '2026.11.1', 'aaaaaaa.202611010900', 'stable', 'update')['outcome'], 'stable compares versions only');
}

// Repair Software: an older version is refused, the same one repairs, a
// newer one updates; both pin the repair.
function test_vendor_release_repair()
{
	$release = vendor_release_test_vectors()['baseline']['answer'];
	$found = array('status' => 200, 'code' => '', 'release' => $release);

	$older = pg_vendor_release_offer($found, '2026.11.2', '', 'stable', 'repair');

	pg_assert_same('refuse', $older['outcome']);
	pg_assert_same('older', $older['reason']);
	pg_assert_same(null, $older['pin']);

	$same = pg_vendor_release_offer($found, '2026.11.1', '', 'stable', 'repair');

	pg_assert_same('repair', $same['outcome']);
	pg_assert_same(vendor_release_test_pin($release, 'repair'), $same['pin']);

	$newer = pg_vendor_release_offer($found, '2026.11.0', '', 'stable', 'repair');

	pg_assert_same('update', $newer['outcome']);
	pg_assert_same('repair', $newer['pin']['mode']);

	// The pointer's build, whatever the site has.
	$release['channel'] = 'beta';
	$release['build'] = 'bbbbbbb.202611051500';

	pg_assert_same('repair', pg_vendor_release_offer(array('status' => 200, 'code' => '', 'release' => $release), '2026.11.1', 'bbbbbbb.202611051500', 'beta', 'repair')['outcome'], 'the same build repairs');

	pg_assert_same('blocked', pg_vendor_release_offer(array('status' => 200, 'code' => '', 'release' => array_merge($release, array('blocked' => array('reason' => 'x', 'stepping_stone' => '2026.11.0')))), '2026.4.10', '', 'beta', 'repair')['outcome'], 'blocked for a repair too');
}

function test_vendor_release_path()
{
	pg_assert_same('/releases/latest?channel=stable&from=2026.11.0', pg_vendor_release_path('stable', '2026.11.0'));
	pg_assert_same('/releases/latest?channel=beta', pg_vendor_release_path('beta', ''), 'no from without a release number');
}

function test_vendor_release_messages()
{
	$release = vendor_release_test_vectors()['baseline']['answer'];
	$found = array('status' => 200, 'code' => '', 'release' => $release);
	$message = function ($found, $installed, $build, $channel, $mode) {
		return pg_vendor_release_message(pg_vendor_release_offer($found, $installed, $build, $channel, $mode), $installed);
	};

	pg_assert_same('Downloading...', $message($found, '2026.11.0', '', 'stable', 'update'), 'an update');
	pg_assert_same('Downloading 2026.11.1...', $message($found, '2026.11.1', '', 'stable', 'repair'), 'a repair names the version');
	pg_assert_contains('2026.11.1;', $message($found, '2026.11.0', '', 'stable', 'repair'), 'a repair that updates');
	pg_assert_contains('older than this installation (2026.11.2)', $message($found, '2026.11.2', '', 'stable', 'repair'), 'older');
	pg_assert_same('There is no update available.', $message($found, '2026.11.1', '', 'stable', 'update'), 'up to date');
	pg_assert_contains('another update channel', $message($found, '2026.11.0', '', 'beta', 'update'), 'another channel');

	$blocked = $found;
	$blocked['release']['version'] = '2027.3.0';
	$blocked['release']['blocked'] = array('reason' => 'min_from_version', 'stepping_stone' => '2026.12.4');
	$text = $message($blocked, '2026.11.0', '', 'stable', 'update');

	pg_assert_contains('2027.3.0', $text, 'the blocked release');
	pg_assert_contains('2026.12.4', $text, 'its stepping stone');

	$closed = array('status' => 403, 'code' => 'access_blocked', 'release' => null);

	pg_assert_same('Service access is closed for this site.', $message($closed, '2026.11.0', '', 'stable', 'update'));

	$none = array('status' => 404, 'code' => 'release_not_found', 'release' => null);

	pg_assert_same('There is no update available.', $message($none, '2026.11.0', '', 'stable', 'update'));
	pg_assert_contains('cannot be repaired now', $message($none, '2026.11.0', '', 'stable', 'repair'));

	$beta = $found;
	$beta['release']['channel'] = 'beta';
	$beta['release']['build'] = 'bbbbbbb.202611051500';

	pg_assert_same('Downloading 2026.11.1 (bbbbbbb.202611051500)...', $message($beta, '2026.11.1', '', 'beta', 'repair'), 'the build a repair installs is named');
}
