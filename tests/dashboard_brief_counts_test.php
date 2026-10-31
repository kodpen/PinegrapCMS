<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The dashboard greeting, the Workspace channel greeting and the Ecommerce
 * card print counts inside sentences. A count of one takes its own key
 * ("1 basket", "One task is ..."), the "There are ..." greetings take a
 * nominative link phrase so the Turkish sentence does not read
 * "5 yorumu var", and the card's counts sit inside one key each.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

/**
 * The source of a product file.
 *
 * @param string $path relative to pinegrap/
 * @return string
 */
function dashboard_brief_counts_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/**
 * The singular keys each file uses, with their Turkish values.
 *
 * @return array path => array(key => Turkish value)
 */
function dashboard_brief_counts_singular_keys()
{
	return array(
		'includes/dashboard/brief.php' => array(
			'{var:1} waiting comment{suffix:1}' => '{var:1} yorumu',
			'{var:1} comment{suffix:1} waiting for approval' => '{var:1} onay bekleyen yorum',
			'Approving the {var:1} puts it on the page it belongs to.' => '{var:1} onaylarsan ait olduğu sayfada görünmeye başlar.',
			'There is {var:1}, and publishing it keeps that page alive.' => '{var:1} var; yayınlamak o sayfayı canlı tutar.',
			'{var:1} empty product{suffix:1}' => '{var:1} ürünü',
			'{var:1} out-of-stock product{suffix:1}' => '{var:1} stoğu biten ürün',
			'Restocking the {var:1} would keep that page selling.' => '{var:1} tazelemek o sayfanın satmaya devam etmesini sağlar.',
			'There is {var:1} on the site, and that is a page that cannot sell.' => 'Sitede {var:1} var ve bu satamayan bir sayfa demek.',
			'{var:1} basket{suffix:1}' => '{var:1} sepet',
			'There is {var:1} left half full this week, and it is worth a look at who walked away.' => 'Bu hafta {var:1} yarım kalmış; kimin bıraktığına bakmakta fayda var.',
			'Someone filled {var:1} this week and left it; it is still on the list.' => 'Bu hafta doldurulup bırakılan {var:1} var, listede duruyor.',
			'{var:1} person' => '{var:1} kişi',
			'The {var:1} who joined this week has not heard from you yet.' => 'Bu hafta katılan {var:1} senden henüz haber almadı.',
			'Orders are running ahead of yesterday while {var:1} product sits out of stock.' => 'Siparişler dünün önünde giderken {var:1} ürün stoksuz duruyor.',
			'No orders today, though {var:1} basket was filled and left this week.' => 'Bugün sipariş yok ama bu hafta {var:1} sepet doldurulup bırakılmış.',
			'One person is looking around the site right now.' => 'Şu anda bir kişi siteyi geziyor.',
			'One new person joined your contacts this week.' => 'Bu hafta rehbere yeni bir kişi katıldı.',
		),
		'includes/workspace/briefing.php' => array(
			'Welcome to {var:1}: {var:2}. One person talks here.' => '{var:1} kanalına hoş geldiniz: {var:2}. Burada bir kişi konuşuyor.',
			'Welcome to {var:1}. One person talks here; the summary tab says what the channel is for.' => '{var:1} kanalına hoş geldiniz. Burada bir kişi konuşuyor; kanalın ne için olduğunu Özet sekmesi anlatıyor.',
			'One new message since your last visit, from {var:1}.' => 'Son gelişinizden bu yana bir yeni mesaj var, {var:1} tarafından.',
			'One new message since your last visit.' => 'Son gelişinizden bu yana bir yeni mesaj var.',
			'{var:1} of the {var:2} open tasks here is past its due date.' => 'Buradaki {var:2} açık görevin {var:1} tanesinin tarihi geçti.',
			'The one open task here is past its due date.' => 'Buradaki tek açık görevin tarihi geçti.',
			'One task in this channel is due today.' => 'Bu kanalda bir görevin son günü bugün.',
			'One task is open in this channel, and it is not late.' => 'Bu kanalda bir açık görev var, gecikmiş değil.',
			'One of the open tasks here is yours.' => 'Buradaki açık görevlerin biri sizde.',
			'One task here is waiting on something.' => 'Buradaki bir görev bir şey bekliyor.',
			'One task was finished here in the last seven days.' => 'Son yedi günde burada bir görev bitirildi.',
		),
		'includes/dashboard/widgets/widget_3.php' => array(
			'{var:1} product{suffix:1}' => '{var:1} ürün',
			'{var:1} piece{suffix:1}' => '{var:1} adet',
		),
	);
}

function test_dashboard_brief_counts_singular_keys_used_and_translated()
{
	$map = pg_test_language_map('tr');

	foreach (dashboard_brief_counts_singular_keys() as $path => $keys) {
		$source = dashboard_brief_counts_source($path);

		foreach ($keys as $key => $value) {
			pg_assert_true(strpos($source, "'" . $key . "'") !== false, $path . ': uses "' . $key . '"');
			pg_assert_true(isset($map[$key]) && ($map[$key] === $value), 'Turkish value of "' . $key . '"');
		}
	}
}

function test_dashboard_brief_counts_plural_only_keys_gone()
{
	$brief = dashboard_brief_counts_source('includes/dashboard/brief.php');

	foreach (array("'{var:1} waiting comments'", "'{var:1} empty products'", "'{var:1} baskets'") as $key) {
		pg_assert_true(strpos($brief, $key) === false, 'brief.php: no plural-only link phrase ' . $key);
	}

	$widget = dashboard_brief_counts_source('includes/dashboard/widgets/widget_3.php');

	pg_assert_true(strpos($widget, "lang('Product(s)')") === false, 'widget_3.php: no count glued to Product(s)');
	pg_assert_true(strpos($widget, "lang('Piece(s)')") === false, 'widget_3.php: no count glued to Piece(s)');
}

function test_dashboard_brief_counts_there_are_reads_nominative()
{
	$map = pg_test_language_map('tr');

	// The link phrase goes into the sentence as {var:1}; the sentence must not
	// repeat the words the phrase already carries.
	$pairs = array(
		array('There are {var:1}, and publishing them keeps those pages alive.', '{var:1} comment{suffix:1} waiting for approval', '5 onay bekleyen yorum var;'),
		array('There are {var:1} on the site, and each one is a page that cannot sell.', '{var:1} out-of-stock product{suffix:1}', 'Sitede 5 stoğu biten ürün var'),
	);

	foreach ($pairs as $pair) {
		$phrase = str_replace('{var:1}', '5', isset($map[$pair[1]]) ? $map[$pair[1]] : '');
		$sentence = str_replace('{var:1}', $phrase, isset($map[$pair[0]]) ? $map[$pair[0]] : '');

		pg_assert_true(strpos($sentence, $pair[2]) !== false, 'Turkish "' . $pair[0] . '" reads "' . $pair[2] . '": ' . $sentence);
	}
}
