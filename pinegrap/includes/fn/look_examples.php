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
        'classes' => array('.pg-button', '.pg-button-fill', '.pg-button-quiet', '.pg-button-ghost', '.pg-button-danger', '.pg-button-sm', '.pg-button-split', '.pg-button-split-more', '.pg-icon-button', '.pg-icon-button-sm', '.is-active'),
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
            . '</div><div class="pg-look-line">'
            . '<span class="pg-button-split">'
            . '<button type="button" class="pg-button pg-button-fill">' . h(lang('Send the canvas')) . '</button>'
            . '<button type="button" class="pg-button pg-button-fill pg-button-split-more" aria-haspopup="menu" aria-expanded="false" aria-label="' . h(lang('More save options')) . '"><span class="bi bi-chevron-down" aria-hidden="true"></span></button>'
            . '</span>'
            . '<span class="pg-text-meta">' . h(lang('Split button: the chevron opens the action\'s own menu.')) . '</span>'
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
    // place, without the dialog, so both panes show it. The frame is no
    // .drawer, which sets the padding variables: p-5 gives the head and the
    // body the 16 px a real drawer has (measured on #api_new).
    $sections[] = array(
        'id'      => 'drawer',
        'title'   => lang('Drawer'),
        'classes' => array('drawer-header', 'drawer-title', 'btn-close', 'role="tablist"', 'role="tab"', 'aria-selected', 'drawer-body', 'drawer-footer', '.pg-seg', '.pg-seg-item', '.pg-pill', '.pg-button-danger', '.pg-button-quiet', '.pg-button-fill'),
        'html'    => '<div class="pg-card overflow-hidden d-flex flex-column" style="max-width:480px">'
            . '<div class="drawer-header p-5"><div class="flex-grow-1 min-w-0"><h5 class="drawer-title mb-0">Ayşe Demir</h5>'
            . '<p class="small fg-2 mb-0 mt-1 d-flex align-items-center gap-3">' . h(lang('Erasure')) . ' <span class="pg-pill pg-tone-amber">' . h(lang(array('string' => '{var:1} days left', 'vars' => array(5)))) . '</span></p></div>'
            . '<button type="button" class="btn-close ms-auto" aria-label="' . h(lang('Close')) . '"></button></div>'
            . '<div class="px-5 pt-3 pb-3 border-bottom"><span class="pg-seg" role="tablist" aria-label="' . h(lang('Request')) . '">'
            . '<button type="button" class="pg-seg-item" role="tab" aria-selected="true" aria-controls="' . $p . '_look_drawer_pane">' . h(lang('Request')) . '</button>'
            . '<button type="button" class="pg-seg-item" role="tab" aria-selected="false" tabindex="-1">' . h(lang('History')) . '</button></span></div>'
            . '<div class="drawer-body p-5" role="tabpanel" id="' . $p . '_look_drawer_pane"><label class="pg-field-label" for="' . $p . '_look_drawer_field">' . h(lang('Handled by')) . '</label><input type="text" class="pg-input" id="' . $p . '_look_drawer_field" value="Mehmet Kaya"></div>'
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
 * keeps the ids apart when they are drawn twice (light and dark). 'phone'
 * marks the patterns the phone pane draws as well.
 *
 * @param string $prefix
 * @return array list of array('id', 'title', 'classes', 'html', 'phone')
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
        'phone'   => true,
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
            . '<div class="flex-grow-1 min-w-0"><h5 class="drawer-title mb-0">' . h($title) . '</h5>' . $line . '</div>'
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
    // the steps a person follows on another site, numbered by the stepper, a
    // title and one sentence each with the values to copy, in a drawer of
    // their own that one quiet button opens.
    $guide = ws_claude_setup_guide('example.com', "Site API: https://example.com/pinegrap/integration.php\n1. GET …/workspace/claude/instructions\n2. Follow them.", 'pg_live_example');
    // Both themes draw it: their own ids.
    $guide = str_replace(array('id="ws-claude-', 'for="ws-claude-', 'data-ws-copy-field="ws-claude-'), array('id="' . $p . '-ws-claude-', 'for="' . $p . '-ws-claude-', 'data-ws-copy-field="' . $p . '-ws-claude-'), $guide);

    $patterns[] = array(
        'id'      => 'pattern-steps',
        'title'   => lang('Numbered steps drawer'),
        'classes' => array('.pg-stepper-cards', 'stepper-item', '.ws-claude-setup-steps', '.ws-claude-step-title', '.ws-claude-code', '.pg-value-row', '.ws-claude-value-field', '.pg-icon-tile-lg', 'drawer-footer', '.pg-button-quiet'),
        'html'    => '<div class="pg-look-line"><button type="button" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>' . h(lang('Set up on claude.ai')) . '</button></div>'
            . '<div class="pg-card overflow-hidden d-flex flex-column" style="max-width:34rem">'
            . '<div class="drawer-header border-bottom gap-3 p-5"><span class="pg-icon-tile pg-icon-tile-lg pg-tone-violet" aria-hidden="true"><i class="bi bi-stars"></i></span>'
            . '<div class="flex-grow-1 min-w-0"><h5 class="drawer-title">' . h(lang('Set up on claude.ai')) . '</h5><p class="small fg-2 mb-0 mt-1">' . h(lang('Five steps; every value has Copy beside it.')) . '</p></div>'
            . '<button type="button" class="btn-close" aria-label="' . h(lang('Close')) . '"></button></div>'
            . '<div class="drawer-body p-5"><ol class="stepper pg-stepper-cards ws-claude-setup-steps">' . $guide . '</ol></div>'
            . '<div class="drawer-footer border-top p-5 d-flex"><button type="button" class="pg-button pg-button-quiet ms-auto">' . h(lang('Close')) . '</button></div>'
            . '</div><p class="pg-text-meta">' . h(lang('From Site Settings › Assistants and agents › Claude: steps done on another site are numbered steps in their own drawer, opened by one button, never paragraphs in the form.')) . '</p>',
    );

    $patterns[] = pg_look_examples_form_drawer_pattern($p);
    // The Workspace's screens: the bar with "Filter" and the empty screen.
    $patterns = array_merge($patterns, pg_look_examples_ws_screen_patterns($p));
    $patterns[] = pg_look_examples_timeline_pattern();
    $patterns[] = pg_look_examples_ws_list_row_pattern($p);
    $patterns = array_merge($patterns, pg_look_examples_record_patterns($p));
    $patterns[] = pg_look_examples_doc_head_pattern();
    $patterns[] = pg_look_examples_figure_grid_pattern();
    $patterns[] = pg_look_examples_address_pattern($p);
    $patterns[] = pg_look_examples_line_row_pattern($p);
    $patterns[] = pg_look_examples_slides_pattern($p);
    $patterns[] = pg_look_examples_tools_dock_pattern($p);
    $patterns[] = pg_look_examples_flow_box_pattern($p);
    $patterns[] = pg_look_examples_canvas_tools_pattern();
    $patterns[] = pg_look_examples_add_list_pattern();
    $patterns[] = pg_look_examples_flow_trial_pattern();
    $patterns[] = pg_look_examples_flow_counts_pattern();
    $patterns[] = pg_look_examples_menu_icon_pattern();
    $patterns[] = pg_look_examples_person_status_pattern($p);

    // The e-mail settings drawing (Site Settings › E-mail, boards A to E):
    // the parts the one E-mail category is built from.
    $patterns = array_merge($patterns, pg_look_examples_mail_patterns($p));

    return $patterns;
}

/**
 * The form drawer as Application Access' application drawer
 * (includes/api/app_drawer_view.php, #api_drawer) puts it together: the head
 * with the thing's tile, kind and state pill; the tabs as the segmented
 * control; the first tab's groups under their labels, what few people change
 * folded as "Advanced" (pg_settings_fold_row(), closed); the danger group
 * last; the foot with Cancel and the one filled Save. Under it the in-place
 * confirm the danger button opens. Drawn in the standing frame at the
 * drawer's width, without the dialog.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_form_drawer_pattern($p)
{
    $tab = function ($label, $selected, $count = '') use ($p) {
        return '<button type="button" class="pg-seg-item' . ($selected ? ' active' : '') . '" role="tab" aria-selected="' . ($selected ? 'true' : 'false') . '"'
            . ($selected ? ' id="' . $p . '_look_form_tab" aria-controls="' . $p . '_look_form_pane"' : ' tabindex="-1"') . '>'
            . h($label) . (($count !== '') ? ' <span class="pg-seg-count">' . h($count) . '</span>' : '') . '</button>';
    };

    $delete_text = '<div class="flex-grow-1 min-w-0"><div class="pg-list-row-title">' . h(lang('Delete the application')) . '</div>'
        . '<div class="pg-text-meta">' . h(lang('Any integration using this key stops working immediately. This cannot be undone.')) . '</div></div>';

    $advanced = '<div class="form-field"><label class="form-label pg-field-label" for="' . $p . '_look_form_rate">' . h(lang('Requests per minute')) . '</label>'
        . '<input type="number" class="form-control form-control-sm" id="' . $p . '_look_form_rate" min="1" max="6000">'
        . '<div class="form-text">' . h(lang('When an application asks for more than this in one minute, the rest of that minute is refused and it is told how long to wait.')) . '</div></div>';

    $drawer = '<div class="pg-card overflow-hidden d-flex flex-column" style="max-width:760px">'
        . '<div class="drawer-header p-5 pb-1 d-block"><div class="d-flex align-items-start gap-3">'
        . '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-key"></i></span>'
        . '<div class="flex-grow-1 min-w-0"><h5 class="drawer-title mb-0">Trendyol sync</h5>'
        . '<p class="small fg-2 mb-0 mt-1">' . h(lang('Integration')) . ' &middot; ' . h(lang('key and secret')) . ' &middot; ' . h(lang('Acts as:')) . ' <b>' . h(lang('the application itself')) . '</b></p></div>'
        . '<span class="pg-pill pg-tone-teal">' . h(lang('Active')) . '</span>'
        . '<button type="button" class="btn-close" aria-label="' . h(lang('Close')) . '"></button></div></div>'
        . '<div class="px-5 pt-3"><div class="pg-seg pg-list-seg" role="tablist">'
        . $tab(lang('Overview'), true) . $tab(lang('Connect'), false) . $tab(lang('Permissions'), false, '12') . $tab(lang('Events'), false) . $tab(lang('Logs'), false)
        . '</div></div>'
        . '<div class="drawer-body p-5" role="tabpanel" id="' . $p . '_look_form_pane" aria-labelledby="' . $p . '_look_form_tab">'
        . '<div class="pg-text-meta mb-2">' . h(lang('Details')) . '</div>'
        . '<div class="row g-5 mb-5">'
        . '<div class="col-12 sm:col-6 form-field"><label class="form-label pg-field-label" for="' . $p . '_look_form_name">' . h(lang('Application name')) . '</label>'
        . '<input type="text" class="form-control form-control-sm" id="' . $p . '_look_form_name" value="Trendyol sync"></div>'
        . '<div class="col-12 sm:col-6 form-field"><label class="form-label pg-field-label" for="' . $p . '_look_form_description">' . h(lang('Description')) . ' <span class="fg-2 fw-normal">' . h(lang('optional')) . '</span></label>'
        . '<input type="text" class="form-control form-control-sm" id="' . $p . '_look_form_description"></div>'
        . '</div>'
        . '<div class="pg-card-inner mb-5"><div class="pg-card-inner-row"><div class="flex-grow-1 min-w-0">'
        . '<label class="pg-list-row-title d-block" for="' . $p . '_look_form_status">' . h(lang('Active')) . '</label>'
        . '<div class="pg-text-meta">' . h(lang('Turning it off refuses the key at once; the settings stay.')) . '</div></div>'
        . '<input type="checkbox" class="pg-switch" id="' . $p . '_look_form_status" checked></div></div>'
        . '<div class="pg-text-meta mb-2">' . h(lang('Summary')) . '</div>'
        . '<div class="pg-card-inner mb-5"><div class="pg-card-inner-row">'
        . '<span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><i class="bi bi-shield-check"></i></span>'
        . '<div class="flex-grow-1 min-w-0"><div class="pg-list-row-title">' . h(lang('Permissions')) . '</div>'
        . '<div class="pg-text-meta">' . h(lang(array('string' => '{var:1} of {var:2} chosen', 'vars' => array(12, 31)))) . '</div></div>'
        . '<button type="button" class="pg-button pg-button-ghost pg-button-sm">' . h(lang('Go to permissions')) . '<i class="bi bi-chevron-right" aria-hidden="true"></i></button></div>'
        . pg_settings_fold_row(array(
            'key'  => $p . '-look-form-advanced',
            'name' => lang('Advanced'),
            'icon' => 'bi-sliders',
            'tone' => 'pg-tone-gray',
            'line' => lang('IP list, end date and rate limit'),
            'pill' => null,
            'html' => $advanced,
        ), false)
        . '</div>'
        . '<div class="pg-text-meta fg-danger mb-2">' . h(lang('Danger')) . '</div>'
        . '<div class="pg-card-inner"><div class="pg-card-inner-row">' . $delete_text
        . '<button type="button" class="pg-button pg-button-danger pg-button-sm"><i class="bi bi-trash" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></div></div>'
        . '</div>'
        . '<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">'
        . '<a class="pg-text-meta fg-2 text-decoration-none" href="connections.php"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('For the services the site reaches: Connections')) . '</a>'
        . '<span class="flex-grow-1"></span>'
        . '<button type="button" class="pg-button pg-button-quiet">' . h(lang('Cancel')) . '</button>'
        . '<button type="button" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button></div>'
        . '</div>';

    // The danger row once Delete is pressed: the button gives way to Cancel
    // and the danger button that does it, in the same row.
    $confirm = '<div class="pg-card-inner" style="max-width:760px"><div class="pg-card-inner-row">' . $delete_text
        . '<span class="text-nowrap"><button type="button" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Cancel')) . '</button>'
        . '<button type="button" class="pg-button pg-button-danger pg-button-sm ms-2">' . h(lang('Yes, delete')) . '</button></span></div></div>';

    return array(
        'id'      => 'pattern-form',
        'title'   => lang('Form drawer'),
        'phone'   => true,
        'classes' => array('drawer-header', '.pg-seg', '.pg-list-seg', '.pg-seg-item', '.pg-seg-count', 'role="tablist"', 'role="tab"', 'aria-selected="true"', 'role="tabpanel"', '.pg-pill', '.pg-text-meta', '.pg-field-label', '.pg-card-inner', '.pg-card-inner-row', '.pg-switch', '.pg-set-fold-item', '.pg-set-fold-head', '.pg-set-fold-body', '.pg-button-danger', 'drawer-footer', '.pg-button-quiet', '.pg-button-fill'),
        'html'    => '<div class="pg-look-stack">' . $drawer . $confirm . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From Application Access\' application drawer: tabs as the segmented control, the first tab\'s groups under their labels, Advanced folded, the danger group last; Cancel and the one filled Save in the foot. Delete asks in its own row. A confirm that names what goes with it is "Confirm with its effects".')) . '</p>',
    );
}

/**
 * The patterns of the approved e-mail settings drawing
 * (docs/memory/plans/mail-settings-one/email-settings.html, boards A to E):
 * tabs with a state, value rows, choice cards, the step line, the provider
 * sign-in, the result line and the confirm with its effects; then the
 * switch rows of the Features settings board. 'phone' marks
 * the patterns the phone pane draws as well.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array list of array('id', 'title', 'classes', 'html', 'phone')
 */
