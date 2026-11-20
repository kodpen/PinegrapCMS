<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - how a message is written so the Workspace draws it: the lines
 * every AI is given (the agent instructions, the guide's workspace topic),
 * a one-sentence short form and the sentence that points at the guide's
 * section. Only marks includes/workspace/render.php draws are named, and
 * every example here is passed through it by tests/workspace_writing_rules_test.php.
 * The text is for AIs: English, kept in code, edited by no screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most characters the lines may hold together.
define('WS_WRITING_RULES_MAX', 800);

// The sentence a route that writes a message ends its text field with: it
// names the guide's section that holds the lines.
define('WS_WRITING_RULES_POINTER', 'How it is written (lists, tables, code, bold): GET /guide?topic=workspace&section=writing, or guide_read.');

/**
 * The examples the lines give, by the mark each shows. Pure.
 *
 * @return string[] mark => text as written in a message
 */
function ws_writing_rules_examples()
{
    return array(
        'bold'  => '**Approved**',
        'code'  => '`render.php`',
        'list'  => "- first\n- second",
        'table' => "| Item | Qty |\n|---|---|\n| Pen | 2 |",
        'quote' => '> quoted words',
        'link'  => '[#12](https://github.com/o/r/pull/12)',
    );
}

/**
 * How a Workspace message is written, one rule a line, in English. Pure.
 *
 * @return string[]
 */
function ws_writing_rules_lines()
{
    $e = ws_writing_rules_examples();

    return array(
        'Bold a decision or a name to be found: ' . $e['bold'] . '.',
        'Code marks for a file, a function, a column, a command: ' . $e['code'] . '.',
        'A list: lines that start "- " or "1. " and run to the paragraph\'s end, then an empty line; a line after the items in the same paragraph leaves them text.',
        'Columns, options and figures as a table: "' . strtok($e['table'], "\n") . '", a row "|---|---|", the rows, then an empty line.',
        'A quote: a line that starts "> ".',
        'A message, a record or a person by its tag: <#message:ID>.',
        'A pull request by its address, as a link: ' . $e['link'] . '; a bare #12 stays text.',
        'No headings: "#" stays text in a message.',
    );
}

/**
 * The lines as one sentence, for a prompt with no room for them. Pure.
 *
 * @return string
 */
function ws_writing_rules_short()
{
    return 'The Workspace draws **bold**, `code`, [links](https://…), lists and tables that run to the paragraph\'s end with an empty line after them, and "> " quotes; it draws no headings.';
}
