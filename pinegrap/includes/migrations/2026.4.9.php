<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.9. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.9 - the release that follows 2026.4.8.
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only and follow the ranges
// 2026.4.8 used (general work from 9.1, ERP 9.58-9.69 and from 9.90, API
// 9.70-9.79, the workspace 9.80-9.89 and 9.110-9.119); the order of the calls
// is what runs.
function upgrade_to_2026_4_9() {

	upgrade_2026_4_9_translation_index();       // 9.1

	upgrade_2026_4_9_template_blog_roles();     // 9.2

}

// What a page drawn in another language answered the search engines
// (2026.4.9, 9.1; pg_tr_page_verdict_keep() and pg_tr_indexable() in
// includes/translate/seo.php). page_translations.indexable is 1 when the
// page's robots meta let it be indexed, 0 when it said noindex, and NULL
// until the page is drawn in the language again after its translations
// changed. Under the "reviewed" index policy sitemap.xml and the hreflang
// alternates follow it, so they offer exactly the language addresses the
// pages themselves allow.
//
// page_translations is a cache of drawn bodies - any change to a language's
// translations empties it - and can be as large as the page table times the
// languages. It is emptied before the column is added, so the ALTER has no
// rows to copy on servers that rebuild the table; the bodies are drawn again
// on the next visits. Only while the column is missing: a second run finds it
// and leaves the cache alone.
function upgrade_2026_4_9_translation_index() {

	if (!install_table_exists('page_translations')) {
		install_skipped(lang(array('string' => 'table {var:1} does not exist, skipped', 'vars' => 'page_translations')));
		return;
	}

	if (!install_column_exists('page_translations', 'indexable')) {
		db("DELETE FROM page_translations");
	}

	install_add_column('page_translations', 'indexable', "TINYINT(1) UNSIGNED NULL DEFAULT NULL");

	if (function_exists('pg_schema_cache_clear')) {
		pg_schema_cache_clear();
	}

	install_note('Languages: sitemap.xml and the hreflang alternates offer a page in another language only when the page itself lets search engines index it.');

}

// The roles of the design templates' blog form (2026.4.9, 9.2;
// pg_cf_with_role() in includes/fn/custom_form.php). The five templates built
// their blog form - title, summary, cover_image, content - without RSS roles,
// so every post page carried the post page's own title and description, the
// share picture was the site's, and the blog lists announced no feed
// (pg_sw_page_form_feed_widget() needs a field with a role). The templates now
// give title the "title" role, summary "description" and cover_image "media";
// this step gives them to the forms sites already installed.
//
// A form is one of those when it has the four fields with their types and no
// field carries a role yet; fields the operator added do not matter, a role
// the operator chose is never replaced. The roles are written twice, where
// they live: on the controls of the custom_form widget tree
// (shared_components.tree_json, props._cf.rss_field - what the next page save
// reads) and on the form's form_fields rows (what the pages read now). The
// tree is decoded as objects and encoded back with the editor's flags, so an
// empty {} stays an object. A second run finds the roles and changes nothing.
function upgrade_2026_4_9_template_blog_roles() {

	$roles = array('title' => 'title', 'summary' => 'description', 'cover_image' => 'media');

	if (!install_table_exists('form_fields') || !install_column_exists('form_fields', 'rss_field')) {
		install_skipped(lang(array('string' => 'table {var:1} does not exist, skipped', 'vars' => 'form_fields')));
		return;
	}

	$widgets = 0;

	if (install_table_exists('shared_components') && install_column_exists('shared_components', 'tree_json') && install_column_exists('shared_components', 'system_region_config')) {

		$rows = db_items("SELECT id, tree_json FROM shared_components WHERE system_region_config LIKE '%\"regionType\":\"custom_form\"%'");

		foreach ((array) $rows as $row) {

			$tree = json_decode((string) $row['tree_json']);

			if (!is_object($tree)) {
				continue;
			}

			// The controls by lower-cased name: the first control of a name
			// is the field, as in _pg_cf_controls().
			$controls = array();
			$has_role = false;

			$walk = function ($node) use (&$walk, &$controls, &$has_role) {
				if (!is_object($node)) {
					return;
				}

				$props = (isset($node->props) && is_object($node->props)) ? $node->props : null;
				$tag = ($props && isset($props->tag)) ? strtolower((string) $props->tag) : '';

				if ($props && in_array($tag, array('input', 'textarea', 'select'), true)) {
					$name = '';
					$type = 'text';

					foreach ((isset($props->_attrs) && is_array($props->_attrs)) ? $props->_attrs : array() as $attr) {
						if (is_object($attr) && isset($attr->name, $attr->value)) {
							if (strtolower((string) $attr->name) === 'name') {
								$name = mb_strtolower(trim((string) $attr->value), 'UTF-8');
							} elseif (strtolower((string) $attr->name) === 'type') {
								$type = strtolower(trim((string) $attr->value));
							}
						}
					}

					if (isset($props->_cf) && is_object($props->_cf) && isset($props->_cf->rss_field) && ((string) $props->_cf->rss_field !== '')) {
						$has_role = true;
					}

					if (($name !== '') && !isset($controls[$name])) {
						$controls[$name] = array('node' => $node, 'kind' => ($tag === 'input') ? $type : $tag);
					}
				}

				if (isset($node->children) && is_array($node->children)) {
					foreach ($node->children as $child) {
						$walk($child);
					}
				}
			};

			$walk($tree);

			$shape = array('title' => 'text', 'summary' => 'textarea', 'cover_image' => 'file', 'content' => 'textarea');
			$match = !$has_role;

			foreach ($shape as $name => $kind) {
				if (!isset($controls[$name]) || ($controls[$name]['kind'] !== $kind)) {
					$match = false;
				}
			}

			if (!$match) {
				continue;
			}

			foreach ($roles as $name => $role) {
				$node = $controls[$name]['node'];

				if (!isset($node->props->_cf) || !is_object($node->props->_cf)) {
					$node->props->_cf = new stdClass();
				}

				$node->props->_cf->rss_field = $role;
			}

			$json = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			if ($json === false) {
				continue;
			}

			db("UPDATE shared_components SET tree_json = '" . e($json) . "' WHERE id = '" . (int) $row['id'] . "'");
			$widgets++;
		}
	}

	$pages = db_items(
		"SELECT page_id
		FROM form_fields
		GROUP BY page_id
		HAVING
			SUM(name = 'title' AND type = 'text box') = 1
			AND SUM(name = 'summary' AND type = 'text area') = 1
			AND SUM(name = 'cover_image' AND type = 'file upload') = 1
			AND SUM(name = 'content' AND type = 'text area') = 1
			AND SUM(rss_field <> '') = 0");

	$forms = 0;

	foreach ((array) $pages as $page) {
		$page_id = (int) $page['page_id'];

		foreach ($roles as $name => $role) {
			db("UPDATE form_fields SET rss_field = '" . e($role) . "' WHERE page_id = '" . $page_id . "' AND name = '" . e($name) . "' AND rss_field = ''");
		}

		$forms++;
	}

	if (($widgets === 0) && ($forms === 0)) {
		install_skipped('form_fields.rss_field: no blog form of a design template without roles');
	} else {
		install_ran('form_fields.rss_field: roles given to ' . $forms . ' blog form(s), ' . $widgets . ' form widget(s)');
	}

	install_note('Blog: the posts of a blog installed from a design template take their page title, description and share picture from the post, and the blog list offers an RSS feed.');

}
