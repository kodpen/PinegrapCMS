<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The document head's words and script (assets/js/pg_doc_head.js,
 * window.pgDocHead): the one row at the top of a note, a Data table, the
 * Artifact Canvas, a file window and the view windows. The script holds no
 * text of its own; its words are printed here, keyed by their English.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Every word the head shows, keyed by its English: the states, the history,
 * the acts and the menu's rows.
 *
 * @return array English => translated
 */
function pg_doc_head_words()
{
    return array(
        'Back'              => lang('Back'),
        'Rename'            => lang('Rename'),
        'Saving…'           => lang('Saving…'),
        'Saved'             => lang('Saved'),
        'Unsaved changes'   => lang('Unsaved changes'),
        'Could not save'    => lang('Could not save'),
        'Just now'          => lang('Just now'),
        '{var:1} min ago'   => lang('{var:1} min ago'),
        'Undo'              => lang('Undo'),
        'Redo'              => lang('Redo'),
        'Share'             => lang('Share'),
        'Download'          => lang('Download'),
        'Save'              => lang('Save'),
        'More actions'      => lang('More actions'),
        'Full screen'       => lang('Full screen'),
        'Close'             => lang('Close'),
        'Open in a new tab' => lang('Open in a new tab'),
        'Copy link'         => lang('Copy link'),
        'Send to a channel' => lang('Send to a channel'),
        'Export…'           => lang('Export…'),
        'Print'             => lang('Print'),
        'Versions'          => lang('Versions'),
        'Help'              => lang('Help'),
        'Delete'            => lang('Delete'),
    );
}

/**
 * The head's words and its script for a page, once per page; the script
 * builds every [data-pg-doc-head] host when the page is ready.
 *
 * @return string HTML for the page
 */
function pg_doc_head_includes()
{
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;
    $file = '/assets/js/pg_doc_head.js';

    return '<script>window.pgDocHeadWords = ' . pg_code_editor_json(pg_doc_head_words()) . ';</script>'
        . '<script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . $file . '?v=' . @filemtime(PG_FUNCTIONS_DIR . $file)) . '"></script>';
}
