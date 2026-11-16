<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shopping cart's closed blocks: the applied offers box, the
 * campaign gifts box and the quick add box are built by pure builders in
 * includes/fn/widgets_cart.php, and the designer canvas draws their twins
 * from assets/js/style_designer.src.js. The twins are cut out and run under
 * Node (skipped on a machine without Node) and compared with the builders
 * byte for byte, for a cart with applied offers, a campaign gift and a quick
 * add group.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cart_blocks_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function cart_blocks_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut twins; null without Node.
function cart_blocks_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = cart_blocks_test_source();
	$script = "function esc(s) { if (!s && s !== 0) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n"
		. "function _sdMoney(n) { return '$' + Number(n).toFixed(2); }\n";
	foreach (array('_sdCartAppliedOffersHtml', '_sdCartPendingRecipientHtml', '_sdCartPendingRowHtml', '_sdCartPendingOffersHtml',
		'_sdCartQuickAddOptionHtml', '_sdCartQuickAddHtml', '_sdCartSectionHtml') as $fn) {
		$script .= cart_blocks_test_function($source, $fn) . "\n";
	}
	$script .= 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgcart');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The applied offers box is the one the cart printed before the builder:
// one line per offer, the heading singular or plural, nothing for none.
function test_cart_blocks_applied_offers()
{
	pg_assert_same('', pg_cart_applied_offers_html(array()), 'no offer, no box');
	pg_assert_same('<div class="pg-cart-applied-offers alert alert-success py-2 px-3 mb-2">'
		. '<div class="fw-semibold small mb-1"><i class="bi bi-tag-fill me-1"></i>' . h(lang('Applied Offers')) . '</div>'
		. '<ul class="mb-0 ps-3 small"><li>10% off all shirts</li><li>Free shipping &amp; returns</li></ul></div>',
		pg_cart_applied_offers_html(array('10% off all shirts', 'Free shipping & returns')), 'the box as the cart printed it');
	pg_assert_contains(h(lang('Applied Offer')) . '</div>', pg_cart_applied_offers_html(array('One')), 'one offer: the singular heading');

	$drawn = cart_blocks_test_run('[_sdCartAppliedOffersHtml([]), _sdCartAppliedOffersHtml(["One"]), _sdCartAppliedOffersHtml(["10% off all shirts", "Free shipping & returns"])]');
	if ($drawn !== null) {
		pg_assert_same(array('', pg_cart_applied_offers_html(array('One')), pg_cart_applied_offers_html(array('10% off all shirts', 'Free shipping & returns'))),
			$drawn, 'the canvas box is the site\'s');
	}
}

// The campaign gifts box: a gift row with its Add button, the recipient as
// decided, as a picker or a picker with a new name; the canvas draws each
// the same.
function test_cart_blocks_campaign_gifts()
{
	$base = 'pending_offer_7_3';
	$site = array(
		pg_cart_pending_offer_recipient_html($base, 'Ayşe Demir', array(), false),
		pg_cart_pending_offer_recipient_html($base, '', array('' => '', 'Mia' => 'Mia'), false),
		pg_cart_pending_offer_recipient_html($base, '', array('' => '', 'myself' => 'myself', 'Mia & Co' => 'Mia & Co'), true),
		pg_cart_pending_offer_row_html(7, 3, 'A free tote bag', ''),
		pg_cart_pending_offer_row_html(7, 3, 'A free tote bag (blue)', '<span>x</span>'),
		pg_cart_pending_offers_html(pg_cart_pending_offer_row_html(7, 3, 'A free tote bag', ''), '/pinegrap/cart_action.php', '/cart?x=1&y=2', ''),
	);
	pg_assert_contains('name="add_pending_offer_7_3" value="1">' . h(lang('Add')) . '</button>', $site[3], 'the Add button posts the field cart_action.php reads');
	pg_assert_contains('<input type="hidden" name="pending_offers" value="true">', $site[5], 'the box carries the pending offers flag');
	pg_assert_contains('name="pending_offer_7_3_add_name" maxlength="50"', $site[2], 'a new name may be typed');

	$drawn = cart_blocks_test_run('['
		. '_sdCartPendingRecipientHtml("pending_offer_7_3", "Ayşe Demir", [], false), '
		. '_sdCartPendingRecipientHtml("pending_offer_7_3", "", [["", ""], ["Mia", "Mia"]], false), '
		. '_sdCartPendingRecipientHtml("pending_offer_7_3", "", [["", ""], ["myself", "myself"], ["Mia & Co", "Mia & Co"]], true), '
		. '_sdCartPendingRowHtml(7, 3, "A free tote bag", ""), '
		. '_sdCartPendingRowHtml(7, 3, "A free tote bag (blue)", "<span>x</span>"), '
		. '_sdCartPendingOffersHtml(_sdCartPendingRowHtml(7, 3, "A free tote bag", ""), "/pinegrap/cart_action.php", "/cart?x=1&y=2", "")]');
	if ($drawn !== null) {
		pg_assert_same($site, $drawn, 'the canvas gifts box is the site\'s');
	}
}

