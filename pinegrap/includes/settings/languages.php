<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Languages and Translation cards: the source language
 * and the target languages with their settings, and the translation engines.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

pg_tr_load();

$output_translation_not_ready = '
                    <div class="col-12">
                        <div class="alert alert-secondary mb-0 py-2 small">' . lang('These settings arrive with the 2026.4.6 upgrade, which this site has not run yet.') . '</div>
                    </div>';

$translation_known = pg_tr_known_languages();
$translation_languages = $translation_settings_ready ? pg_tr_languages(false, true) : array();
$translation_engine_options = pg_tr_engine_options();
$translation_source = ($translation_source_language !== '') ? $translation_source_language : pg_tr_source_language();

// ── Site Languages ──

// The source language: every language the list knows, the panel language
// first when nothing is set.
$output_translation_source_options = '';

foreach ($translation_known as $translation_code => $translation_info) {
    $output_translation_source_options .=
        '<option value="' . h($translation_code) . '"' . (($translation_code === $translation_source) ? ' selected="selected"' : '') . '>'
        . h($translation_info[1]) . ' (' . h($translation_code) . ')</option>';
}

// One row per target language. Every control carries the row's code in its
// name, so the save knows which row it belongs to.
$output_translation_rows = '';

foreach ($translation_languages as $translation_code => $translation_row) {

    if ($translation_code === $translation_source) {
        continue;
    }

    $translation_name = 'languages[' . h($translation_code) . ']';
    $translation_label = ($translation_row['label'] !== '') ? $translation_row['label'] : (isset($translation_known[$translation_code]) ? $translation_known[$translation_code][1] : $translation_code);
    $translation_prefix = ($translation_row['prefix'] !== '') ? $translation_row['prefix'] : $translation_code;

    $output_translation_engines = '';

    foreach (pg_tr_engine_options(array((string) $translation_row['engine'], (string) $translation_row['fallback_engine'])) as $translation_engine_key => $translation_engine_label) {
        $output_translation_engines .= '<option value="' . h($translation_engine_key) . '"' . (($translation_engine_key === $translation_row['engine']) ? ' selected="selected"' : '') . '>' . h($translation_engine_label) . '</option>';
    }

    $output_translation_fallbacks = '<option value="">' . lang('-None-') . '</option>';

    foreach (pg_tr_engine_options(array((string) $translation_row['engine'], (string) $translation_row['fallback_engine'])) as $translation_engine_key => $translation_engine_label) {
        if ($translation_engine_key === 'manual') {
            continue;
        }

        $output_translation_fallbacks .= '<option value="' . h($translation_engine_key) . '"' . (($translation_engine_key === $translation_row['fallback_engine']) ? ' selected="selected"' : '') . '>' . h($translation_engine_label) . '</option>';
    }

    $output_translation_policies = '';

    foreach (array('reviewed' => lang('Reviewed texts only'), 'all' => lang('Machine translations too'), 'none' => lang('Not indexed (visitors only)')) as $translation_policy_key => $translation_policy_label) {
        $output_translation_policies .= '<option value="' . $translation_policy_key . '"' . (($translation_policy_key === $translation_row['index_policy']) ? ' selected="selected"' : '') . '>' . h($translation_policy_label) . '</option>';
    }

    $output_translation_rows .= '
                        <div class="pg-tr-language border rounded p-3" data-code="' . h($translation_code) . '">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <span class="badge text-bg-secondary font-monospace">' . h($translation_code) . '</span>
                                <strong class="me-auto">' . h($translation_label) . '</strong>
                                <div class="form-check form-switch mb-0">
                                    <input value="1"' . (((int) $translation_row['enabled'] === 1) ? ' checked="checked"' : '') . ' class="form-check-input" type="checkbox" id="tr_enabled_' . h($translation_code) . '" name="' . $translation_name . '[enabled]"/>
                                    <label class="form-check-label" for="tr_enabled_' . h($translation_code) . '">' . lang('Served') . '</label>
                                </div>' . (array_key_exists('auto_update', $translation_row) ? '
                                <div class="form-check form-switch mb-0 ms-2" title="' . h(lang('When a page is saved, its new and changed texts are sent to this language\'s engine (Pinegrap AI, Claude or Google) on their own. The browser engine and translation by hand need the Translations screen.')) . '">
                                    <input value="1"' . (((int) $translation_row['auto_update'] === 1) ? ' checked="checked"' : '') . ' class="form-check-input" type="checkbox" id="tr_auto_' . h($translation_code) . '" name="' . $translation_name . '[auto_update]"/>
                                    <label class="form-check-label" for="tr_auto_' . h($translation_code) . '">' . lang('Translate on save') . '</label>
                                </div>' : '') . '
                                <div class="form-check mb-0 ms-2">
                                    <input value="1" class="form-check-input" type="checkbox" id="tr_remove_' . h($translation_code) . '" name="' . $translation_name . '[remove]"/>
                                    <label class="form-check-label text-danger" for="tr_remove_' . h($translation_code) . '">' . lang('Remove') . '</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_label_' . h($translation_code) . '">' . lang('Name shown to visitors') . '</label>
                                    <input type="text" class="form-control" id="tr_label_' . h($translation_code) . '" name="' . $translation_name . '[label]" value="' . h($translation_label) . '" maxlength="100"/>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_prefix_' . h($translation_code) . '">' . lang('Address prefix') . '</label>
                                    <div class="input-group">
                                        <span class="input-group-text">' . h(PATH) . '</span>
                                        <input type="text" class="form-control font-monospace" id="tr_prefix_' . h($translation_code) . '" name="' . $translation_name . '[prefix]" value="' . h($translation_prefix) . '" maxlength="16" pattern="[a-z]{2,8}(-[a-z]{2,4})?"/>
                                        <span class="input-group-text">/</span>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_engine_' . h($translation_code) . '">' . lang('Translation engine') . '</label>
                                    <select class="form-select" id="tr_engine_' . h($translation_code) . '" name="' . $translation_name . '[engine]">' . $output_translation_engines . '</select>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_fallback_' . h($translation_code) . '">' . lang('Fallback engine') . '</label>
                                    <select class="form-select" id="tr_fallback_' . h($translation_code) . '" name="' . $translation_name . '[fallback_engine]">' . $output_translation_fallbacks . '</select>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_policy_' . h($translation_code) . '">' . lang('Search engines index') . '</label>
                                    <select class="form-select" id="tr_policy_' . h($translation_code) . '" name="' . $translation_name . '[index_policy]">' . $output_translation_policies . '</select>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_number_' . h($translation_code) . '">' . lang('Number format') . '</label>
                                    <input type="text" class="form-control font-monospace" id="tr_number_' . h($translation_code) . '" name="' . $translation_name . '[locale_number]" value="' . h($translation_row['locale_number']) . '" maxlength="16" placeholder="1,234.56"/>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_date_' . h($translation_code) . '">' . lang('Date format') . '</label>
                                    <input type="text" class="form-control font-monospace" id="tr_date_' . h($translation_code) . '" name="' . $translation_name . '[locale_date]" value="' . h($translation_row['locale_date']) . '" maxlength="32" placeholder="j/n/Y"/>
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="tr_og_' . h($translation_code) . '">og:locale</label>
                                    <input type="text" class="form-control font-monospace" id="tr_og_' . h($translation_code) . '" name="' . $translation_name . '[og_locale]" value="' . h($translation_row['og_locale']) . '" maxlength="16" placeholder="en_US"/>
                                </div>
                            </div>
                        </div>';
}

