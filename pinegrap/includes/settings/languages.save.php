<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Languages and Translation screen writes: the source
 * language, the target languages and their settings, the Google key, the
 * attribution switch and the style note.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten. A language is added only when nothing of the site is
 * named like it: the save is refused and the names listed otherwise.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// The columns arrive together with the 2026.4.6 upgrade; on a site that has
// not run it the cards show a note and there is nothing to write.
if (waf_table_has_column('config', 'translation_prefixes')) {

    pg_tr_load();

    $translation_known = pg_tr_known_languages();
    $translation_existing = pg_tr_languages(false, true);
    // Every engine, whether or not the Workspace is on: a language set to
    // Pinegrap AI keeps that setting while the module is off for a while.
    $translation_engine_options = pg_tr_engine_options(true);
    $translation_now = time();

    // ── the source language ──
    $translation_source = trim((string) post_value('translation_source_language'));

    if (!isset($translation_known[$translation_source])) {
        $translation_source = '';
    }

    // ── the language to add, checked before anything is written ──
    $translation_add = trim((string) post_value('translation_add_language'));

    if ($translation_add !== '') {

        $translation_effective_source = ($translation_source !== '') ? $translation_source : pg_tr_source_language();

        if (!isset($translation_known[$translation_add]) || isset($translation_existing[$translation_add]) || ($translation_add === $translation_effective_source)) {
            $liveform->mark_error('translation_add_language', lang('The language cannot be added: it is the source language or is already in the list.'));
        } else {
            $translation_conflicts = pg_tr_name_conflicts(array($translation_add, mb_strtolower($translation_add)));

            if ($translation_conflicts) {
                $translation_conflict_names = array();

                foreach ($translation_conflicts as $translation_conflict) {
                    $translation_conflict_names[] = $translation_conflict[1] . ' (' . $translation_conflict[0] . ')';
                }

                $liveform->mark_error('translation_add_language', lang(array(
                    'string' => 'The language "{var:1}" was not added: a page, file or short link carries a name the language directory would shadow. Rename these first: {var:2}',
                    'vars'   => array($translation_add, implode(', ', $translation_conflict_names)))));
            }
        }
    }

    if ($liveform->check_form_errors()) {
        // Nothing is written: the operator sees the errors and the form as it was.
        return;
    }

    // ── config ──
    $translation_sets = array(
        "translation_source_language = '" . e($translation_source) . "'",
        "translation_style_note = '" . e(mb_substr(trim((string) post_value('translation_style_note')), 0, 2000)) . "'",
        "translation_attribution = '" . (post_value('translation_attribution') ? 1 : 0) . "'",
        "last_modified_user_id = '" . USER_ID . "'",
        "last_modified_timestamp = UNIX_TIMESTAMP()",
    );

    db("UPDATE config SET " . implode(', ', $translation_sets));

    // The key: blank keeps what is stored, the checkbox removes it.
    if (post_value('translation_google_key_clear')) {
        pg_tr_google_key_store('');
    } elseif (trim((string) post_value('translation_google_key')) !== '') {
        pg_tr_google_key_store(trim((string) post_value('translation_google_key')));
    }

    // ── the target languages ──
    $translation_posted = post_value('languages');
    $translation_posted = is_array($translation_posted) ? $translation_posted : array();
    $translation_prefixes_seen = array();

    foreach ($translation_existing as $translation_code => $translation_row) {

        if (!isset($translation_posted[$translation_code]) || !is_array($translation_posted[$translation_code])) {
            // The source language's row is not drawn; a row that is not on the
            // form is left as it is.
            continue;
        }

        $translation_values = $translation_posted[$translation_code];

        if (!empty($translation_values['remove'])) {
            db("DELETE FROM site_languages WHERE code = '" . e($translation_code) . "'");
            db("DELETE FROM page_translations WHERE language = '" . e($translation_code) . "'");
            continue;
        }

        $translation_prefix = mb_strtolower(trim((string) ($translation_values['prefix'] ?? '')));

        if (($translation_prefix === '') || !preg_match('/^[a-z]{2,8}(-[a-z]{2,4})?$/', $translation_prefix) || isset($translation_prefixes_seen[$translation_prefix])) {
            $translation_prefix = $translation_code;
        }

        $translation_prefixes_seen[$translation_prefix] = true;

        $translation_engine = (string) ($translation_values['engine'] ?? 'manual');

        if (!isset($translation_engine_options[$translation_engine])) {
            $translation_engine = 'manual';
        }

        $translation_fallback = (string) ($translation_values['fallback_engine'] ?? '');

        if (($translation_fallback !== '') && (!isset($translation_engine_options[$translation_fallback]) || ($translation_fallback === 'manual') || ($translation_fallback === $translation_engine))) {
            $translation_fallback = '';
        }

        $translation_policy = (string) ($translation_values['index_policy'] ?? 'reviewed');

        if (!in_array($translation_policy, array('reviewed', 'all', 'none'), true)) {
            $translation_policy = 'reviewed';
        }

        $translation_og = trim((string) ($translation_values['og_locale'] ?? ''));

        if (($translation_og !== '') && !preg_match('/^[a-z]{2,3}_[A-Z]{2,4}$/', $translation_og)) {
            $translation_og = '';
        }

        db("UPDATE site_languages SET
            label = '" . e(mb_substr(trim((string) ($translation_values['label'] ?? '')), 0, 100)) . "',
            enabled = '" . (!empty($translation_values['enabled']) ? 1 : 0) . "',
            prefix = '" . e($translation_prefix) . "',
            engine = '" . e($translation_engine) . "',
            fallback_engine = '" . e($translation_fallback) . "',
            index_policy = '" . e($translation_policy) . "',
            locale_number = '" . e(mb_substr(trim((string) ($translation_values['locale_number'] ?? '')), 0, 16)) . "',
            locale_date = '" . e(mb_substr(trim((string) ($translation_values['locale_date'] ?? '')), 0, 32)) . "',
            og_locale = '" . e($translation_og) . "',"
            . (array_key_exists('auto_update', $translation_existing[$translation_code]) ? "
            auto_update = '" . (!empty($translation_values['auto_update']) ? 1 : 0) . "'," : '') . "
            updated_at = '$translation_now'
            WHERE code = '" . e($translation_code) . "'");
    }

    // ── the new language, with the defaults of the known list ──
    if ($translation_add !== '') {
        $translation_info = $translation_known[$translation_add];
        // An engine that works without anything else to set up: Google when
        // a key is stored, Claude when its routine is connected, otherwise
        // the browser - "by hand" left nothing to press for a new language.
        $translation_claude = pg_tr_engine('claude');
        $translation_default_engine = (pg_tr_google_key() !== '') ? 'google' : (($translation_claude && $translation_claude['ready']) ? 'claude' : 'chrome');
        $translation_order = (int) db_value("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM site_languages");

        db("INSERT INTO site_languages (code, label, enabled, prefix, engine, fallback_engine, index_policy, locale_number, locale_date, og_locale, sort_order, created_at, updated_at)
            VALUES ('" . e($translation_add) . "', '" . e($translation_info[1]) . "', 1, '" . e(mb_strtolower($translation_add)) . "', '" . e($translation_default_engine) . "', '', 'reviewed',
                    '" . e($translation_info[3]) . "', '" . e($translation_info[4]) . "', '" . e($translation_info[2]) . "', '$translation_order', '$translation_now', '$translation_now')");
    }

    // The router reads one column; it is rewritten from the rows as they are
    // now. The drawn bodies are derived data and are dropped, because the
    // source language or a prefix may have changed.
    $translation_panel_language = (string) db_value("SELECT software_language FROM config");
    pg_tr_prefixes_rewrite(($translation_source !== '') ? $translation_source : (($translation_panel_language !== '') ? $translation_panel_language : 'en'));
    pg_tr_invalidate_pages();
}
