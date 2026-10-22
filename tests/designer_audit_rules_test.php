<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for three rules of the visual designer's audit (_validationRules in
 * assets/js/style_designer.js): an empty link or button (empty_btn), an
 * image without an alt decision (img_alt) and a form without a submit
 * control (form_no_submit). The designer's own validateTree(), its rules and
 * their helpers are cut out of the file and run under Node on node trees,
 * with the editor's page-level and canvas passes stubbed; on a machine
 * without Node the run is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function declaration of the designer, braces matched
// (the braces in these functions' strings come in pairs); '' when missing.
function designer_audit_rules_test_function($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$open = strpos($source, '{', $from);
	$depth = 0;

	for ($i = $open; $i < strlen($source); $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i - $from + 1);
			}
		}
	}

	return '';
}

// The rule list: from its declaration to the first line that closes it.
function designer_audit_rules_test_rules($source)
{
	$from = strpos($source, 'var _validationRules = [');
	$to = ($from !== false) ? strpos($source, "\n    ];\n", $from) : false;

	return ($to !== false) ? substr($source, $from, $to - $from + 7) : '';
}

// Runs $script after the audit's code and returns the JSON it prints; null
// when there is no Node.
function designer_audit_rules_test_run($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$code_js = '';

	foreach (array('_sdAttr', '_sdAuditHasText', '_sdAuditAttr', '_sdAuditHasName', '_sdAuditAltDecided', '_sdAuditFormHasSubmit', 'validateTree') as $name) {
		$found = designer_audit_rules_test_function($source, $name);
		pg_assert_true($found !== '', $name . ' is in the designer');
		$code_js .= $found . "\n";
	}

	$rules = designer_audit_rules_test_rules($source);
	pg_assert_true($rules !== '', 'the rule list is in the designer');

	$stubs = 'function _sdT(s) { return s; }'
		. 'function getNodeLabel(n) { return (n && n.props && n.props.customName) || ""; }'
		. 'function _pageLevelIssues() { return []; }'
		. 'function _sdCanvasAuditIssues() { return []; }'
		// Every case is one tree under a root; the result is the names of
		// the nodes the given rule flags.
		. 'function flagged(rule, kids) {'
		. '  return validateTree({ type: "root", props: {}, children: kids })'
		. '    .filter(function (i) { return i.rule === rule; })'
		. '    .map(function (i) { return i.nodeLabel; });'
		. '}'
		. 'function N(type, props, kids) { return { type: type, props: props, children: kids || [] }; }'
		. 'function icon() { return N("content", { contentType: "icon", iconName: "bi-x" }); }'
		. 'var hidden = [{ name: "aria-hidden", value: "true" }];';

	$file = @tempnam(sys_get_temp_dir(), 'pgsdaudit');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $stubs . "\n" . $code_js . "\n" . $rules . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A link or button is empty only when nothing gives it a name: its text
// prop (the model the ready-made blocks use), a bound text, a child that is
// read (an icon beside a visually hidden label), an image's alt, or an
// aria-label / aria-labelledby / title. Hidden ones are not looked at.
function test_designer_audit_rules_empty_button_or_link()
{
	$result = designer_audit_rules_test_run(
		'console.log(JSON.stringify(flagged("empty_btn", ['
		// Still flagged.
		. 'N("semantic", { tag: "a", href: "#", customName: "bare link" }),'
		. 'N("semantic", { tag: "a", href: "#", text: " ", customName: "blank text" }),'
		. 'N("semantic", { tag: "a", href: "#", customName: "icon only" }, [icon()]),'
		. 'N("semantic", { tag: "button", text: "", customName: "empty span" }, [N("semantic", { tag: "span", text: "" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "hidden label" }, [icon(), N("content", { contentType: "span", text: "Share", _attrs: hidden })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "markup only" }, [N("content", { contentType: "span", text: "<i class=\"bi bi-x\"></i>&nbsp;" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "blank label", _attrs: [{ name: "aria-label", value: " " }] }),'
		. 'N("semantic", { tag: "a", href: "#", customName: "decorative picture" }, [N("content", { contentType: "image", src: "/a.jpg", alt: "" })]),'
		. 'N("content", { contentType: "link", text: "", href: "#", customName: "content link" }),'
		. 'N("content", { contentType: "link", text: "", href: "#", customName: "content icon link" }, [icon()]),'
		// Not flagged.
		. 'N("semantic", { tag: "a", href: "#", text: "Headline", customName: "text prop" }),'
		. 'N("semantic", { tag: "button", text: "Notify me", customName: "button text" }),'
		. 'N("semantic", { tag: "a", href: "#", _bindings: { text: "__title" }, customName: "bound" }),'
		. 'N("semantic", { tag: "a", href: "#", customName: "icon and hidden span" }, [icon(), N("content", { contentType: "heading", tag: "span", text: "Pause", cssClass: "visually-hidden" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "nested text" }, [N("semantic", { tag: "span", cssClass: "d-flex" }, [N("content", { contentType: "paragraph", text: "Weather" })])]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "picture" }, [N("content", { contentType: "image", src: "/a.jpg", alt: "Logo" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "icon label", _attrs: [{ name: "aria-label", value: "Share" }] }, [icon()]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "titled icon" }, [N("content", { contentType: "icon", iconName: "bi-x", _attrs: [{ name: "title", value: "Close" }] })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "tooltip", tooltipEnabled: true, tooltipTitle: "Help" }),'
		. 'N("semantic", { tag: "button", cssClass: "btn-close", customName: "dismiss" }),'
		. 'N("semantic", { tag: "div", _attrs: hidden }, [N("semantic", { tag: "a", href: "#", customName: "under aria-hidden" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "shared" }, [N("shared_ref", { sharedId: 4 })]),'
		. 'N("content", { contentType: "link", text: "", href: "#", customName: "content link child" }, [icon(), N("content", { contentType: "span", text: "Next", cssClass: "visually-hidden" })]),'
		. 'N("content", { contentType: "link", text: "Contact", href: "#", customName: "content link text" })'
		. '])));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		'bare link', 'blank text', 'icon only', 'empty span', 'hidden label', 'markup only', 'blank label',
		'decorative picture', 'content link', 'content icon link',
	), $result, 'only the links and buttons with no name');
}

