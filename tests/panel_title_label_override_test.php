<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel binds every [title] without .no-popover as a Bootstrap 6
 * popover, and Bootstrap's Tooltip._fixTitle() copies the title into
 * aria-label on an element with no aria-label and no text. On a form field
 * that also has a <label>, that aria-label replaces the label as the field's
 * accessible name, and the hint shows only on mouse hover. A labelled field
 * therefore carries no title: a hint that repeats the label is dropped, a
 * real hint is printed under the field with aria-describedby, and a field
 * whose label is only an icon gets its own aria-label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Fields not fixed yet, as file => ids. The list only shrinks.
function panel_title_label_override_known()
{
	return array(
		'editor_select_page_or_file.php' => array('folder_id', 'access_control_type'),
		'includes/workspace/workdays.php' => array('ws_hol_to'),
		'view_ads.php' => array('ad_region_id'),
		'view_contacts.php' => array('filter', 'contact_group', 'multiselect-checkbox-checker-0'),
		'view_files.php' => array('filter_select'),
		'view_pages.php' => array('filter_select'),
		'view_submitted_forms.php' => array('custom_form', 'custom_form_fake', 'multiselect-checkbox-checker-0'),
		'view_users.php' => array('filter_select'),
	);
}

// Every input, select and textarea in one source that has a title, no
// .no-popover, no aria-label, and a <label for> pointing at its id or an
// aria-labelledby. Returns "line id" strings.
function panel_title_label_override_find($source)
{
	$found = array();

	if (!preg_match_all('/<(input|select|textarea)\b[^>]*?\stitle\s*=[^>]*>/is', $source, $tags, PREG_OFFSET_CAPTURE)) {
		return $found;
	}

	foreach ($tags[0] as $tag) {
		list($markup, $offset) = $tag;

		if (preg_match('/no-popover|aria-label\s*=|type\s*=\s*\\\\?["\']?(hidden|submit|button|image|reset)\b/i', $markup)) {
			continue;
		}

		$id = '';

		// The id as written, a PHP or JS expression included.
		if (preg_match('/\sid\s*=\s*(\\\\?)(["\'])(.*?)\1\2/s', $markup, $match)) {
			$id = $match[3];
		}

		$labelled = (bool) preg_match('/aria-labelledby\s*=/i', $markup);

		if (!$labelled && $id !== '') {
			$labelled = (bool) preg_match('/\sfor\s*=\s*\\\\?["\']' . preg_quote($id, '/') . '\\\\?["\']/', $source);
		}

		if ($labelled) {
			$found[] = (substr_count(substr($source, 0, $offset), "\n") + 1) . ' ' . $id;
		}
	}

	return $found;
}

// The same over panel PHP and panel JavaScript. Returns "file:line id".
function panel_title_label_override_scan()
{
	$root = PG_FUNCTIONS_DIR;
	$found = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

		if (!preg_match('/\.(php|js)$/', $path) || preg_match('#(^|/)(lib|vendor|node_modules)/|\.min\.js$#', $path)) {
			continue;
		}

		foreach (panel_title_label_override_find(file_get_contents($file->getPathname())) as $hit) {
			$found[] = $path . ':' . $hit;
		}
	}

	return $found;
}

function test_panel_title_label_override_none()
{
	$known = panel_title_label_override_known();

	foreach (panel_title_label_override_scan() as $hit) {
		list($place, $id) = explode(' ', $hit, 2);
		$file = substr($place, 0, strrpos($place, ':'));

		if (isset($known[$file]) && in_array($id, $known[$file], true)) {
			continue;
		}

		pg_assert_true(false, $hit . ': a labelled field carries a title, whose popover replaces its label as the accessible name');
	}
}

// The finder sees a labelled field, a dynamic id and a JS-built one, and
// leaves alone a field without a label, a .no-popover one and an aria-label.
function test_panel_title_label_override_finder()
{
	$cases = array(
		'<label for="a">A</label><input id="a" title="Hint">' => array('1 a'),
		"<label for=\"' . h(\$id) . '\">A</label><input id=\"' . h(\$id) . '\" title=\"x\">" => array("1 ' . h(\$id) . '"),
		'html += \'<label for="b">B</label><select id="b" title="Hint"></select>\';' => array('1 b'),
		'<input aria-labelledby="h" title="Hint">' => array('1 '),
		'<input id="c" title="Hint">' => array(),
		'<label for="d">D</label><input id="d" class="no-popover" data-bs-toggle="tooltip" title="Hint">' => array(),
		'<label for="e">E</label><select id="e" aria-label="E" title="Hint"></select>' => array(),
		'<label for="f">F</label><input type="hidden" id="f" title="x">' => array(),
	);

	foreach ($cases as $source => $expected) {
		pg_assert_same($expected, panel_title_label_override_find($source), $source);
	}
}

// The icon-labelled filter selects name themselves, and the hint of the
// translation engine select is a tooltip that keyboard focus shows too.
function test_panel_title_label_override_named_selects()
{
	$products = file_get_contents(PG_FUNCTIONS_DIR . '/view_products.php');
	pg_assert_true((bool) preg_match('/<select id="filter_select" aria-label="\' \. h\(lang\(\'Content that viewed\'\)\) \. \'"/', $products), 'view_products: #filter_select has its aria-label');

	$builder = file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	pg_assert_true((bool) preg_match('/<select id="status" aria-label="\' \. h\(lang\(\'Enabled\'\)\) \. \'"/', $builder), 'product_builder: #status has its aria-label');

	$translations = file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');
	pg_assert_true((bool) preg_match('/id="pg_tr_engine" data-bs-toggle="tooltip"/', $translations), 'translations: #pg_tr_engine hint is a tooltip');
}
