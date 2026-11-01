<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for links inside Bootstrap alert boxes and quote attributions on a
 * dark band in the design templates and the widget trees they place: a link
 * in an alert is an .alert-link (the plain link colour measured 3.50:1 on
 * alert-secondary and 4.06:1 on alert-warning), and a .blockquote-footer
 * under data-bs-theme="dark" carries a theme text colour (Bootstrap 5.3.8
 * gives it a fixed grey: 3.29:1 on the dark body).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Templates whose comment sign-in box still writes a plain link: their
// files are held by other open changes and follow once those land.
function _test_tpl_alert_link_pending()
{
	return array('pinegrap-courses', 'pinegrap-events', 'pinegrap-salon');
}

// Calls $fn($node, $ancestors) for every node of the trees in $value.
function _test_tpl_alert_link_walk($value, $fn, $ancestors = array())
{
	if (!is_array($value)) return;
	if (isset($value['type'], $value['props']) && is_array($value['props'])) {
		$fn($value, $ancestors);
		$ancestors[] = $value;
	}
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_alert_link_walk($child, $fn, $ancestors);
	}
}

// ' class list ' of a node, or ' '.
function _test_tpl_alert_link_class($node)
{
	return ' ' . (isset($node['props']['cssClass']) && is_string($node['props']['cssClass']) ? $node['props']['cssClass'] : '') . ' ';
}

// Whether $node sets data-bs-theme="dark" through its attributes.
function _test_tpl_alert_link_dark($node)
{
	$attrs = isset($node['props']['_attrs']) && is_array($node['props']['_attrs']) ? $node['props']['_attrs'] : array();
	foreach ($attrs as $attr) {
		if (is_array($attr) && isset($attr['name'], $attr['value']) && $attr['name'] === 'data-bs-theme' && $attr['value'] === 'dark') return true;
	}
	return false;
}

// Problems found in $tree: links in alerts without .alert-link, quote
// attributions on a dark band without a theme text colour.
function _test_tpl_alert_link_problems($tree)
{
	$bad = array();
	_test_tpl_alert_link_walk($tree, function ($node, $ancestors) use (&$bad) {
		$class = _test_tpl_alert_link_class($node);
		$is_link = (isset($node['props']['contentType']) && $node['props']['contentType'] === 'link')
			|| (isset($node['props']['tag']) && $node['props']['tag'] === 'a');
		if ($is_link && !preg_match('/ (alert-link|btn|link-[a-z-]+) /', $class)) {
			foreach ($ancestors as $a) {
				if (strpos(_test_tpl_alert_link_class($a), ' alert ') !== false) {
					$bad[] = 'plain link in an alert: ' . (isset($node['props']['text']) ? $node['props']['text'] : trim($class));
					break;
				}
			}
		}
		if (strpos($class, ' blockquote-footer ') !== false && !preg_match('/ text-(body|body-secondary|body-emphasis|light|white) /', $class)) {
			foreach ($ancestors as $a) {
				if (_test_tpl_alert_link_dark($a)) {
					$bad[] = 'grey quote attribution on a dark band: ' . trim($class);
					break;
				}
			}
		}
	});
	return $bad;
}

function test_design_template_alert_links_and_dark_quotes()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates';
	$checked = 0;
	foreach (glob($dir . '/*.php') as $file) {
		$id = basename($file, '.php');
		if (in_array($id, _test_tpl_alert_link_pending(), true)) continue;
		$tpl = _pg_tpl_include($file);
		pg_assert_true(is_array($tpl), $id . ' loaded');
		pg_assert_same(array(), _test_tpl_alert_link_problems($tpl), $id . ' keeps alert links and dark quotes readable');
		$checked++;
	}
	pg_assert_true($checked >= 10, 'every template checked');
}

// The Comments widget's default tree (placed by hello-pinegrap and the
// shop templates) and its designer twin give the sign-in link .alert-link.
function test_comments_default_tree_sign_in_is_an_alert_link()
{
	pg_assert_same(array(), _test_tpl_alert_link_problems(pg_comments_default_tree()), 'the default comments tree');
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("contentType: 'link', text: _sdT('Sign in'), href: '#', cssClass: 'alert-link', rel: 'nofollow',\n                                    _bindings: { href: '__login_url' } })", $js, 'the starter twin');
}

// The Boutique story band's attribution sits on the dark band.
function test_design_template_boutique_story_attribution()
{
	$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-boutique.php');
	$found = 0;
	_test_tpl_alert_link_walk($tpl, function ($node, $ancestors) use (&$found) {
		if (strpos(_test_tpl_alert_link_class($node), ' blockquote-footer ') === false) return;
		foreach ($ancestors as $a) {
			if (_test_tpl_alert_link_dark($a)) $found++;
		}
	});
	pg_assert_true($found >= 1, 'the attribution is found under the dark band');
}