// An image is flagged only when it carries no alt decision: alt="" is the
// decorative one, and role="presentation" / "none", aria-hidden on it or an
// ancestor and a bound alt are decisions too. No alt prop at all is none.
function test_designer_audit_rules_image_alt_decision()
{
	$result = designer_audit_rules_test_run(
		'function img(name, extra) { var p = { contentType: "image", src: "/a.jpg", customName: name }; for (var k in extra) p[k] = extra[k]; return N("content", p); }'
		. 'console.log(JSON.stringify(flagged("img_alt", ['
		. 'img("no alt", {}),'
		. 'img("null alt", { alt: null }),'
		. 'img("other role", { _attrs: [{ name: "role", value: "img" }] }),'
		. 'img("aria-hidden false", { _attrs: [{ name: "aria-hidden", value: "false" }] }),'
		. 'img("described", { alt: "Team photo" }),'
		. 'img("decorative", { alt: "" }),'
		. 'img("bound", { _bindings: { alt: "__title" } }),'
		. 'img("presentation", { _attrs: [{ name: "role", value: "presentation" }] }),'
		. 'img("none", { _attrs: [{ name: "role", value: "none" }] }),'
		. 'img("hidden", { _attrs: hidden }),'
		. 'N("semantic", { tag: "div", _attrs: hidden }, [N("semantic", { tag: "div" }, [img("under hidden copy", {})])]),'
		. 'img("after the hidden copy", {})'
		. '])));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array('no alt', 'null alt', 'other role', 'aria-hidden false', 'after the hidden copy'), $result, 'only the images with no decision');
}

// A form is flagged when nothing in it submits it. A Button component
// counts when it is drawn as <button type="submit"> or <input
// type="submit">; drawn as a link, or as a plain button, it does not.
function test_designer_audit_rules_form_submit_control()
{
	$result = designer_audit_rules_test_run(
		'function form(name, kids) { return N("semantic", { tag: "form", customName: name }, [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "email" }] })].concat(kids)); }'
		. 'function btn(extra) { var p = { componentType: "btn", text: "Send" }; for (var k in extra) p[k] = extra[k]; return N("component", p); }'
		. 'console.log(JSON.stringify(flagged("form_no_submit", ['
		. 'form("fields only", []),'
		. 'form("link button", [btn({})]),'
		. 'form("plain button component", [btn({ btnElement: "button", btnType: "button" })]),'
		. 'form("reset input", [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "reset" }] })]),'
		. 'form("semantic button", [N("semantic", { tag: "button", text: "Send", _attrs: [{ name: "type", value: "submit" }] })]),'
		. 'form("button component", [btn({ btnElement: "button", btnType: "submit" })]),'
		. 'form("input component", [btn({ btnElement: "input", btnType: "submit" })]),'
		. 'form("submit input", [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "submit" }] })]),'
		. 'form("image input", [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "IMAGE" }] })]),'
		. 'form("nested", [N("semantic", { tag: "div", cssClass: "input-group" }, [btn({ btnElement: "button", btnType: "submit" })])])'
		. '])));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array('fields only', 'link button', 'plain button component', 'reset input'), $result, 'only the forms nothing submits');
}
