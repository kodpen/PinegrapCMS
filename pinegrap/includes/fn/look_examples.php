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
                lang('The list table: header strip, one-line rows, row actions (list screens step)'),
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

    foreach (pg_look_examples($theme) as $section) {
        $out .= '<section class="pg-look-example" aria-labelledby="pg_look_' . $theme . '_' . $section['id'] . '">'
            . '<h3 class="pg-title-status" id="pg_look_' . $theme . '_' . $section['id'] . '">' . h($section['title']) . '</h3>'
            . $section['html']
            . '<p class="pg-look-classes pg-text-meta">' . implode(' ', array_map(function ($name) {
                return '<code>' . h($name) . '</code>';
            }, $section['classes'])) . '</p>'
            . '</section>';
    }

    return $out . '</div>';
}