function pg_look_examples_mail_patterns($p)
{
    $patterns = array();

    // (A, C) The category's two tabs, each saying its state in its pill.
    $tab = function ($label, $tone, $state, $selected) {
        return '<button type="button" class="pg-seg-item" role="tab" aria-selected="' . ($selected ? 'true' : 'false') . '">' . h($label)
            . ' <span class="pg-pill pg-tone-' . $tone . '" title="' . h($state) . '"><span>' . h($state) . '</span></span></button>';
    };
    $tabs = function ($items) {
        return '<div class="pg-seg pg-seg-tall" role="tablist" aria-label="' . h(lang('E-mail')) . '">' . implode('', $items) . '</div>';
    };

    $patterns[] = array(
        'id'      => 'pattern-tabs',
        'title'   => lang('Tabs with a state'),
        'classes' => array('.pg-seg', '.pg-seg-tall', '.pg-seg-item', 'role="tablist"', 'role="tab"', 'aria-selected="true"', '.pg-pill', '.pg-tone-teal', '.pg-tone-amber', '.pg-tone-gray'),
        'html'    => '<div class="pg-look-stack">'
            . '<div>' . $tabs(array($tab(lang('E-mail sending'), 'teal', lang('Ready'), true), $tab(lang('Mailboxes'), 'amber', lang('Sign-in needed'), false))) . '</div>'
            . '<div>' . $tabs(array($tab(lang('E-mail sending'), 'amber', lang('Check the domain'), false), $tab(lang('Mailboxes'), 'gray', lang('No box'), true))) . '</div>'
            . '</div><p class="pg-text-meta">' . h(lang('From Site Settings › E-mail: each tab says its state in its pill; under 576 px the pill is its dot and the word its title. The tab is kept in the address.')) . '</p>',
    );

    // (A) How e-mail leaves: a row a kind of mail, what happens today in one
    // sentence, Change at the row's end.
    $value = function ($tone, $icon, $name, $line) {
        return '<div class="pg-value-row">'
            . '<span class="pg-icon-tile pg-tone-' . $tone . '" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>'
            . '<span class="pg-value-row-text"><span class="pg-value-row-name">' . h($name) . '</span><span class="pg-value-row-line">' . h($line) . '</span></span>'
            . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" aria-label="' . h(lang('Change') . ': ' . $name) . '">' . h(lang('Change')) . '</button>'
            . '</div>';
    };

    $patterns[] = array(
        'id'      => 'pattern-values',
        'title'   => lang('Value rows'),
        'classes' => array('.pg-card-inner', '.pg-value-row', '.pg-value-row-text', '.pg-value-row-name', '.pg-value-row-line', '.pg-icon-tile', '.pg-button-quiet', '.pg-button-sm', '.pg-field-help'),
        'html'    => '<div><div class="pg-card-inner">'
            . $value('blue', 'bi-envelope-check', lang('System e-mail'), lang(array('string' => 'Leaves through the Google account {var:1}.', 'vars' => array('info@example.com'))))
            . $value('violet', 'bi-megaphone', lang('Campaigns'), lang('Leaves the same way as system e-mail.'))
            . '</div><div class="pg-field-help">' . h(lang('System e-mail is order, form, password and notice mail; campaigns are bulk sends.')) . '</div></div>'
            . '<p class="pg-text-meta">' . h(lang('From Site Settings › E-mail › How e-mail leaves: the sentence says what happens today, whatever decides it.')) . '</p>',
    );

    // (B, D1, D2) The starting-point cards as a radio group: the chosen
    // card's ring and mark, a note at a card's end, and under the group only
    // the chosen card's fields.
    $choice = function ($name, $value, $tone, $icon, $title, $text, $note, $checked) use ($p) {
        $id = $p . '_look_' . $name . '_' . $value;

        return '<div class="form-field form-field-card">'
            . '<input class="radio" type="radio" name="' . $p . '_look_' . $name . '" value="' . h($value) . '" id="' . $id . '" aria-describedby="' . $id . '_help"' . ($checked ? ' checked' : '') . '>'
            . '<label class="pg-start-head" for="' . $id . '">'
            . (($icon !== '') ? '<span class="pg-icon-tile pg-tone-' . $tone . '" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>' : '')
            . '<strong>' . h($title) . '</strong><span class="pg-start-mark" aria-hidden="true"><i class="bi bi-check"></i></span></label>'
            . '<div class="form-text" id="' . $id . '_help">' . h($text) . (($note !== '') ? '<span class="d-block pg-start-note">' . h($note) . '</span>' : '') . '</div>'
            . '</div>';
    };

    $ways = '<fieldset><legend class="visually-hidden">' . h(lang('How should system e-mail leave?')) . '</legend><div class="pg-start pg-start-choice">'
        . $choice('way', 'google', 'blue', 'bi-google', lang('A Google account'), lang('Leaves from your Gmail or Google Workspace address.'), '', true)
        . $choice('way', 'microsoft', 'teal', 'bi-microsoft', lang('A Microsoft account'), lang('Leaves from your Microsoft 365 or Outlook address.'), '', false)
        . $choice('way', 'service', 'violet', 'bi-lightning-charge', lang('A sending service'), lang('With the key of a service such as Brevo, Mailgun or Amazon SES.'), '', false)
        . $choice('way', 'server', 'gray', 'bi-hdd-rack', lang('Your own mail server'), lang('With the server name, a user and a password.'), '', false)
        . '</div>'
        . '<div class="pg-group-label">' . h(lang('A Google account')) . '</div>'
        . '<label class="pg-field-label" for="' . $p . '_look_way_account">' . h(lang('Account')) . '</label>'
        . '<span class="pg-input"><select id="' . $p . '_look_way_account" aria-describedby="' . $p . '_look_way_account_help"><option>info@example.com</option></select><span class="pg-input-end">' . h(lang('Connected for a mailbox')) . '</span></span>'
        . '<div class="pg-field-help" id="' . $p . '_look_way_account_help">' . h(lang('One account reads and sends; no second connection.')) . '</div>'
        . '<div class="pg-look-line mt-4"><button type="button" class="pg-button pg-button-quiet"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Connect another Google account')) . '</button></div>'
        . '</fieldset>';

    $setup = '<fieldset><legend class="visually-hidden">' . h(lang('How should the mailbox sign in?')) . '</legend><div class="pg-start pg-start-choice">'
        . $choice('setup', 'oauth', '', '', lang('Set up Google sign-in'), lang('Once; its steps open in a drawer.'), lang('About 10 minutes'), true)
        . $choice('setup', 'password', '', '', lang('Use an app password'), lang('The password is written to the site.'), lang('2 minutes'), false)
        . '</div></fieldset>';

    $patterns[] = array(
        'id'      => 'pattern-choice',
        'title'   => lang('Choice cards'),
        'phone'   => true,
        'classes' => array('.pg-start', '.pg-start-choice', 'form-field-card', '.pg-start-head', '.pg-start-mark', '.pg-start-note', '.pg-icon-tile', '.pg-group-label', '.pg-field-label', '.pg-input', '.pg-input-end', '.pg-field-help', '.pg-button-quiet'),
        'html'    => '<div class="pg-look-stack">' . $ways . $setup . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From Site Settings › E-mail › Change and Add a mailbox: two a row, one on a phone; under the group only the chosen card\'s fields.')) . '</p>',
    );

    // (D1 to D3) The sheet's step line in its three states.
    $steps = function ($current) {
        $names = array(1 => lang('Choose'), 2 => lang('Sign in'), 3 => lang('Details'));
        $html = '<ol class="stepper pg-steps" aria-label="' . h(lang('Add a mailbox')) . '">';

        foreach ($names as $number => $name) {
            $done = $number < $current;
            $html .= '<li class="stepper-item' . ($done ? ' is-done' : '') . '"' . (($number === $current) ? ' aria-current="step"' : '') . '>'
                . '<span class="pg-steps-name">' . h($name) . ($done ? '<span class="visually-hidden"> (' . h(lang('done')) . ')</span>' : '') . '</span></li>';
        }

        return $html . '</ol>';
    };

    $patterns[] = array(
        'id'      => 'pattern-stepline',
        'title'   => lang('Step line'),
        'phone'   => true,
        'classes' => array('stepper', '.pg-steps', 'stepper-item', 'aria-current="step"', '.is-done', '.pg-steps-name'),
        'html'    => '<div class="pg-look-stack">' . $steps(1) . $steps(2) . $steps(3) . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From Add a mailbox: a number and a name a step, the current one filled, a finished one with the green check; on a phone the other steps keep their numbers.')) . '</p>',
    );

    // (D2) The providers' own buttons, their brand values in both themes.
    $google = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'
        . '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
        . '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>'
        . '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
        . '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
        . '</svg>';
    $microsoft = '<svg width="21" height="21" viewBox="0 0 21 21" aria-hidden="true" focusable="false">'
        . '<rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/>'
        . '<rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/>'
        . '</svg>';

    $patterns[] = array(
        'id'      => 'pattern-signin',
        'title'   => lang('Provider sign-in'),
        'classes' => array('.pg-card-inner', '.pg-signin', '.pg-signin-google', '.pg-signin-microsoft', '.pg-group-label'),
        'html'    => '<div class="pg-look-stack">'
            . '<div class="pg-card-inner pg-signin"><button type="button" class="pg-signin-google">' . $google . h(lang('Sign in with Google')) . '</button>'
            . '<p>' . h(lang('Pinegrap reads and answers this mailbox. Your password is not written to the site.')) . '</p></div>'
            . '<div class="pg-group-label">' . h(lang('When Microsoft is chosen')) . '</div>'
            . '<div class="pg-card-inner pg-signin"><button type="button" class="pg-signin-microsoft">' . $microsoft . h(lang('Sign in with Microsoft')) . '</button></div>'
            . '</div><p class="pg-text-meta">' . h(lang('From Add a mailbox › Sign in: the provider\'s own button as its brand rules draw it, the same in both themes, and one line under it.')) . '</p>',
    );

    // (D3) The green line after a sign-in or a test.
    $patterns[] = array(
        'id'      => 'pattern-result',
        'title'   => lang('Result line'),
        'classes' => array('.pg-result', '.pg-result-title', '.pg-result-line', '.pg-icon-tile', '.pg-tone-teal', 'role="status"'),
        'html'    => '<div class="pg-result" role="status"><span class="pg-icon-tile pg-tone-teal" aria-hidden="true"><i class="bi bi-check-lg"></i></span>'
            . '<div><span class="pg-result-title">' . h(lang(array('string' => '{var:1} connected', 'vars' => array('info@example.com')))) . '</span>'
            . '<span class="pg-result-line">' . h(lang('Connection tested: the inbox is read.')) . '</span></div></div>'
            . '<p class="pg-text-meta">' . h(lang('From Add a mailbox › Details: what the sign-in or the test found, two lines at most.')) . '</p>',
    );

    // (E) The removal's dialog: every effect the removal has, read when it
    // opens; Cancel quiet, the danger button last.
    $effect = function ($kind, $icon, $text, $line) {
        return '<li class="pg-effect pg-effect-' . $kind . '"><i class="bi ' . $icon . '" aria-hidden="true"></i>'
            . '<div>' . h($text) . (($line !== '') ? '<span class="pg-effect-line">' . h($line) . '</span>' : '') . '</div></li>';
    };

    $patterns[] = array(
        'id'      => 'pattern-confirm',
        'title'   => lang('Confirm with its effects'),
        'classes' => array('role="dialog"', '.pg-card-sheet', '.pg-icon-tile-lg', '.pg-tone-rose', '.pg-card-sub', '.pg-card-inner', '.pg-effects', '.pg-effect', '.pg-effect-keep', '.pg-effect-go', '.pg-effect-info', '.pg-effect-line', '.pg-button-quiet', '.pg-button-danger'),
        'html'    => '<div class="pg-card pg-card-sheet overflow-hidden" role="dialog" aria-labelledby="' . $p . '_look_confirm_title" style="max-width:470px">'
            . '<div class="d-flex align-items-start gap-3 p-5 pb-0"><span class="pg-icon-tile pg-icon-tile-lg pg-tone-rose" aria-hidden="true"><i class="bi bi-trash3"></i></span>'
            . '<div class="min-w-0"><div class="pg-title-section" id="' . $p . '_look_confirm_title">' . h(lang(array('string' => 'Remove the {var:1} mailbox?', 'vars' => array(lang('Support'))))) . '</div>'
            . '<p class="pg-card-sub">info@example.com</p></div></div>'
            . '<div class="p-5"><ul class="pg-card-inner pg-effects">'
            . $effect('keep', 'bi-check-circle-fill', lang('The conversations stay'), lang(array('string' => 'The {var:1} conversations of the #{var:2} channel are not deleted.', 'vars' => array(214, 'support'))))
            . $effect('go', 'bi-x-circle-fill', lang('Reading stops'), lang('New e-mail no longer reaches the site.'))
            . $effect('go', 'bi-x-circle-fill', lang(array('string' => 'Its {var:1} rules are deleted', 'vars' => array(3))), '')
            . $effect('info', 'bi-info-circle-fill', lang('The Google account stays connected'), lang('System e-mail leaves through this account.'))
            . '</ul></div>'
            . '<div class="border-top p-5 d-flex justify-content-end gap-3"><button type="button" class="pg-button pg-button-quiet">' . h(lang('Cancel')) . '</button>'
            . '<button type="button" class="pg-button pg-button-danger">' . h(lang('Remove mailbox')) . '</button></div>'
            . '</div><p class="pg-text-meta">' . h(lang('From Mailboxes › Remove mailbox: what stays green, what goes rose, what stays connected blue; the counts are read when the dialog opens.')) . '</p>',
    );

    // The same confirm for a change that History can take back: the tile
    // blue and the button filled, not the danger button.
    $patterns[] = array(
        'id'      => 'pattern-confirm-go',
        'title'   => lang('Confirm with its effects, undone by History'),
        'classes' => array('role="dialog"', '.pg-card-sheet', '.pg-icon-tile-lg', '.pg-tone-blue', '.pg-card-sub', '.pg-card-inner', '.pg-effects', '.pg-effect-keep', '.pg-effect-info', '.pg-effect-go', '.pg-effect-line', '.pg-button-quiet', '.pg-button-fill'),
        'html'    => '<div class="pg-card pg-card-sheet overflow-hidden" role="dialog" aria-labelledby="' . $p . '_look_confirm_go_title" style="max-width:470px">'
            . '<div class="d-flex align-items-start gap-3 p-5 pb-0"><span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>'
            . '<div class="min-w-0"><div class="pg-title-section" id="' . $p . '_look_confirm_go_title">' . h(lang(array('string' => 'Build “{var:1}” again?', 'vars' => array('Page 7')))) . '</div>'
            . '<p class="pg-card-sub">' . h(lang('The sketch changed since the page was built.')) . '</p></div></div>'
            . '<div class="p-5"><ul class="pg-card-inner pg-effects">'
            . $effect('keep', 'bi-check-circle-fill', lang(array('string' => '{var:1} sections stay as they are', 'vars' => array(5))), 'Header, Intro, Cards, Blog list, Footer')
            . $effect('info', 'bi-info-circle-fill', lang(array('string' => '{var:1} is built again', 'vars' => array(lang('Features')))), lang(array('string' => 'It has {var:1} columns now and was not edited.', 'vars' => array(4))))
            . $effect('go', 'bi-x-circle-fill', lang(array('string' => '{var:1} is removed', 'vars' => array(lang('Text')))), lang('Its box was deleted in the sketch; what was written in it goes with it.'))
            . '</ul></div>'
            . '<div class="border-top p-5 d-flex align-items-center justify-content-end gap-3"><span class="pg-text-meta me-auto">' . h(lang('History keeps the page as it is now.')) . '</span>'
            . '<button type="button" class="pg-button pg-button-quiet">' . h(lang('Cancel')) . '</button>'
            . '<button type="button" class="pg-button pg-button-fill">' . h(lang('Build again')) . '</button></div>'
            . '</div><p class="pg-text-meta">' . h(lang('From the Sketch tool › Build again: History takes the change back, so the tile is blue and the button filled; rose and the danger button stay for what cannot be undone.')) . '</p>',
    );

    // The Features settings board (plans/features-switchboard-design.md):
    // a module a row, the switch at its end, its name the switch's label and
    // its line the switch's description. Settings opens the module's folded
    // row and shows whether the module is on or off; a module with its own
    // category has a state pill and Open instead of a switch.
    $switch_row = function ($key, $tone, $icon, $name, $line, $end, $state = '') use ($p) {
        $id = $p . '_look_switch_' . $key;

        if ($end === 'open') {
            $control = '<span class="pg-pill pg-tone-' . (($state === 'on') ? 'teal' : 'gray') . '">' . h(($state === 'on') ? lang('On') : lang('Off')) . '</span>'
                . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" aria-label="' . h(lang('Open it') . ': ' . $name) . '">' . h(lang('Open it')) . '<i class="bi bi-arrow-right" aria-hidden="true"></i></button>';
        } else {
            $control = (($end === 'settings') ? '<button type="button" class="pg-button pg-button-quiet pg-button-sm" aria-label="' . h(lang('Settings') . ': ' . $name) . '">' . h(lang('Settings')) . '</button>' : '')
                . '<input type="checkbox" class="pg-switch" id="' . $id . '" aria-describedby="' . $id . '_line"' . (($state !== 'off') ? ' checked' : '') . (($state === 'locked') ? ' disabled' : '') . '>';
        }

        $name_html = ($end === 'open')
            ? '<span class="pg-set-fold-name">' . h($name) . '</span>'
            : '<label class="pg-set-fold-name" for="' . $id . '">' . h($name) . '</label>';

        return '<div class="pg-set-switch-row' . ((($end === 'open') && ($state !== 'on')) ? ' is-off' : '') . (($state === 'locked') ? ' is-locked' : '') . '">'
            . '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . $tone . '" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>'
            . '<span class="pg-set-fold-text">' . $name_html . '<span class="pg-set-fold-line" id="' . $id . '_line">' . h($line) . '</span></span>'
            . $control . '</div>';
    };

    $patterns[] = array(
        'id'      => 'pattern-switch',
        'title'   => lang('Switch rows'),
        'classes' => array('.pg-group-label', '.pg-group-label-count', '.pg-set-fold', '.pg-set-switch-row', '.is-off', '.is-locked', '.pg-set-fold-text', '.pg-set-fold-name', '.pg-set-fold-line', '.pg-icon-tile-lg', '.pg-switch', '.pg-pill', '.pg-button-quiet', '.pg-button-sm'),
        'html'    => '<div><div class="pg-group-label mb-2">' . h(lang('Modules')) . '<span class="pg-group-label-count">' . h(lang(array('string' => '{var:1} / {var:2} on', 'vars' => array(9, 12)))) . '</span></div>'
            . '<div class="pg-set-fold">'
            . $switch_row('forms', 'blue', 'bi-ui-checks', lang('Forms'), lang('Adds forms to pages; the submissions gather in the panel.'), 'switch')
            . $switch_row('calendars', 'teal', 'bi-calendar3', lang('Calendars'), lang('Event calendars and booking pages.'), 'switch')
            . $switch_row('courses', 'amber', 'bi-mortarboard', lang('Courses'), lang('Lessons, exams and certificates.'), 'settings')
            . $switch_row('ads', 'blue', 'bi-megaphone', lang('Ads'), lang('Off: ad areas do not show on pages or in the menu.'), 'switch', 'off')
            . $switch_row('share', 'violet', 'bi-share', lang('Social sharing'), lang('Adds share buttons to pages.'), 'settings')
            . $switch_row('commerce', 'teal', 'bi-bag', lang('Commerce'), lang('Products, cart and orders. Opens in its own category.'), 'open', 'on')
            . $switch_row('accounting', 'amber', 'bi-receipt', lang('Accounting'), lang('Invoices, accounts and e-documents. Opens in its own category.'), 'open', 'off')
            . $switch_row('approval', 'gray', 'bi-check2-square', lang('Content approval'), lang('Only an administrator can change this.'), 'switch', 'locked')
            . '</div></div>'
            . '<p class="pg-text-meta">' . h(lang('From Site Settings › Features: a module a row with its switch at the end; Settings opens its folded row, on or off; a module with its own category shows its state and Open.')) . '</p>',
    );

    return $patterns;
}