// The quick add box: products with their data, one out of stock, the
// quantity, amount and recipient rows, with and without a heading.
function test_cart_blocks_quick_add()
{
	$options = pg_cart_quick_add_option_html(31, 'Gift wrap - Recycled paper ($4.90)', 'quantity', 0, 2, true)
		. pg_cart_quick_add_option_html(32, 'Donation', 'donation', 1, 0, true)
		. pg_cart_quick_add_option_html(33, 'Card <sold out>', 'none', 0, 0, false);
	pg_assert_same('<option value="31" data-qty="1" data-amount="0" data-recipient="0" data-default-qty="2">Gift wrap - Recycled paper ($4.90)</option>'
		. '<option value="32" data-qty="0" data-amount="1" data-recipient="1" data-default-qty="0">Donation</option>'
		. '<option value="33" data-qty="0" data-amount="0" data-recipient="0" data-default-qty="0" disabled>Card &lt;sold out&gt;</option>',
		$options, 'the options as the cart printed them');
	$site = array(
		$options,
		pg_cart_quick_add_html('Quick Add', $options, true, true, array('Mia', 'Ali & Can'), '₺', '/pinegrap/cart_action.php', '/cart', ''),
		pg_cart_quick_add_html('', $options, false, false, null, '', '/pinegrap/cart_action.php', '/cart', ''),
	);
	pg_assert_contains('<input type="hidden" name="quick_add" value="1">', $site[1], 'the box posts quick_add');
	pg_assert_contains('<span class="input-group-text">₺</span>', $site[1], 'the amount row carries the currency symbol');
	pg_assert_false(strpos($site[2], 'pg-qa-ship-to') !== false, 'no recipient row when no product asks for one');

	$drawn = cart_blocks_test_run('(function () { var o = _sdCartQuickAddOptionHtml(31, "Gift wrap - Recycled paper ($4.90)", "quantity", 0, 2, true)'
		. ' + _sdCartQuickAddOptionHtml(32, "Donation", "donation", 1, 0, true) + _sdCartQuickAddOptionHtml(33, "Card <sold out>", "none", 0, 0, false);'
		. ' return [o, _sdCartQuickAddHtml("Quick Add", o, true, true, ["Mia", "Ali & Can"], "₺", "/pinegrap/cart_action.php", "/cart", ""),'
		. ' _sdCartQuickAddHtml("", o, false, false, null, "", "/pinegrap/cart_action.php", "/cart", "")]; })()');
	if ($drawn !== null) {
		pg_assert_same($site, $drawn, 'the canvas quick add box is the site\'s');
	}
}

