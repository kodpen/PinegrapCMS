<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail's two tabs (includes/settings/email.php): each
 * tab's state pill, and the tab control and panels the category's cards are
 * drawn in. Pure but for lang().
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
 * The E-mail sending tab's pill: Not set up while the site has no way to
 * send (pg_mail_settings_ready()), Check the domain while SPF, DKIM or DMARC
 * is wrong or missing, else Ready. A lookup that could not run says nothing
 * against the domain.
 *
 * @param bool  $ready  pg_mail_settings_ready()
 * @param array $health pg_mail_domain_health(): spf, dkim, dmarc, each with
 *                      its state (ok, warning, error, unknown)
 * @return array words, tone (a pg-tone- class)
 */
function pg_email_tab_sending_state($ready, $health)
{
    if (!$ready) {
        return array('words' => lang('Not set up'), 'tone' => 'pg-tone-gray');
    }

    foreach (array('spf', 'dkim', 'dmarc') as $record) {

        $state = (string) ($health[$record]['state'] ?? 'unknown');

        if (in_array($state, array('warning', 'error'), true)) {
            return array('words' => lang('Check the domain'), 'tone' => 'pg-tone-amber');
        }
    }

    return array('words' => lang('Ready'), 'tone' => 'pg-tone-teal');
}

/**
 * The Mailboxes tab's pill from its boxes' states (ws_mail_box_state()
 * keys): No box, Error, Sign-in needed, then how many are read, else Off.
 *
 * @param string[] $keys off | sign_in | error | reading | none, one a box
 * @return array words, tone (a pg-tone- class)
 */
function pg_email_tab_mailboxes_state($keys)
{
    $keys = array_values((array) $keys);

    if ($keys === array()) {
        return array('words' => lang('No box'), 'tone' => 'pg-tone-gray');
    }

    if (in_array('error', $keys, true)) {
        return array('words' => lang('Error'), 'tone' => 'pg-tone-rose');
    }

    if (in_array('sign_in', $keys, true)) {
        return array('words' => lang('Sign-in needed'), 'tone' => 'pg-tone-amber');
    }

    $reading = count(array_keys($keys, 'reading', true));

    if ($reading > 0) {
        return array('words' => lang(array('string' => '{var:1} reading', 'vars' => $reading)), 'tone' => 'pg-tone-teal');
    }

    return array('words' => lang('Off'), 'tone' => 'pg-tone-gray');
}

/**
 * A category's tab control, the Design examples' tabs with a state: the
 * taller segmented control as a tablist, each tab its label and its state
 * pill (the word in the pill's title too, for the phone, where the pill is
 * its dot). Nothing is drawn for a single tab: a reader with one tab's
 * right sees its panel alone.
 *
 * @param string $key    the category's key
 * @param array  $tabs   tab key => array(label)
 * @param string $first  the tab shown first
 * @param array  $states tab key => array(words, tone), as the pills say
 * @return string HTML
 */
function pg_email_tabs_html($key, $tabs, $first, $states)
{
    if (count($tabs) < 2) {
        return '';
    }

    $html = '<div class="pg-seg pg-seg-tall" role="tablist" aria-label="' . h(lang('E-mail')) . '" data-pg-set-pane="' . h($key) . '">';

    foreach ($tabs as $tab_key => $tab) {
        $panel = pg_settings_tab_section($key, $tab_key);
        $on = ($tab_key === $first);
        $state = $states[$tab_key] ?? null;

        $html .= '<button type="button" class="pg-seg-item" role="tab" id="' . h($panel) . '-tab" aria-selected="' . ($on ? 'true' : 'false') . '" aria-controls="' . h($panel) . '"'
            . ($on ? '' : ' tabindex="-1"') . ' data-pg-set-tab>' . h($tab['label'])
            . ($state ? ' <span class="pg-pill ' . h($state['tone']) . '" title="' . h($state['words']) . '"><span>' . h($state['words']) . '</span></span>' : '')
            . '</button>';
    }

    return '<div>' . $html . '</div></div>';
}

/**
 * A tab's panel around its cards. With more than one tab it is a tabpanel,
 * hidden unless it is the first shown; a hidden field says the tab's cards
 * were posted, so a save writes only the tabs whose fields it carries
 * (includes/settings/email.save.php).
 *
 * @param string $key    the category's key
 * @param string $tab    the tab's key
 * @param string $cards  the tab's cards, HTML
 * @param bool   $tabbed more than one tab is drawn
 * @param bool   $shown  the panel is the one shown first
 * @return string HTML
 */
function pg_email_tab_panel_html($key, $tab, $cards, $tabbed, $shown)
{
    $panel = pg_settings_tab_section($key, $tab);

    return '<div class="pg-settings-body" id="' . h($panel) . '" data-pg-set-tabpanel'
        . ($tabbed ? ' role="tabpanel" aria-labelledby="' . h($panel) . '-tab"' . ($shown ? '' : ' hidden') : '') . '>'
        . '<input type="hidden" name="' . h($key . '_' . $tab . '_tab') . '" value="1">'
        . $cards . '</div>';
}