/**
 * The Workspace's conversation timeline as a still pattern: the well of a
 * channel's tabs row and the card of one message.
 *
 * @return array{id: string, title: string, classes: string[], html: string}
 */
function pg_look_examples_timeline_pattern()
{
    // The Workspace's conversation timeline, still: three days in the well
    // (a hairline and a date each, a run a bar of its writers' turns, the
    // decision a diamond, the part on the screen an accent line) and the
    // card of one message under it.
    $turns = array(
        array(6, 12, 0, 'first'), array(19, 7, 1, 'last'),
        array(43, 18, 0, 'first'), array(62, 7, 1, 'decision'), array(70, 11, 0, 'last'), array(101, 10, 3, 'both'),
        array(120, 6, 1, 'first'), array(127, 14, 0, 'last'),
        array(158, 12, 0, 'first'), array(171, 9, 2, ''), array(181, 16, 1, 'last'), array(211, 10, 0, 'first'), array(222, 8, 3, 'last'),
    );
    $strip = '';

    foreach ($turns as $turn) {
        $ends = ($turn[3] === 'both') ? ' is-first is-last' : (in_array($turn[3], array('first', 'last'), true) ? ' is-' . $turn[3] : '');
        $strip .= '<div class="ws-ctl-turn' . $ends . '" data-tone="' . $turn[2] . '"' . (($turn[3] === 'decision') ? ' data-mark="decision"' : '') . ' style="left:' . $turn[0] . 'px;width:' . $turn[1] . 'px"></div>';
    }

    $tool_row = function ($tone, $icon, $kind, $name, $result) {
        return '<button type="button" class="ws-ctl-row"><span class="pg-icon-tile pg-tone-' . $tone . '"><i class="bi ' . $icon . '" aria-hidden="true"></i></span>'
            . '<em class="ws-ctl-row-kind">' . h($kind) . '</em><span class="ws-ctl-row-name">' . h($name) . '</span>'
            . (($result !== '') ? '<small class="ws-ctl-row-result pg-tabular">' . h($result) . '</small>' : '')
            . '<i class="bi bi-chevron-right ws-ctl-row-go" aria-hidden="true"></i></button>';
    };

    return array(
        'id'      => 'pattern-timeline',
        'title'   => lang('Conversation timeline'),
        'classes' => array('.ws-ctl', '.ws-ctl-bar', '.ws-ctl-port', '.ws-ctl-track', '.ws-ctl-day', '.ws-ctl-date', '.is-now', '.ws-ctl-turn', '.is-first', '.is-last', 'data-mark="decision"', '.ws-ctl-here', '.ws-ctl-zoom', '.pg-icon-button-sm', '.ws-ctl-card', '.ws-ctl-card-head', '.pg-initials', '.ws-ctl-card-name', '.ws-ctl-card-time', '.ws-ctl-card-text', '.ws-ctl-card-mark', '.ws-ctl-rows', '.ws-ctl-row', '.ws-ctl-row-kind', '.ws-ctl-row-name', '.ws-ctl-row-result', '.ws-ctl-row-go', '.ws-ctl-card-foot', '.ws-ctl-card-wait', '.ws-ctl-card-go'),
        'html'    => '<div class="pg-look-stack">'
            . '<div class="ws-ctl d-flex"><div class="ws-ctl-bar"><div class="ws-ctl-port">'
            . '<div class="ws-ctl-track" role="slider" tabindex="0" aria-label="' . h(lang('Conversation timeline')) . '" aria-orientation="horizontal" aria-valuemin="1" aria-valuemax="13" aria-valuenow="13" style="width:238px">'
            . '<span class="ws-ctl-date" style="left:6px">19</span>'
            . '<div class="ws-ctl-day" style="left:34px"></div><span class="ws-ctl-date" style="left:43px">' . h(lang('Yesterday')) . '</span>'
            . '<div class="ws-ctl-day" style="left:149px"></div><span class="ws-ctl-date is-now" style="left:158px">' . h(lang('Today')) . '</span>'
            . $strip
            . '<div class="ws-ctl-here" style="left:158px;width:72px"></div>'
            . '</div></div><div class="ws-ctl-zoom">'
            . '<button type="button" class="pg-icon-button pg-icon-button-sm" title="' . h(lang('Zoom out (-, or Ctrl and the wheel)')) . '" aria-label="' . h(lang('Zoom out (-, or Ctrl and the wheel)')) . '"><i class="bi bi-zoom-out" aria-hidden="true"></i></button>'
            . '<button type="button" class="pg-icon-button pg-icon-button-sm" title="' . h(lang('Zoom in (+, or Ctrl and the wheel)')) . '" aria-label="' . h(lang('Zoom in (+, or Ctrl and the wheel)')) . '" disabled><i class="bi bi-zoom-in" aria-hidden="true"></i></button>'
            . '</div></div></div>'
            . '<div class="ws-ctl-card" data-tone="1">'
            . '<div class="ws-ctl-card-head"><span class="pg-initials">AD</span><span class="ws-ctl-card-name">Ayşe Demir</span><span class="ws-ctl-card-time pg-tabular">' . h(lang(array('string' => 'Yesterday {var:1}', 'vars' => array('16:40')))) . '</span></div>'
            . '<p class="ws-ctl-card-text">' . h(lang('The new prices start on Monday; the list and the task are below.')) . '</p>'
            . '<div class="ws-ctl-card-mark"><span class="pg-icon-tile pg-tone-amber"><i class="bi bi-bookmark-check" aria-hidden="true"></i></span><span>' . h(lang('Decision') . ' · ' . lang(array('string' => 'marked by {var:1}', 'vars' => array('Deniz Kaya')))) . '</span></div>'
            . '<div class="ws-ctl-rows">'
            . $tool_row('gray', 'bi-file-earmark', lang('File'), 'price-list.xlsx', '')
            . $tool_row('blue', 'bi-check2-square', lang('Task'), lang('Update the shop\'s prices'), '')
            . $tool_row('blue', 'bi-bar-chart', lang('Poll'), lang('Which day do we tell the customers?'), '4 / 6')
            . '</div>'
            . '<div class="ws-ctl-card-foot"><span class="ws-ctl-card-wait">' . h(lang(array('string' => '{var:1} after the message before', 'vars' => array(lang(array('string' => '{var:1} h {var:2} min', 'vars' => array(2, 14))))))) . '</span>'
            . '<button type="button" class="ws-ctl-card-go">' . h(lang('Open the message')) . '</button></div>'
            . '</div></div>'
            . '<p class="pg-text-meta">' . h(lang('From the Workspace: at the end of a channel\'s tabs, a day a column, a run of messages one bar, a writer a colour; pointing at a bar shows the card of its message, whose rows open what it carries.')) . '</p>',
    );
}

