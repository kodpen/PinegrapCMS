<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'document', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Page from a document": the form and the rows in the designer's own
 * window for an empty page, and the page built from the rows the New
 * design window left (?start=document). The sketch builder comes with
 * the part 'sketchbuild', loaded through the host first.
 *
 * Memory: docs/memory/designer/document-start.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

return array(
	'sources' => array(
		'assets/js/pg_document_reader.js',
		'assets/js/pg_document_rows.js',
		'assets/js/pg_document_page.js',
		'assets/js/style_designer_document.js',
	),
	'when'    => 'always',
);
