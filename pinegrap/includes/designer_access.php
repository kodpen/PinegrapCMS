<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual designer — who may edit what.
 *
 * The editor was staff-only: `validate_area_access($user, 'designer')`, role
 * 0 or 1, everybody else turned away at the door. That is right for the
 * design itself and wrong for the words on the page — the person who writes
 * the announcement is usually not the person who built the layout, and
 * sending them to a different screen to change one sentence means the
 * sentence and the page it lives on are edited in two different places.
 *
 * So there are two levels:
 *
 *   full     administrator (0) and designer (1). The editor as it always was.
 *   content  manager (2) and user (3). On a page they may edit: text and
 *            images anywhere, plus everything inside a block an administrator
 *            or designer marked as an editable area (`props._editable`).
 *
 * Text and images are "always theirs" for the same reason — they are what a
 * page SAYS. An operator who can rewrite a caption but not replace the photo
 * above it has been given half a job.
 *
 * Three rules hold the second level together, and none of them is optional:
 *
 *   1. A shared component or system widget is never editable at `content`
 *      level, marked area or not. Its tree belongs to every page that uses
 *      it, so an edit there is not an edit to "this page".
 *   2. What the operator may see is decided by the folder, not by the design.
 *      A design can span folders; a user reads the tab strip, not the ACL.
 *   3. The browser decides what to SHOW. The server decides what is WRITTEN
 *      (pg_designer_merge_restricted_tree). A greyed-out node is a courtesy;
 *      a POST does not have to come from that screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DESIGNER_ACCESS_FULL')) {
    define('PG_DESIGNER_ACCESS_FULL', 'full');
    define('PG_DESIGNER_ACCESS_CONTENT', 'content');
    define('PG_DESIGNER_ACCESS_NONE', 'none');
}

/**
 * What this user may do in the editor, before any page is named.
 *
 * @param array $user validate_user() row
 * @return string one of the PG_DESIGNER_ACCESS_* constants
 */
function pg_designer_access($user)
{
    $role = isset($user['role']) ? (int)$user['role'] : 3;

    if ($role <= 1) return PG_DESIGNER_ACCESS_FULL;      // administrator, designer
    if ($role <= 3) return PG_DESIGNER_ACCESS_CONTENT;   // manager, user

    return PG_DESIGNER_ACCESS_NONE;
}

function pg_designer_is_full($user)
{
    return pg_designer_access($user) === PG_DESIGNER_ACCESS_FULL;
}

/**
 * What this user may do with one page.
 *
 *   edit    open it and change what their level allows
 *   locked  the name is shown, the page does not open
 *   hidden  not shown at all — the name itself is information
 *
 * The distinction between `locked` and `hidden` is the point of the whole
 * function. A page in a public folder the user cannot edit is a page they
 * already know exists; hiding it would only make the design look broken.
 * A page behind a private or membership folder is one they are not supposed
 * to know about, and a tab strip listing "prices-2027" tells them plenty
 * without ever opening it.
 *
 * @param array|int $page page row (page_id, page_folder) or a page id
 * @param array     $user
 * @return string 'edit' | 'locked' | 'hidden'
 */
function pg_designer_page_access($page, $user)
{
    if (pg_designer_is_full($user)) return 'edit';

    $folder_id = 0;
    if (is_array($page)) {
        $folder_id = (int)(isset($page['page_folder']) ? $page['page_folder'] : 0);
        if ($folder_id === 0 && isset($page['page_id'])) {
            $folder_id = (int)db_value("SELECT page_folder FROM page WHERE page_id = '" . e((int)$page['page_id']) . "' LIMIT 1");
        }
    } else {
        $page_id = (int)$page;
        if ($page_id <= 0) return 'edit';   // a brand-new unsaved tab is theirs
        $folder_id = (int)db_value("SELECT page_folder FROM page WHERE page_id = '$page_id' LIMIT 1");
    }
    if ($folder_id <= 0) return 'locked';

    // check_edit_access() answers true for every folder at role < 3, which is
    // the manager rule the rest of the software already applies.
    if (check_edit_access($folder_id)) return 'edit';

    // No edit rights. Whether the operator may even know the page is there
    // depends on how the folder is published.
    $type = function_exists('get_access_control_type') ? get_access_control_type($folder_id) : 'public';
    if ($type === 'public' || $type === 'guest' || $type === '') return 'locked';

    return 'hidden';
}

