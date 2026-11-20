<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Design examples screen's parts (design_examples.php): every recipe of
 * the panel's look drawn live with the classes that draw it, and what the
 * look does not cover yet. A screen copies its markup from here; the recipes
 * themselves live once in backend.src.css ("Look A").
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The settings pane pattern draws its rows with Site Settings' own fold
// helper and the Assistants and agents pane's own lines, so it cannot drift
// from the screen; both files hold only functions, the helper's behind this
// gate.
if (!defined('PG_SETTINGS_ENTRY')) {
    define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/assistants_fold.php');

/**
 * The recipes in the order the sheet shows them: each with its title, the
 * classes it is built from and its live markup. $prefix keeps the ids of the
 * fields apart when the examples are drawn twice (light and dark).
 *
 * @param string $prefix
 * @return array list of array('id', 'title', 'classes', 'html')
 */
function pg_look_examples($prefix = 'light')
{
    $p = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $prefix));

    $sections = array();

    $sections[] = array(
        'id'      => 'type',
        'title'   => lang('Type and text'),
        'classes' => array('.pg-title-screen', '.pg-title-section', '.pg-title-status', '.pg-text-2', '.pg-text-meta', '.pg-tabular'),
        'html'    => '<div class="pg-look-stack">'
            . '<div class="pg-title-screen">' . h(lang('Screen title')) . ' <span class="pg-text-meta">24 / 650</span></div>'
            . '<div class="pg-title-section">' . h(lang('Section title')) . ' <span class="pg-text-meta">17 / 650</span></div>'
            . '<div class="pg-title-status">' . h(lang('Status column title')) . ' <span class="pg-text-meta">15 / 650</span></div>'
            . '<div><strong class="pg-list-row-title">' . h(lang('Row title')) . '</strong> <span class="pg-text-meta">14 / 550</span></div>'
            . '<div>' . h(lang('Text')) . ' <span class="pg-text-meta">14 / 400</span></div>'
            . '<div class="pg-text-2">' . h(lang('Second text')) . ' <span class="pg-text-meta">13 / 400</span></div>'
            . '<div class="pg-text-meta">' . h(lang('Time, place, help')) . ' · 12 / 400</div>'
            . '<div class="pg-tabular">1.044 · 2.015,50 · 389,00</div>'
            . '</div>',
    );

    $sections[] = array(
        'id'      => 'buttons',
        'title'   => lang('Buttons'),
        'classes' => array('.pg-button', '.pg-button-fill', '.pg-button-quiet', '.pg-button-ghost', '.pg-button-danger', '.pg-button-sm', '.pg-icon-button', '.pg-icon-button-sm', '.is-active'),
        'html'    => '<div class="pg-look-line">'
            . '<button type="button" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button>'
            . '<button type="button" class="pg-button pg-button-quiet">' . h(lang('Cancel')) . '</button>'
            . '<button type="button" class="pg-button pg-button-ghost">' . h(lang('More')) . '</button>'
            . '<button type="button" class="pg-button pg-button-danger">' . h(lang('Delete')) . '</button>'
            . '</div><div class="pg-look-line">'
            . '<button type="button" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Show')) . '</button>'
            . '<button type="button" class="pg-icon-button is-active" aria-label="' . h(lang('More actions')) . '"><span class="bi bi-three-dots" aria-hidden="true"></span></button>'
            . '<button type="button" class="pg-icon-button" aria-label="' . h(lang('Close')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>'
            . '<button type="button" class="pg-icon-button pg-icon-button-sm" aria-label="' . h(lang('Mark as read')) . '"><span class="bi bi-check2" aria-hidden="true"></span></button>'
            . '</div><p class="pg-text-meta">' . h(lang('At most one filled button in a view.')) . '</p>',
    );

    $sections[] = array(
        'id'      => 'choice',
        'title'   => lang('Segmented control and status'),
        'classes' => array('.pg-seg', '.pg-seg-item', '.pg-seg-count', 'aria-pressed', '.pg-pill', '.pg-tone-teal', '.pg-tone-amber', '.pg-tone-blue', '.pg-tone-rose', '.pg-tone-gray'),
        'html'    => '<div class="pg-look-line"><span class="pg-seg" role="group" aria-label="' . h(lang('Filter')) . '">'
            . '<button type="button" class="pg-seg-item" aria-pressed="true">' . h(lang('All')) . '</button>'
            . '<button type="button" class="pg-seg-item" aria-pressed="false">' . h(lang('Waiting')) . ' <span class="pg-seg-count">6</span></button>'
            . '<button type="button" class="pg-seg-item" aria-pressed="false">' . h(lang('On the way')) . ' <span class="pg-seg-count">12</span></button>'
            . '</span></div><div class="pg-look-line">'
            . '<span class="pg-pill pg-tone-teal">' . h(lang('Paid')) . '</span>'
            . '<span class="pg-pill pg-tone-amber">' . h(lang('Waiting')) . '</span>'
            . '<span class="pg-pill pg-tone-blue">' . h(lang('On the way')) . '</span>'
            . '<span class="pg-pill pg-tone-rose">' . h(lang('Cancelled')) . '</span>'
            . '<span class="pg-pill pg-tone-gray">' . h(lang('Draft')) . '</span>'
            . '</div>',
    );

    $tiles = array(
        array('team', 'bi-chat-dots', lang('Team')),
        array('shop', 'bi-bag', lang('Shop')),
        array('content', 'bi-file-text', lang('Content')),
        array('accounting', 'bi-receipt', lang('Accounting')),
        array('reports', 'bi-graph-up', lang('Reports')),
        array('system', 'bi-gear', lang('System')),
    );
    $tile_html = '';

    foreach ($tiles as $tile) {
        $tile_html .= '<span class="pg-look-tile"><span class="pg-icon-tile pg-tone-' . $tile[0] . '" aria-hidden="true"><span class="bi ' . $tile[1] . '"></span></span><span class="pg-text-meta">' . h($tile[2]) . '</span></span>';
    }

    $sections[] = array(
        'id'      => 'tiles',
        'title'   => lang('Icon tiles and people'),
        'classes' => array('.pg-icon-tile', '.pg-icon-tile-lg', '.pg-tone-team', '.pg-tone-shop', '.pg-tone-content', '.pg-tone-accounting', '.pg-tone-reports', '.pg-tone-system', '.pg-initials', '.pg-initials-2', '.pg-initials-3'),
        'html'    => '<div class="pg-look-line">' . $tile_html . '</div><div class="pg-look-line">'
            . '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-team" aria-hidden="true"><span class="bi bi-bell"></span></span>'
            . '<span class="pg-initials" aria-hidden="true">AD</span><span class="pg-initials pg-initials-2" aria-hidden="true">MK</span><span class="pg-initials pg-initials-3" aria-hidden="true">EŞ</span>'
            . '</div><p class="pg-text-meta">' . h(lang('A tone names the module; a new module gets its tone in the token block, never a colour of its own.')) . '</p>',
    );

    $sections[] = array(
        'id'      => 'fields',
        'title'   => lang('Fields'),
        'classes' => array('.pg-field-label', '.pg-input', '.pg-input-end', '.pg-field-help', '.pg-field-error', 'aria-invalid', '.pg-switch', '.pg-checkbox'),
        'html'    => '<div class="pg-look-stack">'
            . '<div><label class="pg-field-label" for="' . $p . '_look_name">' . h(lang('Store name')) . '</label><input type="text" class="pg-input" id="' . $p . '_look_name" value="' . h(lang('Mug Shop')) . '"></div>'
            . '<div><label class="pg-field-label" for="' . $p . '_look_mail">' . h(lang('E-mail')) . '</label><input type="email" class="pg-input" id="' . $p . '_look_mail" value="orders@example.com" aria-describedby="' . $p . '_look_mail_help">'
            . '<div class="pg-field-help" id="' . $p . '_look_mail_help">' . h(lang('Order notices go to this address.')) . '</div></div>'
            . '<div><label class="pg-field-label" for="' . $p . '_look_tax">' . h(lang('Tax number')) . '</label><input type="text" class="pg-input" id="' . $p . '_look_tax" value="12345" aria-invalid="true" aria-describedby="' . $p . '_look_tax_help">'
            . '<div class="pg-field-help pg-field-error" id="' . $p . '_look_tax_help">' . h(lang('The tax number has 10 digits.')) . '</div></div>'
            . '<div><label class="pg-field-label" for="' . $p . '_look_price">' . h(lang('Price')) . '</label><span class="pg-input pg-look-narrow"><input type="text" id="' . $p . '_look_price" value="389,00"><span class="pg-input-end">₺</span></span></div>'
            . '<div class="pg-look-line">'
            . '<input type="checkbox" class="pg-switch" id="' . $p . '_look_switch_on" checked><label class="pg-text-2" for="' . $p . '_look_switch_on">' . h(lang('On')) . '</label>'
            . '<input type="checkbox" class="pg-switch" id="' . $p . '_look_switch_off"><label class="pg-text-2" for="' . $p . '_look_switch_off">' . h(lang('Off')) . '</label>'
            . '<input type="checkbox" class="pg-checkbox" id="' . $p . '_look_check" checked><label class="pg-text-2" for="' . $p . '_look_check">' . h(lang('Chosen')) . '</label>'
            . '</div></div>',
    );

    $sections[] = array(
        'id'      => 'cards',
        'title'   => lang('Cards'),
        'classes' => array('.pg-card', '.pg-card-sub', '.pg-card-inner', '.pg-card-inner-row', '.pg-floatbar'),
        'html'    => '<div class="pg-card pg-look-pad"><div class="pg-title-section">' . h(lang('Notifications')) . '</div><p class="pg-card-sub">' . h(lang('Choose how and when you are told.')) . '</p>'
            . '<div class="pg-card-inner">'
            . '<div class="pg-card-inner-row"><span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><span class="bi bi-bell"></span></span><div class="pg-look-grow"><strong class="pg-list-row-title">' . h(lang('Notifications on this device')) . '</strong><div class="pg-text-meta">' . h(lang('Tells you even when the tab is closed.')) . '</div></div><input type="checkbox" class="pg-switch" checked aria-label="' . h(lang('Notifications on this device')) . '"></div>'
            . '<div class="pg-card-inner-row"><span class="pg-icon-tile pg-icon-tile-lg pg-tone-teal" aria-hidden="true"><span class="bi bi-envelope"></span></span><div class="pg-look-grow"><strong class="pg-list-row-title">' . h(lang('E-mail summary')) . '</strong><div class="pg-text-meta">' . h(lang('Sends the unread ones together.')) . '</div></div><input type="checkbox" class="pg-switch" aria-label="' . h(lang('E-mail summary')) . '"></div>'
            . '</div></div>'
            . '<div class="pg-floatbar pg-look-bar"><span class="pg-text-2">' . h(lang('2 changes not saved')) . '</span><span class="pg-look-line"><button type="button" class="pg-button pg-button-ghost">' . h(lang('Undo')) . '</button><button type="button" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button></span></div>'
            . '<p class="pg-text-meta">' . h(lang('A drawer or a sheet adds .pg-card-sheet: 20 px corners instead of 16.')) . '</p>',
    );

    $sections[] = array(
        'id'      => 'rows',
        'title'   => lang('Rows and gathered rows'),
        'classes' => array('.pg-list-row', '.is-read', '.pg-list-row-main', '.pg-list-row-head', '.pg-list-row-title', '.pg-list-row-title-verb', '.pg-list-row-time', '.pg-list-row-quick', '.pg-list-row-text', '.pg-list-row-actions', '.pg-list-row-place', '.pg-list-row-side', '.pg-unread-dot', '.pg-count', '.pg-gather', '.pg-gather-line', '.pg-gather-line-end', '.pg-gather-toggle'),
        'html'    => '<div class="pg-look-rows">'
            . '<div class="pg-list-row"><span class="pg-initials" aria-hidden="true">AD</span><div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title">Ayşe Demir <span class="pg-list-row-title-verb">' . h(lang('mentioned you')) . '</span></span>'
            . '<span class="pg-list-row-quick"><button type="button" class="pg-icon-button" aria-label="' . h(lang('Mark as read')) . '"><span class="bi bi-check2" aria-hidden="true"></span></button><button type="button" class="pg-icon-button" aria-label="' . h(lang('More actions')) . '"><span class="bi bi-three-dots" aria-hidden="true"></span></button></span>'
            . '<span class="pg-list-row-time">' . h(lang('41 min')) . '</span></div>'
            . '<div class="pg-list-row-text">' . h(lang('Can you share the current price list for tomorrow\'s meeting? The customer asks about the wholesale discounts and delivery times.')) . '</div>'
            . '<div class="pg-list-row-actions"><button type="button" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Reply')) . '</button><span class="pg-list-row-place">#sales</span></div>'
            . '</div><div class="pg-list-row-side"><span class="pg-unread-dot"></span></div></div>'
            . '<div class="pg-list-row"><span class="pg-icon-tile pg-tone-shop" aria-hidden="true"><span class="bi bi-bag"></span></span><div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title">' . h(lang('New order')) . '<span class="pg-count">2</span></span><span class="pg-list-row-time">' . h(lang('Yesterday')) . '</span></div>'
            . '<div class="pg-list-row-text"><b>2.404,50 ₺</b> · ' . h(lang('last order 16:20')) . '</div>'
            . '<div class="pg-gather"><div class="pg-gather-line"><b>#SP-1044</b><span>Mehmet Kaya · ' . h(lang('3 products')) . '</span><span class="pg-gather-line-end">2.015,50 ₺</span></div>'
            . '<div class="pg-gather-line"><b>#SP-1043</b><span>Elif Şahin · ' . h(lang('1 product')) . '</span><span class="pg-gather-line-end">389,00 ₺</span></div></div>'
            . '<button type="button" class="pg-gather-toggle" aria-expanded="true">' . h(lang('Hide')) . '<span class="bi bi-chevron-up" aria-hidden="true"></span></button>'
            . '</div><div class="pg-list-row-side"><span class="pg-unread-dot"></span></div></div>'
            . '<div class="pg-list-row is-read"><span class="pg-icon-tile pg-tone-gray" aria-hidden="true"><span class="bi bi-arrow-repeat"></span></span><div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><span class="pg-list-row-title">' . h(lang('Software update available')) . '</span><span class="pg-list-row-time">' . h(lang('9 Nov')) . '</span></div>'
            . '<div class="pg-list-row-text">Pinegrap 2026.11.0</div>'
            . '</div><div class="pg-list-row-side"></div></div>'
            . '</div><p class="pg-text-meta">' . h(lang('A title says what happened and with what: no greeting, no label with a colon. Hover shows the quick actions in place of the time.')) . '</p>',
    );

    return $sections;
}