if ($output_translation_rows === '') {
    $output_translation_rows = '
                        <div class="text-body-secondary small">' . lang('No target language yet. Add one below: the pages of the visual designs are then served under its address prefix as well.') . '</div>';
}

// Languages that can still be added: the known list minus the source and
// the ones already there.
$output_translation_add_options = '<option value="">' . lang('-Select-') . '</option>';

foreach ($translation_known as $translation_code => $translation_info) {
    if (($translation_code === $translation_source) || isset($translation_languages[$translation_code])) {
        continue;
    }

    $output_translation_add_options .= '<option value="' . h($translation_code) . '">' . h($translation_info[0]) . ' - ' . h($translation_info[1]) . ' (' . h($translation_code) . ')</option>';
}

$output_translation_languages = '
                    <div class="col-12">
                        <div class="pg-f-md">
                            <label for="translation_source_language" class="form-label">' . lang('Source language') . '</label>
                            <select name="translation_source_language" id="translation_source_language" class="form-select">' . $output_translation_source_options . '</select>
                            <div class="form-text">' . lang('The language the pages are written in. It is separate from the panel language and starts out equal to it.') . '</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">' . lang('Target languages') . '</label>
                        <div class="d-flex flex-column gap-3">' . $output_translation_rows . '
                        </div>
                        <div class="form-text">' . lang('A served language is reachable under its address prefix (for example /en/about). Removing a language takes it off the site; the translations already made are kept and come back when the language is added again.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="pg-f-md">
                            <label for="translation_add_language" class="form-label">' . lang('Add a language') . '</label>
                            <select name="translation_add_language" id="translation_add_language" class="form-select">' . $output_translation_add_options . '</select>
                            <div class="form-text">' . lang('The language is added when the settings are saved. A page, file or short link named like its code or prefix would be shadowed by the language directory, so the save stops and lists them when there are any.') . '</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-text">
                            <i class="bi bi-translate me-1"></i>' . lang(array(
                                'string' => 'The texts themselves are translated and reviewed on the {var:1} screen, which is also under Visual Page Editor in the menu.',
                                'vars'   => array('<a href="translations.php">' . lang('Translations') . '</a>'))) . '
                        </div>
                    </div>';