/**
 * The rows of the Workspace list in the main menu (workspace_side.js, board
 * L3 of plans/workspace-interface), live: pointing at a pinnable row shows
 * its pin and menu in the place of its marks.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array{id: string, title: string, classes: string[], html: string}
 */
function pg_look_examples_ws_list_row_pattern($p)
{
    $tile = function ($icon) {
        return '<span class="pg-icon-tile ws-side-ch-tile ws-ch-icon" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>';
    };
    $row = function ($name, $hex, $icon, $classes, $marks, $tools) use ($tile) {
        return '<a href="#" class="ws-channel-link' . $classes . ($tools ? ' ws-side-has-tools' : '') . '"'
            . (($hex !== '') ? ' style="--ws-ch:' . $hex . '" data-ws-colored="1"' : '') . '>'
            . $tile($icon) . '<span class="ws-channel-name">' . h($name) . '</span>'
            . (($marks !== '') ? '<span class="ws-side-marks">' . $marks . '</span>' : '')
            . ($tools ? '<span class="ws-side-row-tools"><span class="ws-pin no-popover" aria-hidden="true" title="' . h(lang('Pin')) . '"><i class="bi bi-pin-angle"></i></span>'
                . '<span class="no-popover" aria-hidden="true" title="' . h(lang('More')) . '"><i class="bi bi-three-dots"></i></span></span>' : '')
            . '</a>';
    };
    $pill = function ($count, $mention) {
        return '<span class="ws-count' . ($mention ? ' ws-count-mention' : '') . '">' . (int) $count . '</span>';
    };

    return array(
        'id'      => 'pattern-ws-list-row',
        'title'   => lang('Workspace list row'),
        'classes' => array('.ws-menu-side', '.ws-side-title', '.ws-side-pinned', '.ws-channel-link', '.pg-icon-tile', '.ws-side-ch-tile', '.ws-channel-name', '.ws-side-marks', '.ws-count', '.ws-count-mention', '.ws-unread', '.ws-side-muted', '.ws-side-muted-bell', '.ws-side-dot', '.ws-side-draft', '.ws-side-has-tools', '.ws-side-row-tools', '.ws-pin', '.ws-side-join', '.ws-notjoined', '.ws-side-join-now'),
        'html'    => '<div id="ws-look-list-' . $p . '" class="ws-menu-side pg-look-ws-list">'
            . '<div class="ws-side-title">' . h(lang('Channels')) . '</div>'
            . '<div class="ws-side-pinned">'
            . $row('support', '#c9774a', 'bi-hash', ' active', '', true)
            . $row('general', '#b85c73', 'bi-hash', ' ws-unread', $pill(3, false), true)
            . '</div>'
            . $row('website-redesign', '#6b5ca5', 'bi-hash', ' ws-unread', $pill(2, true), true)
            . $row('announcements', '', 'bi-hash', ' ws-side-muted', '<i class="bi bi-bell-slash ws-side-muted-bell" aria-hidden="true"></i><span class="ws-side-dot"></span><span class="visually-hidden">' . h(lang('Unread messages')) . '</span>', true)
            . $row('accounting', '#b5534b', 'bi-hash', '', '<i class="bi bi-pencil ws-side-draft" aria-hidden="true"></i><span class="visually-hidden">' . h(lang('Unsent draft')) . '</span>', true)
            . $row('management', '#6b5ca5', 'bi-lock', '', '', true)
            . '<button type="button" class="ws-side-join" aria-expanded="true"><i class="bi bi-plus-circle-dotted ws-side-join-icon" aria-hidden="true"></i><span class="ws-channel-name">' . h(lang(array('string' => '{var:1} channels you could join', 'vars' => 2))) . '</span><i class="bi bi-chevron-down ws-side-join-chevron" aria-hidden="true"></i></button>'
            . $row('ideas', '', 'bi-hash', ' ws-notjoined', '<span class="ws-side-join-now no-popover" aria-hidden="true">' . h(lang('Join')) . '</span>', false)
            . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From the Workspace list in the main menu: a 30 px row, the channel\'s tile in its colour, the marks at its end; pointing at a row shows its pin and menu in their place.')) . '</p>',
    );
}

/**
 * The record head and the copy button (View Order's head): the record's
 * name with its copy button and state pills, the grey line, the screen's
 * toolbar at the right; and the copy button alone beside a value.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array list of array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_record_patterns($p)
{
    $toolbar = '<nav id="pg-look-record-bar-' . $p . '" class="pg-toolbar" aria-label="' . h(lang('Button Bar')) . '">'
        . '<button type="button" class="pg-button pg-button-ghost"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>' . h(lang('Refund')) . '</button>'
        . '<button type="button" class="pg-button pg-button-ghost"><i class="bi bi-printer" aria-hidden="true"></i>' . h(lang('Print')) . '</button>'
        . '<button type="button" class="pg-icon-button" aria-label="' . h(lang('More')) . '" title="' . h(lang('More')) . '"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>'
        . '</nav>';

    return array(
        array(
            'id'      => 'pattern-record-head',
            'title'   => lang('Record head'),
            'classes' => array('.pg-record-head', '.pg-record-head-main', '.pg-record-head-line', '.pg-record-head-title', '.pg-copy', '.pg-record-head-pills', '.pg-pill', '.pg-record-head-meta', '.pg-record-head-actions'),
            'html'    => pg_record_head(array(
                    'id' => 'pg-look-record-title-' . $p,
                    'title' => lang(array('string' => 'Order #{var:1}', 'vars' => array('1171'))),
                    'copy' => '1171',
                    'copy_label' => lang('Copy the order number'),
                    'pills' => array(array(lang('Complete'), 'teal'), array(lang('Paid'), 'teal'), array(lang('Not shipped'), 'gray')),
                    'meta' => array(h(get_absolute_time(array('timestamp' => 1587048060, 'format' => 'plain_text'))), h(lang('Online')), '<a class="pg-link-secondary" href="#">Ada Yılmaz</a>', h(pg_order_view_counts_text(2, 3))),
                    'actions' => $toolbar,
                ))
                . '<p class="pg-text-meta">' . h(lang('From View Order: under the breadcrumb, the record\'s name with its copy button and pills (done teal, on its way blue, waiting amber, cancelled rose, not begun grey), one grey line, the toolbar at the right. The top bar keeps the screen\'s title.')) . '</p>',
        ),
        array(
            'id'      => 'pattern-copy-button',
            'title'   => lang('Copy button'),
            'classes' => array('.pg-copy', '.pg-icon-button', '.pg-icon-button-sm'),
            'html'    => '<p class="d-flex align-items-center gap-2 mb-2"><span id="pg-look-copy-text-' . $p . '" class="pg-tabular">7340012288</span>'
                . '<span class="pg-copy">' . pg_copy_button('7340012288', lang('Copy the tracking number'), 'pg-look-copy-text-' . $p) . '</span></p>'
                . '<p class="pg-text-meta">' . h(lang('An icon button that copies the text as shown, named for a screen reader. For a moment its icon turns to a check and its tooltip reads Copied, and a polite status beside it says so; when the browser refuses, the text is selected.')) . '</p>',
        ),
    );
}



/**
 * The document head (assets/js/pg_doc_head.js) as the six windows of the
 * drawing fill it: a note, a Data table, the Artifact Canvas, a Word file,
 * an Excel file in a channel's Preview and Quick Look. Each host holds its
 * options as JSON and the script builds the head into it; the screen prints
 * pg_doc_head_includes(). Without callbacks the heads only show, rename in
 * place and open their menus.
 *
 * @return array the pattern's id, title, classes, html and phone flag
 */