// The canvas sample follows the widget's settings: no quick add group, no
// box; the heading as set, empty for none; the offers and gifts always drawn.
function test_cart_blocks_canvas_sample()
{
	$drawn = cart_blocks_test_run('[_sdCartSectionHtml("quick_add", {}), _sdCartSectionHtml("quick_add", { quick_add_product_group_id: "4" }),'
		. ' _sdCartSectionHtml("quick_add", { quick_add_product_group_id: 4, quick_add_label: "" }),'
		. ' _sdCartSectionHtml("applied_offers", {}), _sdCartSectionHtml("pending_offers", {}), _sdCartSectionHtml("other", {})]');
	if ($drawn === null) {
		return;
	}
	pg_assert_same('', $drawn[0], 'no group chosen: the site prints no box, nor does the canvas');
	pg_assert_contains('<i class="bi bi-lightning-charge me-1"></i>Quick Add</div>', $drawn[1], 'the default heading');
	pg_assert_false(strpos($drawn[2], 'bi-lightning-charge') !== false, 'an empty heading prints none');
	pg_assert_contains('<li>10% off all shirts</li>', $drawn[3]);
	pg_assert_contains('name="add_pending_offer_7_3"', $drawn[4]);
	pg_assert_same('', $drawn[5]);

	$source = cart_blocks_test_source();
	pg_assert_contains("_getSystemWidgetRegionType(node) === 'shopping_cart') {\n                    var _cartSample = _sdCartSectionSample(_sec, doc, _sysWidgetRenderCfg);", $source,
		'an empty section box in a shopping cart draws the sample');
	pg_assert_contains("var d = _sdCartSectionSample('applied_offers', doc, null);", $source, 'the applied offers content node draws it too');
	pg_assert_contains("var d = _sdCartSectionSample('applied_offers', doc, null);\n                d.classList.add('sd-editor-ph');", $source, 'as editor chrome, out of the canvas audit');
	pg_assert_contains('_sdCartPendingRecipientHtml(\'pending_offer_7_3\'', $source, 'the sample gift asks where it goes, as a waiting gift does');
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	pg_assert_contains('$applied_offers_html  = pg_cart_applied_offers_html($_pg_offer_labels);', $php, 'the cart builds its box with the builder');
	pg_assert_contains('return pg_cart_pending_offers_html($rows,', $php, 'the gifts box too');
	pg_assert_contains('return pg_cart_quick_add_html($label, $options,', $php, 'and the quick add box');
}

