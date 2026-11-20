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

// The starting-point sheet takes its cards from Application Access' own
// registry (with the automation card's choices, app_access.php) and the
// numbered steps drawer its steps from Claude's set-up guide; these files
// hold only functions, the registry's behind this gate.
if (!defined('PG_API_PANEL')) {
    define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/app_access.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude_setup.php');

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

    // A drawer as Application Access' #api_new and Contacts › Privacy centre
    // draw it: a dialog.drawer opened through pgUI, 480 px off the right edge
    // (a sheet from the bottom under 576 px), one form inside. Drawn here in
    // place, without the dialog, so both panes show it.
    $sections[] = array(
        'id'      => 'drawer',
        'title'   => lang('Drawer'),
        'classes' => array('drawer-header', 'drawer-title', 'btn-close', 'role="tablist"', 'role="tab"', 'aria-selected', 'drawer-body', 'drawer-footer', '.pg-seg', '.pg-seg-item', '.pg-pill', '.pg-button-danger', '.pg-button-quiet', '.pg-button-fill'),
        'html'    => '<div class="pg-card overflow-hidden d-flex flex-column" style="max-width:480px">'
            . '<div class="drawer-header"><div class="flex-grow-1 min-width-0"><h5 class="drawer-title mb-0">Ayşe Demir</h5>'
            . '<p class="small fg-2 mb-0 mt-1 d-flex align-items-center gap-3">' . h(lang('Erasure')) . ' <span class="pg-pill pg-tone-amber">' . h(lang(array('string' => '{var:1} days left', 'vars' => array(5)))) . '</span></p></div>'
            . '<button type="button" class="btn-close ms-auto" aria-label="' . h(lang('Close')) . '"></button></div>'
            . '<div class="px-5 pt-3 pb-3 border-bottom"><span class="pg-seg" role="tablist" aria-label="' . h(lang('Request')) . '">'
            . '<button type="button" class="pg-seg-item" role="tab" aria-selected="true" aria-controls="' . $p . '_look_drawer_pane">' . h(lang('Request')) . '</button>'
            . '<button type="button" class="pg-seg-item" role="tab" aria-selected="false" tabindex="-1">' . h(lang('History')) . '</button></span></div>'
            . '<div class="drawer-body" role="tabpanel" id="' . $p . '_look_drawer_pane"><label class="pg-field-label" for="' . $p . '_look_drawer_field">' . h(lang('Handled by')) . '</label><input type="text" class="pg-input" id="' . $p . '_look_drawer_field" value="Mehmet Kaya"></div>'
            . '<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><button type="button" class="pg-button pg-button-danger">' . h(lang('Refuse')) . '</button><span class="flex-grow-1"></span>'
            . '<button type="button" class="pg-button pg-button-quiet">' . h(lang('Cancel')) . '</button><button type="button" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button></div>'
            . '</div><p class="pg-text-meta">' . h(lang('One form in a dialog.drawer opened through pgUI; tabs as the segmented control with role tab; the danger button at the left of the foot, Save last. A closed record shows plain text and Close only.')) . '</p>',
    );

    $sections[] = array(
        'id'      => 'menus',
        'title'   => lang('Menus'),
        'classes' => array('.pg-menu-wrap', '.menu', '.pg-menu-end', '.menu-item', '.menu-divider'),
        'html'    => '<div class="pg-look-line">'
            . '<button type="button" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Edit')) . '</button>'
            . '<span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
            . '<ul class="menu pg-menu-end">'
            . '<li><button type="button" class="menu-item"><i class="bi bi-files me-3" aria-hidden="true"></i>' . h(lang('Duplicate')) . '</button></li>'
            . '<li><hr class="menu-divider"></li>'
            . '<li><button type="button" class="menu-item fg-emphasis-danger"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></li>'
            . '</ul></span>'
            . '</div><p class="pg-text-meta">' . h(lang('A row\'s "more" menu sits last in its actions, after the quiet buttons; a danger item is last, under a divider, and asks a confirm.')) . '</p>',
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

    // Application Access' New application sheet (includes/api/app_new_view.php),
    // each of its three steps in the Drawer section's standing frame at the
    // sheet's width. The step line is api_app_new_steps_html()'s. The frame
    // is no .drawer, which sets the padding variables, so p-5 gives the head
    // and the body the drawer's own 1rem.
    $sheet_steps = function ($current) {
        $labels = array(1 => lang('What connects'), 2 => lang('Details and permissions'), 3 => lang('Connect'));
        $html = '<ol class="stepper stepper-horizontal mb-0">';

        foreach ($labels as $number => $label) {
            $class = ($number < $current) ? ' active theme-success' : (($number === $current) ? ' active' : '');
            $html .= '<li class="stepper-item' . $class . '"' . (($number === $current) ? ' aria-current="step"' : '') . '>' . h($label) . '</li>';
        }

        return $html . '</ol>';
    };
    $sheet = function ($current, $tile, $title, $line, $body, $foot) use ($sheet_steps) {
        return '<div class="pg-card overflow-hidden d-flex flex-column" style="max-width:760px">'
            . '<div class="drawer-header p-5 pb-1 d-block"><div class="d-flex align-items-start gap-3">' . $tile
            . '<div class="flex-grow-1 min-width-0"><h5 class="drawer-title mb-0">' . h($title) . '</h5>' . $line . '</div>'
            . '<button type="button" class="btn-close ms-auto" aria-label="' . h(lang('Close')) . '"></button></div></div>'
            . '<div class="px-5 pt-3 pb-5 border-bottom">' . $sheet_steps($current) . '</div>'
            . '<div class="drawer-body p-5">' . $body . '</div>'
            . '<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">' . $foot . '</div>'
            . '</div>';
    };

    // The cards of api_app_templates(), as api_app_new_card_html() draws them;
    // the site's OAuth application is there, so the first card says so.
    $cards = '';
    $first = true;

    foreach (api_app_templates(array(), array(), array(), array('id' => 1, 'name' => 'Claude')) as $card) {
        $id = $p . '_look_start_' . $card['id'];
        $secret = $card['secret']
            ? '<i class="bi bi-key" aria-hidden="true"></i>' . h(lang('Key and secret'))
            : '<i class="bi bi-shield-check" aria-hidden="true"></i>' . h(lang('No secret'));
        $cards .= '<div class="form-field form-field-card">'
            . '<input class="radio" type="radio" name="' . $p . '_look_start" value="' . h($card['id']) . '" id="' . $id . '" aria-describedby="' . $id . '_help ' . $id . '_acts"' . ($first ? ' checked' : '') . '>'
            . '<label class="pg-start-head" for="' . $id . '"><span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . h($card['tone']) . '" aria-hidden="true"><i class="bi ' . h($card['icon']) . '"></i></span>'
            . '<span class="fw-bold">' . h($card['title']) . '</span>'
            . (($card['badge'] !== '') ? ' <span class="pg-pill pg-tone-teal">' . h($card['badge']) . '</span>' : '') . '</label>'
            . '<div class="form-text mt-1" id="' . $id . '_help">' . h($card['text']) . '</div>'
            . '<div class="pg-start-acts" id="' . $id . '_acts"><div>' . h(lang('Acts as:')) . ' <b>' . h($card['acts_as']) . '</b></div><div class="fg-2">' . $secret . '</div></div>'
            . '</div>';
        $first = false;
    }

    $head_tile = '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-plug"></i></span>';
    $cancel = '<button type="button" class="pg-button pg-button-ghost">' . h(lang('Cancel')) . '</button>';

    $start = $sheet(1, $head_tile, lang('New Application'),
        '<p class="small fg-2 mb-0 mt-1">' . h(lang('What connects to this site? Pick one and the rest is prepared for it.')) . '</p>',
        '<fieldset><legend class="visually-hidden">' . h(lang('What connects to this site?')) . '</legend><div class="pg-start">' . $cards . '</div>'
            . '<div class="alert theme-primary py-3 px-5 small mt-5 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><b>' . h(lang('Why does "Acts as" matter?')) . '</b> '
            . h(lang('A connection made by signing in writes as the person who signed in. A key writes as the application itself.')) . '</div></fieldset>',
        '<span class="small fg-2"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . h(lang('The choice is only a start; the permissions can be changed in the next step.')) . '</span>'
            . '<span class="flex-grow-1"></span>' . $cancel
            . '<button type="button" class="pg-button pg-button-fill">' . h(lang('Continue')) . '<i class="bi bi-arrow-right" aria-hidden="true"></i></button>');

    $details = $sheet(2, '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-code-slash"></i></span>', lang('New Application'),
        '<p class="small fg-2 mb-0 mt-1">' . h(lang('Own integration')) . ' &middot; <button type="button" class="pg-button pg-button-ghost pg-button-sm">' . h(lang('Change')) . '</button></p>',
        '<div class="row g-5 mb-5">'
            . '<div class="col-12 sm:col-6 form-field"><label class="form-label pg-field-label" for="' . $p . '_look_start_name">' . h(lang('Application name')) . '</label>'
            . '<input type="text" class="form-control form-control-sm" id="' . $p . '_look_start_name" placeholder="' . h(lang('For example: marketplace sync')) . '"></div>'
            . '<div class="col-12 sm:col-6 form-field"><label class="form-label pg-field-label" for="' . $p . '_look_start_description">' . h(lang('Description')) . ' <span class="fg-2 fw-normal">' . h(lang('optional')) . '</span></label>'
            . '<input type="text" class="form-control form-control-sm" id="' . $p . '_look_start_description" placeholder="' . h(lang('What is it used for?')) . '"></div>'
            . '</div>'
            . '<div class="mb-5"><div class="form-label pg-field-label"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>' . h(lang('Permissions')) . '</div>'
            . '<p class="pg-text-2">' . h(lang('The permission picker opens here, as Application Access draws it.')) . '</p></div>'
            . '<details class="pg-start-advanced"><summary class="small"><i class="bi bi-sliders me-1" aria-hidden="true"></i><b>' . h(lang('Advanced')) . '</b> <span class="small fg-2">' . h(lang('IP list and rate limit')) . '</span></summary>'
            . '<div class="form-field mt-3"><label class="form-label pg-field-label" for="' . $p . '_look_start_rate">' . h(lang('Requests per minute')) . '</label>'
            . '<input type="number" class="form-control form-control-sm" id="' . $p . '_look_start_rate" min="1" max="6000"></div></details>',
        '<button type="button" class="pg-button pg-button-ghost"><i class="bi bi-arrow-left" aria-hidden="true"></i>' . h(lang('Back')) . '</button>'
            . '<span class="flex-grow-1"></span><span class="small fg-2">' . h(lang('Nothing is saved before Create.')) . '</span>' . $cancel
            . '<button type="button" class="pg-button pg-button-fill">' . h(lang('Create')) . '</button>');

    // Step 3 is the one-time sheet Application Access shows once the
    // application is made: what to copy, numbered, and Close after the tick.
    $copy = function ($name, $label, $value) use ($p) {
        $id = $p . '_look_start_' . $name;

        return '<div class="form-field mb-5"><span class="form-label pg-field-label" id="' . $id . '_label">' . $label . '</span>'
            . '<div class="pg-start-copy"><code id="' . $id . '">' . h($value) . '</code>'
            . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" aria-labelledby="' . $id . '_copy ' . $id . '_label" id="' . $id . '_copy"><i class="bi bi-clipboard" aria-hidden="true"></i>' . h(lang('Copy')) . '</button></div></div>';
    };

    $done = $sheet(3, '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-amber" aria-hidden="true"><i class="bi bi-key"></i></span>', lang('The application was created'),
        '<p class="small fg-2 mb-0 mt-1">' . h(lang('Own integration')) . '</p>',
        '<p class="mb-5"><b>' . h(lang('Once this window is closed the secret is never shown again.')) . '</b><br><span class="small opacity-75">' . h(lang('Only a verification hash is stored. If it is lost, issue a new one.')) . '</span></p>'
            . $copy('site', '1 &middot; ' . h(lang('Site address')), 'https://example.com')
            . $copy('key', '2 &middot; ' . h(lang('Key')), 'pg_live_4f1c9a20')
            . $copy('secret', '3 &middot; ' . h(lang('Secret')), '9b2e…c41d')
            . '<div class="alert theme-warning py-3 px-5 small mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>' . h(lang('Keep the secret like a password. Do not put it in a URL, an email or a shared file - server logs record those too.')) . '</div>',
        '<div class="form-field me-auto"><input class="check" type="checkbox" id="' . $p . '_look_start_ack"><label class="small" for="' . $p . '_look_start_ack">' . h(lang('I have copied it and stored it somewhere safe')) . '</label></div>'
            . '<button type="button" class="pg-button pg-button-fill disabled">' . h(lang('Close')) . '</button>');

    $patterns[] = array(
        'id'      => 'pattern-start',
        'title'   => lang('Starting-point sheet'),
        'classes' => array('drawer-header', 'stepper-horizontal', 'aria-current="step"', 'form-field-card', '.pg-start', '.pg-start-head', '.pg-start-acts', '.pg-start-advanced', '.pg-start-copy', '.pg-icon-tile', '.pg-pill', '.pg-button-ghost', '.pg-button-fill', 'drawer-footer'),
        'html'    => '<div class="pg-look-stack">' . $start . $details . $done . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From Application Access › New Application: starting points as cards with an icon and one line, then the details, then what is shown once. Nothing is saved before Create.')) . '</p>',
    );

    // Claude's "Set up on claude.ai" drawer (ws_claude_setup_drawer_html()):
    // the steps a person follows on another site, numbered by the stepper, in
    // a drawer of their own that one quiet button opens.
    $guide = ws_claude_setup_guide('https://example.com/pinegrap/', 'example.com', '', function () {
        return '';
    });

    $patterns[] = array(
        'id'      => 'pattern-steps',
        'title'   => lang('Numbered steps drawer'),
        'classes' => array('.pg-stepper-cards', 'stepper-item', '.ws-claude-setup-steps', '.ws-claude-shot', '.pg-field-label', '.pg-button-quiet'),
        'html'    => '<div class="pg-look-line"><button type="button" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>' . h(lang('Set up on claude.ai')) . '</button></div>'
            . '<div class="pg-card overflow-hidden d-flex flex-column" style="max-width:42rem">'
            . '<div class="drawer-header border-bottom p-5"><h5 class="drawer-title">' . h(lang('Set up on claude.ai')) . '</h5><button type="button" class="btn-close" aria-label="' . h(lang('Close')) . '"></button></div>'
            . '<div class="drawer-body p-5"><ol class="stepper pg-stepper-cards ws-claude-setup-steps">' . $guide . '</ol></div>'
            . '</div><p class="pg-text-meta">' . h(lang('From Site Settings › Assistants and agents › Claude: steps done on another site are numbered steps in their own drawer, opened by one button, never paragraphs in the form.')) . '</p>',
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
                lang('Dialogs and the settings modal'),
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
