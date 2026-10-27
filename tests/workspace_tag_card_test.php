<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a message that is one record tag and nothing else, drawn as a
 * card (ws_tag_card_html(), includes/workspace/render.php): which bodies make
 * a card, which stay a chip in the text, and that the card shows a reader no
 * more than the chip already does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');

// The tags as ws_refs_resolve() hands them to a reader who may see the order
// and to one who may not.
function workspace_tag_card_test_refs($known)
{
	$order = $known
		? array('type' => 'order', 'id' => 7, 'label' => 'Order #1007', 'url' => '/pinegrap/view_order.php?id=7', 'meta' => 'Acme · 120,00', 'status' => 'Complete', 'known' => true, 'icon' => 'bi-bag-check')
		: array('type' => 'order', 'id' => 7, 'label' => 'Order #1007', 'url' => '', 'meta' => '', 'status' => '', 'known' => false, 'icon' => 'bi-bag-check');

	return array(
		'order:7'   => $order,
		'product:3' => array('type' => 'product', 'id' => 3, 'label' => 'Blue mug', 'url' => '/pinegrap/edit_product.php?id=3', 'meta' => 'MUG-1 · 9,00', 'status' => '', 'known' => true, 'icon' => 'bi-box-seam'),
		'task:9'    => array('type' => 'task', 'id' => 9, 'label' => 'Call <Acme>', 'url' => '', 'meta' => 'Ada', 'status' => 'Open', 'known' => true, 'icon' => 'bi-check2-square'),
		'order:8'   => array('type' => 'order', 'id' => 8, 'label' => 'Deleted record', 'url' => '', 'meta' => '', 'status' => '', 'known' => false, 'icon' => 'bi-x-circle', 'gone' => true),
		'form:4'    => array('type' => 'form', 'id' => 4, 'label' => 'Contact form', 'url' => '/x', 'meta' => '', 'status' => '', 'known' => true, 'icon' => 'bi-ui-checks', 'fields' => array()),
		'user:2'    => array('type' => 'user', 'id' => 2, 'label' => '@Ada', 'url' => '', 'meta' => '', 'status' => '', 'known' => true, 'icon' => 'bi-person'),
	);
}

function test_workspace_tag_card_lone_tag_is_a_card()
{
	$refs = workspace_tag_card_test_refs(true);

	foreach (array('<#order:7>', "  <#order:7>\n", "\n<#order:7>\n\n") as $body) {
		$html = ws_tag_card_html($body, $refs);

		pg_assert_contains('class="ws-tag-card ws-tag-card-order"', $html, 'a card for ' . json_encode($body));
		pg_assert_contains('href="/pinegrap/view_order.php?id=7"', $html);
		pg_assert_contains('data-ws-ref="order:7"', $html);
		pg_assert_contains('Order #1007', $html);
		pg_assert_contains('Acme · 120,00', $html);
		pg_assert_contains('Complete', $html);
	}

	$product = ws_tag_card_html('<#product:3>', $refs);
	pg_assert_contains('ws-tag-card-product', $product);
	pg_assert_false(strpos($product, 'ws-tag-card-status') !== false, 'no status, no badge');

	// A record without a screen of its own: the card has no link, and the
	// label is escaped.
	$task = ws_tag_card_html('<#task:9>', $refs);
	pg_assert_contains('Call &lt;Acme&gt;', $task);
	pg_assert_false(strpos($task, '<a ') !== false, 'no link without an address');
}

function test_workspace_tag_card_other_bodies_stay_text()
{
	$refs = workspace_tag_card_test_refs(true);

	$bodies = array(
		'',
		'<#order:7> <#product:3>',
		"<#order:7>\n<#product:3>",
		'see <#order:7>',
		'<#order:7> please',
		'`<#order:7>`',
		"```\n<#order:7>\n```",
		'- <#order:7>',
		'> <#order:7>',
		'**<#order:7>**',
		'<@user:2>',
		'<#order:99>',
		// A form has its own card (ws_form_cards_html()).
		'<#form:4>',
		// A record that is gone keeps its muted chip.
		'<#order:8>',
	);

	foreach ($bodies as $body) {
		pg_assert_same('', ws_tag_card_html($body, $refs), 'no card for ' . json_encode($body));
	}
}

function test_workspace_tag_card_reader_without_the_right()
{
	// The reader who may not see the order gets no card: the chip they see
	// today, with nothing more.
	pg_assert_same('', ws_tag_card_html('<#order:7>', workspace_tag_card_test_refs(false)));

	$chip = ws_render_body('<#order:7>', workspace_tag_card_test_refs(false));
	pg_assert_false(strpos($chip, 'Acme') !== false, 'no customer name');
	pg_assert_false(strpos($chip, 'href') !== false, 'no link');
}