function pg_look_examples_doc_head_pattern()
{
    $icon = function ($name, $label) {
        return '<button type="button" class="pg-icon-button" title="' . h($label) . '" aria-label="' . h($label) . '"><i class="bi ' . $name . '" aria-hidden="true"></i></button>';
    };
    $ghost = function ($name, $label, $count = '') {
        return '<button type="button" class="pg-button pg-button-ghost pg-button-sm"><i class="bi ' . $name . '" aria-hidden="true"></i>' . h($label) . $count . '</button>';
    };
    $seg = function ($first, $second) {
        return '<span class="pg-seg" role="group"><button type="button" class="pg-seg-item is-active" aria-pressed="true">' . h($first) . '</button>'
            . '<button type="button" class="pg-seg-item" aria-pressed="false">' . h($second) . '</button></span>';
    };
    $now = time() * 1000;
    $heads = array(
        array(
            'kind' => array('icon' => 'bi-journal-text', 'tone' => 'amber', 'label' => lang('Note')),
            'name' => lang('Launch checklist'), 'renamable' => true,
            'state' => 'saved', 'stateInfo' => array('time' => $now - 120000, 'ago' => true),
            'undo' => array('canUndo' => true, 'canRedo' => false),
            'faces' => array(array('initials' => 'TK', 'name' => 'Tuna Kaya'), array('initials' => 'EG', 'name' => 'Ece Gür')),
            'share' => true,
            'menu' => array('copyLink' => true, 'send' => true, 'print' => true, 'versions' => true, 'delete' => true),
        ),
        array(
            'back' => true,
            'kind' => array('icon' => 'bi-table', 'tone' => 'teal', 'label' => lang('Table')),
            'name' => lang('Leads'), 'renamable' => true,
            'meta' => lang(array('string' => '{var:1} rows', 'vars' => array(124))),
            'state' => 'saved',
            'slotHtml' => $seg(lang('Table'), lang('Board')) . $ghost('bi-funnel', lang('Filter')),
            'share' => true,
            'main' => array('label' => lang('New row'), 'icon' => 'bi-plus-lg'),
            'menu' => array('download' => true, 'own' => array(array('icon' => 'bi-layout-three-columns', 'label' => lang('Columns')), array('icon' => 'bi-box-arrow-in-down', 'label' => lang('Import'))), 'delete' => true),
        ),
        array(
            'kind' => array('icon' => 'bi-easel2', 'tone' => 'rose', 'label' => lang('Artifact Canvas')),
            'name' => lang('Campaign poster'), 'renamable' => true,
            'meta' => '1080 × 1350 px',
            'state' => 'unsaved',
            'undo' => array('canUndo' => true, 'canRedo' => true),
            'slotHtml' => $ghost('bi-clock-history', lang('Versions'), '<span class="pg-count">3</span>') . $icon('bi-box-arrow-in-down', lang('Import')) . $icon('bi-database', lang('Data')) . $icon('bi-translate', lang('Languages')) . $ghost('bi-play-fill', lang('Present')),
            'main' => array('label' => lang('Save')),
            'menu' => array('help' => true),
            'grow' => true,
            'close' => true,
        ),
        array(
            'kind' => array('icon' => 'bi-file-earmark-word', 'tone' => 'blue', 'label' => lang('Word file')),
            'name' => 'Proposal-2026.docx', 'renamable' => true,
            'meta' => '48 KB',
            'state' => 'unsaved',
            'undo' => array('canUndo' => true, 'canRedo' => false),
            'main' => array('label' => lang('Save'), 'gate' => true, 'dirty' => true),
            'menu' => array('open' => true, 'download' => true),
            'close' => true,
        ),
        array(
            'kind' => array('icon' => 'bi-file-earmark-excel', 'tone' => 'teal', 'label' => lang('Excel file')),
            'name' => 'Budget-2027.xlsx',
            'meta' => lang(array('string' => '{var:1} sheets', 'vars' => array(3))),
            'state' => 'saved',
            'undo' => array('canUndo' => false, 'canRedo' => false),
            'slotHtml' => $ghost('bi-table', lang('Save as data')),
            'download' => true,
            'main' => array('label' => lang('Save'), 'gate' => true),
            'menu' => array('open' => true, 'copyLink' => true),
            'close' => true,
        ),
        array(
            'kind' => array('icon' => 'bi-file-earmark-slides', 'tone' => 'amber', 'label' => lang('Presentation')),
            'name' => 'Investor deck.pptx',
            'meta' => lang(array('string' => '{var:1} slides', 'vars' => array(24))) . ' · 3.1 MB',
            'slotHtml' => $seg(lang('Slides'), lang('Text')) . $ghost('bi-play-fill', lang('Present')),
            'download' => true,
            'main' => array('label' => lang('Edit in Artifact Canvas'), 'icon' => 'bi-easel2', 'style' => 'quiet'),
            'menu' => array('open' => true, 'download' => true, 'copyLink' => true),
            'grow' => array('icon' => 'bi-arrows-angle-expand', 'label' => lang('Expand')),
            'close' => true,
        ),
    );
    $html = '';

    foreach ($heads as $options) {
        $html .= '<div class="pg-card pg-doc-head-host" data-pg-doc-head="' . h(json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"></div>';
    }

    return array(
        'id'      => 'pattern-doc-head',
        'title'   => lang('Document head'),
        'classes' => array('.pg-doc-head-host', 'data-pg-doc-head', 'pgDocHead.build()'),
        'phone'   => true,
        'html'    => $html
            . '<p class="pg-text-meta">' . h(lang('From pgDocHead.build(): one head for a note, a Data table, the Artifact Canvas, a file window and the view windows. Left the kind\'s tile, the name (click to rename), the meta and the save state; then Undo, Redo and the kind\'s own controls; at the right faces, Share, Download, the main button, the more menu, grow and Close.')) . '</p>'
            . '<p class="pg-text-meta">' . h(lang('The more menu keeps one order for every kind, a missing row not drawn: Open in a new tab, Full screen; Copy link, Send to a channel; Download, Export…, Print; Versions; the kind\'s own rows; Help, then Delete. A menu of one row stands as its icon. On a phone only Back, the name, the state\'s dot, the main button and the menu stay.')) . '</p>',
    );
}

/**
 * Edit Product's Performance card as the screen draws it
 * (pg_product_insights_card_html()), with a fixed year of sales: the figure
 * grid, the chart, "Carts and interest" and the foot.
 *
 * @return array id, title, classes, html
 */
function pg_look_examples_figure_grid_pattern()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_insights.php');

    $now = mktime(12, 0, 0, 11, 22, 2026);
    $chart = array();

    foreach (array(5, 7, 6, 9, 8, 10, 12, 10, 11, 14, 13, 17) as $index => $units) {
        $chart[] = array('start' => mktime(0, 0, 0, 12 + $index, 1, 2025), 'units' => $units);
    }

    $data = array(
        'unit' => 'month',
        'keep' => 600,
        'no_sales' => false,
        'figures' => array(
            'units' => 128, 'revenue' => 3454024, 'orders' => 97, 'views' => 4210, 'conversion' => 2.3,
            'returns' => 4, 'returns_share' => 3.1, 'margin' => 38.0, 'margin_state' => 'final', 'last_sale' => $now - (3 * 86400),
            'change' => array('units' => 12.0, 'revenue' => 8.0, 'orders' => -3.0, 'views' => 21.0),
        ),
        'chart' => $chart,
        'carts' => array('left_carts' => 23, 'left_units' => 31, 'open_carts' => 5, 'open_units' => 6, 'saved_carts' => 7),
        'interest' => array('favourites' => 41, 'waiting' => 12, 'rating_avg' => 4.6, 'rating_count' => 18),
    );

    return array(
        'id'      => 'pattern-figure-grid',
        'title'   => lang('Figure grid'),
        'classes' => array('.pg-figure-grid', '.pg-figure-grid-cell', '.pg-figure-label', '.pg-figure-value', '.pg-figure-note', '.pg-figure-grid-chart', '.pg-figure-grid-bars', '.pg-figure-grid-axis', '.pg-figure-grid-sub', '.pg-figure-grid-rows', '.pg-figure-grid-row', '.pg-figure-grid-row-link', '.pg-figure-grid-foot'),
        'html'    => '<div class="card">' . pg_product_insights_card_html($data, '20G', '&#8378;', $now) . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From Edit Product\'s Performance card: the figures as cells of the card, four across and two on a phone, a change line under the value (up teal, down rose), then a bar chart, rows of icon, text and count, and a foot.')) . '</p>',
    );
}

/**
 * The address block (View Order's Billing Information): the card's title
 * with the copy button, the address's lines with the name set apart, the
 * grey "Same as the shipping address" line, the rows under a hairline.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array id, title, classes, html
 */
function pg_look_examples_address_pattern($p)
{
    $address = array(
        'first_name' => 'Ada', 'last_name' => 'Yılmaz', 'company' => 'Test Ltd',
        'address_1' => 'Hürriyet Mah. Pamuk Sokak No:22', 'address_2' => '',
        'city' => 'Tire', 'state' => 'İzmir', 'zip_code' => '35960', 'country' => 'TR',
    );
    $id = 'pg-look-address-' . $p;

    return array(
        'id'      => 'pattern-address-block',
        'title'   => lang('Address block'),
        'classes' => array('.pg-address-block', '.pg-address', '.pg-address-name', '.pg-address-same', '.pg-kv', '.pg-copy'),
        'html'    => '<div class="card pg-card" style="max-width:22rem">'
            . '<div class="card-header bg-reset border-0 d-flex align-items-center gap-3"><h3 class="pg-title-section mb-0">' . h(lang('Billing Information')) . '</h3>'
            . '<span class="pg-copy ms-auto">' . pg_copy_button(implode("\n", pg_order_view_address_lines($address)), lang('Copy the billing address'), $id) . '</span></div>'
            . '<div class="card-body"><div class="pg-address-block">' . pg_order_view_address_block($address, $id)
            . '<p class="pg-address-same"><i class="bi bi-check2" aria-hidden="true"></i>' . h(lang('Same as the shipping address')) . '</p></div>'
            . '<dl class="pg-kv"><div><dt>' . h(lang('VKN / TCKN')) . '</dt><dd>1234567890</dd></div><div><dt>' . h(lang('Tax Office')) . '</dt><dd>Tire</dd></div></dl></div>'
            . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From View Order: a postal address as its lines, an empty part left out, the name set apart; the copy button copies the lines. A grey line with a check under it; the rows that hold a value under a hairline.')) . '</p>',
    );
}

/**
 * The line row (View Order's shipment card): the card's title with the
 * address's pill, the heading row, a line that ships with its shipped
 * quantity over the ordered one, a line with a gift card box under it.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array id, title, classes, html
 */
function pg_look_examples_line_row_pattern($p)
{
    $field = 'pg-look-line-shipped-' . $p;
    $stepper = '<div class="input-group number-controls">'
        . '<button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>'
        . '<input type="text" class="form-control text-center border-start-0 border-end-0" id="' . $field . '" value="1" inputmode="numeric" aria-label="' . h(lang('Shipped Qty')) . '">'
        . '<button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button></div>';
    $rows = pg_order_view_line(array(
            'name_html' => '<a href="#" class="pg-link-secondary">' . h(lang('Office chair')) . '</a>',
            'meta' => array('20M', h(lang('Taken from stock')) . ': 1'),
            'amount_html' => '2 × ' . pg_format_money(299.95, '$'),
            'shipped_html' => pg_order_view_line_shipped($stepper, 2),
            'total_html' => pg_format_money(599.90, '$'),
        ))
        . pg_order_view_line(array(
            'name_html' => h(lang('Gift Card')),
            'meta' => array('GC-250'),
            'amount_html' => '1 × ' . pg_format_money(250, '$'),
            'shipped_html' => pg_order_view_line_shipped('<span class="pg-tabular">0</span>', 1),
            'total_html' => pg_format_money(250, '$'),
            'parts_html' => pg_order_view_line_part(h(lang('Gift Card')), '<div class="order-line-facts">' . pg_order_view_line_fact(h(lang('Recipient Email')), 'ayse@example.com') . pg_order_view_line_fact(h(lang('Delivery Date')), h(lang('Immediate'))) . '</div>'),
        ));

    return array(
        'id'      => 'pattern-line-row',
        'title'   => lang('Line row'),
        'classes' => array('.pg-shipment-head', '.pg-line-list', '.pg-line-list-ship', '.pg-line-head', '.pg-line-row', '.pg-line-image', '.pg-line-title', '.pg-line-meta', '.pg-line-amount', '.pg-line-shipped', '.pg-line-of', '.pg-line-total', '.pg-line-parts'),
        'html'    => '<div class="card pg-card pg-shipment">'
            . '<div class="card-header bg-reset border-0 pg-shipment-head"><h3 class="pg-title-section mb-0">' . h(lang('Shipment')) . '</h3>'
            . pg_pill(lang('Partially shipped'), 'amber') . '</div>'
            . '<div class="card-body">' . pg_order_view_line_list($rows, true) . '</div>'
            . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From View Order: one row per line, a picture, the name over a grey line, quantity × price, the shipped quantity over the ordered one, the total; a heading row names the columns once. Gift cards and form answers are boxes under the row. On a phone a line is two rows.')) . '</p>',
    );
}

/**
 * A presentation's Slides tab (pg_office_view.js, renderSlides()): the tabs
 * Slides and Text, the quiet line on what is left out, the strip of
 * numbered thumbs with the chosen one's edge, a hidden slide dimmed, one
 * not drawn yet as a skeleton in its ratio, the slide on the look's ground,
 * the line "2 / 3" with Previous and Next, Speaker notes folded.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array id, title, classes, html
 */
function pg_look_examples_slides_pattern($p)
{
    $thumb = function ($n, $state) {
        $picture = '<span class="office-slides-picture' . (($state === 'skeleton') ? ' office-slides-skeleton' : ' office-slides-failed') . '" style="width:148px;height:83px"></span>';

        return '<li class="office-slides-thumb-item"><button type="button" class="office-slides-thumb' . (($state === 'hidden') ? ' office-slides-thumb-hidden' : '') . (($state === 'active') ? ' is-active' : '') . '"'
            . ' aria-current="' . (($state === 'active') ? 'true' : 'false') . '" tabindex="' . (($state === 'active') ? '0' : '-1') . '"'
            . ' aria-label="' . h(str_replace(array('{1}', '{2}'), array((string) $n, '3'), lang('Slide {1} of {2}'))) . '">'
            . '<span class="pg-text-meta office-slides-n">' . (int) $n . '</span>' . $picture
            . (($state === 'hidden') ? '<span class="pg-text-meta office-slides-hidden">' . h(lang('Hidden slide')) . '</span>' : '')
            . '</button></li>';
    };

    return array(
        'id'      => 'pattern-slides',
        'title'   => lang('Slides'),
        'classes' => array('.office-slides-view', '.pg-seg', '.pg-seg-item', 'role="tablist"', 'role="tab"', '.office-slides-left', '.office-slides-strip', '.office-slides-thumb', '.is-active', '.office-slides-thumb-hidden', '.office-slides-skeleton', '.office-slides-stage', '.office-slides-nav', '.pg-icon-button', '.office-slides-notes-toggle'),
        'html'    => '<div class="office-view"><div class="office-slides-view">'
            . '<div class="pg-seg office-slides-tabs" role="tablist" aria-label="' . h(lang('Slides')) . '">'
            . '<button type="button" class="pg-seg-item active" role="tab" aria-selected="true" id="' . $p . '_look_slides_tab">' . h(lang('Slides')) . '</button>'
            . '<button type="button" class="pg-seg-item" role="tab" aria-selected="false" tabindex="-1">' . h(lang('Text')) . '</button></div>'
            . '<div role="tabpanel" aria-labelledby="' . $p . '_look_slides_tab">'
            . '<p class="pg-text-meta office-slides-left">' . h(lang('Shown approximately.') . ' ' . str_replace('{1}', str_replace(array('{1}', '{2}'), array(lang('Charts'), '2'), lang('{1} ({2})')), lang('Not drawn: {1}.')) . ' ' . str_replace('{1}', '3', lang('{1} font(s) replaced.'))) . '</p>'
            . '<div class="office-slides-body"><ol class="office-slides-strip" aria-label="' . h(lang('Slides')) . '">'
            . $thumb(1, 'hidden') . $thumb(2, 'active') . $thumb(3, 'skeleton') . '</ol>'
            . '<div class="office-slides-main"><div class="office-slides-stage" tabindex="0" role="img" aria-label="' . h(str_replace(array('{1}', '{2}'), array('2', '3'), lang('Slide {1} of {2}'))) . '">'
            . '<span class="office-slides-picture office-slides-skeleton" style="width:320px;height:180px"></span></div>'
            . '<div class="office-slides-nav"><button type="button" class="pg-icon-button pg-icon-button-sm" aria-label="' . h(lang('Previous slide')) . '"><span class="bi bi-chevron-left" aria-hidden="true"></span></button>'
            . '<span class="pg-text-meta office-slides-position">2 / 3</span>'
            . '<button type="button" class="pg-icon-button pg-icon-button-sm" aria-label="' . h(lang('Next slide')) . '"><span class="bi bi-chevron-right" aria-hidden="true"></span></button></div>'
            . '<button type="button" class="pg-button pg-button-quiet pg-button-sm office-slides-notes-toggle" aria-expanded="false">' . h(lang('Speaker notes')) . '</button>'
            . '</div></div></div></div></div>'
            . '<p class="pg-text-meta">' . h(lang('From a presentation\'s preview: Slides opens first, Text is the list of the slides\' text. A strip of thumbs 148 px wide beside the chosen slide, which wears the selected edge; a hidden slide is dimmed, one not drawn yet is a skeleton in its ratio. Below 768 px the strip lies under the slide as one row.')) . '</p>',
    );
}

