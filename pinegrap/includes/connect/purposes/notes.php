<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bringing the person's own OneNote pages in as Workspace notes (the notes
 * import's From OneNote source, includes/workspace/notes_onenote.php).
 *
 * Each person connects their own account: notebooks are private, so nobody
 * reads the site owner's or a colleague's notes through it. Notes.Read is
 * read-only and is all an import needs; it reaches the notebooks the
 * signed-in person can open.
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
    'label'       => lang('Importing notes'),
    'description' => lang('Lets this person bring their own OneNote pages in as Workspace notes.'),
    'owner'       => 'person',
    'scopes'      => array(
        'microsoft' => array('https://graph.microsoft.com/Notes.Read'),
    ),
);
