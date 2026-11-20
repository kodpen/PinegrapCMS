<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's start topic (includes/fn/agent_guide.php): what the site is
 * made of, which tools do which job, and the rules every call keeps. Tools
 * are named as {tool:route.id}, so a renamed or removed route fails
 * tests/agent_guide_test.php rather than leaving the guide behind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

function pg_agent_guide_start_sections() {

	return array(

		array(
			'id'      => 'records',
			'heading' => 'What the site is made of',
			'lines'   => array(
				'Page: one address of the site. Its layout is read with {tool:design.pages.get}; its search settings with {tool:pages.get}.',
				'Folder: the tree pages and files are filed in; it decides who may open them (public, registration, membership, private). {tool:folders.list}',
				'Design: the pages of the Visual Page Editor that share a framework, colours and fonts. {tool:design.designs.list}',
				'Region: content many pages share, such as a header or a footer. {tool:design.common_regions.list}',
				'System widget: a part the software draws on a page (a form, the cart, a product list). In a page\'s HTML it is a <pg-keep> element: moved or removed, never rewritten.',
				'Form: a page that carries fields; each submission is a record. {tool:forms.list}',
				'Collection: a form whose records are shown as pages of their own, such as blog posts. {tool:forms.get} says is_collection.',
				array('module' => 'store', 'text' => 'Product: an item for sale, with its prices and stock. {tool:products.list}'),
				array('module' => 'store', 'text' => 'Order: what a customer bought, with its lines, payment and shipping. {tool:orders.list}'),
				array('module' => 'workspace', 'text' => 'Channel: a Workspace conversation where the team talks, keeps tasks and applies proposed changes. {tool:workspace.channels.list}')
			)
		),

		array(
			'id'      => 'jobs',
			'heading' => 'Which tools do which job',
			'lines'   => array(
				'A tool\'s name is its API route id with dots as underscores: *_list finds records and their ids, *_get reads one, the rest write.',
				'The site itself (currency, modules on, upload limits): {tool:meta.read}.',
				'A page\'s look or layout: read it with {tool:design.pages.get}, then propose operations with {tool:design.proposals.create}; a designer applies them.',
				'A form or collection record: read {tool:forms.fields}, then {tool:forms.submission_create}.',
				array('module' => 'workspace', 'text' => 'Any other record change a person should apply: {tool:workspace.changes.propose}.')
			)
		),

		array(
			'id'      => 'writes',
			'heading' => 'Before a write',
			'lines'   => array(
				'Propose rather than write where a proposal tool exists: the person applies it with their own rights.',
				'Tell the person what a write will do and wait for their yes.',
				'Where a write takes dry_run, call it with dry_run true first: everything is checked and nothing is written.'
			)
		),

		array(
			'id'      => 'calls',
			'heading' => 'Every call',
			'lines'   => array(
				'Money is an integer in minor units: 1999 is 19.99.',
				'Lists are paged: pass page.next_cursor back as cursor while page.has_more is true.',
				'A refusal is an error object with a stable code, a message and the field it is about.'
			)
		),

		array(
			'id'      => 'next',
			'heading' => 'Before work in an area',
			'lines'   => array(
				'Read the area\'s topic with {tool:guide.read} before the first work in it; without topic it lists the topics.'
			)
		)

	);

}