/**
 * The Workspace list's tools dock (boards L1, L2 of plans/workspace-interface):
 * the head line with "⋯", the cells four across with My Tasks' count, and
 * More open as a card over the list.
 *
 * @param string $p the pane's prefix, keeping the ids of the two panes apart
 * @return array id, title, classes, html
 */
function pg_look_examples_tools_dock_pattern($p)
{
    $cell = function ($key, $name, $label, $icon, $active, $count) {
        return '<a href="#" class="ws-side-cell' . ($active ? ' active' : '') . '" data-ws-nav="' . $key . '" title="' . h($label) . '"'
            . ($active ? ' aria-current="page"' : '') . '>'
            . '<span class="pg-icon-tile ws-side-cell-tile" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>'
            . '<span class="ws-side-cell-name">' . h($name) . '</span>'
            . (($count > 0) ? '<span class="ws-side-cell-count ws-side-cell-due">' . (int) $count . '</span>' : '')
            . '</a>';
    };
    $row = function ($key, $label, $icon) {
        return '<a href="#" class="ws-channel-link" data-ws-nav="' . $key . '"><i class="bi ' . $icon . '" aria-hidden="true"></i><span class="ws-channel-name">' . h($label) . '</span></a>';
    };

    return array(
        'id'      => 'pattern-tools-dock',
        'title'   => lang('Tools dock'),
        'classes' => array('.ws-menu-side', '.ws-side-dock', '.ws-side-dock-head', '.pg-icon-button', '.ws-side-more-card', '.ws-side-cells', '.ws-side-cell', '.ws-side-cell-tile', '.ws-side-cell-name', '.ws-side-cell-count', '.ws-side-cell-due'),
        'html'    => '<div class="ws-menu-side pg-look-ws-list pg-look-ws-dock">'
            . '<div class="ws-side-dock" role="group" aria-label="' . h(lang('Tools')) . '">'
            . '<div class="ws-side-dock-head"><span>' . h(lang('Tools')) . '</span>'
            . '<button type="button" class="pg-icon-button pg-icon-button-sm ws-side-dock-more" aria-expanded="true" aria-controls="ws-look-more-' . $p . '" title="' . h(lang('More')) . '" aria-label="' . h(lang('More')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button></div>'
            . '<div id="ws-look-more-' . $p . '" class="ws-side-more-card" role="group" aria-label="' . h(lang('More')) . '">'
            . $row('scheduled', lang('Scheduled actions'), 'bi-alarm')
            . $row('mcp', lang('My MCP connections'), 'bi-plug')
            . $row('calendar_feed', lang('Calendar subscription'), 'bi-calendar-plus')
            . $row('archived', lang('Archived channels'), 'bi-archive')
            . '</div>'
            . '<div class="ws-side-cells">'
            . $cell('tasks', lang('Tasks'), lang('My Tasks'), 'bi-check2-square', true, 3)
            . $cell('notes', lang('Notes'), lang('My Notes'), 'bi-journal-text', false, 0)
            . $cell('wtables', lang('Data'), lang('Data'), 'bi-table', false, 0)
            . $cell('flows', lang('Flows'), lang('Automation flows'), 'bi-diagram-3', false, 0)
            . $cell('calendar', lang('Calendar'), lang('Work Calendar'), 'bi-calendar3', false, 0)
            . $cell('my_calendar', lang('My calendar'), lang('My calendar'), 'bi-calendar-heart', false, 0)
            . $cell('board', lang('Board'), lang('Planning Board'), 'bi-calendar-week', false, 0)
            . $cell('timeline', lang('Decisions'), lang('Decision Timeline'), 'bi-clock-history', false, 0)
            . '</div></div></div>'
            . '<p class="pg-text-meta">' . h(lang('From the Workspace list in the main menu: the tools stay at the list\'s foot with their names, four across, never folded; "⋯" opens More as a card over the list.')) . '</p>',
    );
}

/**
 * The Flows canvas's box as a card (workspace_flow.js, cardFace()), still: a
 * chosen Condition whose two arrows carry their outcome on a pill, an End,
 * an e-mail box with a problem and a value box, at the canvas's own sizes.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_flow_box_pattern($p)
{
    $box = function ($classes, $x, $y, $icon, $kind, $title, $meta, $in, $out, $warn) {
        return '<div class="pgc-node wsf-card ' . $classes . '" role="group" aria-label="' . h($kind . ': ' . $title . '. ' . $meta) . '" title="' . h($kind . "\n" . $title . "\n" . $meta) . '" style="left:' . $x . 'px;top:' . $y . 'px">'
            . ($in ? '<span class="pgc-port pgc-port-in" aria-hidden="true"></span>' : '')
            . '<span class="pg-icon-tile wsf-node-tile" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>'
            . '<div class="wsf-node-text"><div class="wsf-node-head"><span class="wsf-node-kind">' . h($kind) . '</span>'
            . ($warn ? '<i class="bi bi-exclamation-triangle-fill wsf-node-warn" aria-hidden="true"></i>' : '') . '</div>'
            . '<div class="wsf-node-title">' . h($title) . '</div><div class="wsf-node-meta">' . h($meta) . '</div></div>'
            . ($out ? '<span class="pgc-port pgc-port-out" aria-hidden="true"></span>' : '')
            . '</div>';
    };
    // An arrow from the Condition's out port (212, 100) to a box's left
    // edge, its outcome on a pill at its middle, as edgePill() measures it.
    $arrow = function ($y, $outcome) {
        $width = (int) ceil(mb_strlen($outcome) * 6.2) + 14;
        $middle = (100 + $y) / 2;

        return '<g class="pgc-edge"><path class="pgc-edge-line" d="M212 100 C260 100 240 ' . $y . ' 288 ' . $y . '"/>'
            . '<rect class="pgc-edge-pill" x="' . (250 - $width / 2) . '" y="' . ($middle - 9) . '" width="' . $width . '" height="18" rx="9"/>'
            . '<text class="pgc-edge-label" x="250" y="' . ($middle + 4) . '" text-anchor="middle">' . h($outcome) . '</text></g>';
    };
    $marker = $p . '-flow-arrow';

    return array(
        'id'      => 'pattern-flow-box',
        'title'   => lang('Flow box'),
        'classes' => array('.pgc-stage', '.pg-look-flow', '.pgc-node', '.wsf-card', '.pg-icon-tile', '.wsf-node-tile', '.wsf-node-text', '.wsf-node-head', '.wsf-node-kind', '.wsf-node-title', '.wsf-node-meta', '.wsf-node-warn', '.wsf-bad', '.pgc-selected', '.wsf-group-logic', '.wsf-group-message', '.wsf-group-values', '.pgc-port-in', '.pgc-port-out', '.pgc-edge-line', '.pgc-edge-pill', '.pgc-edge-label'),
        'html'    => '<div class="pgc-stage wsf-embed pg-look-flow" style="--pgc-marker:url(#' . $marker . ')"><div class="pgc-layer" style="width:500px;height:266px">'
            . '<svg class="pgc-edges" width="500" height="266" aria-hidden="true"><defs><marker id="' . $marker . '" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="pgc-arrowhead" d="M0 0L10 5L0 10z"/></marker></defs>'
            . $arrow(52, lang('Yes')) . $arrow(172, lang('No')) . '</svg>'
            . '<div class="pgc-boxes">'
            . $box('wsf-group-logic pgc-selected', 16, 64, 'bi-signpost-split', lang('Condition'), lang('Placed an order?'), lang('An order in the last 3 days'), true, true, false)
            . $box('wsf-group-logic', 288, 16, 'bi-flag', lang('End'), lang('Placed an order'), lang('The run ends'), true, false, false)
            . $box('wsf-group-message wsf-bad', 288, 136, 'bi-envelope', lang('Send an e-mail'), lang('Discount code'), lang('To the record\'s contact'), true, true, true)
            . $box('wsf-group-values', 16, 178, 'bi-braces', lang('Constants'), lang('Shop links'), lang('Three values'), false, false, false)
            . '</div></div></div>'
            . '<p class="pg-text-meta">' . h(lang('From the Flows canvas: a box is a card with its kind on a tile in its group\'s colour, then the kind, the title and what it does, each cut to the width with the whole text in its tooltip. The out port is a dot on the right edge; the in port shows on pointing. Chosen: the ring. A problem keeps its red line, a value box its dashed border; an arrow of a box with several outcomes carries the outcome on a pill. The List view, the run view and the read-only card draw the same box.')) . '</p>',
    );
}

/**
 * The main menu's row icon (pg_menu_icon(), pg_menu_icon_tile()): a tile in
 * each category's colour with one of its rows' drawings. Pointing at an item
 * or focusing it plays the icon's move, as on a menu row; the Automation
 * flows item carries data-pg-flows-running, so its dot runs the connector.
 *
 * @return array array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_menu_icon_pattern()
{
    $rows = array(
        array('top', 'flows', lang('Automation flows')),
        array('content', 'calendars', lang('Calendars')),
        array('design', 'design', lang('Page Design Studio')),
        array('ecommerce', 'products', lang('Products')),
        array('accounting', 'erp', lang('Resource planning (ERP)')),
        array('marketing', 'communication', lang('Communication')),
        array('system', 'tools', lang('System tools')),
    );
    $items = '';

    foreach ($rows as $row) {
        $tile = str_replace('pg-mi-tile me-3', 'pg-mi-tile', pg_menu_icon_tile(pg_menu_icon($row[1], '', ''), $row[0]));
        $items .= '<span class="pg-look-tile pg-look-mi-item" tabindex="0"' . (($row[1] === 'flows') ? ' data-pg-flows-running' : '') . '>'
            . $tile . '<span class="pg-text-meta">' . h($row[2]) . '</span></span>';
    }

    // A row at rest beside the open row: the open one's tile is filled with
    // the tone and the row takes the quiet fill, as in the menu.
    $open = '';

    foreach (array('', ' pg-look-mi-open') as $state) {
        $open .= '<span class="pg-look-mi-row' . $state . '">'
            . pg_menu_icon_tile(pg_menu_icon('products', '', ''), 'ecommerce')
            . '<span>' . h(lang('Products')) . '</span></span>';
    }

    return array(
        'id'      => 'pattern-menu-icon',
        'title'   => lang('Menu row icon'),
        'classes' => array('.pg-look-mi', '.pg-look-mi-item', '.pg-look-mi-row', '.pg-look-mi-open', '.pg-mi', '.pg-mi-tile', '.pg-mi-f', '.pg-mi-h', '.pg-mi-date'),
        'html'    => '<div class="pg-look-line pg-look-mi">' . $items . '</div>'
            . '<div class="pg-look-line pg-look-mi">' . $open . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From the main menu: one line drawing on a tile in the colour of the row\'s category. Pointing at a row or focusing it plays its icon\'s one move, half a second; nothing loops but a live part, the running flow\'s dot. With less motion asked for, nothing moves.')) . '</p>'
            . '<p class="pg-text-meta">' . h(lang('The open row, second beside a row at rest: its tile filled with the colour and the drawing white (the menu\'s ground in the dark theme), the row a quiet grey fill with dark words. A panel\'s current entry is drawn the same way; pointing never fills a tile.')) . '</p>',
    );
}

/**
 * A person's own status (person_status_screen.php, workspace.js): the Status
 * dialog's six kinds as the choice cards three across, a name with the mark
 * of its status beside it, and the person card's line under the title.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_person_status_pattern($p)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/person_status_screen.php');

    $looks = ws_person_status_looks();
    $cards = '';

    foreach (pg_person_status_kinds() as $kind => $label) {
        $id = $p . '_look_status_' . $kind;
        $cards .= '<div class="form-field form-field-card">'
            . '<input class="radio" type="radio" name="' . $p . '_look_status" value="' . h($kind) . '" id="' . $id . '"' . (($kind === 'out') ? ' checked' : '') . '>'
            . '<label class="pg-start-head" for="' . $id . '"><span class="pg-icon-tile pg-tone-' . $looks[$kind][1] . '" aria-hidden="true"><i class="bi ' . $looks[$kind][0] . '"></i></span>'
            . '<strong>' . h($label) . '</strong><span class="pg-start-mark" aria-hidden="true"><i class="bi bi-check"></i></span></label>'
            . '</div>';
    }

    $kinds = pg_person_status_kinds();
    $words = $kinds['out'] . ' ' . lang(array('string' => 'until {var:1} today', 'vars' => array('18:00')));
    $mark = '<span class="ws-person-status-mark pg-tone-amber" title="' . h($words) . '"><i class="bi bi-door-open ws-person-status-ink" aria-hidden="true"></i><span class="visually-hidden"> (' . h($words) . ')</span></span>';

    return array(
        'id'      => 'pattern-person-status',
        'title'   => lang('Status'),
        'classes' => array('.ws-person-status-dialog', '.pg-start-choice', '.ws-person-status-mark', '.ws-person-status-ink', '.ws-person-status-line'),
        'html'    => '<div class="ws-person-status-dialog"><fieldset><legend class="visually-hidden">' . h(lang('Your status')) . '</legend><div class="pg-start pg-start-choice">' . $cards . '</div></fieldset></div>'
            . '<div class="pg-look-line"><strong>' . h(lang('A team member')) . '</strong>' . $mark . '</div>'
            . '<div class="ws-person-status-line">' . $mark . '<span>' . h($words) . '</span><button type="button" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Change')) . '</button></div>'
            . '<p class="pg-text-meta">' . h(lang('A person\'s own status: the kind\'s icon in its tone beside a name, its words in the tooltip and for screen readers; the dot on the face stays the heartbeat.')) . '</p>',
    );
}

/**
 * The Flows canvas's tools (workspace_flow.js, drawPalette() and
 * drawStrip()), still: the add rail at the top left with the list open as
 * a card beside it, one group unfolded and one folded, and the bottom strip
 * with Undo, Redo, the zoom, Fit and the data threads.
 *
 * @return array array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_canvas_tools_pattern()
{
    $icon = function ($icon, $label, $classes = 'pg-icon-button pg-icon-button-sm') {
        return '<button type="button" class="' . $classes . '" title="' . h($label) . '" aria-label="' . h($label) . '"><i class="bi ' . $icon . '" aria-hidden="true"></i></button>';
    };
    $group = function ($group, $name, $label) use ($icon) {
        return $icon($name, $label, 'pg-icon-button wsf-palette-rail-group wsf-group-' . $group);
    };
    $row = function ($group, $icon, $name) {
        return '<button type="button" class="wsf-palette-item wsf-group-' . $group . '" title="' . h($name) . '">'
            . '<span class="pg-icon-tile wsf-palette-tile"><i class="bi ' . $icon . '" aria-hidden="true"></i></span>'
            . '<span class="wsf-palette-name">' . h($name) . '</span></button>';
    };
    $divider = '<span class="wsf-strip-divider" aria-hidden="true"></span>';

    return array(
        'id'      => 'pattern-canvas-tools',
        'title'   => lang('Canvas tools'),
        'classes' => array('.wsf-palette', '.wsf-palette-rail', '.wsf-palette-rail-open', '.wsf-palette-rail-group', '.wsf-palette-panel', '.wsf-palette-top', '.wsf-palette-search', '.wsf-palette-groups', '.wsf-palette-head', '.wsf-palette-item', '.wsf-palette-tip', '.wsf-strip', '.wsf-strip-divider', '.wsf-zoom-label', '.wsf-map', '.wsf-map-boxes', '.wsf-map-box', '.wsf-map-view', '.pg-button-fill', '.pg-icon-button'),
        'html'    => '<div class="pgc-stage pg-look-tools">'
            . '<div class="wsf-palette"><div class="wsf-palette-rail" role="group" aria-label="' . h(lang('Add a box')) . '">'
            . $icon('bi-plus-lg', lang('Add a box'), 'pg-button pg-button-fill wsf-palette-rail-open')
            . $group('message', 'bi-chat-left-text', lang('Message'))
            . $group('people', 'bi-person-check', lang('People and tasks'))
            . $group('logic', 'bi-signpost-split', lang('Logic'))
            . $group('system', 'bi-table', lang('Records and tables'))
            . $group('system', 'bi-plug', lang('System'))
            . $group('values', 'bi-braces', lang('Values'))
            . '</div>'
            . '<div class="wsf-palette-panel" role="region" aria-label="' . h(lang('Add a box')) . '">'
            . '<div class="wsf-palette-top"><h4 class="wsf-palette-title">' . h(lang('Add a box')) . '</h4>' . $icon('bi-x-lg', lang('Close the list of boxes'), 'pg-icon-button pg-icon-button-sm wsf-palette-close') . '</div>'
            . '<input type="search" class="pg-input pg-input-sm wsf-palette-search" placeholder="' . h(lang('Search boxes')) . '" aria-label="' . h(lang('Search boxes')) . '">'
            . '<div class="wsf-palette-groups"><div class="wsf-palette-stage">'
            . '<div class="wsf-palette-stage-head"><span class="wsf-palette-stage-num" aria-hidden="true">2</span><span>' . h(lang('Steps')) . '</span></div>'
            . '<div class="wsf-palette-group"><button type="button" class="wsf-palette-head" aria-expanded="true"><i class="bi bi-chevron-down wsf-palette-chevron" aria-hidden="true"></i><span>' . h(lang('Message')) . '</span></button>'
            . '<div class="wsf-palette-rows">' . $row('message', 'bi-chat-square', lang('Post a message')) . $row('message', 'bi-envelope', lang('Send an e-mail')) . '</div></div>'
            . '<div class="wsf-palette-group"><button type="button" class="wsf-palette-head" aria-expanded="false"><i class="bi bi-chevron-right wsf-palette-chevron" aria-hidden="true"></i><span>' . h(lang('Logic')) . '</span><span class="wsf-palette-count">6</span></button></div>'
            . '</div></div>'
            . '<p class="wsf-palette-tip pg-text-meta"><i class="bi bi-hand-index me-1" aria-hidden="true"></i>' . h(lang('Click or drag onto the canvas')) . '</p>'
            . '</div></div>'
            . '<div class="wsf-strip" role="group" aria-label="' . h(lang('Canvas tools')) . '">'
            . $icon('bi-arrow-counterclockwise', lang('Undo')) . $icon('bi-arrow-clockwise', lang('Redo change')) . $divider
            . $icon('bi-dash-lg', lang('Zoom out')) . '<span class="wsf-zoom-label">100%</span>' . $icon('bi-plus-lg', lang('Zoom in')) . $icon('bi-arrows-fullscreen', lang('Fit to the screen')) . $divider
            . '<button type="button" class="pg-button pg-button-ghost pg-button-sm wsf-tidy" title="' . h(lang('Place the boxes in columns by the order of their steps; with several boxes chosen, only those')) . '"><i class="bi bi-magic" aria-hidden="true"></i>' . h(lang('Tidy')) . '</button>'
            . str_replace('<button type="button"', '<button type="button" aria-pressed="true"', $icon('bi-bezier2', lang('Data threads')))
            . $icon('bi-sticky', lang('Note'))
            . str_replace('<button type="button"', '<button type="button" aria-pressed="true"', $icon('bi-map', lang('Map of the flow')))
            . '</div>'
            . '<div class="wsf-map" role="img" aria-label="' . h(lang('Map of the flow')) . '"><div class="wsf-map-boxes">'
            . '<span class="wsf-map-box" style="left: 10px; top: 40px; width: 18px; height: 7px"></span>'
            . '<span class="wsf-map-box" style="left: 40px; top: 40px; width: 18px; height: 7px"></span>'
            . '<span class="wsf-map-box" style="left: 70px; top: 30px; width: 18px; height: 7px"></span>'
            . '<span class="wsf-map-box" style="left: 70px; top: 52px; width: 18px; height: 7px"></span>'
            . '<span class="wsf-map-box" style="left: 100px; top: 30px; width: 18px; height: 7px"></span>'
            . '<span class="wsf-map-box" style="left: 130px; top: 52px; width: 18px; height: 7px"></span>'
            . '</div><div class="wsf-map-view" style="left: 6px; top: 20px; width: 90px; height: 54px"></div></div>'
            . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From the Flows canvas: the add rail floats at the canvas\'s top left, a filled "+" and an icon for each group with its name as tooltip; "+" opens the list as a card beside the rail, a group\'s icon opens it at that group. Esc, a box added or a click on the canvas closes it. The bottom strip, centred over the canvas, holds Undo, Redo, the zoom, Fit and the data threads; a pressed tool shows the quiet fill.')) . '</p>'
            . '<p class="pg-text-meta">' . h(lang('Tidy on the strip places the boxes again in columns by the order of their steps, frames round their boxes. The map at the bottom left shows the whole flow small with the part on screen framed; a drag on it moves the canvas. It shows by itself from 12 boxes, or when Map is pressed.')) . '</p>',
    );
}

/**
 * The Flows canvas's add list at an arrow (board B1): the arrow's "+",
 * the dashed "+" after a box, the card with its search, "Often next" and
 * the groups as chips.
 *
 * @return array the pattern's id, title, classes and markup
 */
function pg_look_examples_add_list_pattern()
{
    $row = function ($group, $icon, $name) {
        return '<button type="button" class="wsf-palette-item wsf-add-item wsf-group-' . $group . '" title="' . h($name) . '">'
            . '<span class="pg-icon-tile wsf-palette-tile"><i class="bi ' . $icon . '" aria-hidden="true"></i></span>'
            . '<span class="wsf-palette-name">' . h($name) . '</span></button>';
    };
    $chip = function ($name, $count, $pressed = false) {
        return '<button type="button" class="pg-button pg-button-quiet pg-button-sm wsf-add-chip" aria-pressed="' . ($pressed ? 'true' : 'false') . '">'
            . h($name) . '<span class="wsf-palette-count">' . (int) $count . '</span></button>';
    };
    $plus = function ($class, $label) {
        return '<button type="button" class="wsf-plus ' . $class . '" title="' . h($label) . '" aria-label="' . h($label) . '" style="position: static"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>';
    };

    return array(
        'id'      => 'pattern-add-list',
        'title'   => lang('Add list'),
        'classes' => array('.wsf-plus', '.wsf-out-plus', '.wsf-add-pop', '.wsf-add-find', '.wsf-add-search', '.wsf-add-key', '.wsf-add-list', '.wsf-add-chips', '.wsf-add-chip', '.wsf-palette-stage-head', '.wsf-palette-tip'),
        'html'    => '<div class="d-flex flex-wrap align-items-start gap-4">'
            . '<div class="d-flex gap-3 align-items-center">' . $plus('wsf-edge-plus', lang('Add a box on this arrow')) . $plus('wsf-out-plus', lang('Add a box after {var:1}')) . '</div>'
            . '<div class="wsf-add-pop" role="dialog" aria-label="' . h(lang('Add a box here')) . '" style="position: relative">'
            . '<div class="wsf-add-find"><input type="search" class="pg-input pg-input-sm wsf-add-search" placeholder="' . h(lang('Search boxes')) . '" aria-label="' . h(lang('Search boxes')) . '"><kbd class="wsf-add-key" aria-hidden="true">/</kbd></div>'
            . '<div class="wsf-add-list">'
            . '<h3 class="wsf-palette-stage-head">' . h(lang('Often next')) . '</h3>'
            . $row('logic', 'bi-hourglass-split', lang('Wait')) . $row('logic', 'bi-signpost-split', lang('Condition')) . $row('people', 'bi-check2-square', lang('Task'))
            . '<h3 class="wsf-palette-stage-head">' . h(lang('All')) . '</h3>'
            . '<div class="wsf-add-chips" role="group" aria-label="' . h(lang('All')) . '">' . $chip(lang('Message'), 6, true) . $chip(lang('People and tasks'), 6) . $chip(lang('Logic'), 5) . $chip(lang('System'), 7) . '</div>'
            . '<div class="wsf-palette-rows">' . $row('message', 'bi-chat-square', lang('Post a message')) . $row('message', 'bi-envelope', lang('Send an e-mail')) . '</div>'
            . '</div>'
            . '<p class="wsf-palette-tip pg-text-meta">' . h(lang('Dropping an arrow\'s end on empty space opens this list too.')) . '</p>'
            . '</div></div>'
            . '<p class="pg-text-meta">' . h(lang('From the Flows canvas: pointing at an arrow shows a "+" at its middle; a dashed "+" stands after a box with a free outcome. Either opens this list there: the search ("/" reaches it), "Often next" by the box before, then the groups as chips with their counts. The box chosen cuts the arrow in two; the boxes after it move one column right when there is no room. A on the canvas opens it at the arrow or box chosen.')) . '</p>',
    );
}

/**
 * The Flows canvas's trial (board C1): the bar while the draft is tried,
 * boxes marked as walked and in turn, the panel's steps with Next step and
 * To the end.
 *
 * @return array the pattern's id, title, classes and markup
 */
function pg_look_examples_flow_trial_pattern()
{
    $badge = function ($state, $icon, $word) {
        return '<span class="wsf-run-badge wsf-run-' . $state . '"><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($word) . '</span>';
    };
    $button = function ($class, $icon, $label) {
        return '<button type="button" class="pg-button pg-button-sm ' . $class . '"><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($label) . '</button>';
    };

    return array(
        'id'      => 'pattern-flow-trial',
        'title'   => lang('Try the flow'),
        'classes' => array('.wsf-trial', '.wsf-run-badge', '.wsf-run-done', '.wsf-run-skipped', '.wsf-run-running', '.wsf-run-failed', '.pg-button-fill', '.pg-button-quiet', '.pg-button-ghost'),
        'html'    => '<div class="d-flex flex-column gap-4 wsf-trial">'
            . '<div class="d-flex flex-wrap align-items-center gap-3"><span class="small fw-semibold fg-primary"><i class="bi bi-eye me-1" aria-hidden="true"></i>' . h(lang('Trial · nothing is sent')) . '</span>'
            . $button('pg-button-fill', 'bi-x-lg', lang('End the trial')) . '</div>'
            . '<div class="d-flex flex-wrap gap-2">' . $badge('done', 'bi-check-circle-fill', lang('Tried')) . $badge('skipped', 'bi-skip-forward', lang('Passed'))
            . $badge('done', 'bi-check-circle-fill', lang('Yes')) . $badge('failed', 'bi-x-octagon-fill', lang('Would fail')) . $badge('running', 'bi-arrow-right-circle', lang('Next')) . '</div>'
            . '<ol class="list-unstyled small mb-0">'
            . '<li class="mb-3"><div class="d-flex align-items-center gap-2 mb-1">' . $badge('done', 'bi-check-circle-fill', lang('Tried')) . '<span class="fw-semibold">' . h(lang('Send an e-mail')) . '</span></div>'
            . '<div>' . h(lang('To the record\'s contact')) . '</div>' . $button('pg-button-ghost mt-1', 'bi-envelope-open', lang('Preview')) . '</li>'
            . '<li class="d-flex align-items-center gap-2 fg-secondary">' . $badge('running', 'bi-arrow-right-circle', lang('Next')) . '<span>' . h(lang('Task')) . '</span></li>'
            . '</ol>'
            . '<div class="d-flex flex-wrap gap-2">' . $button('pg-button-fill', 'bi-arrow-right', lang('Next step')) . $button('pg-button-quiet', 'bi-skip-end', lang('To the end')) . '</div>'
            . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From the Flows canvas: Try in the bar walks the draft from its start with a record, read-only; the bar says nothing is sent and offers End the trial. The panel lists the steps walked in their Try words, the box in turn, Next step and To the end. On the canvas a walked box wears a tick (a Wait "Passed", a condition its outcome), the box in turn "Next"; the arrows taken are green, the others faint.')) . '</p>',
    );
}

/**
 * The Flows canvas's counts: Counts and the days in the bar, the pills over
 * a box (look A's .pg-pill as buttons), the list of a pill's runs.
 *
 * @return array id, title, classes, html
 */
function pg_look_examples_flow_counts_pattern()
{
    $pill = function ($tone, $label, $pressed) {
        return '<button type="button" class="pg-pill pg-tone-' . $tone . ' wsf-count" aria-pressed="' . ($pressed ? 'true' : 'false') . '">' . h($label) . '</button>';
    };
    $days = function ($n, $pressed) {
        return '<button type="button" class="pg-seg-item" aria-pressed="' . ($pressed ? 'true' : 'false') . '">' . h(lang(array('string' => 'Last {var:1} days', 'vars' => $n))) . '</button>';
    };

    return array(
        'id'      => 'pattern-flow-counts',
        'title'   => lang('Counts'),
        'classes' => array('.wsf-counts', '.wsf-count', '.pg-pill', '.pg-tone-amber', '.pg-tone-gray', '.pg-tone-rose', '.pg-seg', '.wsf-run-item'),
        'html'    => '<div class="d-flex flex-column gap-4">'
            . '<div class="d-flex flex-wrap align-items-center gap-3"><button type="button" class="pg-button pg-button-quiet pg-button-sm fg-primary" aria-pressed="true"><i class="bi bi-bar-chart" aria-hidden="true"></i>' . h(lang('Counts')) . '</button>'
            . '<div class="pg-seg" role="group">' . $days(7, false) . $days(30, true) . '</div></div>'
            . '<div class="d-flex flex-wrap gap-2 wsf-counts-sample">' . $pill('amber', lang(array('string' => '{var:1} waiting', 'vars' => 12)), true)
            . $pill('gray', lang(array('string' => '{var:1} passed', 'vars' => 116)), false) . $pill('rose', lang(array('string' => '{var:1} failed', 'vars' => 3)), false) . '</div>'
            . '<ul class="list-unstyled mb-0 wsf-run-list"><li><button type="button" class="wsf-run-item"><span class="wsf-run-item-top"><span class="fw-semibold">Ayşe Demir</span><i class="bi bi-chevron-right ms-auto fg-secondary" aria-hidden="true"></i></span>'
            . '<span class="wsf-run-item-line">' . h(lang(array('string' => 'Waits until {var:1}', 'vars' => '24.11.2026 15:40'))) . '</span></button></li></ul>'
            . '</div>'
            . '<p class="pg-text-meta">' . h(lang('From the Flows canvas: Counts in the bar of a published flow, with the days beside it, puts pills over each box: runs waiting there now in amber, passes in the days plain, failures in rose; a start says how many started. A pill lists its runs in the panel, each opening the run, and Open all in Flow runs narrows the runs list to them.')) . '</p>',
    );
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
                lang('Dialogs'),
                lang('Dashboard cards'),
                lang('Report screens'),
                lang('Workspace: channel screen'),
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

    foreach (pg_look_examples($theme) as $section) {
        $out .= pg_look_examples_section($section, $theme);
    }

    // The patterns: whole pieces put together from the parts above.
    $out .= '<h3 class="pg-title-section pg-look-pane-title" id="pg_look_' . $theme . '_patterns">' . h(lang('Patterns')) . '</h3>';

    foreach (pg_look_examples_patterns($theme) as $section) {
        $out .= pg_look_examples_section($section, $theme);
    }

    return $out . '</div>';
}

/**
 * One example: its title, its live markup and the classes that draw it.
 *
 * @param array $section array('id', 'title', 'classes', 'html')
 * @param string $prefix the pane's id prefix
 * @return string
 */
function pg_look_examples_section($section, $prefix)
{
    return '<section class="pg-look-example" aria-labelledby="pg_look_' . $prefix . '_' . $section['id'] . '">'
        . '<h3 class="pg-title-status" id="pg_look_' . $prefix . '_' . $section['id'] . '">' . h($section['title']) . '</h3>'
        . $section['html']
        . '<p class="pg-look-classes pg-text-meta">' . implode(' ', array_map(function ($name) {
            return '<code>' . h($name) . '</code>';
        }, $section['classes'])) . '</p>'
        . '</section>';
}

/**
 * The phone pane: the patterns marked 'phone', drawn on their own page
 * (design_examples.php?phone=1) that the screen shows in a 390 px frame.
 * A frame, not a narrow box, because the panel's phone rules are media
 * queries on the window's width.
 *
 * @return string
 */
function pg_look_examples_phone_pane()
{
    $out = '<div class="pg-look-pane pg-look-phone-pane">';

    foreach (pg_look_examples_patterns('phone') as $section) {
        if (!empty($section['phone'])) {
            $out .= pg_look_examples_section($section, 'phone');
        }
    }

    return $out . '</div>';
}

/**
 * The frame the screen shows the phone pane in: a phone's 390 px wide.
 *
 * @return string
 */
function pg_look_examples_phone_frame()
{
    return '<section class="pg-look-phone" aria-labelledby="pg_look_phone_title">'
        . '<h2 class="pg-title-section pg-look-pane-title" id="pg_look_phone_title">' . h(lang('Phone')) . '</h2>'
        . '<p class="pg-text-2 pg-look-phone-line">' . h(lang('The settings pane, the form drawer, the document head, the choice cards and the step line at 390 px, as a phone draws them.')) . '</p>'
        . '<iframe class="pg-look-phone-frame" src="design_examples.php?phone=1" width="390" height="844" loading="lazy" title="' . h(lang('Design examples at 390 px')) . '"></iframe>'
        . '</section>';
}

/**
 * A Workspace screen's bar and its empty screen (boards S1 and S3 of
 * plans/workspace-interface; barViews(), barFilter(), barSearch() and
 * emptyScreen() in workspace.js): My Tasks' one line with "Filter" open,
 * the tags of what is set under it; the empty screen with and without its
 * main action.
 *
 * @param string $p the pane's id prefix, already cleaned
 * @return array list of array('id', 'title', 'classes', 'html')
 */
function pg_look_examples_ws_screen_patterns($p)
{
    $views = array(lang('Mine'), lang('Given by me'), lang('My departments'), lang('Everyone'), lang('My time'));
    $seg = '';
    $options = '';

    foreach ($views as $index => $view) {
        $seg .= '<button type="button" class="pg-seg-item" aria-pressed="' . ($index ? 'false' : 'true') . '">' . h($view)
            . ($index ? '' : '<em class="pg-seg-count">6</em>') . '</button>';
        $options .= '<option' . ($index ? '' : ' selected') . '>' . h($view) . '</option>';
    }

    $select = function ($id, $label, $choices) {
        $out = '<div class="ws-plan-field"><label class="pg-field-label" for="' . $id . '">' . h($label) . '</label><select class="pg-input pg-input-sm" id="' . $id . '">';

        foreach ($choices as $choice) {
            $out .= '<option>' . h($choice) . '</option>';
        }

        return $out . '</select></div>';
    };
    $tag = function ($text) {
        return '<span class="ws-bar-tag">' . h($text) . '<button type="button" class="ws-bar-tag-remove" title="' . h(lang(array('string' => 'Remove the filter: {var:1}', 'vars' => array($text)))) . '" aria-label="' . h(lang(array('string' => 'Remove the filter: {var:1}', 'vars' => array($text)))) . '"><i class="bi bi-x" aria-hidden="true"></i></button></span>';
    };

    $bar = '<div class="ws-bar-frame"><nav class="pg-toolbar ws-bar">'
        . '<button type="button" class="pg-button pg-button-fill"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('New task')) . '</button>'
        . '<div class="ws-bar-views"><div class="pg-seg ws-bar-views-seg" role="group" aria-label="' . h(lang('Which tasks')) . '">' . $seg . '</div>'
        . '<select class="pg-input pg-input-sm ws-bar-views-select" aria-label="' . h(lang('Which tasks')) . '">' . $options . '</select></div>'
        . '<div class="pg-toolbar-grow"></div>'
        . '<span class="ws-bar-filter"><button type="button" class="pg-button pg-button-quiet ws-bar-filter-button" aria-haspopup="dialog" aria-expanded="true" aria-label="' . h(lang(array('string' => 'Filter, {var:1} set', 'vars' => array(2)))) . '">'
        . '<i class="bi bi-funnel" aria-hidden="true"></i><span class="ws-bar-filter-label">' . h(lang('Filter')) . '</span><span class="ws-bar-count" aria-hidden="true">2</span></button></span>'
        . '<label class="pg-input pg-input-sm ws-bar-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" placeholder="' . h(lang('Search tasks')) . '" aria-label="' . h(lang('Search tasks')) . '"></label>'
        . '</nav></div>'
        . '<div class="ws-bar-tags">' . $tag(lang('Finished tasks')) . $tag(lang('Pressing, coming up'))
        . '<button type="button" class="pg-button pg-button-ghost pg-button-sm">' . h(lang('Clear')) . '</button></div>';

    $box = '<div class="pg-card ws-bar-box" role="dialog" aria-label="' . h(lang('Filter')) . '"><div class="ws-bar-box-body">'
        . $select($p . '-ws-bar-status', lang('Task status'), array(lang('Finished tasks'), lang('Open tasks'), lang('All tasks')))
        . $select($p . '-ws-bar-dept', lang('Department'), array(lang('All departments')))
        . $select($p . '-ws-bar-priority', lang('Task priority'), array(lang('All priorities')))
        . $select($p . '-ws-bar-made', lang('Made by agents'), array(lang('Made by anyone')))
        . '<label class="ws-plan-switch"><input type="checkbox" class="pg-switch" checked><i class="bi bi-fire fg-danger" aria-hidden="true"></i>' . h(lang('Pressing, coming up')) . '</label>'
        . '</div><div class="ws-bar-box-foot"><button type="button" class="pg-button pg-button-ghost pg-button-sm">' . h(lang('Clear')) . '</button>'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Close')) . '</button></div></div>';

    $empty = function ($tone, $icon, $title, $text, $action) {
        return '<div class="pg-card"><div class="ws-empty-screen"><span class="pg-icon-tile ws-empty-screen-tile pg-tone-' . $tone . '" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>'
            . '<div class="pg-title-section ws-empty-screen-title">' . h($title) . '</div><p class="pg-text-2 ws-empty-screen-text">' . h($text) . '</p>'
            . (($action !== '') ? '<button type="button" class="pg-button pg-button-quiet pg-button-sm">' . $action . '</button>' : '')
            . '</div></div>';
    };

    return array(
        array(
            'id'      => 'pattern-ws-screen-bar',
            'title'   => lang('Screen bar with filters'),
            'classes' => array('.ws-bar-frame', '.pg-toolbar', '.ws-bar', '.pg-button-fill', '.ws-bar-views', '.pg-seg', '.ws-bar-views-seg', '.pg-seg-count', '.ws-bar-views-select', '.pg-toolbar-grow', '.ws-bar-filter', '.ws-bar-filter-label', '.ws-bar-count', '.ws-bar-search', '.ws-bar-tags', '.ws-bar-tag', '.ws-bar-tag-remove', '.ws-bar-box', '.ws-bar-box-body', '.ws-bar-box-foot', '.pg-switch'),
            'html'    => '<div class="pg-look-stack">' . $bar . $box
                . '<p class="pg-text-meta">' . h(lang('From the Workspace\'s screens: one line, never a second row. The main action and the views, then the switches, "Filter" and the search; more than three filters go behind "Filter", what is set is a tag under the bar. Below 1100 px the views are one select; on a phone the bar keeps the main action and the search.')) . '</p></div>',
        ),
        array(
            'id'      => 'pattern-ws-empty-screen',
            'title'   => lang('Empty screen'),
            'classes' => array('.ws-empty-screen', '.pg-icon-tile', '.ws-empty-screen-tile', '.pg-title-section', '.ws-empty-screen-text'),
            'html'    => '<div class="pg-look-stack">'
                . $empty('blue', 'bi-clock-history', lang('No decisions yet'), lang('A message marked as a decision in a channel shows here.'), '')
                . $empty('teal', 'bi-check2-square', lang('No tasks'), lang('All clear - no tasks here.'), '<i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('New task')))
                . $empty('gray', 'bi-funnel', lang('Nothing found'), lang('No task matches the filters or the search.'), '<i class="bi bi-x-lg" aria-hidden="true"></i>' . h(lang('Clear')))
                . '<p class="pg-text-meta">' . h(lang('In the content\'s card: a tile in the screen\'s tone, a title that says what is missing, the sentence, the main action; a list its filters narrowed to nothing offers Clear.')) . '</p></div>',
        ),
    );
}