/**
 * Is this node inside a block marked as an editable area?
 *
 * `$ancestors` is the chain from the root down to (and excluding) the node.
 * The mark is inherited: marking a card makes its heading, its text and its
 * button editable, which is the only reading of "this area" that a person
 * would expect.
 */
function pg_designer_in_editable_area($ancestors)
{
    foreach ((array)$ancestors as $a) {
        if (is_array($a) && !empty($a['props']['_editable'])) return true;
    }
    return false;
}

/**
 * Merge a restricted operator's submission into the stored tree.
 *
 * The submitted tree is NOT written. The stored tree is walked, and only
 * these changes are taken from the submission:
 *
 *   - inside a node marked `_editable`, the whole subtree
 *   - anywhere else, the `text` of a node that already had one, and the
 *     ADDRESS of an image (nothing else about it)
 *
 * Everything else in the submission is discarded, including nodes that were
 * added or removed outside a marked area. Nothing needs to be detected or
 * refused: a change that is not copied has not happened.
 *
 * Children are matched by `_id`, falling back to position for a tree old
 * enough not to carry ids. Position alone was wrong: a node duplicated
 * outside a marked area shifts every sibling after it by one, so the stored
 * B ended up reading the submitted A's text and the page came back with its
 * paragraphs rotated. The duplicate itself was correctly dropped — the
 * damage was to everything below it.
 *
 * Trusting an id from the request body costs nothing here: outside a marked
 * area the only things copied are text and an image address, both of which
 * the operator may set on that node anyway, so pointing an id at a different
 * node grants no permission it did not already have.
 *
 * A shared_ref is copied from the stored tree untouched wherever it appears.
 * Its tree lives in `shared_components` and belongs to every page using it.
 * A custom_php or custom_html content node is treated the same way: its
 * source is code the public page runs (see _pg_dm_locked_kind), so it is
 * never taken from the submission at this level.
 *
 * Every string that IS taken from the submission goes through
 * _pg_dm_neutralise_markers(): the render pipeline recognises its own code
 * nodes by an HTML comment in the generated page, and a text prop is emitted
 * into that page raw, so the comment has to be defused at content level too.
 *
 * @return array the tree to store
 */
function pg_designer_merge_restricted_tree($stored, $submitted)
{
    if (!is_array($stored)) return is_array($submitted) ? $submitted : array();
    if (!is_array($submitted)) return $stored;

    return _pg_dm_node($stored, $submitted, false);
}

function _pg_dm_node($stored, $submitted, $in_editable)
{
    $type = isset($stored['type']) ? (string)$stored['type'] : '';

    // A shared component is never the page's to change.
    if ($type === 'shared_ref') return $stored;

    $editable_here = $in_editable || !empty($stored['props']['_editable']);

    // A code node is never theirs, editable mark or not; it falls through to
    // the stored copy below, where only text, notes and an image address move.
    if ($editable_here && is_array($submitted) && _pg_dm_locked_kind($stored) === '') {
        // The area is theirs — but the mark itself is not. Re-stamp it from
        // the stored node so a restricted operator cannot widen their own
        // permission by sending `_editable` on a node beside it.
        $out = $submitted;
        // The stored node was an ordinary one; a submission that turns it into
        // a code node is a code node the operator added, and is refused.
        if (_pg_dm_locked_kind($out) !== '') return $stored;
        if (!isset($out['props']) || !is_array($out['props'])) $out['props'] = array();
        if (!empty($stored['props']['_editable'])) $out['props']['_editable'] = 1;
        else unset($out['props']['_editable']);
        _pg_dm_strip_editable_flags($out, true);
        _pg_dm_restore_locked($out, $stored);
        _pg_dm_neutralise_markers($out);
        return $out;
    }

    $out = $stored;

    // Text is always theirs. Only `text` — a node that also carries an href,
    // a class or a binding keeps the stored ones.
    if (is_array($submitted) && array_key_exists('text', (array)(isset($stored['props']) ? $stored['props'] : array()))
        && isset($submitted['props']) && array_key_exists('text', (array)$submitted['props'])) {
        $out['props']['text'] = (string)$submitted['props']['text'];
        _pg_dm_neutralise_markers($out['props']['text']);
    }

    // A note is an annotation, not page content: the collaboration feature
    // deliberately lets even a view-mode session leave one, so a content-level
    // operator must be able to leave one anywhere too. The author is NOT
    // copied — pg_designer_save_page() stamps it from the session.
    if (is_array($submitted) && isset($submitted['props']) && is_array($submitted['props'])) {
        if (array_key_exists('_notes', $submitted['props'])) {
            $note = trim((string)$submitted['props']['_notes']);
            if ($note === '') unset($out['props']['_notes'], $out['props']['_notes_by'], $out['props']['_notes_at']);
            else              $out['props']['_notes'] = mb_substr($note, 0, 4000);
        }
    }

    // So is the picture. Swapping the photo in a block somebody else laid out
    // is the same kind of edit as changing the sentence beside it, and the
    // alternative is an operator who can rewrite a caption but not replace
    // the image above it. Only the ADDRESS moves — size, classes, alt text
    // and everything else stay as stored.
    if (is_array($submitted)) {
        _pg_dm_take_image_src($out, $submitted);
    }

    $s_kids = (isset($stored['children']) && is_array($stored['children'])) ? $stored['children'] : array();
    $b_kids = (isset($submitted['children']) && is_array($submitted['children'])) ? $submitted['children'] : array();
    if ($s_kids) {
        $by_id = array();
        foreach ($b_kids as $bi => $bk) {
            if (is_array($bk) && isset($bk['_id']) && $bk['_id'] !== '') $by_id[(string)$bk['_id']] = $bk;
        }
        $kids = array();
        foreach ($s_kids as $i => $child) {
            $mate = null;
            $sid  = (is_array($child) && isset($child['_id'])) ? (string)$child['_id'] : '';
            if ($sid !== '' && isset($by_id[$sid])) {
                $mate = $by_id[$sid];
            } elseif ($sid === '' && isset($b_kids[$i])) {
                // A tree from before ids were stored: position is all there is.
                $mate = $b_kids[$i];
            }
            $kids[] = _pg_dm_node($child, $mate, false);
        }
        $out['children'] = $kids;
    }
    return $out;
}

