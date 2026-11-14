<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * E-mail designs: pages drawn as e-mails in the visual designer. A design
 * lives in the private "E-mail designs" folder (made on first use), its
 * root carries props.pgEmail, and the designer opens it on a 600 px canvas
 * with a 375 px Phone view and the mail blocks in its palette.
 *
 * A mail block is a semantic node whose props._mail names its kind. Its
 * stored tree keeps only classes and props; the look a mail program needs
 * inline is written when the node is drawn, from one map per kind
 * (pg_mail_block_styles()). The canvas draws it from the same map's twin
 * (_SD_MAIL_STYLES in style_designer.src.js). Colours are written as
 * var(--x,#hex): the canvas follows the design's colours, and the mail pass
 * writes the hex (pg_mail_pass_var_fallbacks()).
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
 * The config_text row that holds the folder's id.
 *
 * @return string
 */
function pg_email_designs_folder_key()
{
    return 'email_designs_folder_id';
}

/**
 * The inline look of every mail block kind. The frame and the columns are
 * presentation tables, so Outlook's Word engine lays them out; the head
 * style of the mail pass makes the frame and the columns fit a phone.
 *
 * @return array kind => CSS declarations
 */
function pg_mail_block_styles()
{
    $font = 'font-family:Arial,Helvetica,sans-serif';

    return array(
        'frame'      => 'width:100%;max-width:600px;margin:0 auto;border-collapse:collapse;background-color:var(--bs-body-bg,#ffffff)',
        'cell'       => 'padding:24px;' . $font . ';font-size:16px;line-height:1.5;color:var(--bs-body-color,#212529)',
        'heading'    => 'margin:0 0 12px;' . $font . ';font-size:24px;line-height:1.3;font-weight:bold;color:var(--bs-emphasis-color,#000000)',
        'text'       => 'margin:0 0 16px;' . $font . ';font-size:16px;line-height:1.5;color:var(--bs-body-color,#212529)',
        'button_row' => 'margin:0 0 16px;text-align:center',
        'button'     => 'display:inline-block;padding:12px 24px;border-radius:6px;background-color:var(--bs-primary,#0d6efd);color:#ffffff;' . $font . ';font-size:16px;font-weight:bold;line-height:1.2;text-decoration:none',
        'picture'    => 'display:block;width:100%;max-width:100%;height:auto;border:0',
        'divider'    => 'height:0;margin:24px 0;border:0;border-top:1px solid var(--bs-border-color,#dee2e6)',
        'space'      => 'height:24px;line-height:24px;font-size:0',
        'group'      => 'margin:0;padding:0',
        'columns'    => 'width:100%;border-collapse:collapse',
        'column'     => 'padding:0 8px;vertical-align:top',
    );
}

/**
 * The inline style a node is drawn with: its mail block look first, then
 * the style it was given in the designer, which wins on a repeat.
 *
 * @param array $props a node's props
 * @return string
 */
function pg_mail_block_inline($props)
{
    $own = (is_array($props) && isset($props['inlineStyle']) && is_scalar($props['inlineStyle']))
        ? rtrim((string) $props['inlineStyle'], "; \t\n\r") : '';
    $kind = (is_array($props) && isset($props['_mail']) && is_string($props['_mail'])) ? $props['_mail'] : '';
    $styles = pg_mail_block_styles();

    if ($kind === '' || !isset($styles[$kind])) {
        return $own;
    }

    return $styles[$kind] . ($own !== '' ? ';' . $own : '');
}

/**
 * Whether a page tree is an e-mail design: its root carries props.pgEmail.
 *
 * @param mixed $tree a decoded tree
 * @return bool
 */
function pg_email_design_tree_is($tree)
{
    return is_array($tree) && isset($tree['props']) && is_array($tree['props']) && !empty($tree['props']['pgEmail']);
}

/**
 * A semantic node of a mail block.
 *
 * @param string $tag
 * @param string $kind     the block kind, a key of pg_mail_block_styles()
 * @param string $name     the layer name
 * @param array  $children
 * @param array  $extra    more props (text, href, cssClass, _attrs …)
 * @return array
 */
function pg_mail_block_node($tag, $kind, $name, $children = array(), $extra = array())
{
    return array(
        'type'     => 'semantic',
        'props'    => array_merge(array('tag' => $tag, 'cssClass' => '', 'customName' => $name, '_mail' => $kind), $extra),
        'children' => $children,
    );
}

/**
 * The attributes of a presentation table.
 *
 * @param string $width
 * @return array
 */
function pg_mail_table_attrs($width)
{
    return array(
        array('name' => 'role', 'value' => 'presentation'),
        array('name' => 'width', 'value' => $width),
        array('name' => 'cellpadding', 'value' => '0'),
        array('name' => 'cellspacing', 'value' => '0'),
        array('name' => 'border', 'value' => '0'),
    );
}

/**
 * The frame every e-mail design is built in: a centred 600 px table with
 * one cell, the blocks inside it.
 *
 * @param array $blocks the cell's children
 * @return array
 */
function pg_mail_frame_tree($blocks)
{
    $attrs = pg_mail_table_attrs('600');
    $attrs[] = array('name' => 'align', 'value' => 'center');

    return pg_mail_block_node('table', 'frame', lang('E-mail frame'), array(
        array('type' => 'semantic', 'props' => array('tag' => 'tbody', 'cssClass' => '', 'customName' => ''), 'children' => array(
            array('type' => 'semantic', 'props' => array('tag' => 'tr', 'cssClass' => '', 'customName' => ''), 'children' => array(
                pg_mail_block_node('td', 'cell', lang('Content'), $blocks),
            )),
        )),
    ), array('cssClass' => 'pg-mail-frame', '_attrs' => $attrs));
}

/**
 * The tree a new e-mail design starts with: the frame with a heading, a
 * line of text and a button.
 *
 * @return array
 */
function pg_email_design_starter_tree()
{
    return array(
        'type'     => 'root',
        'props'    => array('pgEmail' => 1),
        'children' => array(
            pg_mail_frame_tree(array(
                pg_mail_block_node('h1', 'heading', lang('Heading'), array(), array('text' => lang('A heading for your e-mail'))),
                pg_mail_block_node('p', 'text', lang('Text'), array(), array('text' => lang('Write what you want to tell your readers here.'))),
                pg_mail_block_node('p', 'button_row', lang('Button'), array(
                    pg_mail_block_node('a', 'button', lang('Button link'), array(), array('text' => lang('Visit our site'), 'href' => '/', 'target' => '')),
                )),
            )),
        ),
    );
}

/**
 * The editor's first tab for a new e-mail design: the starter tree, in the
 * e-mail designs folder, kept out of search, the sitemap and the search
 * engines, never a draft (the folder is private already).
 *
 * @param int $folder_id
 * @return array a page descriptor as pg_designer_blank_page() gives
 */
function pg_email_design_new_page($folder_id)
{
    $page = pg_designer_blank_page(0, pg_email_design_starter_tree());
    $page['page_folder']   = (int) $folder_id;
    $page['page_search']   = 0;
    $page['page_sitemap']  = 0;
    $page['page_noindex']  = 1;
    $page['page_nofollow'] = 1;

    return $page;
}

/**
 * The private "E-mail designs" folder, made on first use when $create is
 * set. 0 while there is none. A folder deleted by hand or sent to the
 * recycle bin is no longer it: a new one is made.
 *
 * @param bool $create
 * @return int
 */
function pg_email_designs_folder_id($create = false)
{
    $id = (int) pg_config_text(pg_email_designs_folder_key());

    if ($id > 0 && (!db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '$id'") || in_array($id, pg_recycle_bin_folder_ids(), true))) {
        $id = 0;
    }

    if ($id > 0 || !$create) {
        return $id;
    }

    $root_id = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    $root_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '$root_id'");
    $name = lang('E-mail designs');

    db("INSERT INTO folder (folder_name, folder_parent, folder_level, folder_order, folder_access_control_type, folder_archived, folder_style, mobile_style_id, folder_timestamp, folder_user)
        VALUES ('" . e($name) . "', '$root_id', '" . ($root_level + 1) . "', '9997', 'private', '0', '0', '0', UNIX_TIMESTAMP(), '" . (defined('USER_ID') ? (int) USER_ID : 0) . "')");

    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return 0;
    }

    pg_config_text_save(pg_email_designs_folder_key(), (string) $id);
    log_activity(lang(array('string' => 'folder ({var:1}) was created', 'vars' => array($name))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');

    return $id;
}
