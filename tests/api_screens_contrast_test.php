<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the API applications screen, the API documentation and the API
 * console: accent text on the emphasis inks, secondary text on the secondary
 * text colour instead of an opacity.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function api_screens_contrast_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The rule body of one selector in an inline stylesheet, or '' when missing.
function api_screens_contrast_rule($source, $selector)
{
	if (preg_match('/^' . preg_quote($selector, '/') . ' \{([^}]*)\}/m', $source, $match) !== 1) {
		return '';
	}

	return $match[1];
}

// Status pills, log codes and webhook errors read the emphasis inks.
function test_api_screens_contrast_settings_inks()
{
	$api = api_screens_contrast_source('api_settings.php');

	$inks = array(
		'.api-status.on' => 'success',
		'.api-status.warn' => 'warning',
		'.api-log .s.ok' => 'success',
		'.api-log .s.bad' => 'danger',
		'.api-meta small.bad' => 'danger',
		'.api-hook-error' => 'danger',
	);

	foreach ($inks as $selector => $tone) {
		$rule = api_screens_contrast_rule($api, $selector);
		pg_assert_true(strpos($rule, 'color: var(--pg-' . $tone . '-fg-emphasis)') !== false,
			$selector . ' reads the ' . $tone . ' ink');
	}
}

// Secondary text on the API applications screen is not faded.
function test_api_screens_contrast_settings_without_opacity()
{
	$api = api_screens_contrast_source('api_settings.php');

	foreach (array('.api-head', '.api-status.off', '.api-meta', '.api-meta small', '.api-empty',
		'.api-perm-text span', '.api-log .d, .api-log .t') as $selector) {
		$rule = api_screens_contrast_rule($api, $selector);
		pg_assert_true($rule !== '', $selector . ' is found');
		pg_assert_true(strpos($rule, 'opacity') === false, $selector . ' is not faded');
		pg_assert_true(strpos($rule, 'color: var(--pg-fg-2)') !== false, $selector . ' reads the secondary text colour');
	}

	pg_assert_true(strpos($api, '.api-hook-meta.fg-danger { opacity: 1; }') !== false,
		'a blocked subscription line shows the danger ink at full strength');
	pg_assert_true(strpos($api, 'opacity-50') === false, 'no text on the screen sits at half opacity');
}

// Method labels and headings shared by the API documentation and the console.
function test_api_screens_contrast_docs()
{
	$view = api_screens_contrast_source('includes/api/console_view.php');

	foreach (array('.doc-m.post' => 'success', '.doc-m.get' => 'primary', '.doc-m.delete' => 'danger') as $selector => $tone) {
		pg_assert_true(strpos(api_screens_contrast_rule($view, $selector), 'color: var(--pg-' . $tone . '-fg-emphasis)') !== false,
			$selector . ' reads the ' . $tone . ' ink');
	}

	foreach (array('.doc-nav-group', '.doc-group-h', '.doc-also', '.doc-params-h') as $selector) {
		$rule = api_screens_contrast_rule($view, $selector);
		pg_assert_true($rule !== '', $selector . ' is found');
		pg_assert_true(strpos($rule, 'opacity') === false, $selector . ' is not faded');
		pg_assert_true(strpos($rule, 'color: var(--pg-fg-2)') !== false, $selector . ' reads the secondary text colour');
	}

	pg_assert_true(strpos($view, 'opacity-50') === false, 'no documentation text sits at half opacity');
}

// The panel's API Documentation screen and the console page.
function test_api_screens_contrast_docs_pages()
{
	$docs = api_screens_contrast_source('api_docs.php');
	$console = api_screens_contrast_source('includes/api/console.php');

	pg_assert_true(strpos($docs, 'opacity-50') === false, 'API Documentation has no half-faded text');
	pg_assert_true(strpos($docs, '<p class="small fg-2 mb-0 mt-3">') !== false, 'the console link line is secondary text, not faded');
	pg_assert_true(strpos($console, 'opacity-50') === false, 'the console has no half-faded text');

	foreach (array('.con-head .con-sub', '#doc_empty') as $selector) {
		$rule = api_screens_contrast_rule($console, $selector);
		pg_assert_true($rule !== '', $selector . ' is found');
		pg_assert_true(strpos($rule, 'opacity') === false, $selector . ' is not faded');
		pg_assert_true(strpos($rule, 'color: var(--pg-fg-2)') !== false, $selector . ' reads the secondary text colour');
	}
}