// The markup development printed, fixed here so the builders cannot drift
// from it in CI: the quick add box of a group with a product that asks for
// a quantity and where it goes, one out of stock, a donation asking for an
// amount (taken from development's _pg_render_quick_add() on a sandbox
// cart, its words put back through lang()); two applied offers (the plural
// heading); a gift row whose recipient the cart already decided.
function test_cart_blocks_markup_as_development_printed()
{
	$quick = strtr('<form class="pg-cart-quick-add card card-body mb-3" method="post" action="/pinegrap/cart_action.php"><input type="hidden" name="quick_add" value="1"><input type="hidden" name="send_to" value="/sepet"><div class="fw-semibold mb-2"><i class="bi bi-lightning-charge me-1"></i>Quick Add</div><div class="row g-2 align-items-end"><div class="col-12 col-sm"><label class="form-label small mb-1 visually-hidden" for="pg-qa-product">%PRODUCT%</label><select class="form-select form-select-sm" id="pg-qa-product" name="quick_add_product_id"><option value=""></option><option value="41" data-qty="1" data-amount="0" data-recipient="1" data-default-qty="0">CGB-076 - Platin Hediye Sepeti (₺29,95) - GERI SIPARIŞ EDILEN RN. Bununla birlikte, bugün ha...</option><option value="42" data-qty="1" data-amount="0" data-recipient="1" data-default-qty="0" disabled>CGB-077 - Premium Hediye Sepeti (₺49,95) - STOK TKENDI</option><option value="43" data-qty="1" data-amount="0" data-recipient="1" data-default-qty="0">CGB-078 - Mega Hediye Sepeti (₺79,95)</option><option value="29" data-qty="0" data-amount="1" data-recipient="0" data-default-qty="0">D10 - Düzenli Bağış</option></select></div><div class="col-6 col-sm-3 pg-qa-row pg-qa-qty" style="display:none"><label class="form-label small mb-1" for="pg-qa-qty">%QTY%</label><input type="text" inputmode="numeric" class="form-control form-control-sm" id="pg-qa-qty" name="quick_add_quantity" value="1" size="4"></div><div class="col-6 col-sm-3 pg-qa-row pg-qa-amount" style="display:none"><label class="form-label small mb-1" for="pg-qa-amount">%AMT%</label><div class="input-group input-group-sm"><span class="input-group-text">₺</span><input type="text" inputmode="decimal" class="form-control" id="pg-qa-amount" name="quick_add_amount" size="6"></div></div><div class="col-12 col-sm-4 pg-qa-row pg-qa-ship-to" style="display:none"><label class="form-label small mb-1" for="pg-qa-ship-to">%SHIP%</label><select class="form-select form-select-sm" id="pg-qa-ship-to" name="quick_add_ship_to"><option value=""></option><option value="%ME%">%ME%</option><option value="Mia">Mia</option></select><input type="text" class="form-control form-control-sm mt-1" name="quick_add_add_name" maxlength="50" placeholder="%NEW%"></div><div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">%ADD%</button></div></div></form>', array(
		'%PRODUCT%' => h(lang('Product')), '%QTY%' => h(lang('Quantity')), '%AMT%' => h(lang('Amount')),
		'%SHIP%' => h(lang('Ship to')), '%ME%' => h(lang('myself')), '%NEW%' => h(lang('or add name')), '%ADD%' => h(lang('Add')),
	));
	$options = pg_cart_quick_add_option_html(41, 'CGB-076 - Platin Hediye Sepeti (₺29,95) - GERI SIPARIŞ EDILEN RN. Bununla birlikte, bugün ha...', 'quantity', 1, 0, true)
		. pg_cart_quick_add_option_html(42, 'CGB-077 - Premium Hediye Sepeti (₺49,95) - STOK TKENDI', 'quantity', 1, 0, false)
		. pg_cart_quick_add_option_html(43, 'CGB-078 - Mega Hediye Sepeti (₺79,95)', 'quantity', 1, 0, true)
		. pg_cart_quick_add_option_html(29, 'D10 - Düzenli Bağış', 'donation', 0, 0, true);
	pg_assert_same($quick, pg_cart_quick_add_html('Quick Add', $options, true, true, array('Mia'), '₺', '/pinegrap/cart_action.php', '/sepet', ''),
		'the quick add box as development printed it: quantity, amount and recipient rows, an option out of stock');

	pg_assert_same('<div class="pg-cart-applied-offers alert alert-success py-2 px-3 mb-2"><div class="fw-semibold small mb-1"><i class="bi bi-tag-fill me-1"></i>'
		. h(lang('Applied Offers')) . '</div><ul class="mb-0 ps-3 small"><li>Yaz indirimi: siparişe %10</li><li>Her siparişe hediye kupa</li></ul></div>',
		pg_cart_applied_offers_html(array('Yaz indirimi: siparişe %10', 'Her siparişe hediye kupa')), 'two offers: the plural heading');

	pg_assert_same('<div class="pg-cart-pending-offer d-flex flex-wrap align-items-end gap-2 py-2 border-bottom"><div class="flex-grow-1 min-width-0"><span class="fw-semibold">Her siparişe hediye kupa</span></div>'
		. '<div class="pg-cart-pending-offer-recipient"><span class="small text-body-secondary">' . h(lang('Ship to')) . ' <span class="fw-semibold">Mia</span></span></div>'
		. '<div><button type="submit" class="btn btn-primary btn-sm" name="add_pending_offer_12_15" value="1">' . h(lang('Add')) . '</button></div></div>',
		pg_cart_pending_offer_row_html(12, 15, 'Her siparişe hediye kupa', pg_cart_pending_offer_recipient_html('pending_offer_12_15', 'Mia', array(), false)),
		'a gift whose recipient the cart decided: named, not asked');
}
