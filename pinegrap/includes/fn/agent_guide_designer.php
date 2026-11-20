<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's designer topic (includes/fn/agent_guide.php): how a page of
 * the Visual Page Editor is shown, the operations a proposal carries, the
 * classes that work, the system widgets, worked operation lists and the
 * frequent mistakes. The lines about the page and the operations are the
 * built-in assistant's own (includes/designer_ai.php), read from there, so
 * the two never drift apart; the worked lists are run through
 * pg_design_ai_apply_ops() by tests/agent_guide_designer_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');

/**
 * Five worked operation lists, each a wish and the ops that do it, written
 * against the page of pg_agent_guide_designer_example_tree().
 *
 * @return array list of array(wish, ops)
 */
function pg_agent_guide_designer_examples() {

	return array(
		array('wish' => 'A heading\'s words, centred', 'ops' => array(
			array('op' => 'update', 'node' => 'sd_3', 'text' => 'Sale', 'add_classes' => 'text-center')
		)),
		array('wish' => 'A look of its own', 'ops' => array(
			array('op' => 'update', 'node' => 'sd_2', 'add_classes' => 'pg-glow'),
			array('op' => 'css', 'css' => '.pg-glow{box-shadow:0 0 24px rgba(13,110,253,.5)}')
		)),
		array('wish' => 'A button after a paragraph', 'ops' => array(
			array('op' => 'insert', 'after' => 'sd_4', 'html' => '<a class="btn btn-primary" href="/contact">Contact us</a>')
		)),
		array('wish' => 'A menu above the heading', 'ops' => array(
			array('op' => 'move', 'node' => 'sd_5', 'before' => 'sd_3')
		)),
		array('wish' => 'A section redone, menu kept', 'ops' => array(
			array('op' => 'replace', 'node' => 'sd_2', 'html' => '<section class="py-5"><h2 data-pg="sd_3">Sale</h2><pg-keep data-pg="sd_5"></pg-keep></section>')
		))
	);

}

/**
 * The page the worked lists are written against: a section (sd_2) holding a
 * heading (sd_3), a paragraph (sd_4) and a menu region (sd_5).
 *
 * @return array pg_design_ai_tree()
 */
function pg_agent_guide_designer_example_tree() {

	return pg_design_ai_tree(array(
		'type'     => 'root',
		'_id'      => 'sd_1',
		'props'    => array(),
		'children' => array(
			array(
				'type'     => 'semantic',
				'_id'      => 'sd_2',
				'props'    => array('tag' => 'section'),
				'children' => array(
					array('type' => 'semantic', '_id' => 'sd_3', 'props' => array('tag' => 'h2', 'text' => 'Season'), 'children' => array()),
					array('type' => 'content', '_id' => 'sd_4', 'props' => array('contentType' => 'paragraph', 'text' => 'Our news.'), 'children' => array()),
					array('type' => 'region', '_id' => 'sd_5', 'props' => array('regionType' => 'menu', 'regionName' => 'Main'), 'children' => array())
				)
			)
		)
	));

}

/**
 * Which system widget does which job, by its kind (pg_sw_type_labels()).
 *
 * @return array job => list of kinds
 */
function pg_agent_guide_designer_widgets() {

	return array(
		'records (blog, news)' => array('form_list_view', 'form_item_view'),
		'products'             => array('catalog_listing', 'catalog_item_view', 'shopping_cart'),
		'a form'               => array('custom_form'),
		'menu'                 => array('menu_region'),
		'events'               => array('calendar_view', 'booking'),
		'comments'             => array('comments'),
		'search'               => array('search_results')
	);

}

function pg_agent_guide_designer_sections() {

	$page = pg_design_ai_page_lines();

	$kinds = array();

	foreach (pg_design_ai_keep_kinds() as $kind => $what) {
		$kinds[] = $kind . ' (' . $what . ')';
	}

	$ops = pg_design_ai_ops_guide();

	$html = $ops['html'];

	unset($ops['html']);

	$widgets = array();

	foreach (pg_agent_guide_designer_widgets() as $job => $list) {
		$widgets[] = $job . ': ' . implode(', ', $list);
	}

	$examples = array();

	foreach (pg_agent_guide_designer_examples() as $example) {
		$examples[] = $example['wish'] . ': ' . json_encode($example['ops'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	return array(

		array(
			'id'      => 'page',
			'heading' => 'How a page is shown',
			'lines'   => array(
				'Read it with {tool:design.pages.get}; a long page: format outline, then one node.',
				$page['html'],
				'A <pg-keep> kind: ' . implode('; ', $kinds) . '; else a content type (video, code).',
				$page['bound']
			)
		),

		array(
			'id'      => 'ops',
			'heading' => 'Operations',
			'lines'   => array_merge(
				array('Send them to {tool:design.proposals.create} as ops, run in order, all or nothing, dry_run true first; a failing one is refused with field ops[N].'),
				array_values($ops),
				array($html)
			)
		),

		array(
			'id'      => 'classes',
			'heading' => 'Classes that work',
			'lines'   => array(
				'{tool:design.designs.list} gives each design\'s framework, colours and font families; the page\'s design_id names its design.',
				'bootstrap5: ' . pg_design_ai_framework_line(array('framework' => 'bootstrap5')),
				'custom or legacy: no framework; style with the css operation and classes of your own.'
			)
		),

		array(
			'id'      => 'widgets',
			'heading' => 'System widgets',
			'lines'   => array(
				'A system widget is kind="shared_ref <its name>"; html cannot make one, a designer places it.',
				'Widget kinds by job: ' . implode('; ', $widgets) . '.'
			)
		),

		array(
			'id'      => 'examples',
			'heading' => 'Worked lists',
			'lines'   => $examples
		),

		array(
			'id'      => 'mistakes',
			'heading' => 'Frequent mistakes',
			'lines'   => array(
				'text for a <pg-keep> or a bound element: refused; use set_props or leave it.',
				'A class of your own without a css rule: it draws nothing.'
			)
		)

	);

}
