<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'markdown', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The Markdown reader (window.PGMarkdown), twin of pg_markdown_html()
 * (includes/fn/markdown.php): the Markdown component's text drawn on the
 * canvas and in the code view. Loaded the first time a Markdown element is
 * drawn.
 *
 * Memory: docs/memory/designer/markdown-component.md
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
		'assets/js/pg_markdown.js',
	),
	'when'    => 'always',
);