/**
 * The patterns under the parts: whole pieces of a screen, each copied from
 * the screen that built it, with the classes it is put together from. $prefix
 * keeps the ids apart when they are drawn twice (light and dark).
 *
 * @param string $prefix
 * @return array list of array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_patterns($prefix = 'light')
{
    $p = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $prefix));

    $patterns = array();

    // Site Settings > Assistants and agents: the MCP servers row open, its
    // servers as rows with their actions (ws_mcp_settings_card()).
    $server = function ($alias, $name, $details, $pill, $sign_in) {
        return '<div class="ws-agset-row">'
            . '<span class="pg-icon-tile ws-agset-face" aria-hidden="true"><i class="bi bi-plug"></i></span>'
            . '<div class="ws-agset-main">'
            . '<div class="ws-agset-name"><strong class="pg-list-row-title">@' . h($alias) . '</strong><span class="pg-text-meta">' . h($name) . '</span></div>'
            . '<div class="pg-text-meta ws-agset-summary">' . h($details) . '</div>'
            . '</div>'
            . '<span class="pg-pill ws-agset-state ' . $pill[1] . '">' . h($pill[0]) . '</span>'
            . '<div class="ws-agset-edit d-flex flex-wrap align-items-center gap-1">' . $sign_in
            . '<button type="button" class="pg-icon-button pg-icon-button-sm" aria-label="' . h(lang('Ask the server for its tools again')) . '"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>'
            . '<button type="button" class="pg-icon-button pg-icon-button-sm" aria-label="' . h(lang('Turn off')) . '"><i class="bi bi-pause-circle" aria-hidden="true"></i></button>'
            . '<button type="button" class="pg-icon-button pg-icon-button-sm" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>'
            . '<button type="button" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-sliders" aria-hidden="true"></i>' . h(lang('Tools and settings')) . '</button>'
            . '</div></div>';
    };

    // The card head's buttons as the screen has them: "Add a server" is quiet
    // with its plus, since the settings window's Save is the one filled button.
    $servers = '<div class="d-flex flex-wrap align-items-center justify-content-end gap-3 mb-3">'
        . '<a class="pg-button pg-button-ghost pg-button-sm" href="workspace_mcp.php"><i class="bi bi-person-gear" aria-hidden="true"></i>' . h(lang('My MCP connections')) . '</a>'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add a server')) . '</button>'
        . '</div>'
        . '<div class="pg-card-inner ws-agset-list">'
        . $server('docs', 'Docs', 'docs.example.com · ' . lang(array('string' => '{var:1} of {var:2} tools on', 'vars' => array(4, 6))), array(lang('Ready'), 'pg-tone-teal'),
            '<button type="button" class="pg-button pg-button-ghost pg-button-sm"><i class="bi bi-x-circle" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>')
        . $server('crm', 'CRM', 'crm.example.com · ' . lang(array('string' => '{var:1} of {var:2} tools on', 'vars' => array(0, 9))), array(lang('Not connected'), 'pg-tone-gray'),
            '<button type="button" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>' . h(lang('Connect')) . '</button>')
        . '</div>';

    $now = mktime(16, 30, 0, 11, 20, 2026);
    $parts = array(
        'ws-claude' => ws_assistants_fold_claude(array('enabled' => true, 'missing' => array(), 'last_run' => mktime(14, 5, 0, 11, 20, 2026)), $now),
        'ws-agents' => ws_assistants_fold_agents(array('total' => 3, 'not_seen' => 1, 'waiting' => 1)),
        'ws-mcp'    => ws_assistants_fold_mcp(array('total' => 2, 'sign_in' => 1, 'unreachable' => 0, 'ready' => 1)) + array('html' => $servers),
        // Switched off: what is missing is not shown then.
        'ws-voice'  => ws_assistants_fold_voice(array('enabled' => false, 'missing' => array('-'))),
    );
    $rows = array();
    $card = '<p class="pg-text-2">' . h(lang('The part\'s card opens here, its fields and buttons as they are.')) . '</p>';

    foreach ($parts as $key => $part) {
        $rows[] = array('key' => $p . '-' . $key) + ws_assistants_fold_face($key) + $part + array('html' => $card);
    }

    $patterns[] = array(
        'id'      => 'pattern-settings',
        'title'   => lang('Settings pane'),
        'classes' => array('.pg-sm-heading', '.pg-sm-desc', '.pg-set-fold', '.pg-set-fold-item', '.is-open', '.pg-set-fold-head', '.pg-set-fold-text', '.pg-set-fold-name', '.pg-set-fold-line', '.pg-set-fold-chevron', '.pg-set-fold-body', '.ws-agset-row', '.ws-agset-face', '.ws-agset-main', '.ws-agset-name', '.ws-agset-state', '.ws-agset-edit'),
        'html'    => '<div class="pg-look-stack">'
            . '<div class="pg-sm-heading"><div class="pg-title-section">' . h(lang('Assistants and agents')) . '</div><p class="pg-sm-desc pg-text-2">' . h(lang('Claude, the agents, Pinegrap AI, voice transcripts, the MCP servers and bulk changes.')) . '</p></div>'
            . pg_settings_fold($rows)
            . '</div><p class="pg-text-meta">' . h(lang('From Site Settings › Assistants and agents: pg_settings_fold() draws the rows, and the row that needs attention arrives open.')) . '</p>',
    );

    // The Connections screen's rows (connections.php): the state pill in its
    // words, the line saying what is still missing, Connect after Not connected.
    $connect = function ($icon, $tone, $name, $line, $pill, $button) {
        return '<a class="pg-card-inner-row connections-row" href="connections.php">'
            . '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . $tone . '" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>'
            . '<span class="connections-row-main"><span class="connections-row-name">' . h($name) . '</span><span class="pg-text-meta d-block">' . h($line) . '</span></span>'
            . '<span class="connections-row-side"><span class="pg-pill pg-tone-' . $pill[0] . '">' . h($pill[1]) . '</span>'
            . '<span class="pg-button pg-button-sm pg-button-quiet">' . h($button) . '</span></span>'
            . '</a>';
    };

    $patterns[] = array(
        'id'      => 'pattern-connect',
        'title'   => lang('Connect row'),
        'classes' => array('.pg-card-inner', '.connections-list', '.pg-card-inner-row', '.connections-row', '.connections-row-main', '.connections-row-name', '.connections-row-side', '.pg-pill'),
        'html'    => '<div class="pg-card-inner connections-list">'
            . $connect('bi-google', 'blue', 'Google', lang('Site Settings') . ' › ' . lang('E-mail sending'), array('teal', lang('Connected')), lang('Manage'))
            . $connect('bi-microsoft', 'blue', 'Microsoft', lang('The account must sign in again.'), array('amber', lang('Needs attention')), lang('Manage'))
            . $connect('bi-whatsapp', 'teal', 'WhatsApp', lang('Site Settings') . ' › ' . lang('Communication'), array('gray', lang('Not connected')), lang('Connect'))
            . '</div><p class="pg-text-meta">' . h(lang('From the Connections screen: a state reads the same wherever the thing is named.')) . '</p>',
    );

    $patterns[] = array(
        'id'      => 'pattern-empty',
        'title'   => lang('A list\'s empty state'),
        'classes' => array('.pg-card-inner', '.ws-empty'),
        'html'    => '<div class="pg-card-inner"><div class="ws-empty py-5"><i class="bi bi-plug" aria-hidden="true"></i>' . h(lang('No MCP server yet.')) . '</div></div>'
            . '<p class="pg-text-meta">' . h(lang('From Site Settings › Assistants and agents › MCP servers.')) . '</p>',
    );

    return $patterns;
}

/**
 * What the look does not cover yet, for a step that meets it: parts the
 * sheet draws that are no recipe yet, and parts the sheet has not drawn.
 *
 * @return array list of array('title', 'items')
 */
