<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the background renderer (includes/fn/background_render.php): a
 * render that throws an Error or an Exception is reported, not fatal; the
 * output buffers a failed render left open are closed; the borrowed $_GET
 * values, the session token placeholder and the rendering flag are put back
 * whether the render returned or threw.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/background_render.php');

function test_background_render_returns_the_html()
{
	$result = pg_background_render(function () {
		return '<p>Merhaba</p>';
	});

	pg_assert_same(array('html' => '<p>Merhaba</p>', 'error' => ''), $result);
}

function test_background_render_catches_an_error()
{
	$result = pg_background_render(function () {
		$missing = null;
		return $missing->name();
	});

	pg_assert_same('', $result['html']);
	pg_assert_true($result['error'] !== '', 'an Error carries its message');
}

function test_background_render_catches_an_exception()
{
	$result = pg_background_render(function () {
		throw new RuntimeException('Sayfa yok');
	});

	pg_assert_same(array('html' => '', 'error' => 'Sayfa yok'), $result);
}

function test_background_render_closes_the_buffers_a_failed_render_left()
{
	$level = ob_get_level();

	pg_background_render(function () {
		ob_start();
		ob_start();
		echo 'yarım';
		throw new RuntimeException('kesildi');
	});

	pg_assert_same($level, ob_get_level());
}

function test_background_render_keeps_the_callers_buffer()
{
	ob_start();
	echo 'önce';
	$level = ob_get_level();

	pg_background_render(function () {
		ob_start();
		throw new RuntimeException('kesildi');
	});

	$after = ob_get_level();
	$kept = ob_get_clean();

	pg_assert_same($level, $after);
	pg_assert_same('önce', $kept);
}

function test_background_render_puts_back_the_get_values()
{
	$saved = $_GET;
	$_GET = array('page' => 'eski', 'keep' => '1');

	$seen = array();
	pg_background_render(function () use (&$seen) {
		$seen = $_GET;
		throw new Error('kırıldı');
	}, array('page' => 'urun', 'r' => 5));

	$after = $_GET;
	$_GET = $saved;

	pg_assert_same(array('page' => 'urun', 'keep' => '1', 'r' => '5'), $seen);
	pg_assert_same(array('page' => 'eski', 'keep' => '1'), $after);
}

function test_background_render_unsets_the_token_it_lent()
{
	$had_session = isset($_SESSION);
	$saved = $had_session ? $_SESSION : null;
	$_SESSION = array();

	$seen = null;
	pg_background_render(function () use (&$seen) {
		$seen = isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : 'none';
		return '';
	});

	$token_after = isset($_SESSION['software']['token']);

	if ($had_session) {
		$_SESSION = $saved;
	} else {
		unset($_SESSION);
	}

	pg_assert_same('', $seen);
	pg_assert_false($token_after, 'the token key is removed, not left blank');
}

function test_background_render_keeps_an_existing_token()
{
	$had_session = isset($_SESSION);
	$saved = $had_session ? $_SESSION : null;
	$_SESSION = array('software' => array('token' => 'abc'));

	pg_background_render(function () {
		return '';
	});

	$token = $_SESSION['software']['token'];

	if ($had_session) {
		$_SESSION = $saved;
	} else {
		unset($_SESSION);
	}

	pg_assert_same('abc', $token);
}

function test_background_render_restores_the_rendering_flag()
{
	$seen = null;
	pg_seo_rendering(true);

	pg_background_render(function () use (&$seen) {
		$seen = pg_seo_rendering();
		throw new Error('kırıldı');
	});

	$after = pg_seo_rendering();
	pg_seo_rendering(false);

	pg_assert_true($seen, 'output_error() throws during the render');
	pg_assert_true($after, 'a caller that had the flag on keeps it on');

	pg_background_render(function () {
		return '';
	});

	pg_assert_false(pg_seo_rendering(), 'a caller that had it off gets it off');
}