$pg_settings_cards[] = '
    <div id="pgset-languages" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Site Languages') . '</span>' . ($translation_settings_ready ? '
                <a class="btn btn-sm btn-outline-primary rounded-pill px-3" href="translations.php"><i class="bi bi-translate me-1" aria-hidden="true"></i>' . lang('Open the Translations screen') . '</a>' : '') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">' . ($translation_settings_ready ? $output_translation_languages : $output_translation_not_ready) . '
                </div>
            </div>
        </div>
    </div>';

// ── Translation Engines ──

$output_translation_google_state = $translation_google_key_stored
    ? '<span class="badge text-bg-success"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>' . lang('A key is stored') . '</span>'
    : '<span class="badge text-bg-secondary">' . lang('No key stored') . '</span>';

// Whether the two AI engines can run here, in one line each.
$translation_ai_state = '';

if ($translation_settings_ready) {
    foreach (array('ai', 'claude') as $translation_ai_key) {
        $translation_ai_engine = pg_tr_engine($translation_ai_key);

        if ($translation_ai_engine) {
            $translation_ai_state .= '<br/>' . ($translation_ai_engine['ready']
                ? '<span class="badge text-bg-success me-1"><i class="bi bi-check-lg" aria-hidden="true"></i></span>' . h($translation_ai_engine['label']) . ': ' . lang('ready')
                : '<span class="badge text-bg-secondary me-1"><i class="bi bi-dash-lg" aria-hidden="true"></i></span>' . h($translation_ai_engine['label']) . ': ' . h($translation_ai_engine['reason']));
        }
    }
}

$output_translation_engines_card = '
                    <div class="col-12">
                        <div class="pg-f-lg">
                            <label for="translation_google_key" class="form-label">' . lang('Google Cloud Translation API key') . ' ' . $output_translation_google_state . '</label>
                            <input type="text" name="translation_google_key" id="translation_google_key" class="form-control font-monospace" value="" autocomplete="off" spellcheck="false" placeholder="' . ($translation_google_key_stored ? h(lang('Leave blank to keep the stored key')) : '') . '"/>
                            <div class="form-text">' . lang('Your own key from the Google Cloud console, with the Cloud Translation API enabled on the project. It is stored encrypted and never shown again; leave the field blank to keep it.') . '</div>
                        </div>
                    </div>' . ($translation_google_key_stored ? '
                    <div class="col-12">
                        <div class="form-check">
                            <input value="1" class="form-check-input" type="checkbox" id="translation_google_key_clear" name="translation_google_key_clear"/>
                            <label class="form-check-label" for="translation_google_key_clear">' . lang('Remove the stored key') . '</label>
                        </div>
                    </div>' : '') . '
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input value="1"' . (($translation_attribution === 1) ? ' checked="checked"' : '') . ' class="form-check-input" type="checkbox" id="translation_attribution" name="translation_attribution"/>
                            <label class="form-check-label" for="translation_attribution">' . lang('Show the "powered by Google Translate" attribution on pages with unreviewed Google output') . '</label>
                            <div class="form-text">' . lang('The Google Cloud terms ask for the attribution, a machine-translation mark on the page and a disclaimer wherever unmodified Google output is published. A text that has been reviewed and corrected no longer counts as unmodified.') . '</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="pg-f-lg">
                            <label for="translation_style_note" class="form-label">' . lang('Style note for the AI engines') . '</label>
                            <textarea name="translation_style_note" id="translation_style_note" class="form-control" rows="3" maxlength="2000">' . h($translation_style_note) . '</textarea>
                            <div class="form-text">' . lang('Given to the AI engines with every text: the tone, the form of address, the terms to keep. Google and the browser do not read it.') . '</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-text">
                            <i class="bi bi-browser-chrome me-1"></i>' . lang('The browser engine translates on your own computer with the Chrome Translator API (desktop Chrome 138 or Edge 148 and newer); it runs from the Translations screen and needs no key.') . '
                            ' . pg_tr_browser_pages_html() . '
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-text">
                            <i class="bi bi-stars me-1"></i>' . lang('Pinegrap AI and Claude use the connections of the Workspace module: Pinegrap AI the site\'s model connection, Claude the routine set up in the Workspace Settings. Both read the style note and the glossary of the Translations screen.') . $translation_ai_state . '
                        </div>
                    </div>';

$pg_settings_cards[] = '
    <div id="pgset-translation-engines" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Translation Engines') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">' . ($translation_settings_ready ? $output_translation_engines_card : $output_translation_not_ready) . '
                </div>
            </div>
        </div>
    </div>';