/**
 * Copy the image address, and nothing else.
 *
 * Two shapes carry one: the image content node keeps it in `props.src`, a
 * bare <img> in its `src` attribute. A background-image is deliberately NOT
 * included — that is styling, and it lives with the classes and the layout
 * this level does not own.
 */
function _pg_dm_take_image_src(&$out, $submitted)
{
    $type = isset($out['type']) ? (string)$out['type'] : '';

    if ($type === 'content'
        && (isset($out['props']['contentType']) ? $out['props']['contentType'] : '') === 'image'
        && isset($submitted['props']['src'])) {
        $out['props']['src'] = (string)$submitted['props']['src'];
        return;
    }

    if ($type === 'semantic'
        && strtolower((string)(isset($out['props']['tag']) ? $out['props']['tag'] : '')) === 'img') {
        $new = null;
        if (!empty($submitted['props']['_attrs']) && is_array($submitted['props']['_attrs'])) {
            foreach ($submitted['props']['_attrs'] as $a) {
                if (is_array($a) && isset($a['name']) && $a['name'] === 'src') {
                    $new = isset($a['value']) ? (string)$a['value'] : '';
                    break;
                }
            }
        }
        if ($new === null) return;
        if (!isset($out['props']['_attrs']) || !is_array($out['props']['_attrs'])) $out['props']['_attrs'] = array();
        $done = false;
        foreach ($out['props']['_attrs'] as &$a) {
            if (is_array($a) && isset($a['name']) && $a['name'] === 'src') { $a['value'] = $new; $done = true; break; }
        }
        unset($a);
        if (!$done) $out['props']['_attrs'][] = array('name' => 'src', 'value' => $new);
    }
}

// Inside an area they own, the operator must not be able to mint new
// editable areas — that would be granting themselves permission.
function _pg_dm_strip_editable_flags(&$node, $skip_self = false)
{
    if (!is_array($node)) return;
    if (!$skip_self && isset($node['props']['_editable'])) unset($node['props']['_editable']);
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$c) { _pg_dm_strip_editable_flags($c, false); }
        unset($c);
    }
}

/**
 * The kind of node a content-level operator may never write, or '' for an
 * ordinary node.
 *
 *   shared_ref   its tree belongs to every page that uses it
 *   custom_php   its source is eval()ed on every public render
 *   custom_html  raw markup, and with PHP_REGIONS on the page pass runs any
 *                `<?` it contains as code
 *
 * The render side trusts these nodes because a designer put them there. A
 * manager or user session must not be able to put one there by hand-editing
 * the tree it POSTs, so all three are restored from the stored tree wherever
 * they appear, inside a marked area or not.
 */
