<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Features below the Modules board as folded rows: the rows'
 * order and names, each row's line of state, every card keeping its id and
 * field names inside its row, the settings that left the old "Other
 * settings" card for their rows, no popover, units after the number, and a
 * row an anchor names arriving open.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/features_fold.php');

function settings_features_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of features.php that draws one card: from the section comment
// before its id to the next section, the fields it adds from variables too.
function settings_features_fold_test_card($id)
{
	$source = settings_features_fold_test_source('includes/settings/features.php');
	$at = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($at === false) {
		return '';
	}

	$start = strrpos(substr($source, 0, $at), '// ── ');
	$end = strpos($source, "\n// ── ", $at);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function test_settings_features_fold_order_and_names()
{
	pg_assert_same(array('search', 'sharing', 'site-push', 'maps', 'page-clicks', 'content-approval', 'speed', 'images', 'revisions', 'signature', 'badge'), pg_features_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_features_fold_order() as $key) {
		$face = pg_features_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
		pg_assert_false(stripos($face['name'], 'Feature Options') !== false, $key . ': never Feature Options');
	}

	pg_assert_same(array('Site search', 'Sharing buttons', 'Visitor notifications', 'Maps', 'Clicks and scrolling', 'Content approval', 'Speed', 'Images', 'Page revisions', 'Signature time stamp', 'Badge'), $names, 'the rows\' names');
}

function test_settings_features_fold_lines()
{
	pg_assert_same('Advanced search · 2 synonym groups · 1 promoted result', pg_features_fold_search(true, 2, 1)['line'], 'the search type and the tuning');
	pg_assert_same('Simple search', pg_features_fold_search(false, null, null)['line'], 'no tuning before its table');
	pg_assert_same(null, pg_features_fold_search(true, 0, 0)['pill'], 'no pill');

	$sharing = pg_features_fold_sharing(true, false, array('Facebook', 'LinkedIn'));
	pg_assert_same('Facebook, LinkedIn', $sharing['line'], 'the services chosen');
	pg_assert_same(array('On', 'pg-tone-teal'), $sharing['pill'], 'an On pill');
	pg_assert_same('Your own code', pg_features_fold_sharing(true, true, array('Facebook'))['line'], 'Advanced: the own code');
	pg_assert_same('No service chosen', pg_features_fold_sharing(false, false, array())['line'], 'nothing ticked');
	pg_assert_same(array('Off', 'pg-tone-gray'), pg_features_fold_sharing(false, false, array())['pill'], 'an Off pill');

	pg_assert_same('Visitors may subscribe · app mode off', pg_features_fold_site_push(true, false)['line'], 'notifications on');
	pg_assert_same('No new subscription is taken · app mode on', pg_features_fold_site_push(false, true)['line'], 'notifications off');

	pg_assert_same('OpenStreetMap tiles', pg_features_fold_maps(true, false)['line'], 'maps on');
	$maps_off = pg_features_fold_maps(false, true);
	pg_assert_same('Off: the Map of places shows its list alone · Your own tile address', $maps_off['line'], 'with Maps off, the line says so and its fields are kept');
	pg_assert_same(array('Off', 'pg-tone-gray'), $maps_off['pill'], 'and its pill');

	pg_assert_same('Clicks and scroll depth are counted', pg_features_fold_page_clicks(true)['line'], 'clicks on');
	pg_assert_same('Nothing new is counted', pg_features_fold_page_clicks(false)['line'], 'clicks off');

	pg_assert_same('Changes to published pages wait for approval · also asked in a channel', pg_features_fold_content_approval(true, true)['line'], 'approval on, with a channel');
	pg_assert_same('Changes are published as they are saved', pg_features_fold_content_approval(false, true)['line'], 'approval off');

	pg_assert_same('Pages kept 300 seconds · menu links fetched ahead · requests timed', pg_features_fold_speed(300, false, true, true)['line'], 'the page cache, prefetch and monitoring');
	pg_assert_same('Page cache off while Mobile view is on', pg_features_fold_speed(300, true, false, false)['line'], 'Mobile view turns the page cache off');
	pg_assert_same('Page cache off', pg_features_fold_speed(0, false, false, false)['line'], 'page cache off');
	pg_assert_same('requests timed', pg_features_fold_speed(null, false, false, true)['line'], 'no page cache on this install');
	pg_assert_same('Nothing is sped up', pg_features_fold_speed(null, false, false, false)['line'], 'nothing on');

	pg_assert_same('Offered as WebP, AVIF · Imagick', pg_features_fold_images(array('WebP', 'AVIF'), 'Imagick')['line'], 'the formats and the library');
	pg_assert_same('Pictures sent as uploaded · GD', pg_features_fold_images(array(), 'GD')['line'], 'no modern format');
	$images = pg_features_fold_images(array('WebP'), '');
	pg_assert_same('No image library on this server', $images['line'], 'no library');
	pg_assert_same(array('Not ready', 'pg-tone-amber'), $images['pill'], 'said by its pill');

	pg_assert_same('The last 30, and every one from the last 90 days', pg_features_fold_revisions(30, 90)['line'], 'the two limits');

	pg_assert_same('Stamped by tsa.example', pg_features_fold_signature('https://tsa.example/tsr')['line'], 'the authority\'s host');
	pg_assert_same('No time stamp is taken', pg_features_fold_signature('  ')['line'], 'no authority');

	pg_assert_same('Members are marked "Member"', pg_features_fold_badge('Member')['line'], 'the default label');
	pg_assert_same('No default label', pg_features_fold_badge('')['line'], 'no label');

	pg_assert_false(pg_features_fold_images(array(), '')['attention'], 'no row asks for attention: the pane arrives with Site search open');
}

function test_settings_features_fold_pane_is_board_and_rows()
{
	$pane = settings_features_fold_test_source('includes/settings/features.php');

	pg_assert_contains('$pg_settings_cards[] = pg_settings_fold($features_rows);', $pane, 'the rows are one list of folded rows');
	pg_assert_same(2, substr_count($pane, '$pg_settings_cards[]'), 'the board and the rows, no card beside them');
	pg_assert_false(strpos($pane, 'pg-features-other') !== false, 'the Other settings card is gone');
	pg_assert_false(strpos($pane, "lang('Other settings')") !== false, 'and its heading');
	pg_assert_same(0, substr_count($pane, 'popover'), 'no popover is left');
	pg_assert_same(0, substr_count($pane, 'class="switch"'), 'no old switch drawing');
	pg_assert_false(strpos($pane, 'font-monospace') !== false, 'the tile address is shown in the text font');
	pg_assert_false(strpos($pane, 'border rounded') !== false, 'no framed box inside a row');

	foreach (array('Feature Options', 'Search Tuning', 'Image Optimization', 'Page Cache\')') as $old) {
		pg_assert_false(strpos($pane, "lang('" . $old) !== false, 'no "' . $old . '" heading');
	}

	foreach (pg_features_fold_order() as $key) {
		pg_assert_contains("\$features_parts['" . $key . "'] = array('html' => ", $pane, $key . ': a part of the rows');
	}

	// The board's Settings opens the row the module's settings sit in.
	foreach (array('sharing', 'site-push', 'maps', 'page-clicks', 'content-approval') as $key) {
		pg_assert_contains("'settings' => 'pg-fold-" . $key . "'", $pane, $key . ': the board leads to its row');
	}
}

function test_settings_features_fold_cards_keep_ids_and_names()
{
	$cards = array(
		'pgset-search-tuning'    => array('search_type', 'search_synonyms', 'search_promoted', 'search_tuning_card'),
		'pgset-sharing'          => array('social_networking_type', 'social_networking_facebook', 'social_networking_email', 'social_networking_code'),
		'pgset-site-push'        => array('site_push_card', 'site_push_app_on'),
		'pgset-place-map'        => array('place_map_card', 'place_map_tiles', 'place_map_credit', 'place_map_key'),
		'pgset-page-clicks'      => array('page_clicks_card'),
		'pgset-content-approval' => array('content_approval_card', 'content_approval_channel'),
		'pgset-page-cache'       => array('page_cache', 'page_cache_ttl', 'speculation_prefetch', 'perf_monitor'),
		'pgset-images'           => array('image_product_optimize', 'image_quality_webp', 'image_quality_avif', 'image_upload_max_dimension'),
		'pgset-revisions'        => array('page_revisions_keep', 'page_revisions_days'),
		'pgset-signature'        => array('signature_tsa_url', 'signature_tsa_auth', 'signature_tsa_password'),
		'pgset-badge'            => array('badge_label'),
	);

	foreach ($cards as $id => $names) {
		$card = settings_features_fold_test_card($id);
		pg_assert_true($card !== '', $id . ' keeps its card');

		foreach ($names as $name) {
			pg_assert_contains('name="' . $name . '"', $card, $id . ': ' . $name . ' posts from its row');
		}
	}

	// Site Search Type, Prefetch Menu Links, Performance Monitoring and
	// Default Badge Label left the old card for their rows.
	$search = settings_features_fold_test_card('pgset-search-tuning');
	pg_assert_contains('<div class="pg-seg flex-wrap">', $search, 'the search type is a segmented control');
	pg_assert_contains('<label class="pg-seg-item" for="search_type_advanced">', $search, 'with its two choices');
	pg_assert_true(strpos($search, 'search_type_help') !== false, 'its help under it');

	$sharing = settings_features_fold_test_card('pgset-sharing');
	pg_assert_contains('<label class="pg-seg-item" for="social_networking_type_simple">', $sharing, 'Setup is a segmented control');
	pg_assert_same(8, substr_count($sharing, 'class="pg-checkbox"'), 'the services as checkboxes');
	pg_assert_same(8, substr_count($sharing, 'class="sm:col-6 '), 'in two columns');
	pg_assert_contains('data-bs-target="#social_networking_code_row"', $sharing, 'Advanced shows the code');

	$speed = settings_features_fold_test_card('pgset-page-cache');
	pg_assert_true(strpos($speed, 'id="page_cache"') < strpos($speed, 'id="speculation_prefetch"') && strpos($speed, 'id="speculation_prefetch"') < strpos($speed, 'id="perf_monitor"'), 'the page cache, prefetch, then monitoring');

	// A unit stands after the number in every language.
	$pane = settings_features_fold_test_source('includes/settings/features.php');
	pg_assert_false(strpos($pane, '$image_quality_adorn') !== false, 'no unit before the number');
	pg_assert_same(2, substr_count($pane, '<label class="form-control form-adorn form-adorn-end">
                                    <span class="form-adorn-text" aria-hidden="true">%</span>'), 'both qualities take % after the number');
	pg_assert_same(0, substr_count($pane, '<label class="form-control form-adorn">'), 'every unit is a suffix');
	pg_assert_contains("\$features_parts['images'] = array('html' => \$output_image_settings_card)", $pane, 'Images holds the image card');

	$save = settings_features_fold_test_source('includes/settings/features.save.php');
	pg_assert_false(strpos($save, 'pg_settings_fold') !== false, 'the save knows nothing of rows');

	foreach (array("post_value('search_type')", "post_value('badge_label')", "post_value('speculation_prefetch')", "post_value('perf_monitor')", "array_key_exists('search_tuning_card', \$_POST)") as $read) {
		pg_assert_contains($read, $save, 'the save reads ' . $read);
	}
}

function test_settings_features_fold_registry_anchors_and_open_row()
{
	$source = settings_features_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'features' => array(");
	$features = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);
	$pane = settings_features_fold_test_source('includes/settings/features.php');

	foreach (array('pgset-features', 'pgset-search-tuning', 'pgset-sharing', 'pgset-site-push', 'pgset-place-map', 'pgset-page-clicks', 'pgset-content-approval', 'pgset-page-cache', 'pgset-images', 'pgset-revisions', 'pgset-signature', 'pgset-badge') as $id) {
		pg_assert_same(2, preg_match_all("/'" . $id . "' +=> /", $features), $id . ' is a section with its keywords');
		pg_assert_contains('id="' . $id . '"', $pane, $id . ' is drawn');
	}

	pg_assert_contains("'pgset-page-cache' => lang('Speed'),", $features, 'a section is named as its row');
	pg_assert_contains("'pgset-images'   => lang('Images'),", $features);
	pg_assert_contains("lang('badge, member, label, comments')", $features, 'the search finds the badge in its row');
	pg_assert_contains('prefetch, speculation rules', substr($features, strpos($features, "'pgset-page-cache' => pg_settings_keywords")), 'and prefetch in Speed');

	// Old links land on the cards; the client opens the row a card sits in.
	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('features', 'pgset-images'), $anchors['pgset-images'], 'an old Image link');
	pg_assert_same(array('features', 'pgset-signature'), $anchors['pgset-signature'], 'an old signature link');
	pg_assert_contains('window.pgSettingsFoldReveal(section);', settings_features_fold_test_source('assets/js/backend.src.js'), 'an anchor opens its row');

	$rows = array();

	foreach (array('search' => 'pgset-search-tuning', 'speed' => 'pgset-page-cache', 'badge' => 'pgset-badge') as $key => $id) {
		$rows[] = array('key' => $key, 'html' => '<div id="' . $id . '" class="pg-set-card"><div class="card"><div class="card-body"><label for="' . $key . '_f">' . $key . ' field</label><input id="' . $key . '_f"></div></div></div>')
			+ pg_features_fold_face($key) + pg_features_fold_badge('');
	}

	$html = pg_settings_fold($rows);
	$labels = pg_settings_card_labels($html, array('pgset-search-tuning', 'pgset-page-cache', 'pgset-badge'));
	pg_assert_same(array('speed field'), $labels['pgset-page-cache'], 'a row\'s labels are its card\'s only');
	pg_assert_same(array('badge field'), $labels['pgset-badge'], 'Badge has its own labels');
	pg_assert_contains('aria-expanded="true" aria-controls="pg-fold-search-body"', $html, 'the first row arrives open');
	pg_assert_contains('id="pg-fold-speed"', $html, 'a row\'s id is pg-fold- and its key');
}