function pg_look_examples_not_done()
{
    return array(
        array(
            'title' => lang('Drawn in the sheet, not a recipe yet'),
            'items' => array(
                lang('Figure cards with a trend line (the list page\'s head)'),
                lang('The 12-column field grid of an add or edit screen (add and edit screens step)'),
                lang('The bell row\'s avatar and verb for a person (the server sends one title)'),
            ),
        ),
        array(
            'title' => lang('Not drawn yet'),
            'items' => array(
                lang('Dialogs and drawers, the settings modal'),
                lang('Dashboard cards'),
                lang('Report screens'),
                lang('Workspace: channel and lists'),
                lang('Phone widths'),
            ),
        ),
    );
}

/**
 * One theme's pane: every recipe with its class names.
 *
 * @param string $theme light or dark
 * @return string
 */
function pg_look_examples_pane($theme)
{
    $theme = ($theme === 'dark') ? 'dark' : 'light';
    $out = '<div class="pg-look-pane" data-bs-theme="' . $theme . '">'
        . '<h2 class="pg-title-section pg-look-pane-title">' . h(($theme === 'dark') ? lang('Dark') : lang('Light')) . '</h2>';

    $draw = function ($section) use ($theme) {
        return '<section class="pg-look-example" aria-labelledby="pg_look_' . $theme . '_' . $section['id'] . '">'
            . '<h3 class="pg-title-status" id="pg_look_' . $theme . '_' . $section['id'] . '">' . h($section['title']) . '</h3>'
            . $section['html']
            . '<p class="pg-look-classes pg-text-meta">' . implode(' ', array_map(function ($name) {
                return '<code>' . h($name) . '</code>';
            }, $section['classes'])) . '</p>'
            . '</section>';
    };

    foreach (pg_look_examples($theme) as $section) {
        $out .= $draw($section);
    }

    // The patterns: whole pieces put together from the parts above.
    $out .= '<h3 class="pg-title-section pg-look-pane-title" id="pg_look_' . $theme . '_patterns">' . h(lang('Patterns')) . '</h3>';

    foreach (pg_look_examples_patterns($theme) as $section) {
        $out .= $draw($section);
    }

    return $out . '</div>';
}
