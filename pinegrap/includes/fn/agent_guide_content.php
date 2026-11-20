<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's content topic (includes/fn/agent_guide.php): pages' settings,
 * folders and files - the records, the tool for each job, what a write needs
 * first and the frequent mistakes. Tools are named as {tool:route.id}; the
 * folder access rules and the upload types are the lists
 * api_folder_access_ranks() and api_files_allowed_types() hold, which
 * tests/agent_guide_areas_test.php compares.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

function pg_agent_guide_content_sections() {

	return array(

		array(
			'id'      => 'records',
			'heading' => 'The records',
			'lines'   => array(
				'Page: one address of the site. These tools hold its settings (title, description, keywords, indexing, comments, folder) and its search score; its layout and text live in the design (guide topic designer).',
				'Folder: the tree pages and files are filed in. Its access rule, from the most open: public, guest, registration, membership, private; a folder without one inherits its parent\'s. The drafts folder and the recycle bin belong to their own screens.',
				'File: an upload the site serves: jpg, jpeg, png, gif, webp, pdf. Files in folders closed to visitors are not listed.'
			)
		),

		array(
			'id'      => 'jobs',
			'heading' => 'Which tool for which job',
			'lines'   => array(
				'Where a site\'s search work starts: {tool:pages.list} with max_score lists the worst pages; {tool:pages.get} gives one page\'s findings, each with a code. The page text is not in the answer: read the page\'s url.',
				'A page\'s title, description, keywords, indexing switches or folder: {tool:pages.update}; only what is sent changes.',
				'A new page: {tool:design.pages.create}. It starts in the drafts folder and a person publishes it in the Visual Page Editor; read guide topic designer before filling it.',
				'Folders: {tool:folders.list} (with access and counts), {tool:folders.create}, {tool:folders.update} (rename, sort, archive, move, a stricter rule), {tool:folders.delete} (empty only).',
				'Files: {tool:files.list}, {tool:files.create} (content_base64, or source_url for the site to fetch), {tool:files.update} (the description), {tool:files.delete} (to the recycle bin).',
				'A page to the recycle bin: {tool:pages.delete}.'
			)
		),

		array(
			'id'      => 'writes',
			'heading' => 'Before a write',
			'lines'   => array(
				'Call the write with dry_run true first and tell the person what it will change.',
				'A change never opens a page or folder to more people: a looser rule or a move under a more open parent answers 409 access_widening_refused. Opening up is the person\'s, on the folder screen.',
				'A delete goes to the recycle bin, where a person can put it back. permanent=true cannot be undone: send it only when the person asked for exactly that.'
			)
		),

		array(
			'id'      => 'mistakes',
			'heading' => 'Frequent mistakes',
			'lines'   => array(
				'Writing a page\'s text with {tool:pages.update}: it takes settings only; text and layout are proposed through the design tools.',
				'Closing a page to search engines while keeping it in the site map: refused, not corrected. nofollow only applies while noindex is on.',
				'Naming the folder of an upload: the site files it where the operator chose for applications. SVG is refused.',
				'Deleting the home page, a page the store or the account area runs on, or a folder with anything in it: refused (409). Move or delete what is inside first.'
			)
		)

	);

}