function _pg_dm_locked_kind($node)
{
    if (!is_array($node)) return '';
    $type = isset($node['type']) ? (string)$node['type'] : '';
    if ($type === 'shared_ref') return 'shared_ref';
    if ($type === 'content') {
        $ct = isset($node['props']['contentType']) ? (string)$node['props']['contentType'] : '';
        if ($ct === 'custom_php' || $ct === 'custom_html') return $ct;
    }
    return '';
}

/**
 * Put the stored locked nodes back into an accepted subtree.
 *
 * Inside a marked area the submission is taken wholesale, and a shared_ref
 * that travelled through the browser could come back pointing at a different
 * component, or a code node with different source. The nodes are matched by
 * position among the subtree's nodes of the same kind, which is enough: a
 * restricted operator cannot add or remove them either (the editor refuses,
 * and an added one simply has no stored counterpart to restore, so it is
 * dropped).
 */
function _pg_dm_restore_locked(&$accepted, $stored)
{
    $stored_nodes = array();
    _pg_dm_collect_locked($stored, $stored_nodes);
    $i = array();
    _pg_dm_replace_locked($accepted, $stored_nodes, $i);
}

function _pg_dm_collect_locked($node, &$out)
{
    if (!is_array($node)) return;
    $kind = _pg_dm_locked_kind($node);
    if ($kind !== '') { $out[$kind][] = $node; return; }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $c) _pg_dm_collect_locked($c, $out);
    }
}

// $i counts, per kind, how many locked nodes have been seen so far in the
// accepted subtree; the n-th one of a kind takes the n-th stored one.
function _pg_dm_replace_locked(&$node, $refs, &$i)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) return;
    $kids = array();
    foreach ($node['children'] as $c) {
        $kind = _pg_dm_locked_kind($c);
        if ($kind !== '') {
            $n = isset($i[$kind]) ? $i[$kind] : 0;
            $i[$kind] = $n + 1;
            if (!isset($refs[$kind][$n])) continue;   // added by the operator: dropped
            $kids[] = $refs[$kind][$n];
            continue;
        }
        _pg_dm_replace_locked($c, $refs, $i);
        $kids[] = $c;
    }
    $node['children'] = $kids;
}

/**
 * Remove every locked node from a tree. Used where there is no stored tree
 * to restore them from, so a restricted operator's submission is written
 * without the nodes their level may not create.
 */
function pg_designer_drop_locked_nodes(&$node)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) return;
    $kids = array();
    foreach ($node['children'] as $c) {
        if (_pg_dm_locked_kind($c) !== '') continue;
        pg_designer_drop_locked_nodes($c);
        $kids[] = $c;
    }
    $node['children'] = $kids;
}

/**
 * Defuse render-pipeline markers in everything taken from a restricted
 * submission.
 *
 * generate_style_code_from_tree() writes a custom_php node into the page as
 * the HTML comment `<!--pg-custom-php:BASE64-->`, and _expand_custom_php()
 * later finds that comment anywhere in the rendered page and eval()s its
 * payload; _expand_shared_refs() and _expand_system_widgets() resolve
 * `<!--pg-shared-ref:ID-->` and `<!--pg-system-widget:ID-->` the same way.
 * The renderers emit a heading's, a paragraph's or a link's `text` raw, so
 * a node the operator may legitimately write is another way to put that
 * comment into the page — the node-kind gate above stops the code NODE, this
 * stops the code MARKER typed into ordinary text.
 *
 * Every string in the node is rewritten from `<!--pg-` to `<!-- pg-`, arrays
 * such as `_attrs` included. The inserted space is what the software already
 * uses for its inert diagnostic comments, and no expander matches it, while
 * the operator's text is otherwise left exactly as typed. Nodes of a locked
 * kind are skipped: they were restored from the stored tree, a designer wrote
 * them, and their source may legitimately carry a marker (a loop slot in a
 * custom_html node, for instance).
 *
 * `$value` is a node array or a bare string, so the same call serves the
 * whole accepted subtree and a single copied `text`.
 */
function _pg_dm_neutralise_markers(&$value)
{
    if (is_string($value)) {
        if (stripos($value, '<!--pg-') !== false) $value = str_ireplace('<!--pg-', '<!-- pg-', $value);
        return;
    }
    if (!is_array($value)) return;
    if (_pg_dm_locked_kind($value) !== '') return;
    foreach ($value as &$v) {
        _pg_dm_neutralise_markers($v);
    }
    unset($v);
}
