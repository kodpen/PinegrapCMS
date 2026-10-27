<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The remaining system widgets: form list and item view, my account, login, registration, search results, order view, membership, custom form, calendar view.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A month's name in the site's language, for the 'd M Y' long-date format.
 *
 * Keeps the month out of date()'s own locale: date('F') is always English, so
 * it is used as the lang() key and translated from there. Replaces the four
 * hard-coded Turkish month arrays this file used to carry.
 *
 * @param  int    $month  1-12
 * @return string         '' when the number is out of range
 */
function pg_widget_month_name($month)
{
    $month = (int)$month;
    if ($month < 1 || $month > 12) return '';
    return lang(date('F', mktime(0, 0, 0, $month, 1, 2000)));
}

/**
 * A system widget's kind (system_region_config.regionType) => its name, for
 * panel screens outside the editor.
 *
 * Mirror of SW_TYPES in assets/js/style_designer.js: same kinds, same English
 * keys. Keep the two in step when a kind is added.
 */
function pg_sw_type_labels()
{
    return array(
        'form_list_view'      => lang('Form List View'),
        'form_item_view'      => lang('Form Item View'),
        'catalog_listing'     => lang('Catalog'),
        'catalog_item_view'   => lang('Catalog Detail'),
        'my_account'          => lang('My Account'),
        'login_form'          => lang('Login'),
        'login_region'        => lang('Login Region'),
        'cart_link'           => lang('Cart Link'),
        'forgot_password'     => lang('Forgot Password'),
        'search_results'      => lang('Search Results'),
        'registration'        => lang('Registration Entrance'),
        'shopping_cart'       => lang('Shopping Cart'),
        'express_order'       => lang('Express Order'),
        'order_view'          => lang('View Order'),
        'membership'          => lang('Membership Entrance'),
        'custom_form'         => lang('Custom Form'),
        'calendar_view'       => lang('Calendar View'),
        'calendar_event_view' => lang('Calendar Event'),
        'account_profile'     => lang('Account Profile'),
        'change_password'     => lang('Change Password'),
        'set_password'        => lang('Set Password'),
        'email_preferences'   => lang('Email Preferences'),
        'address_book'        => lang('Address Book'),
        'account_security'    => lang('Account Security'),
        'account_invoices'    => lang('My Invoices'),
        'wishlist'            => lang('Favourites'),
        'account_email'       => lang('Account E-mail'),
        'logout'              => lang('Logout'),
        'error_page'          => lang('Error Page (404)'),
        'language_switcher'   => lang('Language Switcher'),
        'comments'            => lang('Comments'),
        'comments_recent'     => lang('Recent Comments'),
        'shipping_address_and_arrival' => lang('Shipping Address and Arrival'),
        'shipping_method'     => lang('Shipping Method'),
        'billing_information' => lang('Billing Information'),
        'order_preview'       => lang('Order Preview'),
        'menu_region'         => lang('Menu'),
        'menu_sequence_region' => lang('Menu Sequence'),
        'ad_region'           => lang('Ad Region'),
        'tag_cloud_region'    => lang('Tag Cloud'),
    );
}

/**
 * The kind of a system widget, as the panel, the palette, a template's
 * starter and the census name it: its regionType, except a comments widget
 * in the recent mode ('mode' => 'recent'), which is 'comments_recent'. The
 * row still stores regionType 'comments', and _expand_system_widgets()
 * dispatches on that. '' for a config without a kind. Pure.
 *
 * The editor's twin is _swKindOf() in assets/js/style_designer.js.
 *
 * @param mixed $cfg system_region_config, decoded
 * @return string
 */
function pg_sw_widget_kind($cfg)
{
    if (!is_array($cfg) || !isset($cfg['regionType']) || !is_string($cfg['regionType'])) {
        return '';
    }

    if (($cfg['regionType'] === 'comments') && isset($cfg['mode']) && ($cfg['mode'] === 'recent')) {
        return 'comments_recent';
    }

    return $cfg['regionType'];
}

// ============================================================================
// Standard (system) fields of a submitted form
//
// The classic form list and form item view screens resolve ^^token^^ against
// get_standard_fields_for_view() — reference_code, submitted_date_and_time,
// newest_comment and the rest. The system widgets resolve the same registry,
// so an identifier means the same thing on both sides and a site builder can
// carry what they already know into the designer.
//
// Four entries have no row in that registry: the link to the detail page (the
// classic screens carry it as their own ^^form_item_view^^ token) and the two
// ids, which have an empty `sql` because the renderer supplies their value,
// and submitter_name, the author's name a public page may print (see
// pg_sw_submitter_name_sql()).
//
// `join` names the query joins a field's SQL depends on. A template that never
// mentions the field keeps them out of the query, so a listing that prints
// nothing but its own fields stays a single-table read.
// ============================================================================
function pg_sw_standard_fields()
{
    static $fields = null;
    if ($fields !== null) return $fields;

    $joins = array(
        'submitter'                     => array('submitter'),
        'last_modifier'                 => array('last_modifier'),
        'number_of_views'               => array('submitted_form_info'),
        'number_of_comments'            => array('submitted_form_info'),
        'comment_attachments'           => array('submitted_form_info'),
        'newest_comment_name'           => array('submitted_form_info', 'newest_comment', 'newest_comment_submitter'),
        'newest_comment'                => array('submitted_form_info', 'newest_comment'),
        'newest_comment_date_and_time'  => array('submitted_form_info', 'newest_comment'),
        'newest_comment_id'             => array('submitted_form_info', 'newest_comment'),
        'newest_activity_date_and_time' => array('submitted_form_info', 'newest_comment'),
    );

    $fields = array();
    foreach (get_standard_fields_for_view() as $standard_field) {
        $name = (string)$standard_field['value'];
        $fields[$name] = array(
            'label' => (string)$standard_field['name'],
            'sql'   => (string)$standard_field['sql_name'],
            'type'  => (string)$standard_field['type'],
            'join'  => isset($joins[$name]) ? $joins[$name] : array(),
        );
    }

    $fields['form_item_view'] = array(
        'label' => lang('Form Item View URL'),
        'sql'   => '',
        'type'  => '',
        'join'  => array(),
    );
    $fields['submitted_form_id'] = array(
        'label' => lang('Submitted Form ID'),
        'sql'   => '',
        'type'  => '',
        'join'  => array(),
    );
    $fields['submitter_user_id'] = array(
        'label' => lang('Submitter User ID'),
        'sql'   => '',
        'type'  => '',
        'join'  => array(),
    );
    // `submitter` is the login username: right for staff screens and the
    // classic pages, wrong for a public byline, where it publishes half of a
    // credential (a member's username defaults to the local part of their
    // e-mail address). This one is the contact's name, the same rule as the
    // BlogPosting author, pg_contact_display_name() (includes/fn/contacts.php).
    $fields['submitter_name'] = array(
        'label' => lang('Author Name'),
        'sql'   => pg_sw_submitter_name_sql('submitter_contact'),
        'type'  => '',
        'join'  => array('submitter_contact'),
    );

    return $fields;
}

// The SQL for a submission's author name, read from the contact joined as
// $alias (forms.contact_id): the first and last name, else the nickname, ''
// when the record has no contact or the contact no name. The login username
// is never read. Mirrors pg_contact_display_name() (includes/fn/contacts.php):
// CONCAT_WS skips the NULL halves, so a name with one part has no stray space.
function pg_sw_submitter_name_sql($alias)
{
    $a = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$alias));
    return "IFNULL(NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM($a.first_name), ''), NULLIF(TRIM($a.last_name), ''))), ''),"
         . " IFNULL(TRIM($a.nickname), ''))";
}

// Whether a form's own field named $name overrides the standard field of that
// name. The rule is the other way round for the classic registry's names (a
// standard field beats a same-named form field, as on the classic screens).
// submitter_name is widget-only and newer than forms that may already have a
// field called that, so their field keeps printing what it printed before.
// style_designer.js keeps the same list in _SW_FORM_FIELD_WINS.
function pg_sw_form_field_wins($name)
{
    return in_array(mb_strtolower((string)$name, 'UTF-8'), array('submitter_name'), true);
}

// The identifiers a form's own fields answer to in a widget template, as a
// lookup set: the name, the name without hyphens, the label and the label's
// slug, the keys pg_sw_index_form_data() files a record's values under.
// $rows are form_fields rows carrying `name` and `label`.
function pg_sw_form_field_identifiers($rows)
{
    $ids = array();
    foreach ((array)$rows as $row) {
        $keys  = array();
        $name  = isset($row['name']) ? trim((string)$row['name']) : '';
        $label = isset($row['label']) ? trim((string)$row['label']) : '';
        if ($name !== '') {
            $keys[] = $name;
            $keys[] = preg_replace('/[^a-z0-9_]/i', '', $name);
        }
        if ($label !== '') {
            $keys[] = $label;
            $keys[] = pg_sw_slugify($label);
        }
        foreach ($keys as $key) {
            $key = mb_strtolower(trim((string)$key), 'UTF-8');
            if ($key !== '') $ids[$key] = true;
        }
    }
    return $ids;
}

// The standard names in $tokens (name => true) that yield to a field of the
// form, given that form's identifiers (pg_sw_form_field_identifiers()). The
// form decides, not the record: a record with no value for that field prints
// nothing, as it did before the standard name existed, and never the standard
// value in its place. The filter and the editor decide the same way.
function pg_sw_yielded_standard_names($tokens, $identifiers)
{
    $names = array();
    foreach ((array)$tokens as $name => $unused) {
        $name = mb_strtolower((string)$name, 'UTF-8');
        if (pg_sw_form_field_wins($name) && isset($identifiers[$name])) $names[] = $name;
    }
    return array_values(array_unique($names));
}

// pg_sw_yielded_standard_names() for form $custom_form_page_id. The form's
// fields are read only when $tokens names a standard field that may yield.
function pg_sw_form_yields($tokens, $custom_form_page_id)
{
    $may_yield = false;
    foreach ((array)$tokens as $name => $unused) {
        if (pg_sw_form_field_wins($name)) {
            $may_yield = true;
            break;
        }
    }
    if (!$may_yield) return array();
    $rows = db_items(
        "SELECT name, label
         FROM form_fields
         WHERE page_id = '" . (int)$custom_form_page_id . "'"
    );
    return pg_sw_yielded_standard_names($tokens, pg_sw_form_field_identifiers($rows));
}

// Every identifier a widget template names, as a lookup set. The optional
// %%…%% format suffix is not part of the identifier, so it stays out of the key.
function pg_sw_template_tokens($template)
{
    $tokens = array();
    if (!is_string($template) || $template === '') return $tokens;
    if (preg_match_all('/\^\^(.*?)\^\^/', $template, $matches)) {
        foreach ($matches[1] as $name) {
            $name = mb_strtolower(trim($name), 'UTF-8');
            if ($name !== '') $tokens[$name] = true;
        }
    }
    return $tokens;
}

// SELECT columns and JOINs for the standard fields a template actually names.
//
// $skip lists identifiers the caller already selects for itself (the listing
// query carries reference_code), so no column is aliased twice.
//
// submitted_form_info rows are kept per form item view page, so the view and
// comment counters are the ones recorded against the widget's configured detail
// page. With no detail page configured there is no row to join and the counters
// come back empty, which is the truth: nothing has been read through a page
// that does not exist.
function pg_sw_standard_sql($tokens, $detail_page_id, $skip = array())
{
    $fields = pg_sw_standard_fields();
    $select = array();
    $needed = array();

    foreach ($fields as $name => $field) {
        if ($field['sql'] === '')            continue;
        if (!isset($tokens[$name]))          continue;
        if (in_array($name, $skip, true))    continue;
        $select[] = $field['sql'] . " AS `" . $name . "`";
        foreach ($field['join'] as $join_name) $needed[$join_name] = true;
    }

    // The badge columns ride along with the three fields that print a person's
    // name. They are not values of their own: the renderer appends the badge
    // after the name has been prepared.
    if (isset($tokens['submitter'])) {
        $select[] = "submitter.user_badge AS submitter_badge";
        $select[] = "submitter.user_badge_label AS submitter_badge_label";
        $needed['submitter'] = true;
    }
    if (isset($tokens['last_modifier'])) {
        $select[] = "last_modifier.user_badge AS last_modifier_badge";
        $select[] = "last_modifier.user_badge_label AS last_modifier_badge_label";
        $needed['last_modifier'] = true;
    }
    if (isset($tokens['newest_comment_name'])) {
        $select[] = "newest_comment_submitter.user_badge AS newest_comment_submitter_badge";
        $select[] = "newest_comment_submitter.user_badge_label AS newest_comment_submitter_badge_label";
        // A commenter who left no name is shown as anonymous, but only when
        // there is a comment at all — an empty name and no comment are two
        // different things and the id is what tells them apart.
        if (!isset($tokens['newest_comment_id'])) {
            $select[] = "newest_comment.id AS `newest_comment_id`";
        }
    }

    $join = '';
    if (isset($needed['submitter'])) {
        $join .= " LEFT JOIN user AS submitter ON forms.user_id = submitter.user_id";
    }
    if (isset($needed['last_modifier'])) {
        $join .= " LEFT JOIN user AS last_modifier ON forms.last_modified_user_id = last_modifier.user_id";
    }
    if (isset($needed['submitter_contact'])) {
        $join .= " LEFT JOIN contacts AS submitter_contact ON (forms.contact_id = submitter_contact.id) AND (forms.contact_id > 0)";
    }
    if (isset($needed['submitted_form_info'])) {
        $join .= " LEFT JOIN submitted_form_info"
               . " ON ((forms.id = submitted_form_info.submitted_form_id)"
               . " AND (submitted_form_info.page_id = '" . (int)$detail_page_id . "'))";
    }
    if (isset($needed['newest_comment'])) {
        $join .= " LEFT JOIN comments AS newest_comment ON submitted_form_info.newest_comment_id = newest_comment.id";
    }
    if (isset($needed['newest_comment_submitter'])) {
        $join .= " LEFT JOIN user AS newest_comment_submitter ON newest_comment.created_user_id = newest_comment_submitter.user_id";
    }

    return array(
        'select' => $select ? ",\n" . implode(",\n", $select) : '',
        'join'   => $join,
    );
}

// The badge the classic form list view appends after a person's name.
// Returns '' when the user carries no badge or the site has no label for one.
function _pg_sw_badge_html($row, $badge_column, $label_column)
{
    $on = isset($row[$badge_column]) ? $row[$badge_column] : 0;
    if ($on != 1) return '';

    $label = isset($row[$label_column]) ? (string)$row[$label_column] : '';
    if ($label === '') $label = defined('BADGE_LABEL') ? BADGE_LABEL : '';
    if ($label === '') return '';

    return ' <span class="software_badge ' . h(get_class_name($label)) . '">' . h($label) . '</span>';
}

// Replace every ^^token^^ — with its optional %%format%% — in one template.
//
// Resolution order follows the classic screens: a standard field wins over a
// form field of the same name. Values go through prepare_form_data_for_output()
// so a date obeys the format the designer wrote, a signature prints as the
// drawing, and everything else is escaped the way the classic screens escape it.
// A format that is a text filter chain (%%upper%%, %%number:2%%;
// widgets_fill.php) works on that output, a date in its default format
// included, and on an $extra value.
//
// Each token is replaced once per match rather than globally, because the same
// identifier may appear twice with two different formats and a global replace
// would overwrite the second with the first one's output.
//
// $row           forms row, carrying whatever pg_sw_standard_sql() selected
// $custom        identifier => array('data' => raw value, 'type' => …, 'wysiwyg' => …)
// $extra         identifier => finished string (ids, the detail URL, widget state)
// $extra_fields  identifier => array('type' => …) for a widget that carries a
//                typed value of its own, so it reads a format suffix the same
//                way a standard field does
function pg_sw_apply_tokens($template, $row, $custom = array(), $extra = array(), $extra_fields = array())
{
    if (!is_string($template) || $template === '') return $template;
    if (!preg_match_all('/\^\^(.*?)\^\^(%%(.*?)%%)?/', $template, $variables, PREG_SET_ORDER)) {
        return $template;
    }

    $standard = pg_sw_standard_fields();
    if (is_array($extra_fields) && $extra_fields) $standard = array_merge($standard, $extra_fields);
    if (!is_array($row))    $row    = array();
    if (!is_array($custom)) $custom = array();
    if (!is_array($extra))  $extra  = array();

    foreach ($variables as $variable) {
        $whole_string = $variable[0];
        $name         = mb_strtolower($variable[1], 'UTF-8');

        // A format typed into the rich-text editor may carry HTML entities, and
        // date() would read those as format characters. The suffix is a text
        // filter chain (pg_sw_fill_parse()) or, as before filters existed, a
        // date format.
        $mods        = isset($variable[3]) ? unhtmlspecialchars($variable[3]) : '';
        $filters     = pg_sw_fill_parse($mods);
        $date_format = ($filters === null) ? $mods : '';

        // Values the renderer already finished — ids, URLs, widget state.
        if (array_key_exists($name, $extra)) {
            $value = (string)$extra[$name];
            if ($filters !== null) $value = pg_sw_fill_value($value, $mods);
            $template = preg_replace(
                '/' . preg_quote($whole_string, '/') . '/',
                addcslashes($value, '\\$'),
                $template,
                1
            );
            continue;
        }

        $is_standard      = false;
        $prepare_for_html = true;
        $type             = '';
        $data             = '';

        // A form's own field wins over the few standard names that yield to
        // it (pg_sw_form_field_wins()); every other standard name beats it.
        $form_wins = isset($custom[$name]) && pg_sw_form_field_wins($name);

        if (!$form_wins && isset($standard[$name]) && array_key_exists($name, $row)) {
            $is_standard = true;
            $type        = $standard[$name]['type'];
            $data        = (string)$row[$name];
        } elseif (isset($custom[$name])) {
            $type = (string)$custom[$name]['type'];
            $data = (string)$custom[$name]['data'];
            // Markup from a WYSIWYG area was typed by the submitter, so it is
            // filtered rather than escaped. It is printed raw, so PHP open
            // tags are defused before the page's PHP_REGIONS eval.
            if (!empty($custom[$name]['wysiwyg'])) {
                $prepare_for_html = false;
                $data = pg_defuse_php_tags(pg_sanitize_rich_text($data));
            }
        } else {
            // Not a field of this form. Left in place here and swept by the
            // caller, so one unknown identifier never eats the markup after it.
            continue;
        }

        if ($is_standard) {
            // Two standard fields are rewritten before they are escaped.
            // Trimming or splitting the escaped string would slice an entity in
            // half and print "&am".
            if ($name === 'newest_comment' && mb_strlen($data) > 100) {
                $data = mb_substr($data, 0, 100) . '...';
            }
            if ($name === 'comment_attachments') {
                $links = array();
                foreach (array_filter(explode('||', $data)) as $attachment) {
                    $links[] = '<a href="' . h((defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($attachment))
                             . '" target="_blank">' . h($attachment) . '</a>';
                }
                $template = preg_replace(
                    '/' . preg_quote($whole_string, '/') . '/',
                    addcslashes(implode(', ', $links), '\\$'),
                    $template,
                    1
                );
                continue;
            }
        }

        // No format written after the token: the site's reading order in a
        // display shape ("13.09.2026 14:10"), not the classic screens' input
        // shape ("13/9/2026 2:10 PM").
        if ($date_format === '' && $data !== '') {
            $date_format = pg_sw_default_date_format($type);
        }

        $data = prepare_form_data_for_output($data, $type, $prepare_for_html, $date_format);

        if ($is_standard) {
            switch ($name) {
                // A counter reads as a number, not as an empty cell.
                case 'number_of_views':
                case 'number_of_comments':
                    $data = ($data === '') ? '0' : pg_format_number((float)$data, 0);
                    break;

                case 'newest_comment_name':
                    if ($data === '' && !empty($row['newest_comment_id'])) {
                        $data = h(lang('Anonymous'));
                    }
                    $data .= _pg_sw_badge_html($row, 'newest_comment_submitter_badge', 'newest_comment_submitter_badge_label');
                    break;

                case 'submitter':
                    $data .= _pg_sw_badge_html($row, 'submitter_badge', 'submitter_badge_label');
                    break;

                case 'last_modifier':
                    $data .= _pg_sw_badge_html($row, 'last_modifier_badge', 'last_modifier_badge_label');
                    break;
            }
        }

        // A text filter works on what the field shows: a date in its format,
        // a counter as a number. A value with markup (rich text, a badge, a
        // relative date) comes back as it is.
        if ($filters !== null) $data = pg_sw_fill_value($data, $mods);

        $template = preg_replace(
            '/' . preg_quote($whole_string, '/') . '/',
            addcslashes($data, '\\$'),
            $template,
            1
        );
    }

    return $template;
}

// Make one loop row's element ids its own.
//
// A loop template is the same markup repeated, so every row carries the same
// id — and an accordion, tab or collapse in row two then targets row one.
// Suffixing with the widget and the record keeps two instances of the same
// widget on one page apart as well.
//
// Every attribute that points at an id moves with it, or the pairing breaks
// rather than being preserved: `for` loses its control, `data-bs-parent`
// leaves an accordion closing a panel in a different row, and `aria-controls`
// leaves a screen reader describing the wrong one. The renderers used to
// disagree about which of these they rewrote — that is the bug this exists to
// end, not the suffix itself.
// The format a date takes when the design gives none. The software writes
// dates as the classic screens do, in the order the site reads them
// (DATE_FORMAT); a language file changes the shape by translating these
// PHP date() formats (tr.json: "j/n/Y g:i A" → "d.m.Y H:i"). '' for anything
// that is not a date.
function pg_sw_default_date_format($type)
{
    // A page served in another language writes dates that language's way.
    $locale = function_exists('pg_tr_locale') ? pg_tr_locale('date') : '';
    if ($locale !== '') {
        if ($type === 'date')          return $locale;
        if ($type === 'date and time') return $locale . ' H:i';
    }
    $us = (defined('DATE_FORMAT') && DATE_FORMAT == 'month_day');
    if ($type === 'date')          return $us ? lang('n/j/Y') : lang('j/n/Y');
    if ($type === 'date and time') return $us ? lang('n/j/Y g:i A') : lang('j/n/Y g:i A');
    return '';
}

// An element marked pg-hide-if-empty is dropped when it came out empty, so
// a record that left a field blank shows no bare label and no empty box. A
// "label: value" row (the form starters mark each field row so) is judged by
// its pg-field-value element; an element without one by everything inside
// it. Text counts as content, and so do a picture with an address, a video,
// a frame, a link and a form control. Marked elements inside marked elements
// go first, so a box whose rows all left goes with them. Any element can
// carry the class; the designer keeps one by taking the class off.
// With $mark (the finish pass's cut point, widgets_finish.php) only the
// elements that hold $mark are judged, and one that goes leaves $mark in
// its place, so the element around it is judged in turn.
// With $wait (a product page whose variant picker redraws the values bound
// to data-pg-bind), an empty element holding such a value stays, marked
// hidden and data-pg-empty-wait, for the picker to show when it fills it;
// an element already so marked is left as it is.
function pg_sw_hide_empty_fields($html, $mark = null, $wait = false)
{
    if (!is_string($html) || strpos($html, 'pg-hide-if-empty') === false) return $html;
    $mark = ($mark === null) ? '' : (string)$mark;
    if ($mark !== '' && strpos($html, $mark) === false) return $html;
    if (!preg_match_all('#<([a-z][a-z0-9]*)\b[^>]*\bclass="[^"]*\bpg-hide-if-empty\b[^"]*"[^>]*>#i', $html, $opens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    // Last first: an element inside another one is met before it, and
    // cutting it leaves the offsets of the elements before it as they were.
    for ($i = count($opens) - 1; $i >= 0; $i--) {
        $open  = $opens[$i][0][0];
        $start = $opens[$i][0][1];
        $tag   = strtolower($opens[$i][1][0]);

        if (strpos($open, ' data-pg-empty-wait') !== false) continue;

        if (pg_sw_is_void_tag($tag)) {
            if ($mark !== '' || pg_sw_has_content($open)) continue;
            $html = ($wait && strpos($open, 'data-pg-bind=') !== false)
                ? substr($html, 0, $start) . pg_sw_empty_wait_tag($open) . substr($html, $start + strlen($open))
                : substr($html, 0, $start) . substr($html, $start + strlen($open));
            continue;
        }

        $close = pg_sw_closing_tag($html, $tag, $start + strlen($open));
        if ($close === null) continue;
        $inner = substr($html, $start + strlen($open), $close[0] - $start - strlen($open));
        if ($mark !== '' && strpos($inner, $mark) === false) continue;
        if (pg_sw_has_content(pg_sw_field_value($inner))) continue;
        if ($wait && strpos($open . $inner, 'data-pg-bind=') !== false) {
            $html = substr($html, 0, $start) . pg_sw_empty_wait_tag($open) . substr($html, $start + strlen($open));
            continue;
        }
        $html = substr($html, 0, $start) . $mark . substr($html, $close[1]);
    }

    return $html;
}

// An opening tag marked as an empty element waiting for a variant to fill
// it: hidden, and data-pg-empty-wait for the variant picker to find it.
function pg_sw_empty_wait_tag($open)
{
    $end = (substr($open, -2) === '/>') ? -2 : -1;
    return rtrim(substr($open, 0, $end)) . ' hidden data-pg-empty-wait' . substr($open, $end);
}

// The elements that have no closing tag.
function pg_sw_is_void_tag($tag)
{
    return in_array(strtolower((string)$tag), array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'), true);
}

// Where the element whose content starts at $from closes: array(offset of
// its closing tag, offset just past it), counting the same tag nested inside
// it. null when it never closes.
function pg_sw_closing_tag($html, $tag, $from)
{
    $depth = 1;
    $re    = '#<(/?)' . preg_quote($tag, '#') . '\b[^>]*>#i';
    while (preg_match($re, $html, $m, PREG_OFFSET_CAPTURE, $from)) {
        $at   = $m[0][1];
        $from = $at + strlen($m[0][0]);
        if ($m[1][0] === '/') {
            if (--$depth === 0) return array($at, $from);
        } elseif (substr($m[0][0], -2) !== '/>') {
            $depth++;
        }
    }
    return null;
}

// What a marked element's value is: the inside of its pg-field-value
// element (the element itself when it has no closing tag, a picture), or
// the whole of $inner when it has none.
function pg_sw_field_value($inner)
{
    if (!preg_match('#<([a-z][a-z0-9]*)\b[^>]*\bclass="[^"]*\bpg-field-value\b[^"]*"[^>]*>#i', $inner, $m, PREG_OFFSET_CAPTURE)) {
        return $inner;
    }
    if (pg_sw_is_void_tag($m[1][0])) return $m[0][0];
    $from  = $m[0][1] + strlen($m[0][0]);
    $close = pg_sw_closing_tag($inner, strtolower($m[1][0]), $from);
    return ($close === null) ? substr($inner, $from) : substr($inner, $from, $close[0] - $from);
}

// Does this markup show anything: words (spaces and &nbsp; are not), a
// picture with an address (a file field left empty comes out as
// <img src="">), a video, a frame, a drawing, a link or a form control?
// The value a condition reads (its carrier, widgets_finish.php) is not shown
// and does not count. A list inside a record (its span, widgets_lists.php)
// counts: its rows are drawn by the finish pass, which judges the element
// again when the list comes out empty.
function pg_sw_has_content($html)
{
    if (strpos((string)$html, '<!--pg-list:' . pg_sw_marker_nonce() . ':') !== false) return true;
    $html = pg_sw_finish_strip_carriers((string)$html);
    $text = str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (trim($text) !== '') return true;
    if (preg_match('#<(video|audio|iframe|object|embed|svg|canvas|a|input|select|textarea|button)\b#i', (string)$html)) return true;
    return (bool)preg_match('#<img\b[^>]*\bsrc="[^"]+"#i', (string)$html);
}

// Can the element a widget prints its records into hold nothing but its
// rows? A list holds list items and a table rows: a message, a search box
// or a pagination written into one is broken markup (a <div> in a <ul>).
function pg_sw_loop_parent_is_closed($tag)
{
    return in_array((string)$tag, array('ul', 'ol', 'menu', 'dl', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'select', 'datalist', 'optgroup'), true);
}

// How many cells a row of a table template has, so a message row can span
// them all. 1 when the template is no table row.
function pg_sw_row_cells($html)
{
    $html = (string)$html;
    if (preg_match('#<tr\b.*?</tr>#is', $html, $m)) $html = $m[0];
    return max(1, (int)preg_match_all('#<t[dh]\b#i', $html));
}

// A state message (no records, no record to show) printed where the records
// would be, as an element that may stand there: an item in a list, a row
// spanning a table, a column in a grid row, a line in text, a block anywhere
// else. $class names the state (pg-sw-empty, pg-sw-not-found) for the
// design's own stylesheet; the rest are Bootstrap 5.3 / 6 utilities.
function pg_sw_state_message_html($message, $parent_tag, $class, $cells = 1)
{
    $text  = h((string)$message);
    $class = h(trim($class));
    $look  = 'text-body-secondary py-3';
    switch ((string)$parent_tag) {
        case 'ul':
        case 'ol':
        case 'menu':
            return '<li class="' . $class . ' ' . $look . '">' . $text . '</li>';
        case 'table':
        case 'thead':
        case 'tbody':
        case 'tfoot':
            return '<tr class="' . $class . '"><td colspan="' . max(1, (int)$cells) . '" class="' . $look . '">' . $text . '</td></tr>';
        case 'tr':
            return '<td colspan="' . max(1, (int)$cells) . '" class="' . $class . ' ' . $look . '">' . $text . '</td>';
        case 'dl':
            return '<dd class="' . $class . ' ' . $look . '">' . $text . '</dd>';
        case 'select':
        case 'datalist':
        case 'optgroup':
            return '<option value="" disabled>' . $text . '</option>';
        case 'row':
            return '<div class="col-12 ' . $class . ' ' . $look . '">' . $text . '</div>';
        case 'p':
        case 'span':
        case 'a':
        case 'button':
        case 'label':
        case 'strong':
        case 'em':
        case 'small':
        case 'h1':
        case 'h2':
        case 'h3':
        case 'h4':
        case 'h5':
        case 'h6':
            return '<span class="' . $class . ' text-body-secondary">' . $text . '</span>';
        default:
            return '<div class="' . $class . ' ' . $look . '">' . $text . '</div>';
    }
}

function pg_sw_uniquify_row_ids($html, $widget_id, $row_id)
{
    if (!is_string($html) || $html === '') return $html;

    $suffix = '_pgw' . (int)$widget_id . 'r' . (int)$row_id;
    $moves  = array(
        '/\bid="([^"]+)"/'                => 'id="$1',
        '/\bfor="([^"]+)"/'               => 'for="$1',
        '/data-bs-target="#([^"]+)"/'     => 'data-bs-target="#$1',
        '/aria-controls="([^"]+)"/'       => 'aria-controls="$1',
        '/aria-labelledby="([^"]+)"/'     => 'aria-labelledby="$1',
        '/href="#([^"]+)"/'               => 'href="#$1',
        '/data-bs-parent="#([^"]+)"/'     => 'data-bs-parent="#$1',
    );
    foreach ($moves as $pattern => $replacement) {
        $html = preg_replace($pattern, $replacement . $suffix . '"', $html);
    }

    return $html;
}

// The token keys whose values are HTML on purpose: the rich-text columns an
// operator writes in the editor, and the blocks the renderer builds itself
// (filter chips, variant picker, the strike-through
// price). Everything else a token map carries is plain text, a number or a
// URL.
function pg_sw_raw_token_keys()
{
    static $keys = null;
    if ($keys !== null) return $keys;

    $keys = array_flip(array(
        // Operator rich text.
        '__description',
        '__details',
        '__out_of_stock_message',
        // Prices that expand to strike-through markup when an offer applies.
        '__price_formatted',
        '__price_block_html',
        '__price_range',
        // Renderer-built blocks.
        '__variant_picker',
        '__group_tree_html',
        '__group_tree_inner_html',
        '__attribute_filters_html',
        '__attribute_filters_inner_html',
        '__filters_button',
        '__filters_offcanvas',
        '__filters_offcanvas_inner_html',
        '__clear_filters_button',
        // Comments widget.
        '__comment_message_html',
        '__comment_seller_reply_html',
        // Order view: product form answers, information text and a gift
        // card's message with its line breaks (widgets_lists_order.php).
        '__field_value_html',
        '__field_info_html',
        '__gift_card_message_html',
        // Order view: a return's notes with their line breaks and the
        // customer's moves, forms built by returns.php.
        '__return_customer_note_html',
        '__return_decision_note_html',
        '__return_actions_html',
        // Cart: a payment schedule's frequency choices, the value selected.
        '__schedule_period_options_html',
        // Ad Region widget: the ad's own markup.
        '__ad_content_html',
        '__ad_caption_html',
    ));

    return $keys;
}

// Escape a token map's plain-text values once, before substitution.
//
// A token is substituted into the designer's markup wherever they bound it,
// and that is as often an attribute as a text node — a product named
// `24" Monitor` bound to an image's alt closes the attribute and everything
// after it is markup. So the map is escaped as a whole and the raw-HTML keys
// are named, rather than each renderer remembering which of its own values to
// wrap: the one that forgets is the one that breaks.
//
// $raw_extra names further keys this particular map fills with markup.
//
// A raw value is still not code: the widget is drawn into the page before
// get_page_content.php evaluates it (PHP_REGIONS), so a PHP open tag in a
// product's rich description written by a manager, a role-3 user with
// manage_ecommerce or a products:write API key would run on the server. Every
// raw value goes through pg_defuse_php_tags(); renderer-built blocks never
// carry `<?`, so they pass unchanged.
function pg_sw_escape_token_values($values, $raw_extra = array())
{
    if (!is_array($values)) return $values;

    $raw = pg_sw_raw_token_keys();
    foreach ($raw_extra as $key) {
        $raw[$key] = true;
    }

    foreach ($values as $key => $value) {
        if (isset($raw[$key])) {
            $values[$key] = pg_defuse_php_tags($value);
            continue;
        }
        $values[$key] = h((string)$value);
    }

    return $values;
}

// Drop the identifiers nothing answered, with their format suffix, so a raw
// ^^token^^ never reaches the browser. While a widget renders, the six row
// position tokens (^^__loop_index^^ and the rest) stay for the finish pass,
// which fills them per row and takes out any left (widgets_finish.php);
// outside a widget render they go like any other. Both read the markup as
// the same run of tokens, left to right: a kept token is matched whole, so
// its closing carets never pair with the next token's opening ones.
function pg_sw_sweep_tokens($html)
{
    if (!is_string($html) || $html === '') return $html;
    if (strpos($html, '^^__loop_') !== false && pg_sw_finish_active()) {
        return preg_replace('/(\^\^__loop_(?:index|count|is_first|is_last|is_odd|is_even)\^\^(?:%%.*?%%)?)|\^\^[^\^]*\^\^(?:%%.*?%%)?/', '$1', $html);
    }
    return preg_replace('/\^\^[^\^]*\^\^(%%.*?%%)?/', '', $html);
}

// The form's own fields, indexed by every identifier a designer might write for
// them: the field name, the denormalized copy on the data row, and the label in
// its written, lowercased and slugified spellings.
//
// Values stay raw here — pg_sw_apply_tokens() escapes them, and escaping twice
// is how "&" becomes "&amp;amp;". A field that holds several values (a
// multi-select, a check box group) has them joined the way the classic screens
// join them.
function pg_sw_index_form_data($data_rows)
{
    $index = array();
    if (!is_array($data_rows)) return $index;

    foreach ($data_rows as $data_row) {
        $form_id = isset($data_row['form_id']) ? (int)$data_row['form_id'] : 0;
        $type    = isset($data_row['field_type']) ? (string)$data_row['field_type'] : '';
        $value   = isset($data_row['data']) ? (string)$data_row['data'] : '';

        // A file upload keeps its file name in `files`, not in form_data.data,
        // and what a reader wants from it is an address they can open.
        if ($type === 'file upload' && !empty($data_row['file_name'])) {
            $value = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . $data_row['file_name'];
            $type  = '';
        }

        $keys = array();
        if (!empty($data_row['field_name'])) {
            $keys[] = (string)$data_row['field_name'];
            // A binding typed before hyphens were kept in tokens reads the
            // name without them ("publish-date" → "publishdate").
            $bare = preg_replace('/[^a-z0-9_]/i', '', (string)$data_row['field_name']);
            if ($bare !== '') $keys[] = $bare;
        }
        if (!empty($data_row['data_name']))   $keys[] = (string)$data_row['data_name'];
        if (!empty($data_row['field_label'])) {
            $label  = (string)$data_row['field_label'];
            $keys[] = $label;
            $slug   = pg_sw_slugify($label);
            if ($slug !== '') $keys[] = $slug;
        }

        // One row answers each of its identifiers once. The field name and
        // the data row's copy of it are usually the same word, and without
        // this the value was appended to itself ("Başlık, Başlık").
        $keys = array_unique(array_map(function ($k) { return mb_strtolower(trim($k), 'UTF-8'); }, $keys));
        foreach ($keys as $key) {
            if ($key === '') continue;
            if (isset($index[$form_id][$key]) && $index[$form_id][$key]['data'] !== '') {
                $index[$form_id][$key]['data'] .= ', ' . $value;
            } else {
                $index[$form_id][$key] = array(
                    'data'    => $value,
                    'type'    => $type,
                    'wysiwyg' => (!empty($data_row['field_wysiwyg']) && $type === 'text area') ? 1 : 0,
                    // Whether the field's answers are translated
                    // (form_fields.translate_values); read before the column
                    // existed, they are.
                    'translate' => (isset($data_row['field_translate']) && (string)$data_row['field_translate'] === '0') ? 0 : 1,
                );
            }
        }
    }

    return $index;
}

// "Açıklama Alanı" → "aciklama_alani". Turkish letters and the common accents
// are folded first so a label written in the site's own language still yields
// an identifier a designer can type.
function pg_sw_slugify($string)
{
    $string = trim((string)$string);
    if ($string === '') return '';
    $string = mb_strtolower($string, 'UTF-8');
    $string = strtr($string, array(
        'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ş' => 's', 'Ş' => 's',
        'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        'â' => 'a', 'î' => 'i', 'û' => 'u',
    ));
    $string = preg_replace('/[^a-z0-9_]+/', '_', $string);
    return trim($string, '_');
}

// The page this request resolved to, as a page.page_id.
//
// submitted_form_info keeps its counters per form item view page, so a widget
// that prints a view or comment count has to say whose counters it means — and
// for the detail widget that is the page it is rendering on. The router leaves
// the resolved slug in $_GET['page']; any segments after it address the record,
// not the page.
function _pg_sw_current_page_id()
{
    static $page_id = null;
    if ($page_id !== null) return $page_id;

    $page_id = 0;
    $slug = isset($_GET['page']) ? (string)$_GET['page'] : '';
    if ($slug !== '') {
        $first_slash = mb_strpos($slug, '/');
        if ($first_slash !== false) $slug = mb_substr($slug, 0, $first_slash);
        if ($slug !== '') {
            $page_id = (int)db_value(
                "SELECT page_id FROM page WHERE page_name = '" . e($slug) . "' LIMIT 1"
            );
        }
    }

    return $page_id;
}

// The record statistics a form_list_view can order by, beside the built-ins
// and the form's own fields: the detail page's counters and the record's
// newest activity. They win over a form field of the same name, as a
// standard field does in pg_sw_apply_tokens(). style_designer.js keeps the
// same list in _SW_FORM_ORDER_COUNTERS.
function pg_sw_form_order_counters()
{
    return array('number_of_views', 'number_of_comments', 'recent_views', 'newest_activity_date_and_time');
}

// The window of the recent_views order, in days: today and the days before
// it. 1 to 180, 7 when unset or not a positive number. The daily view rows
// are swept at six months, so a longer window would read rows that are gone.
function pg_sw_recent_views_days($value)
{
    if (!is_numeric($value)) return 7;
    $days = (int)$value;
    if ($days < 1) return 7;
    if ($days > 180) return 180;
    return $days;
}

// The ORDER BY term of a counter order (pg_sw_form_order_counters()), or null
// when $field is not one of them and '' when it is but the list names no
// detail page: the counters and the newest comment are kept per detail page,
// so without one there is nothing to order by and the list keeps its date
// order.
//
// Correlated scalar subqueries rather than joins: submitted_form_info has no
// unique key, and a joined duplicate row would print a record twice. MAX()
// picks one row; the daily rows are summed over the window, counted in
// MySQL's clock (CURDATE(), as the writer stamps them). Without the daily
// table, recent_views reads the all-time counter. Newest activity is the later
// of the record's submitted time and the time of the comment the detail
// page's row names as its newest. Every value that reaches the SQL is
// whitelisted or cast to int.
function pg_sw_counter_order_sql($field, $direction, $detail_page_id, $recent_days, $stats_ready)
{
    $field = (string)$field;
    if (!in_array($field, pg_sw_form_order_counters(), true)) return null;

    $page = is_scalar($detail_page_id) ? (int)$detail_page_id : 0;
    if ($page <= 0) return '';

    $dir = (strtoupper((string)$direction) === 'ASC') ? 'ASC' : 'DESC';

    // The same source as the ^^newest_activity_date_and_time^^ token and the
    // classic list's order: submitted_form_info.newest_comment_id. MAX()
    // because the row has no unique key. Compared as integers, with 0 (never
    // '') when there is no row or the comment is gone, so the submitted time
    // stands.
    if ($field === 'newest_activity_date_and_time') {
        return "GREATEST(forms.submitted_timestamp, IFNULL((SELECT MAX(pg_ord_c.created_timestamp)"
             . " FROM submitted_form_info pg_ord_i"
             . " INNER JOIN comments pg_ord_c ON pg_ord_c.id = pg_ord_i.newest_comment_id"
             . " WHERE pg_ord_i.submitted_form_id = forms.id AND pg_ord_i.page_id = '$page'), 0)) $dir";
    }

    if ($field === 'recent_views' && $stats_ready) {
        $back = pg_sw_recent_views_days($recent_days) - 1;
        return "IFNULL((SELECT SUM(pg_ord_s.views) FROM submitted_form_view_stats pg_ord_s"
             . " WHERE pg_ord_s.submitted_form_id = forms.id AND pg_ord_s.page_id = '$page'"
             . " AND pg_ord_s.view_date >= DATE_SUB(CURDATE(), INTERVAL $back DAY)), 0) $dir";
    }

    $column = ($field === 'number_of_comments') ? 'number_of_comments' : 'number_of_views';
    return "IFNULL((SELECT MAX(pg_ord_i.$column) FROM submitted_form_info pg_ord_i"
         . " WHERE pg_ord_i.submitted_form_id = forms.id AND pg_ord_i.page_id = '$page'), 0) $dir";
}

// The record a form_item_view widget showed on this request, for
// get_page.php to count once the page has been sent.
//   null  — read: array() or array('form_id' => …, 'page_id' => …)
//   false — clear
//   ints  — register, when nothing is registered yet and both are positive;
//           the first record wins, as in pg_track_content()
function pg_sw_record_view_seen($form_id = null, $page_id = null)
{
    static $seen = array();

    if ($form_id === false) {
        $seen = array();
        return $seen;
    }

    if ($form_id !== null) {
        $form_id = (int)$form_id;
        $page_id = (int)$page_id;
        if (!$seen && $form_id > 0 && $page_id > 0) {
            $seen = array('form_id' => $form_id, 'page_id' => $page_id);
        }
    }

    return $seen;
}

// Whether a registered record view counts: a record and a page, a GET
// (link checkers' HEAD and form posts are not reads) and a person, not an
// automated client (pg_request_is_automated()).
function pg_sw_should_count_record_view($seen, $method, $automated)
{
    if (!is_array($seen) || empty($seen['form_id']) || empty($seen['page_id'])) return false;
    if ((int)$seen['form_id'] <= 0 || (int)$seen['page_id'] <= 0) return false;
    if (strtoupper((string)$method) !== 'GET') return false;
    return !$automated;
}

// Query parameter names a list filter may not read its value from: the
// software reads them itself on public pages, several with side effects
// (t and a store a tracking or affiliate code in the session and a cookie,
// o a special-offer code, rss/pdf/email/icalendar switch the output,
// pg_lang the language, highlight marks text, send_to redirects), and the
// widgets read the rest (page_number, query, r, ...; the comments widget
// comments, comment, comments_page and reply_to, and add_comment.php's
// redirect adds comment= to every page). The names starting with utm_ or
// pg_ are refused in pg_sw_url_param_name(). The editor keeps a copy
// (_SW_URL_PARAM_RESERVED in style_designer.js); a test compares them.
// A designed form control's _cf.url_param obeys the same names.
function pg_sw_url_param_reserved()
{
    return array(
        'page', 'page_number', 'query', 'r', 'rss', 'pdf', 'icalendar', 'email',
        'edit', 'edit_theme', 'edit_submitted_form', 'theme_id', 'from', 'logged_out',
        'send_to', 'mode', 'name', 'local', 't', 'a', 'o', 'k', 'sig', 'secure_mode',
        'highlight', 'preview_auto_dialog', 'pg_lang', 'id', 'date', 'view', 'cat',
        'pid', 'pmin', 'pmax', 'sort', 'stock', 'q', 'search', 'calendar_id',
        'recurrence_number', 'folder_id', 'comments', 'comment', 'comments_page', 'reply_to',
        'order_id', 'oid', 'cancelled', 'token', 'password', 'add_watcher', 'connect_to_contact',
    );
}

// The query parameter a list filter reads its value from, as stored in the
// filter's dynamic_value_attribute: lower case, a letter first, then letters,
// digits and underscores, at most 32 characters, not reserved. '' when the
// name cannot be used; such a filter is left out.
function pg_sw_url_param_name($name)
{
    if (!is_string($name) && !is_int($name)) return '';
    $name = strtolower(trim((string)$name));
    if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $name)) return '';
    if (strpos($name, 'utm_') === 0 || strpos($name, 'pg_') === 0) return '';
    if (in_array($name, pg_sw_url_param_reserved(), true)) return '';
    return $name;
}

// The value of query parameter $name in $request, as a list filter compares
// it: a string only (?author[]=x is no value), trimmed, valid UTF-8 without
// control or format characters (C0 and C1 controls, zero-width characters,
// the byte order mark, bidi controls) or line and paragraph separators, at
// most 100 characters. '' when there is none, and the filter is then left
// out.
function pg_sw_url_param_value($request, $name)
{
    if ($name === '' || !is_array($request) || !isset($request[$name]) || !is_string($request[$name])) return '';
    $value = trim($request[$name]);
    if ($value === '' || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $value)) return '';
    return mb_substr($value, 0, 100, 'UTF-8');
}

// The query parameters a list's URL filters read: the visitor's address,
// except when the page is drawn as an e-mail body, inside another request
// (the SEO pass, the editor's preview) or by the search indexer, where no
// address belongs to the page and every URL filter is left out.
function pg_sw_filter_request()
{
    $ctx = pg_sw_render_context();
    if (!empty($ctx['email'])) return array();
    if (function_exists('pg_seo_rendering') && pg_seo_rendering()) return array();
    if (defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true) return array();
    return is_array($_GET) ? $_GET : array();
}

// The page title of a list page whose URL filters found records: the
// list's filter_title with {value} replaced by the value, text without
// {value} after the value, or the value alone. '' without a value.
function pg_sw_filter_title($pattern, $value)
{
    $value   = (string)$value;
    $pattern = trim((string)$pattern);
    if ($value === '') return '';
    if ($pattern === '') return $value;
    if (strpos($pattern, '{value}') !== false) return str_replace('{value}', $value, $pattern);
    return $value . ' ' . $pattern;
}

// A list's filter_title setting, at most 120 characters, in the language the
// page is served in (the setting is extracted for translation by its name).
function pg_sw_list_filter_title($cfg)
{
    $title = (is_array($cfg) && isset($cfg['filter_title']) && is_string($cfg['filter_title']))
        ? mb_substr(trim($cfg['filter_title']), 0, 120, 'UTF-8') : '';
    if ($title !== '' && function_exists('pg_tr_text')) $title = trim((string)pg_tr_text($title, 'text', 'seo'));
    return $title;
}

// The applied value as ^^__filter_value^^ prints it: escaped once, and its
// carets written as entities so text from the address cannot form another
// ^^token^^ that a later replacement or the sweep would act on.
function pg_sw_filter_value_html($value)
{
    return str_replace('^', '&#94;', h((string)$value));
}

// The query string (without "?") that names a URL-filtered list page: the
// applied parameters sorted by name, encoded as RFC 3986, then the page
// number under the list's page parameter (`page_param`, else page_number)
// when the list showed a later page and $with_page. '' when no parameter
// applies: a page without an address value keeps its bare canonical.
function pg_sw_url_filter_query($state, $with_page = true)
{
    $params = (is_array($state) && isset($state['params']) && is_array($state['params'])) ? $state['params'] : array();
    if (!$params) return '';
    ksort($params, SORT_STRING);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $page  = (is_array($state) && isset($state['page_number'])) ? (int)$state['page_number'] : 0;
    $name  = (is_array($state) && isset($state['page_param'])) ? pg_sw_list_page_param(array('page_param' => $state['page_param'])) : 'page_number';
    if ($with_page && $page > 1) $query .= '&' . $name . '=' . $page;
    return $query;
}

// The text ^^__filter_value^^, the page title and the feed's channel title
// give what a list's URL filters applied ($filter_sql, from
// pg_sw_form_list_filter_sql()): the values joined with ", ", only when
// every one was compared with "is equal to", $total_records were found and
// `inexact` is still off. Any text matches "is not equal to", "does not
// contain" or a size comparison ('!' sorts below every date), so such a
// value is never printed. "is equal to" alone proves nothing either: it
// compares under the column's collation, which ignores case, accents,
// zero-width characters and combining marks. The caller therefore sets
// `inexact` unless one of the records it found holds every value byte for
// byte (the `exact` condition). Only text stored in the data is printed,
// so a crafted link cannot put its own words on the page, its title or its
// social card. '' otherwise.
function pg_sw_url_filter_value($filter_sql, $total_records)
{
    if (!is_array($filter_sql) || empty($filter_sql['params']) || !is_array($filter_sql['params'])) return '';
    if (!empty($filter_sql['inexact']) || (int)$total_records <= 0) return '';
    return implode(', ', array_map('strval', $filter_sql['params']));
}

// The page number a list adds to the page's canonical: the later page it
// showed, when it applied an address value, is not being searched (a
// searched page 2 is not page 2 of the list) and showed the page the
// address asked for (a page past the last one is clamped to it, and that
// page is not this address). 0 otherwise.
function pg_sw_url_filter_page($filter_sql, $request, $current_page, $total_pages, $search_active, $page_param = 'page_number')
{
    if (!is_array($filter_sql) || empty($filter_sql['params']) || $search_active) return 0;
    $current_page = (int)$current_page;
    if ((int)$total_pages <= 1 || $current_page <= 1) return 0;
    if (!is_array($request) || !isset($request[$page_param]) || !is_scalar($request[$page_param])) return 0;
    return ((int)$request[$page_param] === $current_page) ? $current_page : 0;
}

// Whether a URL-filtered list page stays out of search engines: a value
// applied whose list found nothing, or one compared other than "is equal
// to" (any text then shows some records). The bare page is unaffected.
function pg_sw_url_filter_noindex($state)
{
    if (!is_array($state) || empty($state['params'])) return false;
    return !empty($state['empty']) || !empty($state['inexact']);
}

// What the page's lists took from its address, for the head the page gets
// after its widgets are drawn (canonical, title, robots, feed link).
// get_page_content() resets it (false) before its widget pass; a list adds
// an array: its applied parameters (the first value of a name wins),
// whether one was compared inexactly, whether it found no record, its
// title, whether that title is the list's own filter_title
// (`title_configured`), and the page number it showed (with `page_param`
// when the list pages under a name of its own). An e-mail body and a
// render inside another request (SEO pass, preview) add nothing.
// Several lists on one page may read the same address (an archive and a
// "most read" box): the page is `empty` only when every list that applied a
// value found nothing, `inexact` and the title come only from lists that
// found records, and the first configured title wins over a bare value.
// Returns the state.
function pg_sw_url_filter_state($add = null)
{
    static $state = null;
    $blank = array('params' => array(), 'inexact' => false, 'empty' => false, 'title' => '', 'title_configured' => false, 'page_number' => 0);
    if ($state === null || $add === false) $state = $blank;
    if (!is_array($add)) return $state;

    $ctx = pg_sw_render_context();
    if (!empty($ctx['email']) || (function_exists('pg_seo_rendering') && pg_seo_rendering())) return $state;

    $params = (isset($add['params']) && is_array($add['params'])) ? $add['params'] : array();
    $first  = !$state['params'];
    foreach ($params as $name => $value) {
        if (!isset($state['params'][$name])) $state['params'][$name] = (string)$value;
    }
    $found = $params && empty($add['empty']);
    if ($params) $state['empty'] = $first ? !$found : ($state['empty'] && !$found);
    if ($found && !empty($add['inexact'])) $state['inexact'] = true;
    $title      = ($found && isset($add['title'])) ? (string)$add['title'] : '';
    $configured = !empty($add['title_configured']);
    if ($title !== '' && ($state['title'] === '' || ($configured && !$state['title_configured']))) {
        $state['title']            = $title;
        $state['title_configured'] = $configured;
    }
    if ($state['page_number'] <= 1 && isset($add['page_number']) && (int)$add['page_number'] > 1) {
        $state['page_number'] = (int)$add['page_number'];
        // The list's own page parameter names that page in the canonical.
        if (isset($add['page_param']) && $add['page_param'] !== 'page_number') $state['page_param'] = (string)$add['page_param'];
    }
    return $state;
}

// The fields a filter may name on form $custom_form_page_id, keyed by their
// lower-case name: every named field but the information blocks.
function pg_sw_filter_form_fields($custom_form_page_id)
{
    $custom = array();
    $rows = db_items(
        "SELECT id, name, type, multiple
         FROM form_fields
         WHERE page_id = '" . (int)$custom_form_page_id . "'
           AND type != 'information'
           AND name != ''"
    );
    foreach ((array)$rows as $row) {
        $custom[mb_strtolower((string)$row['name'], 'UTF-8')] = $row;
    }
    return $custom;
}

// The filters a form_list_view widget applies to its records: the classic
// form list view's filters (edit_form_list_view.php, form_list_view_filters),
// kept in the widget's system_region_config as `filters`, a list of
//   { field, operator, value, dynamic_value, dynamic_value_attribute }
//
// `field` is a standard field (reference_code, submitter, submitted_date_and_time
// …, see pg_sw_standard_fields()) or the name of one of the form's own fields;
// a form field named like a standard field that yields (pg_sw_form_field_wins())
// is the one compared.
// A filter with a value compares against that value. One without compares
// against its dynamic value, worked out on every request the way the classic
// screen does it (get_dynamic_value()): today, N days ago, the signed-in
// viewer, the viewer's e-mail address. Which dynamic values a field offers
// depends on its type, so the editor lists only those that fit.
//
// A filter naming a field the form no longer has, or an operator outside the
// classic list, is left out of the SQL rather than failing the query, and
// counted: the caller then shows no record (pg_sw_filter_sql_closed()).
// Every filter must match (AND), as on the classic screen.
//
// Returns the SQL to add to the listing's WHERE, the standard fields it
// reads (name => true) so the caller joins the tables they live in,
// `missing`, how many filters were left out for naming a field the form
// does not have, and `invalid`, how many named a field with an operator
// outside the list (lists and record views fail closed on either). A row
// with no field is blank and counts in neither.
//
// The dynamic value 'url parameter' (lists only) takes the value from the
// query parameter its dynamic_value_attribute names (pg_sw_url_param_name():
// a reserved or malformed name gives no value). $request holds the
// parameters; only the list's render and the list's feed pass one
// (pg_sw_filter_request()). Without a value in it the filter is left out,
// so the list shows every record. With $request null there is no address
// in this context (record views, sitemap, search index, preview): the
// filter is left out and counted in `address`, on which a record view
// closes. A value applied is returned in `params` (name => value), with
// `inexact` set when its operator is not "is equal to". For the values
// compared with "is equal to", `exact` holds the same comparisons byte for
// byte (" AND (...)"): the caller looks for one record that passes them
// before it prints a value (pg_sw_url_filter_value()). Compared by size, a
// value PHP reads as a number but that is not a plain decimal (1e400, .5)
// matches nothing rather than reaching the SQL unquoted.
function pg_sw_form_list_filter_sql($custom_form_page_id, $filters, $request = null)
{
    $out = array('where' => '', 'tokens' => array(), 'missing' => 0, 'invalid' => 0,
                 'params' => array(), 'inexact' => false, 'address' => 0, 'exact' => '');
    if (!is_array($filters) || !$filters) return $out;

    $operators = array(
        'contains', 'does not contain', 'is equal to', 'is not equal to',
        'is less than', 'is less than or equal to', 'is greater than', 'is greater than or equal to',
    );
    $dynamic_values = array(
        'current date', 'current date and time', 'current time', 'days ago',
        'viewer', 'viewers email address', 'url parameter',
    );
    $size_operators = array('is less than', 'is less than or equal to', 'is greater than', 'is greater than or equal to');
    $standard = pg_sw_standard_fields();
    $custom   = null;

    // At most 50 filters are applied. A filter past them cannot be applied
    // and is counted in `invalid`, so the set closes rather than showing the
    // records it would hold back.
    foreach (array_values($filters) as $position => $filter) {
        if (!is_array($filter)) continue;
        $field    = isset($filter['field'])    ? trim((string)$filter['field']) : '';
        $operator = isset($filter['operator']) ? (string)$filter['operator']    : '';
        if ($field === '') continue;
        if ($position >= 50 || !in_array($operator, $operators, true)) {
            $out['invalid']++;
            continue;
        }

        $value    = isset($filter['value']) ? (string)$filter['value'] : '';
        $dynamic  = isset($filter['dynamic_value']) ? (string)$filter['dynamic_value'] : '';
        if (!in_array($dynamic, $dynamic_values, true)) $dynamic = '';

        // The value from the address. A fixed value wins, as it does over
        // every dynamic value.
        $url_param = '';
        $url_value = '';
        $asked_operator = $operator;
        if ($dynamic === 'url parameter') {
            $dynamic = '';
            if ($value === '') {
                if ($request === null) {
                    $out['address']++;
                    continue;
                }
                $attribute = isset($filter['dynamic_value_attribute']) ? $filter['dynamic_value_attribute'] : '';
                $url_param = is_scalar($attribute) ? pg_sw_url_param_name((string)$attribute) : '';
                $value     = pg_sw_url_param_value($request, $url_param);
                if ($value === '') continue;
                $url_value = $value;
            }
        }
        $dynamic_attribute = isset($filter['dynamic_value_attribute']) ? (int)$filter['dynamic_value_attribute'] : 0;
        // The byte-for-byte twin of an address value's "is equal to", for
        // the `exact` condition.
        $exact_sql = '';

        $use_standard = isset($standard[$field]) && $standard[$field]['sql'] !== '';
        // A form that has its own field for a yielding name compares that
        // field; one known only by its label is not comparable and is
        // skipped as missing, as it was before the standard name existed.
        if ($use_standard && pg_sw_form_yields(array($field => true), $custom_form_page_id)) {
            $use_standard = false;
        }

        if ($use_standard) {
            $operand_1 = $standard[$field]['sql'];
            $type      = $standard[$field]['type'];
            $out['tokens'][$field] = true;
        } else {
            if ($custom === null) $custom = pg_sw_filter_form_fields($custom_form_page_id);
            $key = mb_strtolower($field, 'UTF-8');
            if (!isset($custom[$key])) {
                $out['missing']++;
                continue;
            }

            $field_id = (int)$custom[$key]['id'];
            $type     = (string)$custom[$key]['type'];

            // A multi-select pick list or a check box keeps one row per
            // chosen value, so "is (not) equal to" counts the matching rows
            // and every other operator reads the values joined together —
            // the classic screen's own treatment.
            if (($type === 'pick list' && (int)$custom[$key]['multiple'] === 1) || $type === 'check box') {
                if ($operator === 'is equal to' || $operator === 'is not equal to') {
                    if ($url_param !== '') {
                        $exact_sql = "(SELECT COUNT(*) FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id') AND (CAST(form_data.data AS BINARY) = CAST('" . e($value) . "' AS BINARY))) > 0";
                    }
                    $operand_1 = "(SELECT COUNT(*) FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id') AND (form_data.data = '" . e($value) . "'))";
                    $operator  = ($operator === 'is equal to') ? 'is greater than' : 'is equal to';
                    $value     = '0';
                    $dynamic   = '';
                } else {
                    $operand_1 = "(SELECT GROUP_CONCAT(form_data.data ORDER BY form_data.id ASC SEPARATOR ', ') FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id'))";
                }
            } elseif ($type === 'file upload') {
                $operand_1 = "(SELECT files.name FROM form_data LEFT JOIN files ON form_data.file_id = files.id WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id') LIMIT 1)";
            } else {
                $operand_1 = "(SELECT form_data.data FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id') LIMIT 1)";
            }
        }

        if ($value !== '') {
            // Dates are compared as stored (YYYY-MM-DD, YYYY-MM-DD HH:MM:SS).
            // The editor's date inputs already write that; a value typed in
            // the site's own date format is converted the classic way. An
            // address value compared with "is equal to" is kept in the
            // stored form, so 31.12.2024 names the page ?date=2024-12-31 and
            // prints the stored text.
            if ($type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && preg_match('/^\d{1,2}[-\/.]\d{1,2}[-\/.]\d{4}$/', $value)) {
                $value = (string)prepare_form_data_for_input(str_replace('.', '-', $value), 'date');
                if ($url_param !== '' && $asked_operator === 'is equal to') $url_value = $value;
            } elseif ($type === 'date and time') {
                $value = str_replace('T', ' ', $value);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) $value .= ':00';
                if ($url_param !== '' && $asked_operator === 'is equal to') $url_value = $value;
            }
            $operand_2 = $value;
        } elseif ($dynamic !== '') {
            $operand_2 = (string)get_dynamic_value($dynamic, $dynamic_attribute);
        } else {
            $operand_2 = '';
        }

        if ($url_param !== '') {
            $out['params'][$url_param] = $url_value;
            if ($asked_operator !== 'is equal to') {
                $out['inexact'] = true;
            } else {
                if ($exact_sql === '') {
                    $exact_sql = "CAST(IFNULL(" . $operand_1 . ", '') AS BINARY) = CAST('" . e((string)$operand_2) . "' AS BINARY)";
                }
                $out['exact'] .= ' AND (' . $exact_sql . ')';
            }
            // prepare_sql_operation() leaves a numeric operand of a size
            // comparison unquoted; only a plain decimal may go that way.
            // Checked on the operand it receives: a date-and-time value has
            // had its T replaced by then ('1e400T' became '1e400 ').
            $operand_2 = (string)$operand_2;
            if (in_array($asked_operator, $size_operators, true) && is_numeric($operand_2)
                && !preg_match('/^-?\d{1,15}(\.\d{1,6})?$/', $operand_2)) {
                $out['where'] .= ' AND (TRUE = FALSE)';
                continue;
            }
        }

        $out['where'] .= ' AND (' . prepare_sql_operation($operator, $operand_1, $operand_2) . ')';
    }

    return $out;
}

// Whether a set of filters, as pg_sw_form_list_filter_sql() returned it,
// shows no record at all. A filter that names a field the form no longer
// has (renamed or deleted) or an operator outside the list cannot be
// applied, and leaving it out would show the records it holds back: a
// "Status is equal to Published" list would list the drafts. So the list,
// its feeds, the sitemap and the search index show none, as the record view
// does. $record_view also closes on a filter valued from the page address,
// which a record view never reads; a list leaves such a filter out when the
// address carries no value (`address` counts only the contexts without one).
function pg_sw_filter_sql_closed($filter_sql, $record_view = false)
{
    if (!is_array($filter_sql)) return false;
    $missing = isset($filter_sql['missing']) ? (int)$filter_sql['missing'] : 0;
    $invalid = isset($filter_sql['invalid']) ? (int)$filter_sql['invalid'] : 0;
    $address = isset($filter_sql['address']) ? (int)$filter_sql['address'] : 0;
    if ($missing > 0 || $invalid > 0) return true;
    return $record_view && $address > 0;
}

// Render a system widget's form_list_view. Loop-area aware:
//
//   1. _split_widget_tree pulls the loop_area out of the tree (replaced by a marker).
//   2. Static portion of the tree is rendered ONCE — search box, header, pagination,
//      empty-state messages, etc. live here.
//   3. Loop_area's children are compiled into a per-record template; each submitted_form
//      gets a clone with ^^token^^ placeholders replaced.
//   4. The accumulated loop output is str_replace'd into the static HTML at the marker.
//
// If the widget has NO loop_area (legacy / non-loop widgets), the entire tree is
// treated as the per-record template (old behavior preserved for backwards-compat).
//
// Tokens supported (per record):
//   ^^{form_field.name}^^   — value from form_data.data for the field with that name
//   ^^{form_field.label}^^  — also matched (case-insensitive + slugified variants)
//   ^^{standard_field}^^    — every field pg_sw_standard_fields() carries, i.e. the
//                             same registry the classic form list view resolves
//                             against: reference_code, submitted_date_and_time,
//                             submitter, number_of_comments, newest_comment …,
//                             plus form_item_view (the detail link),
//                             submitted_form_id, submitter_user_id and
//                             submitter_name (the contact's name, for a
//                             public byline; submitter is the username)
//
// A date or date-and-time field takes an optional format suffix, as on the
// classic screens: ^^submitted_date_and_time^^%%d.m.Y%% or %%relative%%.
//
// Config (`system_region_config`) keys used here:
//   items_per_page      int (default 10, max 500)
//   max_results         int (default 0 = no cap, max 100000): the records a
//                       visitor can page through
//   search_enabled      bool (default false)
//   search_fields       array<string>  form_fields.name tokens to search on
//   empty_message       string         shown when no records match (default: the
//                                      translated "No records found.")
//   order_by_field      string  submitted_date_and_time | submitted_form_id |
//                               number_of_views | number_of_comments |
//                               recent_views | newest_activity_date_and_time |
//                               form_fields.name (the record statistics need
//                               detail_page_id, whose counters and newest
//                               comment they read: pg_sw_counter_order_sql())
//   order_by_direction  ASC | DESC (default DESC)
//   order_by_2_field, order_by_2_direction — the second order, same values
//   order_recent_days   int 1-180 (default 7): the recent_views window,
//                       today and the days before it
//   filters             list  the classic form list view's filters, see
//                             pg_sw_form_list_filter_sql()
//   filter_title        string the page title while a value from the address
//                             is applied with "is equal to" and records
//                             match; {value} stands
//                             for the value (pg_sw_filter_title())
//
// A filter whose dynamic value is 'url parameter' reads its value from the
// visitor's address (?author=Jane), for archive pages. ^^__filter_value^^
// prints the applied values when each was compared with "is equal to" and
// records match; the visibility flags has_filter_value (that case) and
// no_filter_value (no value applied) tell the states apart; the page's
// head follows them through pg_sw_url_filter_state().
//
// ^^__result_count^^ prints, in the surround and in every row, how many
// complete records the list matched (its filters, the visitor's search and
// the viewer filter), capped at max_results like the classic list's "Found
// N results", grouped with the site's separators. The flags has_records and
// no_records follow that number. A list whose loop area is empty but whose
// surround is not (a "1,284 topics" strip) runs only the count and returns
// the surround, without rows or pagination.
//
// URL parameters (shared across the page — if multiple system widgets render on
// the same page they share `page_number` / `query` state; cleaner URLs at the
// cost of per-widget independence):
//   page_number   page index (1-based); a list with a `page_param` of its own
//                 pages under that name instead (pg_sw_list_page_param())
//   query         search query string
//
// `offset` skips that many matched records before the first row; the count,
// the pages and max_results all start after them. ^^__page_number^^,
// ^^__page_count^^, ^^__prev_page_url^^, ^^__next_page_url^^ and the flags
// has_prev_page, has_next_page, has_more_pages describe the pages
// (pg_sw_list_paging()).
//
// NOTE: We deliberately avoid the bare key `page` because Pinegrap's router
// uses `$_GET['page']` as the URL slug (set by .htaccess rewrite). A
// pagination link of the form `?page=2` would overwrite the slug and break
// routing entirely. Match the legacy form_list_view's `page_number` instead.
function _render_system_widget_form_list($custom_form_page_id, $tree_json, $widget_id, $cfg = array())
{
    $custom_form_page_id = (int)$custom_form_page_id;
    $widget_id           = (int)$widget_id;
    if ($custom_form_page_id <= 0 || $tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Resolve config values with defaults ───────────────────────────────
    $items_per_page = isset($cfg['items_per_page']) ? (int)$cfg['items_per_page'] : 10;
    if ($items_per_page <= 0)  $items_per_page = 10;
    if ($items_per_page > 500) $items_per_page = 500;

    // Outer dataset cap. 0 / missing = unlimited. Capped at 100k to keep
    // pathological values from killing the server even if config is tampered.
    $max_results = isset($cfg['max_results']) ? (int)$cfg['max_results'] : 0;
    if ($max_results < 0) $max_results = 0;
    if ($max_results > 100000) $max_results = 100000;

    $search_enabled = !empty($cfg['search_enabled']);
    $search_fields  = (isset($cfg['search_fields']) && is_array($cfg['search_fields'])) ? $cfg['search_fields'] : array();

    // search_width → Bootstrap utility class on the search wrapper. Whitelisted
    // so config tampering can't inject arbitrary classes.
    $search_width_map = array(
        'full'    => 'w-100',
        'half'    => 'w-50',
        'quarter' => 'w-25',
        'auto'    => 'w-auto',
    );
    $search_width_key = (isset($cfg['search_width']) && isset($search_width_map[$cfg['search_width']]))
                            ? $cfg['search_width'] : 'full';
    $search_width_cls = $search_width_map[$search_width_key];

    // Placeholder text for the search input. Empty config falls back to the translated "Search...".
    $search_label = (isset($cfg['search_label']) && is_string($cfg['search_label']) && $cfg['search_label'] !== '')
                        ? substr($cfg['search_label'], 0, 100)
                        : (string)lang('Search...');

    $empty_message  = (isset($cfg['empty_message']) && is_string($cfg['empty_message']) && $cfg['empty_message'] !== '')
                        ? $cfg['empty_message']
                        : lang('No records found.');

    $order_by_field    = isset($cfg['order_by_field']) ? (string)$cfg['order_by_field'] : 'submitted_date_and_time';
    $order_by_direction = (isset($cfg['order_by_direction']) && strtoupper($cfg['order_by_direction']) === 'ASC') ? 'ASC' : 'DESC';

    // viewer_filter family. Sub-toggles default to true once the parent is on,
    // mirroring the legacy form_list_view defaults.
    $viewer_filter           = !empty($cfg['viewer_filter']);
    $viewer_filter_submitter = isset($cfg['viewer_filter_submitter']) ? !empty($cfg['viewer_filter_submitter']) : true;
    $viewer_filter_watcher   = isset($cfg['viewer_filter_watcher'])   ? !empty($cfg['viewer_filter_watcher'])   : true;
    $viewer_filter_editor    = isset($cfg['viewer_filter_editor'])    ? !empty($cfg['viewer_filter_editor'])    : true;

    $offset_records = pg_sw_list_offset($cfg);

    // ── URL params ────────────────────────────────────────────────────────
    // The search text is page-wide; the page number too unless the list
    // pages under a name of its own.
    $page_param     = pg_sw_list_page_param($cfg);
    $query_param    = 'query';
    $requested_page = isset($_GET[$page_param]) ? $_GET[$page_param] : null;
    // The parameters the URL filters read; none in an e-mail or a preview.
    $request      = pg_sw_filter_request();
    $search_query = ($search_enabled && isset($_GET[$query_param]) && is_scalar($_GET[$query_param])) ? trim((string)$_GET[$query_param]) : '';
    if (strlen($search_query) > 100) $search_query = substr($search_query, 0, 100);

    // Decode + split the widget tree into static + loop-template halves.
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Messages: liveform 'form_list_view', as on the legacy page (a record
    // deleted from its item view leaves its notice there). An unnamed node
    // would print and use up every form's messages on the page.
    _pg_inject_messages_node($tree_decoded, 'form_list_view');
    // "Show only when" is resolved once the count is known.
    // Only the flags a list evaluates are marked: a flag from another widget
    // type (a pasted record block's record_found) leaves its element drawn,
    // as lists did before they had flags of their own.
    _pg_sw_mark_visibility($tree_decoded, array('has_filter_value', 'no_filter_value', 'has_records', 'no_records', 'has_prev_page', 'has_next_page', 'has_more_pages'));
    pg_sw_record_href_bindings($tree_decoded);
    // Links to another page with the record's values: a token each, and
    // their pages read once, before the surround and the rows are drawn.
    // Outside the rows (the surround, an empty list) such a link opens the
    // bare page, never the current address.
    $page_links = pg_sw_page_link_bindings($tree_decoded);
    pg_sw_page_link_resolve($page_links);
    $page_link_bare = pg_sw_page_link_values($page_links, array(), array(), array());
    // Fields of the records a record points at: a token each, filled per
    // row once the rows are read (includes/fn/collections.php).
    $refs = pg_coll_ref_bindings($tree_decoded);
    $split = _split_widget_tree($tree_decoded, true);

    // Render the loop template (per-record). If no loop_area was found, fall back to
    // rendering the whole tree as the template (legacy widgets without a loop_area).
    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';  // no static surround — output is purely the loop
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // ── Resolve order_by field to a SQL clause (whitelist!) ───────────────
    // Built-ins map to forms.* columns; otherwise the value must match a real
    // form_fields.name for THIS form_pid — anything else falls back to the
    // safe default (submitted_timestamp). No raw user input ever hits SQL.
    //
    // Resolves a single token to {sql, join_alias} with optional secondary
    // sort-data join. Returns null for "no contribution" (empty/invalid input).
    $_resolve_order = function ($field, $direction, $alias) use ($custom_form_page_id, $cfg) {
        if ($field === '' || $field === null) return null;
        if ($field === 'submitted_form_id') {
            return array('sql' => "forms.id $direction", 'join' => '');
        }
        if ($field === 'submitted_date_and_time') {
            return array('sql' => "forms.submitted_timestamp $direction", 'join' => '');
        }
        // A record statistic (counter or newest activity), read for the
        // list's detail page. It wins over a form field of the same name;
        // without a detail page it gives no order (the date order then
        // applies) and no form field is tried.
        $counter = pg_sw_counter_order_sql(
            $field,
            $direction,
            isset($cfg['detail_page_id']) ? $cfg['detail_page_id'] : 0,
            isset($cfg['order_recent_days']) ? $cfg['order_recent_days'] : null,
            pg_sfv_stats_ready()
        );
        if ($counter !== null) return ($counter === '') ? null : array('sql' => $counter, 'join' => '');
        $fid = (int)db_value(
            "SELECT id FROM form_fields
             WHERE page_id = '" . e($custom_form_page_id) . "'
               AND name = '" . e($field) . "'
             LIMIT 1"
        );
        if ($fid <= 0) return null;
        $join = "LEFT JOIN form_data $alias
                   ON $alias.form_id = forms.id
                  AND $alias.form_field_id = '$fid'";
        return array('sql' => "$alias.data $direction", 'join' => $join);
    };

    $order_join   = '';
    $order_parts  = array();
    $primary = $_resolve_order($order_by_field, $order_by_direction, 'sort_data');
    if ($primary) {
        $order_parts[] = $primary['sql'];
        if ($primary['join']) $order_join .= ' ' . $primary['join'];
    } else {
        // Fallback to the safe default if the configured primary is invalid.
        $order_parts[] = "forms.submitted_timestamp $order_by_direction";
    }

    // Secondary sort — cfg key is order_by_2_field; same whitelist guard.
    $order_by_2_field     = isset($cfg['order_by_2_field']) ? (string)$cfg['order_by_2_field'] : '';
    $order_by_2_direction = (isset($cfg['order_by_2_direction']) && strtoupper($cfg['order_by_2_direction']) === 'ASC') ? 'ASC' : 'DESC';
    if ($order_by_2_field !== '') {
        $secondary = $_resolve_order($order_by_2_field, $order_by_2_direction, 'sort_data2');
        if ($secondary) {
            $order_parts[] = $secondary['sql'];
            if ($secondary['join']) $order_join .= ' ' . $secondary['join'];
        }
    }
    // Final tie-breaker so identical primary/secondary values don't reorder
    // mid-page on subsequent loads.
    $order_parts[] = "forms.id $order_by_direction";
    $order_by_sql  = implode(', ', $order_parts);

    // ── Resolve search-field tokens to ids (whitelist) ────────────────────
    $search_field_ids = array();
    if ($search_enabled && $search_query !== '' && !empty($search_fields)) {
        $clean = array();
        foreach ($search_fields as $f) {
            $f = trim((string)$f);
            if ($f !== '') $clean[] = "'" . e($f) . "'";
        }
        if ($clean) {
            $clean_in = implode(',', $clean);
            $rows = db_items(
                "SELECT id FROM form_fields
                 WHERE page_id = '$custom_form_page_id'
                   AND name IN ($clean_in)"
            );
            if (is_array($rows)) {
                foreach ($rows as $r) { $search_field_ids[] = (int)$r['id']; }
            }
        }
    }
    $search_active = $search_enabled && $search_query !== '' && !empty($search_field_ids);

    // ── viewer_filter SQL (audience-based row visibility) ─────────────────
    // Ported from get_form_list_view.php's logic. Three audiences can be
    // ORed: submitter, editor, watcher. Empty/anonymous viewer or all-three-
    // disabled with the parent on => "no rows" (TRUE=FALSE).
    $sql_viewer_filter = '';
    if ($viewer_filter) {
        $all_disabled = !$viewer_filter_submitter && !$viewer_filter_watcher && !$viewer_filter_editor;
        $logged_in    = (defined('USER_LOGGED_IN') && USER_LOGGED_IN === true);
        if ($all_disabled || !$logged_in) {
            $sql_viewer_filter = ' AND (TRUE = FALSE) ';
        } else {
            // Editor short-circuit: if the viewer can edit the custom form, they
            // see everything — no per-row audience filter needed.
            $edit_custom_form_access = false;
            if ($viewer_filter_editor) {
                if (defined('USER_ROLE') && USER_ROLE < 3) {
                    $edit_custom_form_access = true;
                } else {
                    $custom_form_folder_id = db_value(
                        "SELECT page_folder FROM page WHERE page_id = '" . e($custom_form_page_id) . "'"
                    );
                    if (function_exists('check_edit_access') && check_edit_access($custom_form_folder_id) === true) {
                        $edit_custom_form_access = true;
                    }
                }
            }

            if (!$viewer_filter_editor || !$edit_custom_form_access) {
                $clauses = array();
                $uid     = defined('USER_ID') ? USER_ID : 0;
                $umail   = defined('USER_EMAIL_ADDRESS') ? USER_EMAIL_ADDRESS : '';

                if ($viewer_filter_submitter) {
                    $clauses[] = "(forms.user_id = '" . e($uid) . "')";
                }
                if ($viewer_filter_editor) {
                    $clauses[] = "(forms.form_editor_user_id = '" . e($uid) . "')";
                }
                if ($viewer_filter_submitter) {
                    // Connect-to-contact email-address field also counts as submitter.
                    $submitter_field_id = db_value(
                        "SELECT id FROM form_fields
                         WHERE page_id = '" . e($custom_form_page_id) . "'
                           AND contact_field = 'email_address'"
                    );
                    if ($submitter_field_id) {
                        $clauses[] = "((SELECT form_data.data FROM form_data
                                         WHERE form_data.form_id = forms.id
                                           AND form_data.form_field_id = '" . (int)$submitter_field_id . "'
                                         LIMIT 1) = '" . e($umail) . "')";
                    }
                }
                if ($viewer_filter_watcher) {
                    // Watchers of the configured detail page: a classic form
                    // item view page, or a designed page whose record view
                    // shows this form, with comments and watching on
                    // (pg_sw_list_watcher_page_id()). Without one, the watcher
                    // contribution collapses to FALSE — same as legacy logic.
                    $watcher_page_id = pg_sw_list_watcher_page_id(isset($cfg['detail_page_id']) ? $cfg['detail_page_id'] : 0, $custom_form_page_id);
                    if ($watcher_page_id > 0) {
                        $clauses[] = "(EXISTS (SELECT 1 FROM watchers
                                              WHERE watchers.page_id = '" . (int)$watcher_page_id . "'
                                                AND watchers.item_id = forms.id
                                                AND watchers.item_type = 'submitted_form'
                                                AND ( watchers.user_id = '" . e($uid) . "'
                                                   OR watchers.email_address = '" . e($umail) . "' )
                                              LIMIT 1))";
                    } else {
                        $clauses[] = '(TRUE = FALSE)';
                    }
                }
                if ($clauses) {
                    $sql_viewer_filter = ' AND (' . implode(' OR ', $clauses) . ') ';
                }
            }
        }
    }

    // ── Filters the designer set (every record must match all of them) ────
    $filter_sql   = pg_sw_form_list_filter_sql($custom_form_page_id, isset($cfg['filters']) ? $cfg['filters'] : array(), $request);
    // A filter that cannot be applied (its field gone, its operator unknown)
    // lists nothing rather than everything it was meant to hold back.
    if (pg_sw_filter_sql_closed($filter_sql)) {
        $filter_sql['where']  = ' AND (0 = 1)';
        $filter_sql['tokens'] = array();
    }
    $filter_where = $filter_sql['where'];
    // The value from the address, printed only once records match it
    // exactly, so the page never repeats text that names nothing in the
    // data.
    $filter_value = '';
    $flags        = array('has_filter_value' => false, 'no_filter_value' => empty($filter_sql['params']));
    $filter_join  = ($filter_where !== '' && $filter_sql['tokens'])
        ? pg_sw_standard_sql($filter_sql['tokens'], isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0, array('reference_code'))
        : array('select' => '', 'join' => '');
    // ^^__result_count^^ and its flags, set once the records are counted.
    $result_count      = 0;
    $result_count_html = '0';
    $paging_tokens     = array();

    // ── Build the optional EXISTS clause for search filtering ─────────────
    $search_where = '';
    if ($search_active) {
        $sf_in   = implode(',', $search_field_ids);
        $q_esc   = e(escape_like($search_query));
        $search_where =
            " AND EXISTS (SELECT 1 FROM form_data fd_q
                          WHERE fd_q.form_id = forms.id
                            AND fd_q.form_field_id IN ($sf_in)
                            AND fd_q.data LIKE '%$q_esc%')";
    }

    // ── Build search box HTML (rendered above the loop output) ────────────
    // Preserves all current URL params except the widget-specific page/query
    // so the user keeps their wider page state. Hidden inputs replay the rest.
    $search_html = '';
    if ($search_enabled) {
        $hidden = '';
        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                if ($k === $query_param || $k === $page_param) continue;
                if (is_array($v)) continue;  // skip array params; rare and unsafe
                $hidden .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
            }
        }
        $search_html =
            '<form method="get" class="pg-sw-search mb-3" role="search">' .
                $hidden .
                '<div class="input-group input-group-sm ' . $search_width_cls . '">' .
                    '<input type="text" class="form-control" name="' . h($query_param) . '" ' .
                           'value="' . h($search_query) . '" placeholder="' . h($search_label) . '" maxlength="100">' .
                    '<button type="submit" class="btn btn-outline-secondary"><span class="bi bi-search"></span></button>' .
                    ($search_query !== ''
                        ? '<a class="btn btn-outline-secondary" href="?' . h(_pg_sw_build_query($query_param, '', $page_param, '')) . '"><span class="bi bi-x-lg"></span></a>'
                        : '') .
                '</div>' .
            '</form>';
    }

    // Empty-state HTML used when no records match (with or without search
    // active), as an element the records' parent may hold.
    $empty_html = pg_sw_state_message_html($empty_message, $split['loop_parent'], 'pg-sw-empty', pg_sw_row_cells($loop_template));

    // The records go where the loop_area was. The search box and the
    // pagination go there too, except into a list or a table: those may
    // hold only their rows, so the two stand before and after it.
    // The surround renders once, outside any record, so no record's values
    // are applied to it — but an identifier left standing there is one the
    // visitor would read, and form_item_view and my_account both sweep theirs.
    // "Show only when" is resolved on the surround before the records go in
    // (the rows come from a template resolved the same way), so the pattern
    // never spans record HTML: a long span would exceed PCRE's backtrack
    // limit and empty the list, and a record's own text could close a span.
    $stitch = function ($rows, $pagination) use ($static_html, $search_html, &$filter_value, &$flags, &$result_count_html, &$paging_tokens, $page_link_bare) {
        if ($static_html === '') return $search_html . $rows . $pagination;
        $html = _pg_sw_resolve_visibility(
            pg_sw_sweep_tokens(_pg_sw_fill_tokens($static_html, array(
                '__filter_value' => pg_sw_filter_value_html($filter_value),
                '__result_count' => $result_count_html,
            ) + $paging_tokens + $page_link_bare)),
            $flags
        );
        if (strpos($html, '<!--pg-loop-before-->') !== false) {
            return str_replace(
                array('<!--pg-loop-before-->', '<!--pg-loop-slot-->', '<!--pg-loop-after-->'),
                array($search_html, $rows, $pagination),
                $html
            );
        }
        return str_replace('<!--pg-loop-slot-->', $search_html . $rows . $pagination, $html);
    };

    // An empty loop area has no record to repeat, but its surround may still
    // print the count, the flags and the search box (a "1,284 topics" strip):
    // such a list runs only the count below and returns before the rows.
    // Taken before "Show only when" is resolved on the template, so a loop
    // area that only a flag empties stays a list with rows and pages.
    // With neither loop nor surround there is nothing to print.
    $loop_empty = ($loop_template === '');
    if ($loop_empty && $static_html === '') return '';

    // ── Count total matching rows (for pagination) ────────────────────────
    $total_records = (int)db_value(
        "SELECT COUNT(*) FROM forms
         {$filter_join['join']}
         WHERE forms.page_id = '$custom_form_page_id'
           AND forms.complete = 1
           $filter_where
           $search_where
           $sql_viewer_filter"
    );
    // The number a visitor can page through, as the classic list's "Found
    // N results" and the search results widget count it: what is left after
    // the offset, capped by max_results.
    $listed_records = max(0, $total_records - $offset_records);
    $result_count = ($max_results > 0) ? min($listed_records, $max_results) : $listed_records;
    $result_count_html = h(pg_format_number($result_count, 0));

    // The pages: a count strip draws no rows, so it has one page at most.
    $total_pages  = $loop_empty ? min(1, $result_count) : (int)ceil($result_count / $items_per_page);
    $current_page = pg_sw_list_current_page($requested_page, $total_pages);
    $paging       = pg_sw_list_paging($page_param, $current_page, $total_pages);
    $paging_tokens = $paging['tokens'];

    // What the address applied, for this list's tokens and flags and for
    // the page's head (canonical, title, robots, feed link).
    // Printed only when it is text found in the data (pg_sw_url_filter_value()):
    // one of the records found must hold every value byte for byte, not
    // only under the column's collation.
    if ($total_records > 0 && $filter_sql['params'] && !$filter_sql['inexact']
        && ($filter_sql['exact'] === '' || !db_value(
            "SELECT 1 FROM forms
             {$filter_join['join']}
             WHERE forms.page_id = '$custom_form_page_id'
               AND forms.complete = 1
               $filter_where
               {$filter_sql['exact']}
               $search_where
               $sql_viewer_filter
             LIMIT 1"
        ))
    ) {
        $filter_sql['inexact'] = true;
    }
    $filter_value = pg_sw_url_filter_value($filter_sql, $total_records);
    $flags = array(
        'has_filter_value' => $filter_value !== '',
        'no_filter_value'  => empty($filter_sql['params']),
        'has_records'      => $result_count > 0,
        'no_records'       => $result_count === 0,
    ) + $paging['flags'];
    // The flags are final: the per-record template is resolved once, before
    // any record is drawn into it.
    $loop_template = _pg_sw_resolve_visibility($loop_template, $flags);
    if ($filter_sql['params']) {
        $list_filter_title = pg_sw_list_filter_title($cfg);
        pg_sw_url_filter_state(array(
            'params'  => $filter_sql['params'],
            'inexact' => $filter_sql['inexact'],
            'empty'   => $total_records === 0,
            'title'   => pg_sw_filter_title($list_filter_title, $filter_value),
            'title_configured' => $list_filter_title !== '',
        ));
    }

    if ($result_count === 0) return $stitch($empty_html, '');
    // A count strip: the head state above is registered, no row is read.
    if ($loop_empty) return $stitch('', '');

    // A later page of an address-filtered list is its own address (the
    // classic list keeps page_number in its canonical too).
    $url_filter_page = pg_sw_url_filter_page($filter_sql, $request, $current_page, $total_pages, $search_active, $page_param);
    if ($url_filter_page > 0) {
        pg_sw_url_filter_state(array('page_number' => $url_filter_page, 'page_param' => $page_param));
    }
    // The pages run through the $result_count records after the offset, so
    // max_results caps them: the last page's LIMIT may be shorter than
    // $items_per_page (max=23, ipp=10 → page 3 reads 3).
    $page_start = ($current_page - 1) * $items_per_page;
    $page_limit = min($items_per_page, $result_count - $page_start);
    $offset     = $offset_records + $page_start;

    // Resolve the detail page (if configured) once. Each row then links to
    // its own record there, matching the legacy form_list_view pattern:
    //   PATH . encode_url_path(page_name) . '?r=' . forms.reference_code
    // or, when the form has pretty URLs on and the record has a name,
    //   PATH . encode_url_path(page_name) . '/' . forms.address_name
    // which get_page.php resolves for the detail page's widget
    // (pg_sw_route_record_address()).
    //
    // This URL is INTENTIONALLY independent of `_pg_sw_build_query`. The detail
    // link points at a *different* page (the form_item_view), so it must NOT
    // carry the current page's pagination/search params (page_number, query) —
    // if it did, the browser would land on the detail page with stale list-view
    // state in the query string (and on installs without URL rewriting, the
    // `page=<slug>` slug param could even point at the wrong page entirely).
    //
    // The detail page must show the SAME custom form the widget is listing:
    // the detail screen's WHERE clause asserts
    //   forms.page_id = $custom_form_page_id AND forms.reference_code = ?
    // so a page bound to another form would 404 every link. A stale config
    // gives no address ('#' per record). pg_sw_list_detail_target() applies
    // that guard to a classic form item view page and to a designed page
    // alike.
    $detail_page_id = isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0;
    $detail_target  = pg_sw_list_detail_target($detail_page_id, $custom_form_page_id);

    // Which standard fields this design actually prints decides what the
    // listing query reads: a template that never mentions a commenter never
    // joins the comments table. A link's values are read from the record
    // too.
    $template_tokens = pg_sw_template_tokens($loop_template) + pg_sw_page_link_tokens($page_links);
    // A filter on a standard field needs that field's table joined too.
    // A standard name the form has its own field for is not selected, so the
    // token prints that field or nothing (pg_sw_yielded_standard_names()).
    $standard_skip   = array_merge(array('reference_code', 'address_name'), pg_sw_form_yields($template_tokens, $custom_form_page_id));
    $standard_sql    = pg_sw_standard_sql($template_tokens + $filter_sql['tokens'], $detail_page_id, $standard_skip);

    // 1. Pull submitted form rows (the `forms` table) for this custom form,
    //    applying ordering + search + pagination.
    //    Only `complete=1` rows are shown — incomplete sessions are drafts.
    $forms = db_items(
        "SELECT forms.id, forms.page_id, forms.user_id, forms.reference_code, forms.address_name
                {$standard_sql['select']}
         FROM forms
         $order_join
         {$standard_sql['join']}
         WHERE forms.page_id = '$custom_form_page_id'
           AND forms.complete = 1
           $filter_where
           $search_where
           $sql_viewer_filter
         ORDER BY $order_by_sql
         LIMIT $page_limit OFFSET $offset"
    );
    if (!$forms) return $stitch($empty_html, '');

    // 2. Bulk-fetch form_data rows for all those submissions in one query.
    //    Pull BOTH form_fields.name AND form_fields.label (plus form_data.name denormalized
    //    copy) so the token can match whichever identifier the designer used — written
    //    tokens are free text ("^^description^^" etc.) and may correspond to any of them.
    $form_ids = array();
    foreach ($forms as $f) { $form_ids[] = (int)$f['id']; }
    $form_ids_str = implode(',', $form_ids);

    // JOIN files so file-upload fields resolve to a usable URL instead of an empty string.
    // form_data.data is empty for file-upload rows; the actual filename lives on
    // form_data.file_id → files.id → files.name. Pinegrap serves uploaded files at
    // OUTPUT_PATH . file_name (see edit_file.php: $data_src = OUTPUT_PATH . $file_name).
    $data_rows = db_items(
        "SELECT
            form_data.form_id,
            form_data.id        AS data_id,
            form_data.data,
            form_data.name      AS data_name,
            form_data.file_id   AS data_file_id,
            form_fields.name    AS field_name,
            form_fields.label   AS field_label,
            form_fields.type    AS field_type,
            form_fields.wysiwyg AS field_wysiwyg,
            form_fields.office_use_only AS field_office_use,"
            . (pg_schema_has('form_fields', 'translate_values') ? " form_fields.translate_values AS field_translate," : "") . "
            files.name          AS file_name
         FROM form_data
         LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
         LEFT JOIN files       ON form_data.file_id       = files.id
         WHERE form_data.form_id IN ($form_ids_str)"
    );
    // The rows of the lists inside each record (a field's values, the
    // attachments), drawn by the finish pass for the records shown here.
    pg_sw_list_publish('form_record', $data_rows, array('translate' => !empty($cfg['translate_records'])));

    // Index the form's own fields under every identifier a designer might
    // write for them. Values stay raw — escaping happens once, at replace time.
    $by_form = pg_sw_index_form_data($data_rows);
    // The values as stored, for the links to another page: the list there
    // compares them with what it stores, not with a translation.
    $by_form_source = $by_form;

    // A widget that includes its records in the translation prints their
    // texts in the language the page is served in (includes/fn/translate.php).
    if (!empty($cfg['translate_records'])) {
        $by_form = array_map('pg_tr_record_fields', $by_form);
    }
    pg_coll_ref_fill($refs, $by_form, $by_form_source);

    // 3. Render each submission by cloning the LOOP template and replacing tokens.
    //    An identifier this form does not answer is swept after the pass, so a
    //    raw "^^something^^" never leaks into the page.
    $loop_output = '';
    foreach ($forms as $f) {
        $fid = (int)$f['id'];

        // Detail link → configured form_item_view page + reference_code.
        //
        // Fallback to `#` (not '') when no detail page is configured. An empty
        // href is resolved by browsers to the *current* URL — which on this
        // page already carries `page_number` / `query` from the list-view's
        // pagination/search state. That made the rendered detail link look
        // like `?page=deneme&page_number=1` instead of pointing nowhere, and
        // tripped users into thinking the URL builder was leaking those
        // params. `#` makes the inert state explicit.
        $extra = array(
            'form_item_view'    => ($detail_target['page_name'] !== '' && !empty($f['reference_code']))
                                       ? pg_sw_record_url($detail_target['page_name'], $f['reference_code'],
                                                          isset($f['address_name']) ? $f['address_name'] : '', $detail_target['pretty'])
                                       : '#',
            'submitted_form_id' => (string)$fid,
            'submitter_user_id' => isset($f['user_id']) ? (string)$f['user_id'] : '',
        );
        // The catalog's name for the same link. A link bound to it in a
        // form list printed an empty address.
        $extra['__detail_url'] = $extra['form_item_view'];
        $extra['__filter_value'] = pg_sw_filter_value_html($filter_value);
        $extra['__result_count'] = $result_count_html;
        $extra += $paging_tokens;
        // Links to another page, valued from this record.
        $extra += pg_sw_page_link_values($page_links, $f, isset($by_form_source[$fid]) ? $by_form_source[$fid] : array(), array(
            'form_item_view'    => ($extra['form_item_view'] !== '#') ? $extra['form_item_view'] : '',
            'submitted_form_id' => $extra['submitted_form_id'],
            'submitter_user_id' => $extra['submitter_user_id'],
        ));

        $rendered = pg_sw_apply_tokens(
            $loop_template,
            $f,
            isset($by_form[$fid]) ? $by_form[$fid] : array(),
            $extra
        );
        $rendered = pg_sw_hide_empty_fields(pg_sw_sweep_tokens($rendered));

        // Uniquify Bootstrap component IDs per loop iteration so tabs, collapses
        // and dropdowns in different rows don't share the same id/target.
        // Using both widget_id and record's DB id as suffix prevents collisions
        // across multiple instances of the same widget on the same page.
        $rendered = pg_sw_uniquify_row_ids($rendered, $widget_id, $fid);

        // The pencil for whoever may edit the submissions of this form.
        if (pg_sw_can_edit_submitted_forms($custom_form_page_id)) {
            $rendered = pg_sw_add_edit_chip($rendered, pg_sw_backend_edit_url('edit_submitted_form.php', $fid), lang('Edit the record'), 'forms');
        }

        $loop_output .= $rendered;
    }

    // 4. Pagination (Bootstrap 5 nav) — only when there is more than one page.
    //    A design holding a list of the page links draws them itself: its
    //    rows are published and the built-in pagination is left out.
    if (pg_sw_list_wanted('paging')) {
        pg_sw_list_publish('paging', pg_sw_list_paging_rows($page_param, $current_page, $total_pages));
        $pagination_html = '';
    } else {
        $pagination_html = pg_sw_pagination_html($page_param, $current_page, $total_pages);
    }

    // 5. Stitch search + loop output + pagination into the static surround
    //    (legacy widgets with no loop_area: the records with their controls).
    return $stitch($loop_output, $pagination_html);
}

/**
 * May the visitor see this submitted form on a detail page?
 *
 * $access_control is the form_item_view widget's setting. 'public' lets
 * everybody in, 'logged_in' every signed-in visitor, and 'submitter_only'
 * applies the legacy form item view's submitter security rule for rule
 * (get_page_content.php, add_comment.php): the visitor who submitted it in
 * this browser session; signed in, anybody who may edit the custom form, its
 * form editor, its submitter, the owner of the address in its
 * connect-to-contact email field, and a watcher of the record on this
 * detail page.
 *
 * $form carries id, page_id (the custom form), reference_code, user_id and
 * form_editor_user_id.
 */
function pg_sw_submitted_form_visible($form, $access_control, $detail_page_id = 0)
{
    if (!is_array($form) || empty($form['id'])) return false;
    // The search index stores what an anonymous visitor reads, even when an
    // editor's click on the panel button runs it with their session's
    // sign-in and role: another record view on the page that only signed-in
    // visitors or the submitter may see then counts the record as missing,
    // as it does in a command line run.
    if (defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true) {
        return $access_control !== 'logged_in' && $access_control !== 'submitter_only';
    }
    $signed_in = defined('USER_LOGGED_IN') && USER_LOGGED_IN === true;
    if ($access_control === 'logged_in') return $signed_in;
    if ($access_control !== 'submitter_only') return true;

    $codes = (isset($_SESSION['software']['submitted_form_reference_codes'])
              && is_array($_SESSION['software']['submitted_form_reference_codes']))
        ? $_SESSION['software']['submitted_form_reference_codes'] : array();
    if (in_array((string)$form['reference_code'], $codes, true)) return true;
    if (!$signed_in) return false;

    $user_id = defined('USER_ID') ? (int)USER_ID : 0;
    if ($user_id > 0 && ((int)$form['user_id'] === $user_id || (int)$form['form_editor_user_id'] === $user_id)) return true;

    $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int)$form['page_id'] . "' LIMIT 1");
    if ($folder !== null && $folder !== '' && check_edit_access($folder)) return true;

    $email = defined('USER_EMAIL_ADDRESS') ? (string)USER_EMAIL_ADDRESS : '';
    if ($email === '') return false;
    $submitter_email = db_value(
        "SELECT form_data.data FROM form_data
         LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
         WHERE form_data.form_id = '" . (int)$form['id'] . "'
           AND form_fields.contact_field = 'email_address'
         LIMIT 1");
    if ($submitter_email !== null && mb_strtolower((string)$submitter_email) === mb_strtolower($email)) return true;

    return (int)$detail_page_id > 0 && (int)db_value(
        "SELECT COUNT(*) FROM watchers
         WHERE (user_id = '" . $user_id . "' OR email_address = '" . e($email) . "')
           AND page_id = '" . (int)$detail_page_id . "'
           AND item_id = '" . (int)$form['id'] . "'
           AND item_type = 'submitted_form'") > 0;
}

// The filters a form_item_view widget holds its record to: the list widget's
// `filters` shape (pg_sw_form_list_filter_sql()). An empty list means every
// record of the form may be shown, under the access setting alone.
function pg_sw_record_view_filters($cfg)
{
    if (!is_array($cfg) || !isset($cfg['filters']) || !is_array($cfg['filters'])) return array();
    $out = array();
    foreach ($cfg['filters'] as $filter) {
        if (is_array($filter)) $out[] = $filter;
    }
    return $out;
}

// The filters a list's records go through when each is addressed on the
// list's detail page: the record view's own first (they decide what the
// page shows), then the list's. pg_sw_form_list_filter_sql() applies at
// most fifty and closes on a filter past them.
function pg_sw_merge_record_filters($list_cfg, $view_cfg)
{
    $list = (is_array($list_cfg) && isset($list_cfg['filters']) && is_array($list_cfg['filters']))
        ? array_values($list_cfg['filters']) : array();
    return array_merge(pg_sw_record_view_filters($view_cfg), $list);
}

// Whether a form widget shows the same records to every visitor: its access
// setting is public and, for a list, the viewer filter is off. Only such a
// widget gets a public feed and puts its records in the sitemap; a viewer
// filtered list shows an anonymous visitor nothing, as on the legacy page.
// A record view never reads `viewer_filter`: a list switched to a record
// view in the editor keeps the key, and it stays inert there.
function pg_sw_form_widget_public($cfg)
{
    if (!is_array($cfg)) return false;
    $access = isset($cfg['access_control']) ? (string)$cfg['access_control'] : 'public';
    if ($access !== '' && $access !== 'public') return false;
    if (isset($cfg['regionType']) && $cfg['regionType'] === 'form_item_view') return true;
    return empty($cfg['viewer_filter']);
}

// Whether a record view shows its record without its filters: only on an
// e-mail drawn for a record the server named (a form's confirmation or
// notification, $form_id passed with the call). An address's ?r= on a page
// asked for with ?email=true is still held to the filters.
function pg_sw_record_filter_bypass($email, $form_id)
{
    return (bool)$email && (int)$form_id > 0;
}

// The JOIN and WHERE that hold the records of a form to a list of filters,
// for a query over `forms`. The detail page is the one whose view counters
// a standard field reads. Both parts are '' when no filter applies.
//
// A filter naming a field the form no longer has (renamed or deleted) or an
// operator outside the list (a translated "eşittir" in a hand-written
// config) matches no record (`closed`, pg_sw_filter_sql_closed()). $strict
// is for a record view's filters: a value taken from the address (a
// list-only dynamic value; a record view never reads the address) closes
// it too, where a list leaves that filter out.
//
// $request is the query the list's URL filters read (pg_sw_filter_request());
// null leaves them out. `params`, `inexact` and `exact` say what was applied
// (pg_sw_form_list_filter_sql()).
function pg_sw_record_filter_clause($custom_form_page_id, $filters, $detail_page_id = 0, $strict = false, $request = null)
{
    $out = array('join' => '', 'where' => '', 'closed' => false, 'params' => array(), 'inexact' => false, 'exact' => '');
    if (!is_array($filters) || !$filters) return $out;
    $filter_sql = pg_sw_form_list_filter_sql((int)$custom_form_page_id, $filters, $request);
    if (pg_sw_filter_sql_closed($filter_sql, $strict)) {
        $out['where']  = ' AND (0 = 1)';
        $out['closed'] = true;
        return $out;
    }
    $out['params']  = $filter_sql['params'];
    $out['inexact'] = $filter_sql['inexact'];
    $out['exact']   = $filter_sql['exact'];
    if ($filter_sql['where'] === '') return $out;
    $out['where'] = $filter_sql['where'];
    if ($filter_sql['tokens']) {
        $standard    = pg_sw_standard_sql($filter_sql['tokens'], (int)$detail_page_id, array('reference_code'));
        $out['join'] = $standard['join'];
    }
    return $out;
}

// The JOIN and WHERE for a list's records as its detail page shows them:
// the record view's filters and the list's (pg_sw_merge_record_filters()),
// and no record at all when the record view's own filters fail closed or a
// filter of the list cannot be applied (pg_sw_filter_sql_closed()).
// For the list's feed and the sitemap, whose links lead to that page.
// $request: the feed passes the address its list's URL filters read; the
// sitemap passes none, so those filters are left out and every record the
// detail page shows is listed. The record view's part never reads it.
function pg_sw_list_record_filter_clause($custom_form_page_id, $list_cfg, $view_cfg, $detail_page_id = 0, $request = null)
{
    $view = pg_sw_record_filter_clause($custom_form_page_id, pg_sw_record_view_filters($view_cfg), 0, true);
    if ($view['closed']) return $view;
    return pg_sw_record_filter_clause($custom_form_page_id, pg_sw_merge_record_filters($list_cfg, $view_cfg), $detail_page_id, false, $request);
}

// Whether one record of a form passes a record view's filters. A query of
// its own: the standard fields' joins use fixed aliases (submitter,
// submitted_form_info, ...) that the record query may already hold. A failed
// query, or a filter naming a field the form no longer has or an unknown
// operator, counts as not passing.
//
// One request asks the same question up to three times (the pretty address,
// the head context, the widget), so the answer is kept for the request.
function pg_sw_record_passes_filters($custom_form_page_id, $filters, $form_id, $detail_page_id = 0)
{
    static $cache = array();
    $custom_form_page_id = (int)$custom_form_page_id;
    $form_id             = (int)$form_id;
    if ($custom_form_page_id <= 0 || $form_id <= 0) return false;
    if (!is_array($filters) || !$filters) return true;
    $key = $custom_form_page_id . '|' . $form_id . '|' . (int)$detail_page_id . '|' . md5(serialize($filters));
    if (isset($cache[$key])) return $cache[$key];
    $clause = pg_sw_record_filter_clause($custom_form_page_id, $filters, $detail_page_id, true);
    if ($clause['closed']) {
        $passes = false;
    } elseif ($clause['where'] === '') {
        $passes = true;
    } else {
        $passes = (int)db_value(
            "SELECT COUNT(*) FROM forms
             {$clause['join']}
             WHERE forms.id = '" . $form_id . "'
               AND forms.page_id = '" . $custom_form_page_id . "'
               {$clause['where']}"
        ) > 0;
    }
    // The search index asks once per record of the site in one process.
    if (count($cache) >= 256) $cache = array();
    return $cache[$key] = $passes;
}

/**
 * The record a designed page's comments belong to.
 *
 * A page built in the visual editor shows a submitted form through its
 * form_item_view widget, not through a page type, so the comment code asks
 * here what a legacy form item view page answers through its page type: the
 * submitted form the page shows - by id when a comment names one, else by
 * ?r= - taken from the widget's own custom form only, and whether the
 * visitor may see it under the widget's access setting.
 *
 * A record outside the widget's filters (pg_sw_record_view_filters()) is
 * not visible either, except on an e-mail drawn for a record the server
 * named ($email with $form_id, pg_sw_record_filter_bypass()): a form's
 * confirmation or notification shows the record just submitted, whatever
 * its state. A ?r= record on a page asked for with ?email=true is not one.
 *
 * Returns null when the page carries no such widget, otherwise
 * array('custom_form_page_id', 'access_control', 'form' => row or null,
 * 'visible' => bool). The row carries id, page_id, reference_code, user_id,
 * form_editor_user_id, form_editor_username and form_editor_email_address.
 */
function pg_sw_record_comment_context($page_id, $form_id = 0, $email = false)
{
    static $cache = array();
    $page_id = (int)$page_id;
    $form_id = (int)$form_id;
    $email   = pg_sw_record_filter_bypass($email, $form_id);
    if ($page_id <= 0) return null;
    $key = $page_id . ':' . $form_id . ($email ? ':email' : '');
    // Without an id the record is the one ?r= names; the search index draws
    // one record after another on the same page in one process.
    if ($form_id <= 0) $key .= ':' . ((isset($_GET['r']) && is_scalar($_GET['r'])) ? (string)$_GET['r'] : '');
    if (array_key_exists($key, $cache)) return $cache[$key];
    // The search index asks once per record of the site in one process.
    if (count($cache) >= 256) $cache = array();
    $cache[$key] = null;

    $widget = pg_sw_page_widget($page_id, 'form_item_view');
    if (!$widget) return null;
    $cfg = $widget['cfg'];
    $custom_form_page_id = pg_sw_record_view_form_page_id($cfg);
    $access = (isset($cfg['access_control']) && in_array($cfg['access_control'], array('public', 'submitter_only', 'logged_in'), true))
        ? $cfg['access_control'] : 'public';

    $where = '';
    if ($form_id > 0) {
        $where = "forms.id = '" . $form_id . "'";
    } else {
        $reference = (isset($_GET['r']) && is_scalar($_GET['r'])) ? trim((string)$_GET['r']) : '';
        if ($reference !== '' && strlen($reference) <= 100) $where = "forms.reference_code = '" . e($reference) . "'";
    }
    $form = null;
    if ($custom_form_page_id > 0 && $where !== '') {
        $form = db_item(
            "SELECT forms.id, forms.page_id, forms.reference_code, forms.address_name, forms.user_id, forms.form_editor_user_id,
                    user.user_username AS form_editor_username, user.user_email AS form_editor_email_address
             FROM forms
             LEFT JOIN user ON forms.form_editor_user_id = user.user_id
             WHERE forms.page_id = '" . $custom_form_page_id . "' AND " . $where . "
             LIMIT 1");
        if (!is_array($form) || empty($form['id'])) $form = null;
    }

    return $cache[$key] = array(
        'custom_form_page_id' => $custom_form_page_id,
        'access_control'      => $access,
        'form'                => $form,
        'visible'             => ($form !== null) && pg_sw_submitted_form_visible($form, $access, $page_id)
                                 && ($email || pg_sw_record_passes_filters($custom_form_page_id, pg_sw_record_view_filters($cfg), $form['id'], $page_id)),
    );
}

// The custom form a record view's comments and watchers belong to:
// custom_form_page_id, else the older form_page_id; 0 when it names neither.
// The source_page name is not looked up (pg_sw_form_widget_form_page_id()
// does), so the comment context above and the list's watcher page below
// read the same form. Pure.
function pg_sw_record_view_form_page_id($cfg)
{
    if (!is_array($cfg)) return 0;
    foreach (array('custom_form_page_id', 'form_page_id') as $key) {
        if (!empty($cfg[$key])) return is_scalar($cfg[$key]) ? (int)$cfg[$key] : 0;
    }
    return 0;
}

// The page whose watchers a viewer filtered form list counts as the
// viewer's records (viewer_filter_watcher), or 0 when none counts. Pure:
// $page is the detail page's row (page_id, page_type, comments,
// comments_watcher_email_page_id), $view_form_page_id the form of the
// page's first record view widget (pg_sw_record_view_form_page_id(), 0
// without one).
//
// - Comments and watching must be on: add_or_remove_watcher.php stores no
//   watcher on a page without them.
// - A classic form item view page counts by its page type, as it always did.
// - Any other page counts when its record view shows the list's own form:
//   add_or_remove_watcher.php and add_comment.php take that widget's record
//   as the page's thread (pg_sw_record_comment_context(),
//   pg_comment_thread_verify()). A page without one, or whose record view
//   shows another form, counts no watcher.
function pg_sw_list_watcher_page_decision($page, $list_form_page_id, $view_form_page_id)
{
    if (!is_array($page)) return 0;
    $page_id = (isset($page['page_id']) && is_scalar($page['page_id'])) ? (int)$page['page_id'] : 0;
    if ($page_id <= 0) return 0;
    $comments = (isset($page['comments']) && is_scalar($page['comments'])) ? (int)$page['comments'] : 0;
    $watching = (isset($page['comments_watcher_email_page_id']) && is_scalar($page['comments_watcher_email_page_id']))
        ? (int)$page['comments_watcher_email_page_id'] : 0;
    if ($comments !== 1 || $watching === 0) return 0;
    $page_type = (isset($page['page_type']) && is_scalar($page['page_type'])) ? (string)$page['page_type'] : '';
    if ($page_type === 'form item view') return $page_id;
    $list_form = is_scalar($list_form_page_id) ? (int)$list_form_page_id : 0;
    $view_form = is_scalar($view_form_page_id) ? (int)$view_form_page_id : 0;
    return ($list_form > 0 && $view_form === $list_form) ? $page_id : 0;
}

// The page whose watchers a viewer filtered list of $custom_form_page_id
// counts: pg_sw_list_watcher_page_decision() over the list's detail page
// and, on a page other than a classic form item view, its first record view
// widget, the one pg_sw_record_comment_context() asks. One page query; the
// widget lookup is kept per request (pg_sw_page_widgets()), and the list's
// record links read the same page. 0 when no watcher counts.
function pg_sw_list_watcher_page_id($detail_page_id, $custom_form_page_id)
{
    $detail_page_id      = is_scalar($detail_page_id) ? (int)$detail_page_id : 0;
    $custom_form_page_id = is_scalar($custom_form_page_id) ? (int)$custom_form_page_id : 0;
    if ($detail_page_id <= 0 || $custom_form_page_id <= 0) return 0;
    $page = db_item(
        "SELECT page_id, page_type, comments, comments_watcher_email_page_id
         FROM page
         WHERE page_id = '" . $detail_page_id . "'
           AND comments = '1'
           AND comments_watcher_email_page_id != '0'
         LIMIT 1");
    if (!is_array($page)) return 0;
    $view_form = 0;
    if ((string)$page['page_type'] !== 'form item view') {
        $widget = pg_sw_page_widget($detail_page_id, 'form_item_view');
        if ($widget) $view_form = pg_sw_record_view_form_page_id($widget['cfg']);
    }
    return pg_sw_list_watcher_page_decision($page, $custom_form_page_id, $view_form);
}

// ============================================================================
// Pretty record addresses (the custom form's "Enable Pretty URLs")
// ============================================================================
// A form with pretty URLs on gives every record a name made from its Title
// field (forms.address_name, written by custom_form.php and
// update_submitted_form_address_name()). The classic screens reach such a
// record through the list page (/blog/<name>, get_page.php). A page built in
// the visual editor reaches it through the page that shows it, the page with
// the Form Item View widget, the way a Catalog Detail page is reached
// (/<page>/<product address>). The reference code (?r=) keeps answering
// either way, so links written before the setting was turned on still work.

// A link bound to a record's Address Name opens the record. The name alone
// is not an address: printed as an href it resolves against the page it is
// on (/blog/<name> on the list page), and that page shows the list again.
// Such a link takes the record's own address (form_item_view: the detail
// page with the name when the form has pretty URLs on, ?r= otherwise).
// Text bound to the name keeps printing the name.
function pg_sw_record_href_bindings(&$node)
{
    if (!is_array($node)) return;
    if (isset($node['props']['_bindings']['href'])
        && is_string($node['props']['_bindings']['href'])
        && strtolower(trim($node['props']['_bindings']['href'])) === 'address_name') {
        $node['props']['_bindings']['href'] = 'form_item_view';
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) pg_sw_record_href_bindings($child);
        unset($child);
    }
}

// ============================================================================
// Links to another page with the record's values in the address.
//
// A link in a form list row or on a form detail page may open another page
// of the site with query parameters valued from the record: a board badge
// opens /board?board=<board>, a byline /members?member=<author name>. The
// link node stores the sentinel binding and, beside it, the page and the
// values:
//   props._bindings.href   = '__page_link'
//   props._bindParams.href = array('page_id' => 12,
//                                  'params'  => array(array('name' => 'board', 'token' => 'board'), ...))
// The renderer gives each such link a token of its own (__page_link_<n>, the
// way the catalog expands __link_label), reads the pages once and fills the
// token per record with the finished, escaped address. Names and values
// follow the rules of the list filters that read them on the other page
// (pg_sw_url_param_name(), pg_sw_url_param_value()), so whatever a link
// sends is a value that filter can read. The editor's twins are
// _swPageLinkSpec() and _SW_PAGE_LINK_MAX in style_designer.js.

// The most values one link carries.
function pg_sw_page_link_max()
{
    return 8;
}

// A stored link spec in the shape the renderer reads: page_id a positive id
// or 0 (a tab not published yet, 'tab:<key>', is 0 here), params a list of
// name/token pairs. A name the URL filters refuse, an empty token or a name
// given twice is dropped (the first one stays); at most
// pg_sw_page_link_max() pairs. Tokens keep the characters _apply_bindings()
// keeps, lowercased as the token map is keyed.
function pg_sw_page_link_spec($spec)
{
    $out = array('page_id' => 0, 'params' => array());
    if (!is_array($spec)) return $out;

    $page_id = isset($spec['page_id']) ? $spec['page_id'] : 0;
    if (is_int($page_id) && $page_id > 0) {
        $out['page_id'] = $page_id;
    } elseif (is_string($page_id) && $page_id !== '' && ctype_digit($page_id) && (int)$page_id > 0) {
        $out['page_id'] = (int)$page_id;
    }

    $params = (isset($spec['params']) && is_array($spec['params'])) ? $spec['params'] : array();
    $seen   = array();
    foreach ($params as $param) {
        if (count($out['params']) >= pg_sw_page_link_max()) break;
        if (!is_array($param)) continue;
        $name  = pg_sw_url_param_name(isset($param['name']) ? $param['name'] : '');
        $token = (isset($param['token']) && is_string($param['token']))
            ? strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $param['token'])) : '';
        if ($name === '' || $token === '' || isset($seen[$name])) continue;
        $seen[$name] = true;
        $out['params'][] = array('name' => $name, 'token' => $token);
    }
    return $out;
}

// The pre-pass over a widget tree: every link bound to '__page_link' gets a
// token of its own (__page_link_0, __page_link_1, ... in walk order) and its
// spec is collected under that token, with an empty 'base' that
// pg_sw_page_link_resolve() fills. A node with a spec under another binding
// is left alone: the spec is kept for the editor and does nothing here.
// A link drawn in a list inside the record (a loop_area with a source) may
// take a value from the list's row (__value_param, __file_name): such a
// param is marked 'row', and the list's node lists the link's token in
// props._pgRowLinks, which its row template keeps (pg_sw_list_span()) so
// the pass fills the address per row (pg_sw_page_link_row_value()). Only
// the nearest list's row tokens count, as the editor offers them.
function pg_sw_page_link_bindings(&$tree)
{
    $links = array();
    $none  = array();
    _pg_sw_page_link_walk($tree, $links, 0, $none, array());
    return $links;
}

function _pg_sw_page_link_walk(&$node, &$links, $depth, &$row_links, $row_tokens)
{
    if (!is_array($node) || $depth > 80) return;
    if (isset($node['props']['_bindings']['href']) && $node['props']['_bindings']['href'] === '__page_link') {
        $token = '__page_link_' . count($links);
        $node['props']['_bindings']['href'] = $token;
        $spec = pg_sw_page_link_spec(isset($node['props']['_bindParams']['href']) ? $node['props']['_bindParams']['href'] : null);
        $by_row = false;
        foreach ($spec['params'] as $i => $param) {
            if (!isset($row_tokens[$param['token']])) continue;
            $spec['params'][$i]['row'] = true;
            $by_row = true;
        }
        $links[$token] = array('page_id' => $spec['page_id'], 'params' => $spec['params'], 'base' => '');
        if ($by_row) $row_links[] = $token;
    }
    if (empty($node['children']) || !is_array($node['children'])) return;
    if (pg_sw_list_is_sourced($node)) {
        $sources = pg_sw_list_sources();
        $mine    = array();
        $tokens  = array();
        if (isset($sources[$node['props']['source']])) {
            foreach ($sources[$node['props']['source']]['tokens'] as $pair) $tokens[$pair[0]] = true;
        }
        foreach ($node['children'] as &$child) _pg_sw_page_link_walk($child, $links, $depth + 1, $mine, $tokens);
        unset($child);
        if ($mine) {
            $node['props']['_pgRowLinks'] = $mine;
        } else {
            unset($node['props']['_pgRowLinks']);
        }
        return;
    }
    foreach ($node['children'] as &$child) _pg_sw_page_link_walk($child, $links, $depth + 1, $row_links, $row_tokens);
    unset($child);
}

// The tokens the links read their values from, as a lookup set, so the
// record query selects the standard fields they name.
function pg_sw_page_link_tokens($links)
{
    $tokens = array();
    foreach ((array)$links as $link) {
        if (empty($link['params']) || !is_array($link['params'])) continue;
        foreach ($link['params'] as $param) {
            $token = isset($param['token']) ? strtolower((string)$param['token']) : '';
            if ($token !== '') $tokens[$token] = true;
        }
    }
    return $tokens;
}

// The address of the page a link opens, from its page row (page_name,
// page_folder) and the folder ids of the Recycle Bin: $base (PATH when null)
// plus the page name, the base a record's own address uses
// (pg_sw_record_url()). '' for a page that is gone or in the bin; the link
// then prints '#'.
function pg_sw_page_link_base($row, $bin_folder_ids, $base = null)
{
    if (!is_array($row) || !isset($row['page_name']) || trim((string)$row['page_name']) === '') return '';
    $folder = isset($row['page_folder']) ? (int)$row['page_folder'] : 0;
    if ($folder > 0 && in_array($folder, array_map('intval', (array)$bin_folder_ids), true)) return '';
    if ($base === null) $base = defined('PATH') ? PATH : '/';
    return $base . encode_url_path((string)$row['page_name']);
}

// Fill each link's 'base' with the address of its page. The pages are read
// in one query per request; a renderer with no link to a page asks nothing.
function pg_sw_page_link_resolve(&$links)
{
    static $pages = array();
    if (!is_array($links) || !$links) return;

    $wanted = array();
    foreach ($links as $link) {
        $page_id = isset($link['page_id']) ? (int)$link['page_id'] : 0;
        if ($page_id > 0 && !array_key_exists($page_id, $pages)) $wanted[$page_id] = true;
    }
    if ($wanted) {
        foreach (array_keys($wanted) as $page_id) $pages[$page_id] = null;
        $rows = db_items(
            "SELECT page_id, page_name, page_folder
             FROM page
             WHERE page_id IN (" . implode(',', array_map('intval', array_keys($wanted))) . ")"
        );
        foreach ((is_array($rows) ? $rows : array()) as $row) $pages[(int)$row['page_id']] = $row;
    }

    $bin = null;
    foreach ($links as $token => $link) {
        $page_id = isset($link['page_id']) ? (int)$link['page_id'] : 0;
        if ($page_id <= 0 || empty($pages[$page_id])) {
            $links[$token]['base'] = '';
            continue;
        }
        if ($bin === null) $bin = pg_recycle_bin_folder_ids();
        $links[$token]['base'] = pg_sw_page_link_base($pages[$page_id], $bin);
    }
}

// The raw value of token $name for one record, in the order
// pg_sw_apply_tokens() resolves it: a value the renderer worked out
// ($extra_raw: the record's address, its id, its submitter's id), then a
// standard field the record query selected (unless the form's own field of
// that name wins, pg_sw_form_field_wins()), then the form's own field as
// stored: unescaped, no date format, a file upload as its address, several
// values joined with ', '. Columns the query reads for itself (id, user_id)
// are no standard field and give nothing; so does a name nobody answers.
function pg_sw_raw_token_value($name, $row, $custom, $extra_raw)
{
    $name = mb_strtolower(trim((string)$name), 'UTF-8');
    if ($name === '') return '';
    if (is_array($extra_raw) && array_key_exists($name, $extra_raw)) return (string)$extra_raw[$name];

    $custom    = is_array($custom) ? $custom : array();
    $standard  = pg_sw_standard_fields();
    $form_wins = isset($custom[$name]) && pg_sw_form_field_wins($name);
    if (!$form_wins && isset($standard[$name]) && is_array($row) && array_key_exists($name, $row)) {
        return (string)$row[$name];
    }
    if (isset($custom[$name]) && is_array($custom[$name])) {
        return isset($custom[$name]['data']) ? (string)$custom[$name]['data'] : '';
    }
    return '';
}

// The address a link opens, not yet escaped: $base followed by the
// name => value pairs in their order, each name and value encoded once
// (RFC 3986). A value is sent only as the URL filter on the other page
// would read it (pg_sw_url_param_value(): trimmed, without control or
// format characters, at most 100 characters); an empty or unreadable one is
// left out, and with none left the bare page opens, which lists everything,
// as a URL filter with no value does. '#' when there is no page.
function pg_sw_page_link_url($base, $pairs)
{
    $base = (string)$base;
    if ($base === '') return '#';
    $query = array();
    foreach ((array)$pairs as $name => $raw) {
        $name = pg_sw_url_param_name($name);
        if ($name === '' || !is_scalar($raw)) continue;
        $value = trim((string)$raw);
        if ($value === '' || pg_sw_url_param_value(array('v' => $value), 'v') !== $value) continue;
        $query[] = rawurlencode($name) . '=' . rawurlencode($value);
    }
    return $query ? $base . '?' . implode('&', $query) : $base;
}

// The finished token values of one record: token => the escaped address.
// pg_sw_apply_tokens() prints the values of its $extra map as they are, so
// the address is escaped here, once: '&' between the values becomes '&amp;'
// and a value's own '&' is %26 before that. A link with a value from a list
// row is not finished yet while a widget renders: its value is a placeholder
// carrying the page and the record's values (pg_sw_page_link_defer()),
// which the list fills per row.
function pg_sw_page_link_values($links, $row, $custom, $extra_raw)
{
    $values = array();
    foreach ((array)$links as $token => $link) {
        $base  = isset($link['base']) ? (string)$link['base'] : '';
        $pairs = array();
        $defer = array();
        if ($base !== '' && !empty($link['params']) && is_array($link['params'])) {
            foreach ($link['params'] as $param) {
                $by_row  = !empty($param['row']);
                $value   = $by_row ? '' : pg_sw_raw_token_value($param['token'], $row, $custom, $extra_raw);
                $pairs[$param['name']] = $value;
                $defer[] = array($param['name'], $by_row ? $param['token'] : '', $value);
            }
        }
        $values[$token] = ($base !== '' && pg_sw_finish_active() && pg_sw_page_link_has_row($link))
            ? pg_sw_page_link_defer($base, $defer)
            : h(pg_sw_page_link_url($base, $pairs));
    }
    return $values;
}

// Whether a link takes a value from a list row.
function pg_sw_page_link_has_row($link)
{
    if (empty($link['params']) || !is_array($link['params'])) return false;
    foreach ($link['params'] as $param) {
        if (!empty($param['row'])) return true;
    }
    return false;
}

// The placeholder of a link whose address waits for a list row: plain
// characters (base64 with '.', '_' and '~' for '+', '/' and '='), so it
// passes every fill and h() unchanged, with this request's nonce, so a
// value shaped like one is text. $pairs: array(name, row token or '', the
// record's value) each, in the link's order.
function pg_sw_page_link_defer($base, $pairs)
{
    $json = json_encode(array((string)$base, array_values($pairs)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return 'pgpl-' . pg_sw_marker_nonce() . '-' . strtr(base64_encode((string)$json), '+/=', '._~') . '-';
}

// The escaped address one placeholder opens, its row values read from $row
// (token => raw value, as a list source gives it; null: no row, those values
// are left out). __value_param is already address-encoded and is decoded
// first, so it is encoded once. '' for text that is no placeholder of $nonce.
function pg_sw_page_link_row_value($placeholder, $nonce, $row)
{
    $head = 'pgpl-' . $nonce . '-';
    $placeholder = (string)$placeholder;
    if (strncmp($placeholder, $head, strlen($head)) !== 0 || substr($placeholder, -1) !== '-') return '';
    $json = base64_decode(strtr(substr($placeholder, strlen($head), -1), '._~', '+/='), true);
    $data = ($json === false) ? null : json_decode($json, true);
    if (!is_array($data) || count($data) !== 2 || !is_string($data[0]) || !is_array($data[1])) return '';

    $pairs = array();
    foreach ($data[1] as $pair) {
        if (!is_array($pair) || count($pair) !== 3) continue;
        $value = (string)$pair[2];
        if ((string)$pair[1] !== '') {
            $value = (is_array($row) && isset($row[$pair[1]])) ? (string)$row[$pair[1]] : '';
            if ($pair[1] === '__value_param') $value = rawurldecode($value);
        }
        $pairs[(string)$pair[0]] = $value;
    }
    return h(pg_sw_page_link_url($data[0], $pairs));
}

// $html with every link placeholder of $nonce still standing (a link no list
// row filled) as its address without the row values.
function pg_sw_page_link_settle($html, $nonce)
{
    if (!is_string($html) || strpos($html, 'pgpl-' . $nonce . '-') === false) return $html;
    $out = preg_replace_callback('/pgpl-' . preg_quote($nonce, '/') . '-[A-Za-z0-9._~]*-/', function ($m) use ($nonce) {
        return pg_sw_page_link_row_value($m[0], $nonce, null);
    }, $html);
    return ($out === null) ? $html : $out;
}

// A widget tree with the links' unpublished pages ('tab:<key>' in
// props._bindParams.*.page_id) as the ids the tabs got on Publish ($tab_ids,
// key => id); a key with no id (a tab closed meanwhile) gives 0. Nothing
// else is read: a text or an attribute that reads 'tab:...' stays as it
// is, and a tree with nothing to resolve comes back unchanged.
function pg_sw_page_link_resolve_tabs($node, $tab_ids, $depth = 0)
{
    if (!is_array($node) || $depth > 80) return $node;
    if (isset($node['props']['_bindParams']) && is_array($node['props']['_bindParams'])) {
        foreach ($node['props']['_bindParams'] as $prop => $spec) {
            if (!is_array($spec) || !isset($spec['page_id']) || !is_string($spec['page_id'])
                || strncmp($spec['page_id'], 'tab:', 4) !== 0) {
                continue;
            }
            $key = substr($spec['page_id'], 4);
            $node['props']['_bindParams'][$prop]['page_id'] = isset($tab_ids[$key]) ? (int)$tab_ids[$key] : 0;
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            $node['children'][$i] = pg_sw_page_link_resolve_tabs($child, $tab_ids, $depth + 1);
        }
    }
    return $node;
}

// The list widget types: form_list_view under its older names.
function pg_sw_form_list_types()
{
    return array('form_list_view', 'submitted_forms_list', 'form_list', 'blog_list');
}

// Whether the form names its records in their addresses. Asked once per form
// per request; $reset forgets the answers (a save that just changed it).
function pg_sw_form_pretty_urls($form_page_id, $reset = false)
{
    static $cache = array();
    if ($reset) $cache = array();
    $form_page_id = (int)$form_page_id;
    if ($form_page_id <= 0) return false;
    if (!isset($cache[$form_page_id])) {
        $cache[$form_page_id] = (check_if_pretty_urls_are_enabled($form_page_id) == true);
    }
    return $cache[$form_page_id];
}

// The address of one record on the page that shows it, from $base (PATH when
// null): /<page>/<address name> when $pretty and the record has a name,
// /<page>?r=<reference code> otherwise.
function pg_sw_record_url($page_name, $reference_code, $address_name, $pretty, $base = null)
{
    if ($base === null) $base = defined('PATH') ? PATH : '/';
    $url = $base . encode_url_path((string)$page_name);
    $address_name = trim((string)$address_name);
    if ($pretty && $address_name !== '') return $url . '/' . encode_url_path($address_name);
    return $url . '?r=' . rawurlencode((string)$reference_code);
}

// The address segment a page was asked for: what follows the page name in
// $_GET['page'] (/blog-post/<segment>), up to the next slash, decoded the
// way the catalog widgets decode theirs. '' when there is none.
function pg_sw_request_segment($page_parameter = null)
{
    $full = ($page_parameter === null) ? (isset($_GET['page']) ? (string)$_GET['page'] : '') : (string)$page_parameter;
    $slash = mb_strpos($full, '/', 0, 'UTF-8');
    if ($slash === false) return '';
    $segment = mb_substr($full, $slash + 1, null, 'UTF-8');
    $next = mb_strpos($segment, '/', 0, 'UTF-8');
    if ($next !== false) $segment = mb_substr($segment, 0, $next, 'UTF-8');
    $segment = trim(urldecode(trim($segment)));
    return (strlen($segment) > 255) ? '' : $segment;
}

// The record of a form a pretty address names: array('id', 'reference_code',
// 'address_name'), or null.
function pg_sw_record_by_address($form_page_id, $address_name)
{
    $form_page_id = (int)$form_page_id;
    $address_name = trim((string)$address_name);
    if ($form_page_id <= 0 || $address_name === '') return null;
    $row = db_item(
        "SELECT id, reference_code, address_name FROM forms
         WHERE page_id = '" . $form_page_id . "'
           AND address_name = '" . e($address_name) . "'
         ORDER BY id ASC
         LIMIT 1");
    return (is_array($row) && !empty($row['id'])) ? $row : null;
}

/**
 * A pretty record address asked of a page built in the visual editor.
 *
 * Called by get_page.php before the page is drawn, with the page the address
 * starts with and the segment after it.
 *
 *   /<page>/<name>, the page shows the record (a Form Item View widget of a
 *   form with pretty URLs on): the record's reference code goes into
 *   $_GET['r'], where the widget, its comments and the page's canonical
 *   address read it. A name that matches no record, or one the widget's
 *   filters keep out, leaves an empty reference and the widget shows its
 *   not-found state.
 *
 *   /<list page>/<name>, the classic shape: a list widget whose detail page
 *   shows the same form sends the visitor on to the record's own address,
 *   when that page's record view is public, the visitor may open that
 *   page's private or membership folder and its filters let the record
 *   through.
 *
 * A page with a catalog widget keeps its segment (a product, a product
 * group), and an explicit ?r= wins over the segment.
 *
 * @return string '' when nothing more is to be done, else the address (from
 *                PATH) to send the visitor to
 */
function pg_sw_route_record_address($page_id, $segment)
{
    $page_id = (int)$page_id;
    $segment = trim((string)$segment);
    if ($page_id <= 0 || $segment === '') return '';
    if (isset($_GET['r']) && is_scalar($_GET['r']) && trim((string)$_GET['r']) !== '') return '';

    $item_view = null;
    $lists = array();
    foreach (pg_sw_page_widgets($page_id) as $w) {
        $type = isset($w['cfg']['regionType']) ? (string)$w['cfg']['regionType'] : '';
        if ($type === 'catalog_listing' || $type === 'catalog_item_view') return '';
        if ($type === 'form_item_view' && $item_view === null) $item_view = $w;
        if (in_array($type, pg_sw_form_list_types(), true)) $lists[] = $w;
    }

    if ($item_view !== null) {
        $form_page_id = pg_sw_form_widget_form_page_id($item_view['cfg']);
        if ($form_page_id > 0 && pg_sw_form_pretty_urls($form_page_id)) {
            $record = pg_sw_record_by_address($form_page_id, $segment);
            if ($record && !pg_sw_record_passes_filters($form_page_id, pg_sw_record_view_filters($item_view['cfg']), $record['id'], $page_id)) {
                $record = null;
            }
            $_GET['r'] = $record ? (string)$record['reference_code'] : '';
        }
        return '';
    }

    foreach ($lists as $w) {
        $form_page_id   = pg_sw_form_widget_form_page_id($w['cfg']);
        $detail_page_id = isset($w['cfg']['detail_page_id']) ? (int)$w['cfg']['detail_page_id'] : 0;
        if ($form_page_id <= 0 || $detail_page_id <= 0 || $detail_page_id === $page_id) continue;
        if (!pg_sw_form_pretty_urls($form_page_id)) continue;
        $detail_view = pg_sw_page_widget($detail_page_id, 'form_item_view', function ($c) use ($form_page_id) {
            return pg_sw_form_widget_form_page_id($c) === $form_page_id;
        });
        if (!$detail_view) continue;
        // A record view not open to every visitor (submitter_only,
        // logged_in) gets no redirect either: the 301 would tell a signed-out
        // visitor which names exist, drafts included. The answer is the one
        // an unknown name gets.
        if (!pg_sw_form_widget_public($detail_view['cfg'])) continue;
        // Nor does a detail page in a private or membership folder this
        // visitor may not open: this runs before any folder check, and the
        // 301 would name what the detail page's own sign-in answer keeps
        // hidden. A visitor with access keeps the redirect. Registration and
        // guest folders pass (second argument): anyone gets in by signing up
        // or choosing guest, and the 301 lets the detail page ask for that.
        $detail_page = db_item("SELECT page_name, page_folder FROM page WHERE page_id = '" . $detail_page_id . "' LIMIT 1");
        $detail_page_name = is_array($detail_page) ? (string)$detail_page['page_name'] : '';
        if ($detail_page_name === '') continue;
        if (!check_view_access($detail_page['page_folder'], true)) continue;
        $record = pg_sw_record_by_address($form_page_id, $segment);
        if (!$record) continue;
        // A record the detail page's filters keep out gets no redirect: the
        // answer would tell which names exist.
        if (!pg_sw_record_passes_filters($form_page_id, pg_sw_record_view_filters($detail_view['cfg']), $record['id'], $detail_page_id)) continue;
        return pg_sw_record_url($detail_page_name, $record['reference_code'], $record['address_name'], true);
    }
    return '';
}

// The address a list links each record to: the detail page's, resolved once
// per render. A detail page that shows another form gives no address (its
// widget would not find the record). Returns array('page_name' => '' when
// there is none, 'pretty' => bool).
function pg_sw_list_detail_target($detail_page_id, $custom_form_page_id)
{
    $out = array('page_name' => '', 'pretty' => false);
    $detail_page_id = (int)$detail_page_id;
    $custom_form_page_id = (int)$custom_form_page_id;
    if ($detail_page_id <= 0 || $custom_form_page_id <= 0) return $out;

    // A classic form item view page bound to the same form.
    $name = (string)db_value(
        "SELECT page.page_name
         FROM page
         INNER JOIN form_item_view_pages
                 ON form_item_view_pages.page_id = page.page_id
                AND form_item_view_pages.collection = 'a'
                AND form_item_view_pages.custom_form_page_id = '" . $custom_form_page_id . "'
         WHERE page.page_id = '" . $detail_page_id . "'
         LIMIT 1"
    );
    $classic = ($name !== '');
    // A page built in the visual editor shows the record through a
    // form_item_view widget; it must be bound to the same form.
    if (!$classic && pg_sw_page_widget($detail_page_id, 'form_item_view', function ($c) use ($custom_form_page_id) {
        return pg_sw_form_widget_form_page_id($c) === $custom_form_page_id;
    })) {
        $name = (string)db_value("SELECT page_name FROM page WHERE page_id = '" . $detail_page_id . "' LIMIT 1");
    }
    $out['page_name'] = $name;
    // A classic detail page answers only ?r= under its own name; its pretty
    // address is the classic list page's (get_page.php).
    $out['pretty'] = ($name !== '' && !$classic && pg_sw_form_pretty_urls($custom_form_page_id));
    return $out;
}

// ============================================================================
// Render a form_item_view system widget — single submitted-form detail.
//
// Companion to _render_system_widget_form_list: the list view's
// ^^__detail_url^^ token links here (`?r=<reference_code>`). This renderer
// resolves that reference back to a `forms` row, joins all of its `form_data`,
// and applies tokens to the loop_area template ONCE (no iteration).
//
// Differs from form_list_view in three ways:
//   1. No pagination, no search, no order_by, no max_results — there's only
//      ever one record in scope.
//   2. ?r= is the trigger; missing ?r= or no matching row → not_found_message
//      (configurable). Empty `?r=` is intentional, not an error.
//   3. Access control is per-record audience (public / submitter_only /
//      logged_in), not per-list-row audience like form_list_view's viewer_filter.
//
// Tokens (loop_area template + static portion) — the same registry
// form_list_view resolves, so a design moves between the two widgets intact:
//   ^^{standard_field}^^    — everything pg_sw_standard_fields() carries
//                             (reference_code, submitted_date_and_time,
//                             submitter, tracking_code, address_name,
//                             number_of_comments, newest_comment …), with the
//                             optional %%format%% suffix on date fields
//   ^^form_item_view^^      — this page's URL with ?r= preserved (canonical self)
//   ^^submitted_form_id^^   — forms.id
//   ^^submitter_user_id^^   — forms.user_id
//   ^^submitter_name^^      — the contact's name, never the username (a
//                             public byline binds this, not submitter)
//   ^^not_found^^           — empty when the record is shown; the configured
//                             message when ?r= is missing or the visitor may
//                             not see it (wrap a hide/show region around it);
//                             ^^__not_found_message^^ is the same value under
//                             the name the catalog and calendar widgets use
//   ^^{form_field.name}^^   — value from form_data
//   ^^{form_field.name}__label^^  — when show_field_labels is on, the field's
//                                   label as a separate token
//
// Config:
//   custom_form_page_id   — bound custom form (page.page_id of page_type=custom_form)
//   not_found_message     — string shown via ^^not_found^^ token when miss
//   hide_when_not_found   — bool, default on: with no record, the loop_area
//                           keeps only what is meant for that state
//                           (pg_sw_prune_record_layout())
//   access_control        — 'public' | 'submitter_only' | 'logged_in'
//   show_field_labels     — bool: emit {field}__label tokens beside the values
//   title_field, title_suffix — the page's <title> (pg_sw_record_title())
//   filters               — the list widget's filters: a record outside them
//                           is not found (pg_sw_record_passes_filters());
//                           an e-mail page shows its record regardless
//   (no key)              — the record shown counts one view on this page,
//                           in get_page.php (pg_sw_record_view_seen())
//
// URL params:
//   r — reference_code (matches Pinegrap's classic form item view convention);
//       on /<page>/<address name> of a form with pretty URLs on, get_page.php
//       fills it from the name (pg_sw_route_record_address())
function _render_system_widget_form_item_view($custom_form_page_id, $tree_json, $widget_id, $cfg = array())
{
    $custom_form_page_id = (int)$custom_form_page_id;
    $widget_id           = (int)$widget_id;
    if ($custom_form_page_id <= 0 || $tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Config resolution ────────────────────────────────────────────────
    $not_found_message = (isset($cfg['not_found_message']) && is_string($cfg['not_found_message']) && $cfg['not_found_message'] !== '')
                            ? $cfg['not_found_message']
                            : lang('No records found.');
    $access_control = isset($cfg['access_control']) ? (string)$cfg['access_control'] : 'public';
    if (!in_array($access_control, array('public', 'submitter_only', 'logged_in'), true)) {
        $access_control = 'public';
    }
    $show_field_labels = !empty($cfg['show_field_labels']);

    // Decode + split the widget tree into static + loop-template halves.
    // The loop_area renders ONCE (no iteration). If the tree has no loop_area
    // we render the whole tree as the template, matching form_list_view's
    // legacy fallback so designers without a loop_area still get output.
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Messages: liveform 'form_item_view', as on the legacy page (a record
    // saved through send_to leaves its notice there).
    _pg_inject_messages_node($tree_decoded, 'form_item_view');
    pg_sw_record_href_bindings($tree_decoded);
    // Links to another page with the record's values: a token each, and
    // their pages read once (pg_sw_page_link_bindings()). Before the copy
    // below, so the no-record layout carries the same tokens.
    $page_links = pg_sw_page_link_bindings($tree_decoded);
    pg_sw_page_link_resolve($page_links);
    // Fields of the records this record points at (includes/fn/collections.php).
    $refs = pg_coll_ref_bindings($tree_decoded);
    // The tree as designed, for the no-record layout below.
    $tree_designed = $tree_decoded;
    // The record template (loop_area) and the surround, drawn from a tree.
    $draw = function ($tree) {
        // Visibility flag has_new_account, known once the record is read.
        _pg_sw_mark_visibility($tree);
        // The in-browser summary's button and box.
        _pg_sw_apply_ai_summary_bindings($tree);
        $split = _split_widget_tree($tree);
        if ($split['loop_children'] === null) {
            return array(trim(_render_tree_node($tree, 0, 0)), '', $split);
        }
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        return array(trim($loop_template), trim(_render_tree_node($split['static_tree'], 0, 0)), $split);
    };
    list($loop_template, $static_html, $split) = $draw($tree_decoded);

    // ── Resolve the requested record ─────────────────────────────────────
    // Empty ?r= is treated as "no record" (not an error) — designer might
    // place this widget on a page hit without a reference, and we want the
    // not_found template to render gracefully.
    // Which standard fields this design prints decides what the record query
    // reads — the same rule the listing follows. `?r=` identifies the record,
    // and the detail page whose view counters apply is this page itself.
    $template_tokens = pg_sw_template_tokens($loop_template . $static_html) + pg_sw_page_link_tokens($page_links);
    // As on the list: a standard name the form has its own field for is not
    // selected (pg_sw_yielded_standard_names()).
    $standard_skip   = array_merge(array('reference_code', 'address_name'), pg_sw_form_yields($template_tokens, $custom_form_page_id));
    $standard_sql    = pg_sw_standard_sql($template_tokens, _pg_sw_current_page_id(), $standard_skip);

    $reference_code = isset($_GET['r']) ? trim((string)$_GET['r']) : '';
    // An e-mail page (a form's confirmation or notification) is drawn for
    // the record just submitted: its id comes with the call, not the URL.
    $sw_ctx          = pg_sw_render_context();
    $email_record_id = (!empty($sw_ctx['email']) && !empty($sw_ctx['form_id'])) ? (int)$sw_ctx['form_id'] : 0;
    $form_row = null;
    if ($email_record_id > 0) {
        $form_row = db_item(
            "SELECT forms.id, forms.page_id, forms.user_id, forms.form_editor_user_id,
                    forms.reference_code, forms.submitted_timestamp, forms.address_name,
                    forms.tracking_code, forms.complete, forms.quiz_score
                    {$standard_sql['select']}
             FROM forms
             {$standard_sql['join']}
             WHERE forms.page_id = '" . (int)$custom_form_page_id . "'
               AND forms.id      = '" . $email_record_id . "'
             LIMIT 1"
        );
    } elseif ($reference_code !== '' && strlen($reference_code) <= 100) {
        $form_row = db_item(
            "SELECT forms.id, forms.page_id, forms.user_id, forms.form_editor_user_id,
                    forms.reference_code, forms.submitted_timestamp, forms.address_name,
                    forms.tracking_code, forms.complete, forms.quiz_score
                    {$standard_sql['select']}
             FROM forms
             {$standard_sql['join']}
             WHERE forms.page_id   = '" . (int)$custom_form_page_id . "'
               AND forms.reference_code = '" . e($reference_code) . "'
             LIMIT 1"
        );

        // Register the article/record for visitor tracking. Registered before
        // the access check below on purpose: a denied view is still a view of
        // this record, and treating it as an unnamed hit on the host page
        // would put it back in the bucket this change exists to empty.
        if (is_array($form_row) && !empty($form_row['id'])) {
            pg_track_content('form', $form_row['id']);
        }
    }

    // ── Access control ───────────────────────────────────────────────────
    // Translates the cfg.access_control enum into a single allowed/denied bit.
    // We treat denied as a "not found" state — same UX as missing record so
    // the page never leaks the existence of a record the visitor can't see.
    // 'submitter_only' is the legacy submitter security: see
    // pg_sw_submitted_form_visible().
    // The e-mail's recipient was chosen by the server (the sender, the
    // form's administrators), not by whoever holds the session.
    // A record outside the widget's filters (a draft under a "Status is
    // equal to Published" filter) is not there for the address either.
    $allowed = !$form_row || ($email_record_id > 0)
        || (pg_sw_submitted_form_visible($form_row, $access_control, _pg_sw_current_page_id())
            && pg_sw_record_passes_filters($custom_form_page_id, pg_sw_record_view_filters($cfg), $form_row['id'], _pg_sw_current_page_id()));
    $found_and_allowed = ($form_row && $allowed);

    // The address asked for a record (?r=, or a name under the page when the
    // form has pretty URLs on and no catalog widget on the page reads that
    // segment instead) and there is none to show: a 404, not the page.
    if (!$found_and_allowed && $email_record_id === 0) {
        $asked = ($reference_code !== '');
        if (!$asked && pg_sw_request_segment() !== '' && pg_sw_form_pretty_urls($custom_form_page_id)) {
            $asked = true;
            foreach (pg_sw_page_widgets(_pg_sw_current_page_id()) as $w) {
                $type = isset($w['cfg']['regionType']) ? (string)$w['cfg']['regionType'] : '';
                if ($type === 'catalog_listing' || $type === 'catalog_item_view') {
                    $asked = false;
                    break;
                }
            }
        }
        if ($asked) pg_sw_item_missing(true);
    }

    // The record shown here counts one view on this page. Only registered:
    // get_page.php does the counting once the page has been sent, so a
    // background render (search index, SEO pass, canvas preview, e-mail)
    // never counts, and the flags below only say so twice. A record not
    // found, denied or outside the filters is never registered.
    if ($found_and_allowed && $email_record_id === 0 && empty($sw_ctx['email'])
        && !(function_exists('pg_seo_rendering') && pg_seo_rendering())
        && !(defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true)) {
        pg_sw_record_view_seen((int)$form_row['id'], _pg_sw_current_page_id());
    }

    // No record to show: the record's layout would print as a column of
    // empty fields. Unless the widget is set to keep it, the record template
    // keeps only what is meant for this state, and a design that shows the
    // not-found state nowhere gets the message where the record would be.
    if (!$found_and_allowed && (!isset($cfg['hide_when_not_found']) || !empty($cfg['hide_when_not_found']))) {
        $tree_missing  = $tree_designed;
        $shows_message = pg_sw_prune_record_layout($tree_missing);
        list($loop_template, $static_html, $split) = $draw($tree_missing);
        if (!$shows_message) {
            $loop_template .= pg_sw_state_message_html($not_found_message, $split['loop_parent'], 'pg-sw-not-found');
        }
    }

    // Helper: build current page URL with `?r=<code>` for the ^^form_item_view^^ token.
    // Mirrors form_list_view's detail-link convention so the same token name
    // works in both widget kinds (designer can paste the same link patterns).
    $self_url = '';
    if (defined('PATH')) {
        // Reconstruct slug from $_GET['page'] (router writes the resolved page slug there)
        $page_slug = isset($_GET['page']) ? (string)$_GET['page'] : '';
        if ($page_slug !== '') {
            // Strip trailing /<group>/... segments — keep only the page slug itself
            $first_slash = mb_strpos($page_slug, '/');
            if ($first_slash !== false) $page_slug = mb_substr($page_slug, 0, $first_slash);
            // The record's own address: its name when the form has pretty
            // URLs on (pg_sw_route_record_address() answers it on this page).
            $self_url = $found_and_allowed
                ? pg_sw_record_url($page_slug, $form_row['reference_code'], $form_row['address_name'], pg_sw_form_pretty_urls($custom_form_page_id))
                : PATH . encode_url_path($page_slug);
        }
    }

    // ── Build the token map ──────────────────────────────────────────────
    $custom = array();
    // The record's values as stored, for the links to another page (the
    // list there compares stored values, not a translation).
    $custom_source = array();
    if (!$found_and_allowed) {
        // No record, or one this visitor may not see. Only the widget's own
        // state answers; every field identifier is left unanswered and swept,
        // so the designer's not_found region renders and nothing else does.
        $form_row = array();
        $extra    = array(
            'form_item_view'         => '#',
            'submitted_form_id'      => '',
            'submitter_user_id'      => '',
            'not_found'              => $not_found_message,
            '__not_found_message'    => $not_found_message,
            '__new_account_email'    => '',
            '__new_account_password' => '',
            '__new_account_set_password_url' => '',
            '__edit_url'             => '',
        ) + pg_sw_entity_values('form_record', null);
    } else {
        // Pull all form_data rows for the matched submission. Same JOIN shape
        // as form_list_view's batch fetcher (single `form_id`) — file-upload
        // fields resolve to a public URL via OUTPUT_PATH + files.name.
        $data_rows = db_items(
            "SELECT
                '" . (int)$form_row['id'] . "' AS form_id,
                form_data.id        AS data_id,
                form_data.data,
                form_data.name      AS data_name,
                form_data.file_id   AS data_file_id,
                form_fields.name    AS field_name,
                form_fields.label   AS field_label,
                form_fields.type    AS field_type,
                form_fields.wysiwyg AS field_wysiwyg,
                form_fields.office_use_only AS field_office_use,"
                . (pg_schema_has('form_fields', 'translate_values') ? " form_fields.translate_values AS field_translate," : "") . "
                files.name          AS file_name
             FROM form_data
             LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
             LEFT JOIN files       ON form_data.file_id       = files.id
             WHERE form_data.form_id = '" . (int)$form_row['id'] . "'"
        );
        // The rows of the lists inside the record, drawn by the finish pass.
        pg_sw_list_publish('form_record', $data_rows, array('translate' => !empty($cfg['translate_records'])));

        $indexed = pg_sw_index_form_data($data_rows);
        $custom  = isset($indexed[(int)$form_row['id']]) ? $indexed[(int)$form_row['id']] : array();
        $custom_source = $custom;

        // The record's texts in the page's language, when the widget includes
        // its records in the translation (includes/fn/translate.php).
        if (!empty($cfg['translate_records'])) {
            $custom = pg_tr_record_fields($custom);
        }
        $by_record = array((int)$form_row['id'] => $custom);
        pg_coll_ref_fill($refs, $by_record, array((int)$form_row['id'] => $custom_source));
        $custom = $by_record[(int)$form_row['id']];

        // An account the form's auto-registration just opened for this
        // visitor: the confirmation page tells them how to sign in, as the
        // legacy custom form confirmation screen did. No password is handed
        // out. The set password link, for the visitor alone, has a token of
        // its own for a button's href; the password token, which older
        // designs print as text, carries a sentence with that link
        // (pg_cf_new_account_password_note()).
        $new_account_email = pg_cf_new_account_email((int)$form_row['id']);
        $new_account_link  = ($new_account_email !== '') ? pg_cf_new_account_link((int)$form_row['id']) : '';
        $new_account_note  = ($new_account_email !== '')
            ? pg_cf_new_account_password_note($new_account_link, pg_cf_new_account_for_submitter()) : '';

        $extra = array(
            'form_item_view'         => $self_url !== '' ? $self_url : '#',
            'submitted_form_id'      => (string)$form_row['id'],
            'submitter_user_id'      => (string)$form_row['user_id'],
            'not_found'              => '',  // empty when found — the not_found region renders empty
            '__not_found_message'    => '',
            '__new_account_email'    => h($new_account_email),
            '__new_account_password' => h($new_account_note),
            // Empty unless the link is live and the viewer sent the form.
            '__new_account_set_password_url' => h($new_account_link),
            // The record's edit screen, for whoever may edit this form's
            // submissions (the legacy form item view's Edit button).
            '__edit_url'             => pg_sw_can_edit_submitted_forms($custom_form_page_id)
                                            ? h(pg_sw_backend_edit_url('edit_submitted_form.php', (int)$form_row['id'])) : '',
        );

        // The record's allow-listed values (widgets_entity_fields.php): the
        // quiz score of a quiz form. This view shows one record to whoever
        // may open it; the list view offers none of them.
        $extra += array_map('h', pg_sw_entity_values('form_record', $form_row + array(
            'quiz' => (int)db_value("SELECT quiz FROM custom_form_pages WHERE page_id = '" . $custom_form_page_id . "' LIMIT 1"),
        )));

        // Optional label tokens: {field}__label beside the value, when toggled on.
        if ($show_field_labels && is_array($data_rows)) {
            foreach ($data_rows as $data_row) {
                if (empty($data_row['field_name'])) continue;
                $extra[mb_strtolower((string)$data_row['field_name'], 'UTF-8') . '__label'] =
                    isset($data_row['field_label']) ? h((string)$data_row['field_label']) : '';
            }
        }
    }

    // The AI assistants' links carry the record's address, so they are
    // filled only for a record anybody may open, on the page itself: an
    // assistant cannot open a page behind a sign-in, and an e-mail has no
    // page of its own.
    $ai_page_url = ($found_and_allowed && $access_control === 'public' && empty($sw_ctx['email']) && $self_url !== '')
        ? pg_sw_ai_page_url($self_url) : '';
    $extra = array_merge($extra, pg_sw_ai_tokens($ai_page_url, $cfg));
    // Links to another page, valued from the record, in the record template
    // and the surround alike. With no record to show every value is empty
    // and such a link opens the bare page.
    $extra += pg_sw_page_link_values($page_links, $form_row, $custom_source, array(
        'form_item_view'    => ($extra['form_item_view'] !== '#') ? (string)$extra['form_item_view'] : '',
        'submitted_form_id' => (string)$extra['submitted_form_id'],
        'submitter_user_id' => (string)$extra['submitter_user_id'],
    ));

    // ── Apply tokens to template + static_html (single render, NO loop) ──
    $rendered = pg_sw_hide_empty_fields(pg_sw_sweep_tokens(pg_sw_apply_tokens($loop_template, $form_row, $custom, $extra)));
    $static   = $static_html;
    if ($static !== '') {
        $static = pg_sw_hide_empty_fields(pg_sw_sweep_tokens(pg_sw_apply_tokens($static, $form_row, $custom, $extra)));
    }

    // Sent from this browser session: the page a form hands its visitor to
    // after they submit (a thank-you, the first message of a conversation).
    $own_codes = (isset($_SESSION['software']['submitted_form_reference_codes']) && is_array($_SESSION['software']['submitted_form_reference_codes']))
        ? $_SESSION['software']['submitted_form_reference_codes'] : array();
    $flags = array(
        'has_new_account'        => $found_and_allowed && $extra['__new_account_email'] !== '',
        'has_set_password_link'  => $found_and_allowed && $extra['__new_account_set_password_url'] !== '',
        'record_found'           => (bool)$found_and_allowed,
        'record_not_found'       => !$found_and_allowed,
        'submitted_this_session' => $found_and_allowed && in_array((string)$form_row['reference_code'], array_map('strval', $own_codes), true),
        'can_edit'               => $found_and_allowed && $extra['__edit_url'] !== '',
        'has_ai_links'           => $ai_page_url !== '',
    );
    // The pencil on the record itself, for a design that places no Edit
    // button of its own.
    if ($flags['can_edit'] && strpos($loop_template . $static_html, '^^__edit_url^^') === false) {
        $rendered = pg_sw_add_edit_chip($rendered, pg_sw_backend_edit_url('edit_submitted_form.php', (int)$form_row['id']), lang('Edit the record'), 'forms');
    }
    if ($static === '') return _pg_sw_resolve_visibility($rendered, $flags);
    return _pg_sw_resolve_visibility(str_replace('<!--pg-loop-slot-->', $rendered, $static), $flags);
}

// Does $node stand for the no-record state of a form_item_view widget:
// shown only when there is no record, or carrying the not-found message?
function _pg_sw_is_not_found_node($node)
{
    $b = (is_array($node) && isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    return (isset($b['eo_visible_if']) && $b['eo_visible_if'] === 'record_not_found')
        || (isset($b['text']) && in_array($b['text'], array('not_found', '__not_found_message'), true));
}

// $nodes cut to what a page with no record to show keeps: the elements for
// that state (whole), the message area, and the elements around them.
// Anything shown only in another state goes: with no record, every other
// flag a form_item_view widget knows is off.
function _pg_sw_keep_for_not_found($nodes)
{
    $out = array();
    foreach ((array)$nodes as $node) {
        if (!is_array($node)) continue;
        if (_pg_sw_is_not_found_node($node)
            || (isset($node['type'], $node['props']['contentType']) && $node['type'] === 'content' && $node['props']['contentType'] === 'messages')) {
            $out[] = $node;
            continue;
        }
        if (!empty($node['props']['_bindings']['eo_visible_if'])) continue;
        if (empty($node['children']) || !is_array($node['children'])) continue;
        $kept = _pg_sw_keep_for_not_found($node['children']);
        if (!$kept) continue;
        $node['children'] = $kept;
        $out[] = $node;
    }
    return $out;
}

// A form_item_view tree as it shows when there is no record: the record
// template (the main loop_area's children; the whole widget when it has no
// main loop_area) cut by _pg_sw_keep_for_not_found(), the surround as
// designed. A list inside a record (a loop_area with props.source) is not
// the record template. Returns whether the tree still shows the no-record
// state anywhere - a not-found message, an element shown only then. When it
// does not, the renderer writes the message itself.
function pg_sw_prune_record_layout(&$tree)
{
    if (!is_array($tree)) return false;
    $cut = function (&$node) use (&$cut) {
        if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) return false;
        foreach ($node['children'] as $i => $child) {
            if (pg_sw_list_is_sourced($child)) continue;
            if (isset($child['type']) && $child['type'] === 'loop_area') {
                $node['children'][$i]['children'] = _pg_sw_keep_for_not_found(isset($child['children']) ? $child['children'] : array());
                return true;
            }
            if ($cut($node['children'][$i])) return true;
        }
        return false;
    };
    if (!$cut($tree) && !empty($tree['children'])) {
        $tree['children'] = _pg_sw_keep_for_not_found($tree['children']);
    }

    $shows = function ($node) use (&$shows) {
        if (!is_array($node)) return false;
        if (_pg_sw_is_not_found_node($node)) return true;
        foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
            if ($shows($child)) return true;
        }
        return false;
    };
    return $shows($tree);
}

// The <title> of a page that shows a record through its form_item_view
// widget, from the widget's settings: the value of the field it names
// (title_field, a field of the form or a standard field such as the
// reference code), else $rss_title (the form's RSS title fields), followed
// by title_suffix ("| Inbox"). '' when there is no text, and the page keeps
// its own title. $values: field name => the record's value, as plain text.
function pg_sw_record_title($cfg, $values, $rss_title = '')
{
    $cfg   = is_array($cfg) ? $cfg : array();
    $field = isset($cfg['title_field']) ? strtolower(trim((string)$cfg['title_field'])) : '';
    $title = '';
    if ($field !== '' && is_array($values) && isset($values[$field])) {
        $title = trim(preg_replace('/\s+/u', ' ', (string)$values[$field]));
    }
    if ($title === '') $title = trim((string)$rss_title);
    if ($title === '') return '';

    $suffix = isset($cfg['title_suffix']) ? trim((string)$cfg['title_suffix']) : '';
    if ($suffix !== '' && function_exists('pg_tr_text')) $suffix = trim((string)pg_tr_text($suffix, 'text', 'seo'));
    return ($suffix !== '') ? $title . ' ' . $suffix : $title;
}

// The in-browser summary (assets/js/pg_ai_summary.js), bound rather than
// hand-wired:
//   button   _bindings.action = 'ai_summarize' → data-pg-ai-summarize; a
//            plain button (a link keeps no address to follow), shown by the
//            script only in a browser that can summarize
//   any      _bindings.text   = '__ai_summary' → data-pg-ai-summary, the box
//            the summary is written into, shown once there is one
// Both start hidden twice over: by the attribute, and by d-none for a design
// whose display utilities (d-flex and the like) would show a [hidden]
// element anyway. The script takes both away.
function _pg_sw_apply_ai_summary_bindings(&$node)
{
    if (!is_array($node)) return;
    $b    = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    $type = isset($node['type']) ? $node['type'] : '';
    $tag  = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';

    $mark = function (&$n, $add) {
        $kept = array();
        foreach ((isset($n['props']['_attrs']) && is_array($n['props']['_attrs'])) ? $n['props']['_attrs'] : array() as $a) {
            if (is_array($a) && isset($a['name']) && !array_key_exists(strtolower((string)$a['name']), $add)) $kept[] = $a;
        }
        foreach ($add as $name => $value) $kept[] = array('name' => $name, 'value' => $value);
        $n['props']['_attrs'] = $kept;
        $class = isset($n['props']['cssClass']) ? trim((string)$n['props']['cssClass']) : '';
        if (!preg_match('/(^|\s)d-none(\s|$)/', $class)) $n['props']['cssClass'] = trim($class . ' d-none');
    };

    if (isset($b['action']) && $b['action'] === 'ai_summarize') {
        $is_btn  = ($type === 'component' && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn');
        $is_link = ($type === 'semantic' && $tag === 'a')
                || ($type === 'content' && isset($node['props']['contentType']) && $node['props']['contentType'] === 'link');
        if ($is_btn) {
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'button';
            $mark($node, array('data-pg-ai-summarize' => '', 'hidden' => ''));
        } elseif ($type === 'semantic' && $tag === 'button') {
            $mark($node, array('type' => 'button', 'data-pg-ai-summarize' => '', 'hidden' => ''));
        } elseif ($is_link) {
            $node['props']['href'] = '#';
            $mark($node, array('role' => 'button', 'data-pg-ai-summarize' => '', 'hidden' => ''));
        }
    }
    if (isset($b['text']) && $b['text'] === '__ai_summary') {
        $mark($node, array('data-pg-ai-summary' => '', 'aria-live' => 'polite', 'hidden' => ''));
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) _pg_sw_apply_ai_summary_bindings($child);
        unset($child);
    }
}

// The address of the page being drawn, absolute and in the language it is
// shown in. $path is the page's address under PATH; on a language page the
// router has cut the language's directory off the request (LANGUAGE_PATH),
// so it is put back.
function pg_sw_ai_page_url($path)
{
    $path = (string)$path;
    if ($path === '' || !defined('URL_SCHEME') || !defined('HOSTNAME_SETTING')) return '';
    $base = defined('PATH') ? (string)PATH : '/';
    if (defined('LANGUAGE_PATH') && strpos($path, $base) === 0) {
        $path = LANGUAGE_PATH . substr($path, strlen($base));
    }
    return URL_SCHEME . HOSTNAME_SETTING . $path;
}

// The question the AI assistants' links ask about a page: the widget's own
// wording (ai_prompt_text, in the page's language when the Translations
// screen has it) or the software's. {url} stands for the page's address; a
// wording without it gets the address at its end.
function pg_sw_ai_prompt($cfg, $page_url)
{
    $prompt = (is_array($cfg) && isset($cfg['ai_prompt_text']) && is_string($cfg['ai_prompt_text'])) ? trim($cfg['ai_prompt_text']) : '';
    if ($prompt !== '' && function_exists('pg_tr_text')) $prompt = trim((string)pg_tr_text($prompt));
    if ($prompt === '') $prompt = lang('Summarize the main points of this article: {url}');
    return (strpos($prompt, '{url}') !== false) ? str_replace('{url}', $page_url, $prompt) : $prompt . ' ' . $page_url;
}

// The addresses that open an AI assistant with $prompt already typed in,
// by service. Gemini takes no question from its address, so Google's AI
// Mode answers for Google.
function pg_ai_assistant_urls($prompt)
{
    $q = rawurlencode((string)$prompt);
    return array(
        'chatgpt'    => 'https://chatgpt.com/?q=' . $q,
        'claude'     => 'https://claude.ai/new?q=' . $q,
        'perplexity' => 'https://www.perplexity.ai/search/new?q=' . $q,
        'google'     => 'https://www.google.com/search?udm=50&q=' . $q,
    );
}

// A record's AI tokens: the assistants' links (empty without an address)
// and the in-browser summary's box, which the page carries empty
// (pg_ai_summary.js writes into it).
function pg_sw_ai_tokens($page_url, $cfg = array())
{
    $links = ($page_url !== '') ? pg_ai_assistant_urls(pg_sw_ai_prompt($cfg, $page_url)) : array();
    $link  = function ($service) use ($links) {
        return isset($links[$service]) ? h($links[$service]) : '';
    };
    return array(
        '__ai_summary'        => '',
        '__ai_chatgpt_url'    => $link('chatgpt'),
        '__ai_claude_url'     => $link('claude'),
        '__ai_perplexity_url' => $link('perplexity'),
        '__ai_google_url'     => $link('google'),
    );
}

/**
 * The in-browser summary's script, for a page whose HTML holds a button
 * bound to the ai_summarize action (data-pg-ai-summarize). Asked once every
 * shared component and system widget is drawn in (get_page_content.php), so
 * the file follows the button wherever it was placed. The labels are in the
 * page's language.
 *
 * @param string $content page HTML
 * @return string
 */
function pg_ai_summary_inject($content)
{
    if (strpos($content, 'data-pg-ai-summarize') === false) return $content;
    if (strpos($content, '/assets/js/pg_ai_summary.js') !== false) return $content;

    // pg_ai_summary.js falls back to the English key.
    $labels = array(
        'The browser is summarizing the post...' => lang('The browser is summarizing the post...'),
        'Downloading the AI model: {n}%'          => lang(array('string' => 'Downloading the AI model: {var:1}%', 'vars' => array('{n}'))),
        'The summary could not be made in this browser.' => lang('The summary could not be made in this browser.'),
        'Summarized on your device by the browser\'s built-in AI. It may contain mistakes.' => lang('Summarized on your device by the browser\'s built-in AI. It may contain mistakes.'),
    );
    $file = '<script>window.pgAiSummaryLabels = ' . encode_json($labels) . ';</script>' . "\n"
          . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_ai_summary.js?v='
          . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_ai_summary.js') . '" defer></script>' . "\n";

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}

// Render a system widget's my_account view.  Loop-area aware:
//
//   1. _split_widget_tree pulls the loop_area out of the tree.
//   2. The static portion is rendered ONCE — profile info, header/footer.
//   3. If the widget has a loop_area AND show_submissions is enabled, each
//      of the user's form submissions gets a clone of the loop template with
//      per-submission tokens replaced.
//   4. If there is no loop_area, the entire tree acts as the profile display
//      (backwards-compat; no submission list rendered).
//
// If the visitor is NOT logged in:
//   - If redirect_to_login is true and login_page_id is configured → redirect.
//   - Otherwise render with ^^__not_logged_in^^ = not_logged_in_message; all
//     other tokens collapse to empty, the loop outputs nothing.
//
// Static tokens (always available when logged in):
//   ^^account_id^^               — user.user_id
//   ^^username^^                 — user.user_username
//   ^^email_address^^            — user.user_email
//   ^^full_name^^                — contacts.first_name + last_name
//   ^^first_name^^               — contacts.first_name
//   ^^last_name^^                — contacts.last_name
//   ^^registered_date_and_time^^ — user.user_timestamp, with the same optional
//                                  %%format%% suffix the form widgets take
//   ^^avatar_url^^               — contacts.image URL (or empty)
//   ^^member_id^^                — contacts.member_id
//   ^^member^^                   — "1" if active member, "0" otherwise
//   ^^not_logged_in^^            — empty when logged in, cfg message when not
//
// Per-submission loop tokens (loop_area + show_submissions) — named as the two
// form widgets name them, so a row design carries over:
//   ^^form_name^^                — page.page_title of the form
//   ^^reference_code^^           — forms.reference_code
//   ^^submitted_date_and_time^^  — forms.submitted_timestamp (+ %%format%%)
//   ^^form_item_view^^           — submissions_detail_page?r=<code> (or '#')
//   ^^submitted_form_id^^        — forms.id
//
// Config (`system_region_config`) keys used here:
//   not_logged_in_message      string  shown via ^^__not_logged_in^^ when not logged in
//   redirect_to_login          bool    redirect to login_page_id when not logged in
//   login_page_id              int     page.page_id of the login page
//   show_submissions           bool    enable loop_area submission list
//   filter_form_id             int     0 = all forms, >0 = one specific form
//   submissions_detail_page_id int     page_id of the form_item_view detail page
//   staff_page_id              int     page_id of the staff page (^^__staff_url^^, is_staff)
function _render_system_widget_my_account($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Config resolution ─────────────────────────────────────────────────
    $not_logged_in_message = (isset($cfg['not_logged_in_message']) && is_string($cfg['not_logged_in_message']) && $cfg['not_logged_in_message'] !== '')
                                ? $cfg['not_logged_in_message']
                                : lang('You must be logged in to view this page.');
    $redirect_to_login  = !empty($cfg['redirect_to_login']);
    $login_page_id      = isset($cfg['login_page_id']) ? (int)$cfg['login_page_id'] : 0;
    $show_submissions   = !empty($cfg['show_submissions']);
    $filter_form_id     = isset($cfg['filter_form_id']) ? (int)$cfg['filter_form_id'] : 0;
    $detail_page_id     = isset($cfg['submissions_detail_page_id']) ? (int)$cfg['submissions_detail_page_id'] : 0;

    // ── Decode + split the widget tree ────────────────────────────────────
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Messages: liveform 'my_account', as on the legacy page — the notices
    // the account screens send here (password changed, order retrieved,
    // reordered, deleted). Unnamed, this widget printed and used up the
    // messages of every other widget on the page drawn after it.
    _pg_inject_messages_node($tree_decoded, 'my_account');

    // Links to the other account pages and the order history
    // (widgets_account.php): a link with nowhere to go leaves with its node,
    // the list lands in its section.
    $logged_in = (defined('USER_LOGGED_IN') && USER_LOGGED_IN === true);
    $extras    = _pg_my_account_extras($logged_in, $cfg);
    _pg_member_drop_empty_links($tree_decoded, $extras['links']);
    pg_cf_apply_section_bindings($tree_decoded, $extras['sections']);
    _eo_apply_visibility_bindings($tree_decoded, $extras['flags']);
    $link_values = array();
    foreach ($extras['links'] as $k => $v) $link_values[$k] = h($v);

    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // ── Auth check ────────────────────────────────────────────────────────

    if (!$logged_in) {
        // Redirect mode: send to login page when configured and accessible.
        // A page drawn inside another request (SEO pass, prototype frame)
        // draws the signed-out view instead: go() would send a Location
        // header and end that request in the middle of the render.
        $background = function_exists('pg_seo_rendering') && pg_seo_rendering();
        if ($redirect_to_login && $login_page_id > 0 && !$background) {
            $login_page_name = (string)db_value(
                "SELECT page_name FROM page WHERE page_id = '" . (int)$login_page_id . "' LIMIT 1"
            );
            if ($login_page_name !== '') {
                $login_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($login_page_name);
                go($login_url);
                return '';
            }
        }

        // A tree with no place for ^^not_logged_in^^ (the starter has none)
        // showed a signed-out visitor an empty profile card and one blank
        // row of the submissions list: say why instead, with the way in.
        if (strpos(pg_sw_cond_tree_json($tree_decoded), 'not_logged_in') === false) {
            $login_url = function_exists('_pg_acct_page_url') ? _pg_acct_page_url($login_page_id, 'login') : '';
            return _pg_sw_render_message_only($tree_decoded, 'my_account',
                h($not_logged_in_message)
                . ($login_url !== '' ? ' <a href="' . h($login_url) . '" class="alert-link">' . h(lang('Log In')) . '</a>' : ''),
                'warning');
        }

        // Render with "not logged in" token map; loop_area outputs nothing.
        $values = array(
            'account_id'               => '',
            'username'                 => '',
            'email_address'            => '',
            'full_name'                => '',
            'first_name'               => '',
            'last_name'                => '',
            'registered_date_and_time' => '',
            'avatar_url'               => '',
            'member_id'                => '',
            'member'                   => '0',
            'not_logged_in'            => $not_logged_in_message,
            '__display_name'           => '',
        ) + $link_values;
        // The list rows belong to a signed-in member: none for a visitor.
        $rendered = ($static_html !== '') ? '' : pg_sw_sweep_tokens(pg_sw_apply_tokens($loop_template, array(), array(), $values));
        $static   = $static_html;
        if ($static !== '') {
            $static = pg_sw_sweep_tokens(pg_sw_apply_tokens($static, array(), array(), $values));
        }
        if ($static === '') return $rendered;
        return str_replace('<!--pg-loop-slot-->', $rendered, $static);
    }

    // ── Fetch logged-in user data (user + contact JOIN) ───────────────────
    $uid = defined('USER_ID') ? (int)USER_ID : 0;
    $user_row = null;
    if ($uid > 0) {
        $user_row = db_item(
            "SELECT
                user.user_id,
                user.user_username,
                user.user_email,
                FROM_UNIXTIME(user.user_timestamp) AS registered_date_and_time,
                contacts.first_name,
                contacts.last_name,
                contacts.image    AS avatar_image,
                contacts.member_id
             FROM user
             LEFT JOIN contacts ON contacts.id = user.user_contact
             WHERE user.user_id = '" . e($uid) . "'
             LIMIT 1"
        );
    }

    $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    // Build full_name from contact record; fall back gracefully.
    $fn = ($user_row && isset($user_row['first_name'])) ? trim((string)$user_row['first_name']) : '';
    $ln = ($user_row && isset($user_row['last_name']))  ? trim((string)$user_row['last_name'])  : '';
    $full_name = trim($fn . ' ' . $ln);

    // Avatar: contacts.image stores the filename relative to the site root.
    $avatar_url = '';
    if ($user_row && !empty($user_row['avatar_image'])) {
        $avatar_url = $output_base . $user_row['avatar_image'];
    }

    // The registration date keeps its MySQL shape so the format suffix can work
    // on it; it is declared below as a typed field rather than pre-formatted here.
    $account_row = array(
        'registered_date_and_time' => ($user_row && isset($user_row['registered_date_and_time']))
                                          ? (string)$user_row['registered_date_and_time'] : '',
    );
    $account_fields = array(
        'registered_date_and_time' => array('label' => lang('Registration Date & Time'), 'sql' => '', 'type' => 'date and time', 'join' => array()),
    );

    // Base token map shared by the static portion and every loop row. Values
    // are escaped here because they go in as finished strings — a contact's
    // own first name is not markup.
    $values = array(
        'account_id'    => $user_row ? (string)$user_row['user_id']       : (string)$uid,
        'username'      => h($user_row ? (string)$user_row['user_username'] : (string)(defined('USER_USERNAME')      ? USER_USERNAME      : '')),
        'email_address' => h($user_row ? (string)$user_row['user_email']    : (string)(defined('USER_EMAIL_ADDRESS') ? USER_EMAIL_ADDRESS : '')),
        'full_name'     => h($full_name),
        'first_name'    => h($fn),
        'last_name'     => h($ln),
        'avatar_url'    => h($avatar_url),
        'member_id'     => h(($user_row && !empty($user_row['member_id'])) ? (string)$user_row['member_id'] : ''),
        'member'        => (defined('USER_MEMBER') && USER_MEMBER === true) ? '1' : '0',
        // The name to greet the member by: their full name, else their user name.
        '__display_name' => h($full_name !== '' ? $full_name : ($user_row ? (string)$user_row['user_username'] : (defined('USER_USERNAME') ? (string)USER_USERNAME : ''))),
        'not_logged_in' => '',
    ) + $link_values;
    // The member's own allow-listed account values (pg_sw_entity_fields('account')).
    $values += pg_sw_entity_account_tokens($uid, pg_sw_cond_tree_json($tree_decoded));

    // A list of the member's orders (widgets_lists_order.php): the order
    // history's rows, filed only when the tree drew one.
    if (pg_sw_list_wanted('account')) {
        pg_sw_list_publish('account', pg_sw_list_account_rows($values['account_id'], $extras['orders'], $extras['order_view_url'], pg_sw_default_date_format('date')));
    }

    // ── No loop_area — render entire tree once with account tokens ─────────
    if ($split['loop_children'] === null) {
        return pg_sw_sweep_tokens(
            pg_sw_apply_tokens($loop_template, $account_row, array(), $values, $account_fields)
        );
    }

    // ── Loop_area present — build submission rows ─────────────────────────
    $loop_rendered = '';
    if ($show_submissions && $loop_template !== '') {
        // Resolve the detail page URL base, and the form its record widget
        // shows: only that form's records may take their pretty address
        // there (pg_sw_route_record_address() resolves no other).
        $detail_page_name = '';
        $detail_form_id   = 0;
        if ($detail_page_id > 0) {
            $detail_page_name = (string)db_value(
                "SELECT page_name FROM page WHERE page_id = '" . e($detail_page_id) . "' LIMIT 1"
            );
            $detail_widget = pg_sw_page_widget($detail_page_id, 'form_item_view');
            if ($detail_widget) $detail_form_id = pg_sw_form_widget_form_page_id($detail_widget['cfg']);
        }

        // Optional single-form filter — validate that page_id exists and is a custom form.
        $form_where = '';
        if ($filter_form_id > 0) {
            $valid_pid = (int)db_value(
                "SELECT page_id FROM page
                 WHERE page_id = '" . e($filter_form_id) . "'
                   AND " . pg_form_page_sql('page') . "
                 LIMIT 1"
            );
            if ($valid_pid > 0) {
                $form_where = " AND forms.page_id = '" . e($valid_pid) . "'";
            }
        }

        // Fetch at most 200 completed submissions owned by this user.
        $submission_rows = db_items(
            "SELECT
                forms.id,
                forms.page_id,
                forms.reference_code,
                forms.address_name,
                FROM_UNIXTIME(forms.submitted_timestamp) AS submitted_date_and_time,
                page.page_title AS form_name
             FROM forms
             LEFT JOIN page ON forms.page_id = page.page_id
             WHERE forms.user_id = '" . e($uid) . "'
               AND forms.complete = 1
               $form_where
             ORDER BY forms.submitted_timestamp DESC
             LIMIT 200"
        );

        if (is_array($submission_rows)) {
            foreach ($submission_rows as $sr) {
                // Build detail link — the same ^^form_item_view^^ the two form
                // widgets carry, so a row design moves between them.
                $detail_url = '#';
                if ($detail_page_name !== '') {
                    $sr_pretty  = ($detail_form_id > 0) && ((int)$sr['page_id'] === $detail_form_id)
                                  && pg_sw_form_pretty_urls($detail_form_id);
                    $detail_url = pg_sw_record_url($detail_page_name, $sr['reference_code'], $sr['address_name'], $sr_pretty, $output_base);
                }

                // Per-row tokens extend the base account map. The row itself
                // answers the two typed standard fields (reference_code and the
                // submission date), so a format suffix works here as well.
                $row_extra = array_merge($values, array(
                    'form_name'         => h(isset($sr['form_name']) ? (string)$sr['form_name'] : ''),
                    'form_item_view'    => $detail_url,
                    // The name the settings once gave it; designs written
                    // with it keep their links.
                    '__detail_url'      => $detail_url,
                    'submitted_form_id' => (string)$sr['id'],
                ));

                $row_html = pg_sw_sweep_tokens(
                    pg_sw_apply_tokens($loop_template, array_merge($account_row, $sr), array(), $row_extra, $account_fields)
                );

                // Uniquify Bootstrap component IDs per loop iteration so tabs, collapses
                // and dropdowns in different rows don't share the same id/target.
                // Using both widget_id and record's DB id as suffix prevents collisions
                // across multiple instances of the same widget on the same page.
                $row_html = pg_sw_uniquify_row_ids($row_html, $widget_id, (int)$sr['id']);

                $loop_rendered .= $row_html;
            }
        }
    }

    // ── Apply account tokens to static portion + assemble output ──────────
    $static = $static_html;
    if ($static !== '') {
        $static = pg_sw_sweep_tokens(
            pg_sw_apply_tokens($static, $account_row, array(), $values, $account_fields)
        );
    }

    if ($static === '') return $loop_rendered;
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static);
}

// ── Member widgets: login_form, forgot_password ─────────────────────────────
//
// The designer draws the controls; the server owns everything that has to be
// right for the legacy processors to accept the post. Same model as the
// custom form widget: a control IS a field, its `name` attribute is the
// contract, and the names are the ones index.php / forgot_password.php have
// always read (email, password, remember_me, screen). The rendered widget is
// wrapped in the <form> those scripts expect, with the hidden fields they
// need; the designer never draws a form tag.
//
// Errors and notices come back on the processors' liveforms ('login',
// 'forgot_password') and are printed by the widget's Messages node, scoped to
// that form. A feature that is switched off (remember me, forgot-password
// link, Google sign-in) leaves no element behind — the control, its label and
// an emptied wrapper are dropped rather than rendered inert.

// The `name` attribute of a semantic form control, or '' for anything else.
function _pg_member_control_name($node)
{
    if (!is_array($node) || !isset($node['type']) || $node['type'] !== 'semantic') return '';
    $tag = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';
    if (!in_array($tag, array('input', 'select', 'textarea', 'button'), true)) return '';
    $attrs = _pg_cf_node_attrs($node);
    return isset($attrs['name']) ? (string)$attrs['name'] : '';
}

// Set (or remove, with null) one attribute in a node's `_attrs` list.
function _pg_member_set_attr(&$node, $attr, $value)
{
    if (!isset($node['props']['_attrs']) || !is_array($node['props']['_attrs'])) $node['props']['_attrs'] = array();
    $node['props']['_attrs'] = array_values(array_filter($node['props']['_attrs'], function ($a) use ($attr) {
        return !(is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === $attr);
    }));
    if ($value !== null) $node['props']['_attrs'][] = array('name' => $attr, 'value' => $value);
}

// Fill the control named $name: a text-like input gets `value`, a checkbox
// gets `checked` (or loses it). Password inputs are never refilled.
function _pg_member_fill_control(&$tree, $name, $value, $checked = null)
{
    if (!is_array($tree)) return;
    if (_pg_member_control_name($tree) === $name) {
        $attrs = _pg_cf_node_attrs($tree);
        $type  = strtolower((string)(isset($attrs['type']) ? $attrs['type'] : 'text'));
        $tag   = strtolower((string)$tree['props']['tag']);
        if ($type === 'checkbox' || $type === 'radio') {
            _pg_member_set_attr($tree, 'checked', $checked ? '' : null);
        } elseif ($tag === 'textarea') {
            $tree['props']['text'] = (string)$value;
        } elseif ($tag === 'input' && $type !== 'password' && $type !== 'file') {
            if ((string)$value !== '') _pg_member_set_attr($tree, 'value', (string)$value);
        }
        return;
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$c) _pg_member_fill_control($c, $name, $value, $checked);
        unset($c);
    }
}

// Remove the controls $match says yes to, together with the <label for> that
// pointed at each and any wrapper left with nothing in it — an empty
// `.form-check` under a heading reads as a broken form, not a hidden option.
// $match receives (tag, attrs, node) and returns true to drop.
function _pg_member_drop_controls(&$tree, $match)
{
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) return;
    $had_children = count($tree['children']) > 0;
    $drop_ids = array();
    $kept = array();
    foreach ($tree['children'] as $child) {
        if (!is_array($child)) { $kept[] = $child; continue; }
        $is_control = false;
        if (isset($child['type']) && $child['type'] === 'semantic' && isset($child['props']['tag'])) {
            $tag = strtolower((string)$child['props']['tag']);
            if (in_array($tag, array('input', 'select', 'textarea', 'button'), true)) {
                $attrs = _pg_cf_node_attrs($child);
                if ($match($tag, $attrs, $child)) {
                    $is_control = true;
                    if (!empty($attrs['id'])) $drop_ids[(string)$attrs['id']] = true;
                }
            }
        } elseif (isset($child['type']) && $child['type'] === 'component'
                  && isset($child['props']['componentType']) && $child['props']['componentType'] === 'btn'
                  && $match('btn', array(), $child)) {
            $is_control = true;
        }
        if (!$is_control) $kept[] = $child;
    }
    if (!empty($drop_ids)) {
        $kept = array_values(array_filter($kept, function ($c) use ($drop_ids) {
            if (!is_array($c) || !isset($c['props']['tag']) || strtolower((string)$c['props']['tag']) !== 'label') return true;
            $la = _pg_cf_node_attrs($c);
            return !(isset($la['for']) && isset($drop_ids[(string)$la['for']]));
        }));
    }
    $tree['children'] = $kept;
    foreach ($tree['children'] as &$c) _pg_member_drop_controls($c, $match);
    unset($c);
    // A wrapper that only ever held what was dropped goes with it.
    $tree['children'] = array_values(array_filter($tree['children'], function ($c) {
        return !(is_array($c) && !empty($c['props']['_pg_member_empty']));
    }));
    if ($had_children && empty($tree['children']) && isset($tree['type']) && $tree['type'] === 'semantic') {
        $tree['props']['_pg_member_empty'] = true;
    }
}

// Drop the control named $name (see _pg_member_drop_controls).
function _pg_member_drop_control(&$tree, $name)
{
    _pg_member_drop_controls($tree, function ($tag, $attrs) use ($name) {
        return isset($attrs['name']) && (string)$attrs['name'] === $name;
    });
}

// Drop every control and button — the form has done its job (a confirmation
// screen) and only the words around it stay.
function _pg_member_drop_all_controls(&$tree)
{
    _pg_member_drop_controls($tree, function ($tag, $attrs, $node) { return true; });
}

// Drop links whose href is bound to a token that resolved to '': a feature
// that is off must not leave an <a href=""> behind. Covers semantic <a>,
// content links and button components. $tokens is token => resolved value.
function _pg_member_drop_empty_links(&$tree, $tokens)
{
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) return;
    $kept = array();
    foreach ($tree['children'] as $child) {
        if (is_array($child) && isset($child['props']['_bindings']['href'])) {
            $tok = (string)$child['props']['_bindings']['href'];
            if (array_key_exists($tok, $tokens) && (string)$tokens[$tok] === '') continue;
        }
        $kept[] = $child;
    }
    $tree['children'] = $kept;
    foreach ($tree['children'] as &$c) _pg_member_drop_empty_links($c, $tokens);
    unset($c);
}

// Site name for the ^^__site_name^^ token: the organization name from the
// config row (init.php defines it). There is no `settings` table in this
// product; a query against one is fatal, not empty.
function _pg_member_site_name()
{
    return defined('ORGANIZATION_NAME') ? (string)ORGANIZATION_NAME : '';
}

// Front-end address of a page id, '' when the page is gone.
function _pg_member_page_url($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return '';
    $name = db_value("SELECT page_name FROM page WHERE page_id = '$page_id' LIMIT 1");
    if ($name === '' || $name === null || $name === false) return '';
    return (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path((string)$name);
}

// A same-origin path from the query string, or '' — the caller decides the
// default. pg_safe_redirect_path() falls back to PATH, which is not an
// answer to "did the visitor give us one".
function _pg_member_query_path($key)
{
    if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) return '';
    $v = (string)$_GET[$key];
    if ($v === '') return '';
    return (pg_safe_redirect_path($v, '/__none__') === '/__none__') ? '' : $v;
}

// Wrap the rendered widget in the <form> a legacy processor expects. The
// hidden fields ride along with the CSRF token; the controls are the
// designer's and nothing is added under them.
function _pg_member_form_wrap($rendered, $action, $hidden, $class)
{
    // Bootstrap's own validation, as on the custom form: `novalidate` stops
    // the browser's bubbles, `needs-validation` + the shared snippet show the
    // styled :invalid feedback on the first submit attempt. The server checks
    // the same `required` again.
    $open = '<form action="' . h($action) . '" method="post" novalidate class="needs-validation pg-cf-form ' . h($class) . '">' . get_token_field();
    foreach ($hidden as $k => $v) {
        $open .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
    }
    $script = function_exists('pg_cf_validation_script') ? pg_cf_validation_script() : '';
    return $open . $rendered . $script . '</form>';
}

// Replace ^^tokens^^ (longest key first) and strip whatever is left. A
// template with a format after a token (^^x^^%%upper%%) is filled in one
// typed pass instead (pg_sw_fill_typed()), so the format is read, never
// printed; one without fills as it always did.
function _pg_member_apply_tokens($rendered, $values)
{
    if (is_string($rendered) && strpos($rendered, '^^%%') !== false) {
        return pg_sw_sweep_tokens(pg_sw_fill_typed($rendered, $values));
    }
    $keys = array_keys($values);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($keys as $k) {
        $rendered = str_replace('^^' . $k . '^^', (string)$values[$k], $rendered);
    }
    return pg_sw_sweep_tokens($rendered);
}

// A signed-in visitor on a sign-in / sign-up page. The widget keeps its
// shape - container, heading, the Messages block where the designer put it
// - and only the form goes: every control, button, link and server block is
// dropped and the word is printed as a notice on the form's own liveform,
// so it lands exactly where errors would have. $sections are the widget's
// section names, $tokens its link tokens; both are emptied so nothing is
// left pointing at a sign-in the visitor no longer needs.
function _pg_member_render_signed_in(&$tree, $form, $form_name, $send_to, $page_url, $sections, $tokens, $values)
{
    $software   = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    // The token lets logout.php sign the member out at once instead of asking
    // for confirmation first.
    $logout_url = $software . '/logout.php?send_to=' . urlencode($page_url) . '&token=' . urlencode($_SESSION['software']['token'] ?? '');

    $notice = h(lang('You are already signed in.'));
    if ($send_to !== '') {
        $notice .= ' <a href="' . h($send_to) . '" class="alert-link">' . h(lang('Continue')) . '</a>';
    }
    $notice .= ' <a href="' . h($logout_url) . '" class="alert-link" rel="nofollow">' . h(lang('Logout')) . '</a>';
    $form->add_notice($notice);

    _pg_member_drop_all_controls($tree);
    $empty_sections = array();
    foreach ($sections as $name) $empty_sections[$name] = '';
    pg_cf_apply_section_bindings($tree, $empty_sections);
    $empty_tokens = array();
    foreach ($tokens as $name) $empty_tokens[$name] = '';
    _pg_member_drop_empty_links($tree, $empty_tokens);
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, $form_name);

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $form->remove();
    return _pg_member_apply_tokens($rendered, $values);
}

// Every semantic form control in the tree, in document order:
// array('name' => ..., 'node' => array, 'contact_field' => ...). Controls
// without a name are skipped; a submit button is not a field.
function _pg_member_controls($tree)
{
    $out = array();
    $walk = function ($n) use (&$walk, &$out) {
        if (!is_array($n)) return;
        $name = _pg_member_control_name($n);
        if ($name !== '' && strtolower((string)$n['props']['tag']) !== 'button') {
            $attrs = _pg_cf_node_attrs($n);
            $type  = strtolower((string)(isset($attrs['type']) ? $attrs['type'] : 'text'));
            if (!in_array($type, array('submit', 'reset', 'button', 'image'), true)) {
                $cf = (isset($n['props']['_cf']) && is_array($n['props']['_cf'])) ? $n['props']['_cf'] : array();
                $out[] = array(
                    'name'          => $name,
                    'type'          => $type,
                    'node'          => $n,
                    'contact_field' => isset($cf['contact_field']) ? (string)$cf['contact_field'] : '',
                );
            }
        }
        if (!empty($n['children']) && is_array($n['children'])) {
            foreach ($n['children'] as $c) $walk($c);
        }
    };
    $walk($tree);
    return $out;
}

// Put what the visitor typed back into every control the liveform knows,
// by name. Passwords are never refilled; a checkbox is checked when the
// stored value matches its own value (or is truthy when it has none).
function _pg_member_fill_from_form(&$tree, $form, $skip = array())
{
    foreach (_pg_member_controls($tree) as $c) {
        if (in_array($c['name'], $skip, true)) continue;
        if (!$form->field_in_session($c['name'])) continue;
        $value = $form->get_field_value($c['name']);
        if ($c['type'] === 'checkbox' || $c['type'] === 'radio') {
            $attrs = _pg_cf_node_attrs($c['node']);
            $own   = isset($attrs['value']) ? (string)$attrs['value'] : '';
            $checked = ($own !== '') ? ((string)$value === $own) : ((string)$value !== '' && (string)$value !== '0');
            _pg_member_fill_control($tree, $c['name'], $own, $checked);
        } else {
            _pg_member_fill_control($tree, $c['name'], (string)$value);
        }
    }
}

// Apply $fn (by reference) to every control named $name.
function _pg_member_edit_control(&$tree, $name, $fn)
{
    if (!is_array($tree)) return;
    if (_pg_member_control_name($tree) === $name) {
        $fn($tree);
        return;
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$c) _pg_member_edit_control($c, $name, $fn);
        unset($c);
    }
}

// True when a live page (not in the recycle bin) carries a system widget of
// this type. The Google sign-up gate asks this: a designed registration page
// is an entrance page even though no page_type says so.
function pg_member_widget_published($type)
{
    static $cache = array();
    $type = (string)$type;
    if (isset($cache[$type])) return $cache[$type];
    $cache[$type] = false;

    // page_tree_json arrived with the multi-page editor; on a database the
    // upgrade has not reached, no designed page can carry a widget yet.
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return false;

    $rows = db_items(
        "SELECT id FROM shared_components
         WHERE system_region_config LIKE '%\"regionType\":\"" . e($type) . "\"%'");
    if (!is_array($rows) || count($rows) === 0) return false;

    $binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';
    foreach ($rows as $r) {
        $wid = (int)$r['id'];
        if ($wid <= 0) continue;
        $hit = db_value(
            "SELECT page.page_id FROM page
             WHERE (page.page_tree_json LIKE '%\"sharedId\":" . $wid . ",%'
                 OR page.page_tree_json LIKE '%\"sharedId\":" . $wid . "}%')" . $binned . "
             LIMIT 1");
        if ($hit) { $cache[$type] = true; break; }
    }
    return $cache[$type];
}

// The entrance scripts' extras for a post that came from a member widget
// (registration or membership): the address-book columns its contact-bound
// controls fill, read from the widget's own tree (the visitor names the
// widget, the tree names the columns), and the contact group the widget
// chose. $type is the widget type the caller serves; another type's id is
// ignored.
function pg_member_widget_register_opts($widget_id, $form, $type = 'registration')
{
    $opts = array('contact_fields' => array());
    $widget_id = (int)$widget_id;
    if ($widget_id <= 0) return $opts;

    $row = db_item("SELECT tree_json, system_region_config FROM shared_components WHERE id = '" . $widget_id . "' LIMIT 1");
    if (empty($row)) return $opts;
    $cfg = json_decode((string)$row['system_region_config'], true);
    if (!is_array($cfg) || !isset($cfg['regionType']) || $cfg['regionType'] !== (string)$type) return $opts;
    $tree = json_decode((string)$row['tree_json'], true);
    if (!is_array($tree)) return $opts;

    foreach (_pg_member_controls($tree) as $c) {
        if ($c['contact_field'] === '' || !$form->field_in_session($c['name'])) continue;
        $opts['contact_fields'][$c['contact_field']] = $form->get_field_value($c['name']);
    }
    if (!empty($cfg['contact_group_id']) && (int)$cfg['contact_group_id'] > 0) {
        $opts['contact_group_id'] = (int)$cfg['contact_group_id'];
    }
    return $opts;
}

// Render a 'login_form' system widget.
//
// Posts to <software>/index.php, the same script the legacy login page and
// the login region post to. Controls by name: email, password, remember_me.
// Tokens: ^^__site_name^^, ^^__redirect_url^^, ^^__forgot_password_url^^,
// ^^__register_url^^, ^^__google_signin_url^^. Sections: google_signin
// (every sign-in button that is on: Google, Microsoft, Apple),
// forgot_password_link, register_link. Errors: liveform 'login', printed by
// the Messages node.
//
// cfg: redirect_page_id (after sign-in), show_remember_me (default on),
// forgot_password_page_id, register_page_id (a page carrying the matching
// widget; the legacy page types are the fallback), show_register_link.
function _render_system_widget_login_form($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();

    // Where a successful sign-in lands: the configured page, else what the
    // link that brought the visitor here asked for, else nothing — and
    // send_user_to_login_home() decides (the user's home page, the account
    // page, the control panel).
    $send_to = '';
    if (!empty($cfg['redirect_page_id']) && (int)$cfg['redirect_page_id'] > 0) {
        $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    }
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');
    if ($send_to === '') $send_to = _pg_member_query_path('redirect');

    $lf = new liveform('login');

    // Already signed in: the form would only sign them in again. Editors
    // still see the form in edit mode, so the page can be worked on.
    if ($mode !== 'edit' && function_exists('pg_session_signed_in') && pg_session_signed_in()) {
        return _pg_member_render_signed_in($tree, $lf, 'login', $send_to, $page_url,
            array('google_signin', 'forgot_password_link', 'register_link'),
            array('__forgot_password_url', '__register_url', '__google_signin_url'),
            array('__site_name' => h($site_name), '__redirect_url' => h($send_to)));
    }

    // What the visitor typed, back in the boxes after a refused attempt.
    // index.php keeps the email and blanks the password; the messages node
    // prints the error and then consumes the form, so read first.
    $submitted = $lf->field_in_session('token');
    _pg_member_fill_control($tree, 'email', (string)$lf->get_field_value('email'));

    // Remember me: the site setting first, then the widget's own switch.
    // Checked by default when the visitor asked for it last time (legacy
    // behaviour, read from the cookie), or when they had it on in the attempt
    // that was just refused.
    $remember_ok = defined('REMEMBER_ME') && REMEMBER_ME
                && !(isset($cfg['show_remember_me']) && ($cfg['show_remember_me'] === false || $cfg['show_remember_me'] === 0 || $cfg['show_remember_me'] === '0'));
    if (!$remember_ok) {
        _pg_member_drop_control($tree, 'remember_me');
    } else {
        $checked = $submitted
            ? ((string)$lf->get_field_value('remember_me') === '1')
            : ((isset($_COOKIE['software']['remember_me']) ? (string)$_COOKIE['software']['remember_me'] : '') === 'true');
        _pg_member_fill_control($tree, 'remember_me', '1', $checked);
    }

    // Forgot password: the widget page when one is chosen, else the legacy
    // page type, else the script itself. Off with the site setting.
    $forgot_url = '';
    if (defined('FORGOT_PASSWORD_LINK') && FORGOT_PASSWORD_LINK) {
        if (!empty($cfg['forgot_password_page_id'])) $forgot_url = _pg_member_page_url($cfg['forgot_password_page_id']);
        if ($forgot_url === '') {
            $legacy = function_exists('get_page_type_url') ? get_page_type_url('forgot password') : false;
            $forgot_url = ($legacy !== false && $legacy !== '') ? (string)$legacy : $software . '/forgot_password.php';
        }
        $forgot_url .= (strpos($forgot_url, '?') === false ? '?' : '&') . 'send_to=' . urlencode($page_url);
    }

    // Registration: a page carrying the registration widget when chosen;
    // otherwise, when the switch is on, the legacy registration entrance.
    $register_url = '';
    if (!empty($cfg['register_page_id'])) $register_url = _pg_member_page_url($cfg['register_page_id']);
    if ($register_url === '' && !empty($cfg['show_register_link']) && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('registration entrance');
        if ($legacy !== false && $legacy !== '') $register_url = (string)$legacy;
    }

    // Google, Microsoft, Apple: sign-in only from here (the staff login does
    // the same); an identity nobody matches is refused, not registered.
    $google_url  = function_exists('pg_google_signin_url') ? pg_google_signin_url($send_to, 'none') : '';
    $google_html = function_exists('pg_signin_buttons') ? pg_signin_buttons($send_to, 'none') : '';

    $sections = array(
        'google_signin'        => $google_html,
        'forgot_password_link' => ($forgot_url !== '')
            ? '<a href="' . h($forgot_url) . '" class="pg-member-link pg-forgot-password-link" rel="nofollow">' . h(lang('Forgot password?')) . '</a>' : '',
        'register_link'        => ($register_url !== '')
            ? '<a href="' . h($register_url) . '" class="pg-member-link pg-register-link">' . h(lang('Sign Up')) . '</a>' : '',
    );
    pg_cf_apply_section_bindings($tree, $sections);
    _pg_member_drop_empty_links($tree, array(
        '__forgot_password_url' => $forgot_url,
        '__register_url'        => $register_url,
        '__google_signin_url'   => $google_url,
    ));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'login');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    // The screen has been drawn from it; the legacy screen drops the form at
    // this point too, so a stale attempt never shapes a later visit.
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, $software . '/index.php', array(
        'send_to'         => $send_to,
        // Where index.php sends a refused attempt: back to this page, with
        // the error on the liveform the Messages node prints.
        'return_to'       => $page_url,
        'require_cookies' => 'true',
    ), 'pg-login-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'           => h($site_name),
        '__redirect_url'        => h($send_to),
        '__forgot_password_url' => h($forgot_url),
        '__register_url'        => h($register_url),
        '__google_signin_url'   => h($google_url),
    ));
}

// Render a 'forgot_password' system widget.
//
// Posts to <software>/forgot_password.php. Control by name: email. Tokens:
// ^^__site_name^^, ^^__password_hint^^, ^^__back_url^^. Section:
// password_hint. Screens follow the processor's `screen` field on the
// 'forgot_password' liveform: '' (the form), 'password_hint' (the hint is
// shown and the same form asks again), 'confirm' (the controls are gone, the
// notice stays — worded the same for a known and an unknown address).
//
// cfg: redirect_page_id (send_to: where the visitor may go afterwards),
// login_page_id (the "back to sign-in" link; the legacy login page type is
// the fallback), reset_email_page_id / reset_email_subject (the designed
// reset e-mail, read by forgot_password.php; widgets_account.php).
function _render_system_widget_forgot_password($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();

    $lf     = new liveform('forgot_password');
    $screen = (string)$lf->get_field_value('screen');
    $email  = (string)$lf->get_field_value('email');

    $send_to = '';
    if (!empty($cfg['redirect_page_id'])) $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');
    if ($send_to === '') $send_to = defined('PATH') ? PATH : '/';

    $back_url = '';
    if (!empty($cfg['login_page_id'])) $back_url = _pg_member_page_url($cfg['login_page_id']);
    if ($back_url === '' && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('login');
        if ($legacy !== false && $legacy !== '') $back_url = (string)$legacy;
    }
    if ($back_url === '') $back_url = $send_to;

    // The hint screen: PASSWORD_HINT on, the account has one, and the visitor
    // has not yet said it did not help (that second post keeps screen =
    // password_hint and the processor sends the mail).
    $hint = '';
    if ($screen === 'password_hint' && defined('PASSWORD_HINT') && PASSWORD_HINT && $email !== '') {
        $hint = (string)db_value("SELECT user_password_hint FROM user WHERE user_email = '" . e($email) . "' LIMIT 1");
    }

    if ($screen === 'confirm') {
        _pg_member_drop_all_controls($tree);
    } else {
        _pg_member_fill_control($tree, 'email', $email);
    }

    pg_cf_apply_section_bindings($tree, array(
        'password_hint' => ($hint !== '')
            ? '<div class="alert alert-info pg-password-hint" role="alert"><strong>' . h(lang('Password Hint')) . ':</strong> ' . h($hint) . '</div>' : '',
    ));
    _pg_member_drop_empty_links($tree, array('__back_url' => $back_url));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'forgot_password');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $lf->remove();
    if ($screen !== 'confirm') {
        // pg_widget_id: this widget's reset_email_page_id chooses the e-mail.
        $rendered = _pg_member_form_wrap($rendered, $software . '/forgot_password.php', array(
            'send_to'      => $send_to,
            'return_to'    => $page_url,
            'screen'       => $screen,
            'pg_widget_id' => $widget_id,
        ), 'pg-forgot-password-form');
    }

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'     => h($site_name),
        '__password_hint' => h($hint),
        '__back_url'      => h($back_url),
    ));
}

// ============================================================================
// Pages and the widgets placed on them
// ============================================================================

// The system widgets placed on a page, in tree order: [['id' => sid,
// 'cfg' => system_region_config]]. Read from the page's own layout tree (the
// style's, for a page saved before the multi-page editor), so a widget that
// sits in the design library but not on the page does not count.
function pg_sw_page_widgets($page_id)
{
    static $cache = array();
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    if (isset($cache[$page_id])) return $cache[$page_id];
    $cache[$page_id] = array();

    $tree = json_decode((string)pg_page_tree_json($page_id), true);
    if (!is_array($tree)) return array();

    $sids = array();
    $find = function ($n) use (&$find, &$sids) {
        if (!is_array($n)) return;
        if (isset($n['type']) && $n['type'] === 'shared_ref') {
            $sid = (int)(isset($n['props']['sharedId']) ? $n['props']['sharedId'] : 0);
            if ($sid > 0 && !in_array($sid, $sids, true)) $sids[] = $sid;
            return;
        }
        if (!empty($n['children']) && is_array($n['children'])) foreach ($n['children'] as $c) $find($c);
    };
    $find($tree);
    if (!$sids) return array();

    $rows = db_items(
        "SELECT id, system_region_config FROM shared_components
         WHERE id IN (" . implode(',', array_map('intval', $sids)) . ")
           AND system_region_config IS NOT NULL AND system_region_config != ''");
    $by_id = array();
    foreach ((array)$rows as $row) {
        $cfg = json_decode((string)$row['system_region_config'], true);
        if (is_array($cfg)) $by_id[(int)$row['id']] = $cfg;
    }
    $out = array();
    foreach ($sids as $sid) {
        if (isset($by_id[$sid])) $out[] = array('id' => $sid, 'cfg' => $by_id[$sid]);
    }
    return $cache[$page_id] = $out;
}

// The first widget of $type on the page — its config narrowed by $test when
// one is given — as ['id', 'cfg'], or null.
function pg_sw_page_widget($page_id, $type, $test = null)
{
    foreach (pg_sw_page_widgets($page_id) as $w) {
        if (!isset($w['cfg']['regionType']) || $w['cfg']['regionType'] !== $type) continue;
        if ($test !== null && !$test($w['cfg'])) continue;
        return $w;
    }
    return null;
}

// Pages that carry a widget of $type ($test narrows by config):
// [['page_id', 'page_name', 'page_title']], binned pages left out. For the
// page pickers, and for finding a companion page (the event page of a
// calendar, the detail page of a list) when none is configured.
function pg_sw_widget_pages($type, $test = null)
{
    $rows = db_items(
        "SELECT id, system_region_config FROM shared_components
         WHERE system_region_config LIKE '%\"regionType\":\"" . e($type) . "\"%'");
    $likes = array();
    foreach ((array)$rows as $row) {
        $cfg = json_decode((string)$row['system_region_config'], true);
        if (!is_array($cfg) || !isset($cfg['regionType']) || $cfg['regionType'] !== $type) continue;
        if ($test !== null && !$test($cfg)) continue;
        $sid = (int)$row['id'];
        if ($sid <= 0) continue;
        $likes[] = pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sid . ",%'"
                 . " OR " . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sid . "}%'";
    }
    if (!$likes) return array();
    $pages = db_items(
        "SELECT page.page_id, page.page_name, page.page_title
         FROM page
         INNER JOIN style ON page.page_style = style.style_id
         WHERE (" . implode(' OR ', $likes) . ")" . pg_designer_not_binned_sql('page.page_folder') . "
         ORDER BY page.page_title ASC, page.page_name ASC");
    return is_array($pages) ? $pages : array();
}

// Name of the page product links open: the configured page, else the first
// designed page carrying a Catalog Detail widget, else the site's legacy
// catalog detail page. '' when the site has none. A bare /<product-slug>
// does not resolve, so the lists, the cart and the checkout all ask here
// instead of falling back to it.
function pg_sw_catalog_detail_page_name($page_id = 0)
{
    static $auto = null;
    $page_id = (int)$page_id;
    if ($page_id > 0) {
        $name = (string)db_value("SELECT page_name FROM page WHERE page_id = '" . $page_id . "' LIMIT 1");
        if ($name !== '') return $name;
    }
    if ($auto === null) {
        $auto  = '';
        $pages = pg_sw_widget_pages('catalog_item_view');
        if ($pages) $auto = (string)$pages[0]['page_name'];
        if ($auto === '') {
            $auto = (string)db_value("SELECT page_name FROM page WHERE page_type = 'catalog detail' ORDER BY page_id ASC LIMIT 1");
        }
    }
    return $auto;
}

// ============================================================================
// RSS feed of the form widgets (Form List View, Form Item View)
// ============================================================================
// A legacy "form list view" page answers ?rss=true with the form's newest
// records and announces the feed in its head; a page built in the visual
// editor is page_type 'standard' and got neither. These helpers give such a
// page one feed, chosen by the widget it carries, built from the same custom
// form, filters and detail page the widget renders with.

// The custom form a form widget shows, from its config: custom_form_page_id,
// the older form_page_id, or the source_page name. 0 when it names none.
function pg_sw_form_widget_form_page_id($cfg)
{
    if (!is_array($cfg)) return 0;
    if (!empty($cfg['custom_form_page_id'])) return (int)$cfg['custom_form_page_id'];
    if (!empty($cfg['form_page_id'])) return (int)$cfg['form_page_id'];
    if (!empty($cfg['source_page'])) {
        return (int)db_value("SELECT page_id FROM page WHERE page_name = '" . e($cfg['source_page']) . "' LIMIT 1");
    }
    return 0;
}

// The widget a page's feed is built from: the record page (form_item_view)
// wins over a list (form_list_view, under any of its older type names), so
// a page that shows one record and lists others feeds the record's form.
// null when the page has no form widget, the widget is not public or is a
// viewer filtered list (pg_sw_form_widget_public()), or the form has no
// field with an RSS element - then there is nothing to feed.
// Returns array('type' => 'form_item_view' | 'form_list_view',
//               'widget' => the pg_sw_page_widget() row,
//               'custom_form_page_id' => int).
function pg_sw_page_form_feed_widget($page_id)
{
    static $cache = array();
    $page_id = (int)$page_id;
    if ($page_id <= 0) return null;
    if (array_key_exists($page_id, $cache)) return $cache[$page_id];
    $cache[$page_id] = null;

    $types = array(
        'form_item_view'       => 'form_item_view',
        'form_list_view'       => 'form_list_view',
        'submitted_forms_list' => 'form_list_view',
        'form_list'            => 'form_list_view',
        'blog_list'            => 'form_list_view',
    );
    foreach ($types as $region_type => $kind) {
        $widget = pg_sw_page_widget($page_id, $region_type);
        if (!$widget) continue;
        $cfg = $widget['cfg'];
        if (!pg_sw_form_widget_public($cfg)) return null;
        $form_page_id = pg_sw_form_widget_form_page_id($cfg);
        if ($form_page_id <= 0) continue;
        $rss_fields = (int)db_value(
            "SELECT COUNT(*) FROM form_fields WHERE page_id = '" . $form_page_id . "' AND rss_field != ''"
        );
        if ($rss_fields === 0) continue;
        return $cache[$page_id] = array(
            'type'                => $kind,
            'widget'              => $widget,
            'custom_form_page_id' => $form_page_id,
        );
    }
    return null;
}

// The RSS title and description of one record, from the form's fields whose
// RSS element is "title" (every one, in field order, joined by a space, as
// the page title does) and "description" (the first). Rich-text values are
// flattened: neither tag takes markup.
// Returns array('title' => string, 'description' => string).
function pg_sw_submitted_form_rss_text($form_id)
{
    $out = array('title' => '', 'description' => '');
    $rows = db_items(
        "SELECT form_data.data, form_data.type, form_fields.rss_field
         FROM form_data
         INNER JOIN form_fields ON form_data.form_field_id = form_fields.id
         WHERE form_data.form_id = '" . (int)$form_id . "'
           AND form_fields.rss_field IN ('title', 'description')
         ORDER BY form_fields.sort_order ASC, form_data.id ASC"
    );
    foreach ((array)$rows as $row) {
        $text = (string)$row['data'];
        if ($row['type'] == 'html' && $text != '') $text = trim(convert_html_to_text($text));
        $text = trim($text);
        if ($text === '') continue;
        if ($row['rss_field'] == 'title') {
            $out['title'] .= ($out['title'] !== '' ? ' ' : '') . $text;
        } elseif ($out['description'] === '') {
            $out['description'] = $text;
        }
    }
    return $out;
}

// The channel and the items of a form widget page's feed: the newest fifty
// complete records of the form, the widget's own filters applied, each
// with the fields whose RSS element is title, description, media or category.
// An item links to the widget's detail page (?r=) when it has one, and to the
// page itself for a record page; the channel describes the host page.
// Same return shape as _pg_build_catalog_listing_rss_parts().
function pg_sw_form_feed_parts($page_id, $page_name, $page_title, $page_meta_description, $feed)
{
    $cfg          = $feed['widget']['cfg'];
    $form_page_id = (int)$feed['custom_form_page_id'];
    $base_path    = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $site         = URL_SCHEME . HOSTNAME_SETTING;

    // Where an item links: the record page is its own detail page; a list
    // links to its detail page when that page is bound to the same form.
    // A record of a form with pretty URLs on links by its name.
    $detail_page_name = '';
    $detail_pretty    = false;
    if ($feed['type'] === 'form_item_view') {
        $detail_page_name = $page_name;
        $detail_pretty    = pg_sw_form_pretty_urls($form_page_id);
    } else {
        $detail_target    = pg_sw_list_detail_target(isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0, $form_page_id);
        $detail_page_name = $detail_target['page_name'];
        $detail_pretty    = $detail_target['pretty'];
    }

    // The records, under the filters the designer set: the record page's
    // own (the records it would show), or the list's together with its
    // detail page's, so that no item links to a record that page keeps out.
    if ($feed['type'] === 'form_item_view') {
        $filter_clause = pg_sw_record_filter_clause($form_page_id, pg_sw_record_view_filters($cfg), (int)$page_id, true);
    } else {
        $filter_detail_id = isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0;
        $detail_view      = ($filter_detail_id > 0)
            ? pg_sw_page_widget($filter_detail_id, 'form_item_view', function ($c) use ($form_page_id) {
                return pg_sw_form_widget_form_page_id($c) === $form_page_id;
            })
            : null;
        // The list's URL filters read the feed's own address
        // (/tag?tag=climate&rss=true lists that tag's records).
        $filter_clause = pg_sw_list_record_filter_clause($form_page_id, $cfg, $detail_view ? $detail_view['cfg'] : array(), $filter_detail_id, pg_sw_filter_request());
    }
    $filter_where  = $filter_clause['where'];
    $filter_join   = $filter_clause['join'];
    $records = db_items(
        "SELECT forms.id, forms.reference_code, forms.address_name, forms.submitted_timestamp
         FROM forms
         $filter_join
         WHERE forms.page_id = '" . $form_page_id . "'
           AND forms.complete = 1
           $filter_where
         ORDER BY forms.submitted_timestamp DESC
         LIMIT 50"
    );

    // The category and media fields, read once; title and description per
    // record through pg_sw_submitted_form_rss_text().
    $category_field_id = (int)db_value("SELECT id FROM form_fields WHERE page_id = '" . $form_page_id . "' AND rss_field = 'category' ORDER BY sort_order ASC LIMIT 1");
    $media_field_id    = (int)db_value("SELECT id FROM form_fields WHERE page_id = '" . $form_page_id . "' AND rss_field = 'media' ORDER BY sort_order ASC LIMIT 1");

    $items = '';
    foreach ((array)$records as $record) {
        $form_id = (int)$record['id'];
        $text    = pg_sw_submitted_form_rss_text($form_id);
        $link    = ($detail_page_name !== '')
            ? $site . pg_sw_record_url($detail_page_name, $record['reference_code'], $record['address_name'], $detail_pretty, $base_path)
            : $site . $base_path . encode_url_path($page_name);

        $categories = '';
        if ($category_field_id > 0) {
            $values = db_items("SELECT data FROM form_data WHERE form_id = '" . $form_id . "' AND form_field_id = '" . $category_field_id . "' ORDER BY id ASC");
            foreach ((array)$values as $value) {
                foreach (explode('||', (string)$value['data']) as $category) {
                    $category = trim($category);
                    if ($category !== '') $categories .= '<category>' . h($category) . '</category>';
                }
            }
        }

        $enclosure = '';
        if ($media_field_id > 0) {
            $media = db_item("SELECT data, file_id FROM form_data WHERE form_id = '" . $form_id . "' AND form_field_id = '" . $media_field_id . "' LIMIT 1");
            if (is_array($media) && !empty($media['file_id'])) {
                $file = db_item("SELECT name, type, size FROM files WHERE id = '" . (int)$media['file_id'] . "' LIMIT 1");
                if (is_array($file)) {
                    $enclosure = '<enclosure url="' . $site . PATH . h(rawurlencode($file['name'])) . '" length="' . (int)$file['size'] . '" type="' . h(get_mime_type($file['type'])) . '"/>';
                }
            } elseif (is_array($media) && trim((string)$media['data']) !== '') {
                // A URL typed into the field: its size and type are not known here.
                $enclosure = '<enclosure url="' . h(trim((string)$media['data'])) . '" length="50000" type="image/jpeg"/>';
            }
        }

        $items .= '
        <item>
            <title>' . h($text['title'] !== '' ? $text['title'] : lang('There is not an RSS title specified in the form.')) . '</title>
            <link>' . h($link) . '</link>
            <guid>' . h($link) . '</guid>
            <description>' . h($text['description'] !== '' ? $text['description'] : lang('There is not an RSS description specified in the form.')) . '</description>
            <pubDate>' . date('r', (int)$record['submitted_timestamp']) . '</pubDate>
            ' . $categories . $enclosure . '
        </item>';
    }

    $channel_title = trim((string)$page_title) !== '' ? trim((string)$page_title) : $page_name;
    $channel_desc  = trim((string)$page_meta_description) !== '' ? trim((string)$page_meta_description) : $channel_title;
    $channel_link  = $site . $base_path . encode_url_path($page_name);

    // A feed for one value of the address (an author, a tag) is that
    // value's channel. It is named after the value only when it found
    // records and every value was compared with "is equal to", as on the
    // page.
    $filter_params = !empty($filter_clause['params']) ? $filter_clause['params'] : array();
    if ($filter_params && $records) {
        // Named only after text one of the records holds byte for byte.
        if (!$filter_clause['inexact'] && ($filter_clause['exact'] === '' || !db_value(
            "SELECT 1 FROM forms
             $filter_join
             WHERE forms.page_id = '" . $form_page_id . "'
               AND forms.complete = 1
               $filter_where
               {$filter_clause['exact']}
             LIMIT 1"
        ))) {
            $filter_clause['inexact'] = true;
        }
        $filter_value = pg_sw_url_filter_value($filter_clause, count($records));
        if ($filter_value !== '') $channel_title = pg_sw_filter_title(pg_sw_list_filter_title($cfg), $filter_value);
        $channel_link .= '?' . h(pg_sw_url_filter_query(array('params' => $filter_params), false));
    }

    return array(
        'title'       => h($channel_title),
        'link'        => $channel_link,
        'description' => h($channel_desc),
        'items'       => $items,
    );
}

// The query parameter a list pages under: its own `page_param` setting, a
// name pg_sw_url_param_name() accepts (so never one the software reads
// itself), else page_number, the name every list shared before and the one
// running sites' addresses carry. Two lists with names of their own on one
// page turn their pages apart.
function pg_sw_list_page_param($cfg)
{
    $name = (is_array($cfg) && isset($cfg['page_param'])) ? pg_sw_url_param_name($cfg['page_param']) : '';
    return ($name !== '') ? $name : 'page_number';
}

// How many of the matched records a list skips before its first row
// (`offset`, 0 to 100000): a box shows the newest record, the list beside it
// starts at the second.
function pg_sw_list_offset($cfg)
{
    $n = (is_array($cfg) && isset($cfg['offset']) && is_scalar($cfg['offset'])) ? (int)$cfg['offset'] : 0;
    return max(0, min(100000, $n));
}

// The page a list shows: the address value $requested as a whole number,
// clamped to 1 .. $pages (a page past the last one is the last); 1 without
// a usable value or without pages.
function pg_sw_list_current_page($requested, $pages)
{
    $n = is_scalar($requested) ? (int)$requested : 1;
    return max(1, min(max(1, (int)$pages), $n));
}

// The paging tokens and flags of a list showing page $current of $pages
// under $page_param. Tokens, escaped once: ^^__page_number^^ (the page
// shown), ^^__page_count^^, ^^__prev_page_url^^ and ^^__next_page_url^^
// ('' on the first and the last page; the links keep every other query
// parameter, as the pagination's do). Flags: has_prev_page,
// has_next_page, has_more_pages (more than one page).
function pg_sw_list_paging($page_param, $current, $pages)
{
    $current = (int)$current;
    $pages   = (int)$pages;
    $url = function ($n) use ($page_param) {
        return h('?' . _pg_sw_build_query($page_param, $n));
    };
    $prev = ($current > 1);
    $next = ($current < $pages);
    return array(
        'tokens' => array(
            '__page_number'   => (string)$current,
            '__page_count'    => (string)$pages,
            '__prev_page_url' => $prev ? $url($current - 1) : '',
            '__next_page_url' => $next ? $url($current + 1) : '',
        ),
        'flags' => array(
            'has_prev_page'  => $prev,
            'has_next_page'  => $next,
            'has_more_pages' => ($pages > 1),
        ),
    );
}

// Bootstrap pagination for a widget list: previous / next, a window of two
// pages around the current one, the first and the last. '' for a single page.
// Links keep every other query parameter (search text, filters).
function pg_sw_pagination_html($page_param, $current_page, $total_pages, $fragment = '')
{
    $current_page = (int)$current_page;
    $total_pages  = (int)$total_pages;
    if ($total_pages <= 1) return '';

    // $fragment: the anchor every link lands on (the comments widget's
    // #software_comments); without one the links are as they always were.
    $link = function ($n, $label, $aria = '') use ($page_param, $fragment) {
        return '<li class="page-item"><a class="page-link" href="?' . h(_pg_sw_build_query($page_param, $n)) . (($fragment !== '') ? '#' . h($fragment) : '') . '"'
             . ($aria !== '' ? ' aria-label="' . h($aria) . '"' : '') . '>' . $label . '</a></li>';
    };
    $html = '<nav class="pg-sw-pagination" aria-label="' . h(lang('Page navigation')) . '"><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
    $html .= ($current_page > 1)
        ? $link($current_page - 1, '&laquo;', lang('Previous'))
        : '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    $window = 2;
    $from = max(1, $current_page - $window);
    $to   = min($total_pages, $current_page + $window);
    if ($from > 1) {
        $html .= $link(1, '1');
        if ($from > 2) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    for ($i = $from; $i <= $to; $i++) {
        $html .= ($i === $current_page)
            ? '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>'
            : $link($i, (string)$i);
    }
    if ($to < $total_pages) {
        if ($to < $total_pages - 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $html .= $link($total_pages, (string)$total_pages);
    }
    $html .= ($current_page < $total_pages)
        ? $link($current_page + 1, '&raquo;', lang('Next'))
        : '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    return $html . '</ul></nav>';
}

// Visibility bindings for a renderer that learns its flags only after it has
// drawn the tree. Every node carrying _bindings.eo_visible_if is wrapped
// between two numbered comments; _pg_sw_resolve_visibility() then keeps or
// cuts each span. (_eo_apply_visibility_bindings() drops the node from the
// tree instead, which needs the flags before the render.) With $only, a
// flag not in that list is left unmarked, so its node is always drawn.
function _pg_sw_mark_visibility(&$tree, $only = null)
{
    static $seq = 0;
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) return;
    $out = array();
    foreach ($tree['children'] as $child) {
        if (!is_array($child)) { $out[] = $child; continue; }
        _pg_sw_mark_visibility($child, $only);
        $flag = isset($child['props']['_bindings']['eo_visible_if'])
            ? preg_replace('/[^a-z0-9_]/', '', (string)$child['props']['_bindings']['eo_visible_if']) : '';
        if ($flag === '' || (is_array($only) && !in_array($flag, $only, true))) { $out[] = $child; continue; }
        $n = ++$seq;
        $out[] = array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<!--pg-vis:' . $flag . ':' . $n . '-->'), 'children' => array());
        $out[] = $child;
        $out[] = array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<!--/pg-vis:' . $n . '-->'), 'children' => array());
    }
    $tree['children'] = $out;
}

// Keep only the branches of $node that lead to a messages content node.
// Returns whether $node is or holds one.
function _pg_sw_prune_to_messages(&$node)
{
    if (!is_array($node)) return false;
    if (isset($node['type'], $node['props']['contentType'])
        && $node['type'] === 'content' && $node['props']['contentType'] === 'messages') {
        return true;
    }
    if (empty($node['children']) || !is_array($node['children'])) return false;
    $keep = array();
    foreach ($node['children'] as $child) {
        if (_pg_sw_prune_to_messages($child)) $keep[] = $child;
    }
    $node['children'] = $keep;
    return (bool)$keep;
}

// The widget drawn as its message area alone, carrying $message_html (already
// escaped; it may hold a link). For the states where the rest of the design
// has nothing to show - a signed-out account page, an order that cannot be
// shown, an empty cart: the designer's container and message block stay, the
// alert comes through them, and no empty labels or dead buttons are left.
// $extra_html (already safe) follows the message area - the empty cart's
// quick add box.
function _pg_sw_render_message_only($tree, $form_name, $message_html, $kind = 'notice', $extra_html = '')
{
    if (!is_array($tree)) return '';
    _pg_inject_messages_node($tree, $form_name);
    _pg_sw_prune_to_messages($tree);
    if ($extra_html !== '' && function_exists('_pg_inject_html_after_messages')) {
        _pg_inject_html_after_messages($tree, $extra_html);
    }
    if ($message_html !== '' && class_exists('liveform')) {
        $lf = new liveform($form_name);
        if ($kind === 'warning') $lf->add_warning($message_html);
        else $lf->add_notice($message_html);
    }
    return trim(_render_tree_node($tree, 0, 0));
}

// Keeps or cuts each span _pg_sw_mark_visibility() marked. Spans nest (a
// record's part holds the button only its editors see), so a span that is
// kept has the spans inside it resolved in turn.
function _pg_sw_resolve_visibility($html, $flags)
{
    if (!is_string($html) || strpos($html, '<!--pg-vis:') === false) return $html;
    return preg_replace_callback('/<!--pg-vis:([a-z0-9_]+):(\d+)-->(.*?)<!--\/pg-vis:\2-->/s', function ($m) use ($flags) {
        return !empty($flags[$m[1]]) ? _pg_sw_resolve_visibility($m[3], $flags) : '';
    }, $html);
}

// Fill ^^token^^ slots from an already-escaped map in one pass. strtr() never
// rescans what it wrote, so a value that itself reads "^^x^^" stays literal.
// A template with a format after a token (^^x^^%%upper%%) goes through the
// typed pass, which never rescans either and reads the format; $typed names
// the keys that hold a date (key => array('type', 'data')) for a date
// format. A template without one fills through strtr() as it always did.
function _pg_sw_fill_tokens($html, $values, $typed = array())
{
    if (!is_string($html) || $html === '' || !$values) return $html;
    if (strpos($html, '^^%%') !== false) return pg_sw_fill_typed($html, $values, $typed);
    $map = array();
    foreach ($values as $k => $v) $map['^^' . $k . '^^'] = (string)$v;
    return strtr($html, $map);
}

// The address the visitor is on, without its query string — the target of
// the widget's own GET forms and navigation links.
function _pg_sw_current_path()
{
    return (string)strtok((string)get_request_uri(), '?');
}

// The visitor's query parameters. On a pretty address (/widget-takvim) the
// router puts the page's name into $_GET['page'] itself; carrying it into our
// own links and forms would only repeat the path. On /index.php?page=… it is
// the address and stays.
function _pg_sw_query_params()
{
    $params = is_array($_GET) ? $_GET : array();
    if (isset($params['page']) && !preg_match('/\.php$/i', _pg_sw_current_path())) unset($params['page']);
    return $params;
}

// Hidden inputs that carry the current query string through a GET form,
// except the keys the form sets itself. Without them a search submitted from
// a page reached as /page?lang=en would drop lang.
function _pg_sw_hidden_query_inputs($skip)
{
    $html = '';
    foreach (_pg_sw_query_params() as $name => $value) {
        if (in_array((string)$name, $skip, true) || !is_scalar($value)) continue;
        $html .= '<input type="hidden" name="' . h($name) . '" value="' . h((string)$value) . '">';
    }
    return $html;
}

// The designer's search form, bound rather than hand-wired:
//   form   _bindings.action = 'search_form'   → GET to this page (+ carried params)
//   input  _bindings.value  = 'search_query'  → name=<param>, the current text
//   button _bindings.action = 'submit_search' → a submit button
// $ctx: action_url, hidden_html, param, query.
function _pg_sw_apply_search_form_bindings(&$node, $ctx)
{
    if (!is_array($node)) return;
    $b    = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    $type = isset($node['type']) ? $node['type'] : '';
    $tag  = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';

    $set_attrs = function (&$n, $managed, $add) {
        $kept = array();
        foreach ((isset($n['props']['_attrs']) && is_array($n['props']['_attrs'])) ? $n['props']['_attrs'] : array() as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) $kept[] = $a;
        }
        foreach ($add as $name => $value) $kept[] = array('name' => $name, 'value' => $value);
        $n['props']['_attrs'] = $kept;
    };

    if ($type === 'semantic' && $tag === 'form' && isset($b['action']) && $b['action'] === 'search_form') {
        $set_attrs($node, array('action' => 1, 'method' => 1, 'role' => 1),
            array('action' => $ctx['action_url'], 'method' => 'get', 'role' => 'search'));
        if ($ctx['hidden_html'] !== '') {
            if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
            array_unshift($node['children'], array('type' => 'content',
                'props' => array('contentType' => 'custom_html', 'html' => $ctx['hidden_html']), 'children' => array()));
        }
    }
    if ($type === 'semantic' && $tag === 'input' && isset($b['value']) && $b['value'] === 'search_query') {
        $set_attrs($node, array('type' => 1, 'name' => 1, 'value' => 1, 'maxlength' => 1),
            array('type' => 'search', 'name' => $ctx['param'], 'value' => $ctx['query'], 'maxlength' => '100'));
    }
    if (isset($b['action']) && $b['action'] === 'submit_search') {
        if ($type === 'component') {
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'submit';
        } elseif ($type === 'semantic' && $tag === 'button') {
            $set_attrs($node, array('type' => 1), array('type' => 'submit'));
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) _pg_sw_apply_search_form_bindings($child, $ctx);
        unset($child);
    }
}

// The readable text of a layout tree: what a visitor sees on a page built in
// the visual editor, for the site search. Class names, bindings and other
// props are not text and are left out.
function _pg_sw_tree_text($node)
{
    if (!is_array($node)) return '';
    $type = isset($node['type']) ? $node['type'] : '';
    if ($type === 'region' || $type === 'shared_ref') return '';
    $p   = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    $out = '';
    foreach (array('text', 'html', 'alt') as $k) {
        if (isset($p[$k]) && is_string($p[$k]) && $p[$k] !== '') {
            $out .= ' ' . html_entity_decode(strip_tags($p[$k]), ENT_QUOTES, 'UTF-8');
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $c) $out .= _pg_sw_tree_text($c);
    }
    return $out;
}

// About $length characters of $text around the first match of $query, with
// an ellipsis where it was cut — the excerpt of a page that has no meta
// description.
function _pg_sw_snippet($text, $query, $length = 180)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if ($text === '') return '';
    $pos = mb_stripos($text, (string)$query);
    if ($pos === false) $pos = 0;
    $start = max(0, $pos - (int)($length / 3));
    // Start on a word, not in the middle of one.
    if ($start > 0) {
        $space = mb_strpos($text, ' ', $start);
        if ($space !== false && $space < $pos) $start = $space + 1;
    }
    $snip  = mb_substr($text, $start, $length);
    return ($start > 0 ? '… ' : '') . trim($snip) . (($start + $length) < mb_strlen($text) ? ' …' : '');
}

// ============================================================================
// Search sections
// ============================================================================
//
// A part of a page the visual editor marked as a search section
// (props.searchSection, Search and SERP in the options): the site search
// points a matching result at it, the visitor lands on it with the words
// marked (assets/js/pg_search_highlight.js), and the page's structured data
// names it with its anchor (pg_search_sections_jsonld()). The element's id is
// the anchor; the editor makes one when the switch goes on.

// The first heading inside a node, as text: a heading content node, or an
// h1-h6 element. '' when there is none.
function _pg_search_first_heading($node, $depth = 0)
{
    if (!is_array($node) || $depth > 80) return '';
    $type = isset($node['type']) ? $node['type'] : '';
    if ($type === 'region' || $type === 'shared_ref') return '';
    $p = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    if ($type === 'content' && isset($p['contentType']) && $p['contentType'] === 'heading') {
        return trim(preg_replace('/\s+/u', ' ', _pg_sw_tree_text($node)));
    }
    if ($type === 'semantic' && isset($p['tag']) && preg_match('/^h[1-6]$/i', (string)$p['tag'])) {
        return trim(preg_replace('/\s+/u', ' ', _pg_sw_tree_text($node)));
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $c) {
            $h = _pg_search_first_heading($c, $depth + 1);
            if ($h !== '') return $h;
        }
    }
    return '';
}

// The search sections of a page's layout tree, in page order. Each:
// ['id', 'title' (props.searchTitle, else the first heading inside, else ''),
// 'keywords' (props.searchKeywords split on commas), 'text' (what a visitor
// reads in it)]. A section without an id has no anchor and is left out; one
// inside a shared component or a widget belongs to that, not to the page.
function pg_search_sections($tree)
{
    $out = array();
    $walk = function ($node, $depth) use (&$walk, &$out) {
        if (!is_array($node) || $depth > 80) return;
        $type = isset($node['type']) ? $node['type'] : '';
        if ($type === 'region' || $type === 'shared_ref') return;
        $p = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
        $id = isset($p['id']) ? trim((string)$p['id']) : '';
        if (!empty($p['searchSection']) && $id !== '') {
            $title = isset($p['searchTitle']) ? trim(strip_tags((string)$p['searchTitle'])) : '';
            if ($title === '') $title = _pg_search_first_heading($node);
            $keywords = array();
            foreach (explode(',', isset($p['searchKeywords']) ? (string)$p['searchKeywords'] : '') as $k) {
                $k = trim(strip_tags($k));
                if ($k !== '') $keywords[] = $k;
            }
            $out[] = array(
                'id'       => $id,
                'title'    => $title,
                'keywords' => $keywords,
                'text'     => trim(preg_replace('/\s+/u', ' ', _pg_sw_tree_text($node))),
            );
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $c) $walk($c, $depth + 1);
        }
    };
    $walk($tree, 0);
    return $out;
}

// The topics given to the search sections of a layout tree, as one text: they
// are words of the page for the site search, as its promoted keywords are,
// though nothing on the page shows them.
function pg_search_section_topics($tree)
{
    $topics = array();
    foreach (pg_search_sections($tree) as $s) {
        foreach ($s['keywords'] as $k) $topics[] = $k;
    }
    return implode(', ', array_unique($topics));
}

// Lower case for matching, with the Turkish i's made one letter: "İade" is
// lowered to an i with a combining dot, which "iade" would not find, and a
// visitor types ı and i alike.
function _pg_search_fold($text)
{
    return str_replace(array("\u{0307}", "\u{0131}"), array('', 'i'), mb_strtolower((string)$text, 'UTF-8'));
}

// The section that answers a search, or null. The whole phrase counts most -
// in the title, then in the text - and a topic that is the phrase itself;
// for a search of several words each word found adds a little, so "kargo
// süresi" still reaches the shipping part.
function pg_search_section_match($sections, $query)
{
    $needle = _pg_search_fold(trim(str_replace(array('"', '+'), ' ', (string)$query)));
    $needle = trim(preg_replace('/\s+/u', ' ', $needle));
    if ($needle === '' || !$sections) return null;
    $words = array();
    foreach (explode(' ', $needle) as $w) {
        if (mb_strlen($w) >= 2) $words[] = $w;
    }

    $best = null;
    $best_score = 0;
    foreach ($sections as $s) {
        $title = _pg_search_fold($s['title']);
        $text  = _pg_search_fold($s['text']);
        $score = 8 * mb_substr_count($title, $needle) + 4 * mb_substr_count($text, $needle);
        foreach ($s['keywords'] as $k) {
            $k = _pg_search_fold($k);
            if ($k === $needle) {
                $score += 10;
            } elseif (mb_strpos($k, $needle) !== false) {
                $score += 3;
            } elseif (count($words) > 1 && mb_strpos($needle, $k) !== false) {
                $score += 2;
            }
        }
        if (count($words) > 1) {
            foreach ($words as $w) $score += min(3, mb_substr_count($title . ' ' . $text, $w));
        }
        if ($score > $best_score) {
            $best = $s;
            $best_score = $score;
        }
    }
    return $best;
}

// Point the page results of a search at the section that answers it: the
// link carries the search (?highlight=) for the marking and the section's
// anchor, the excerpt comes from the section, and 'section' names it
// (^^__result_section^^). Rows of pages without a matching section are left
// as they are. One query for every page of the list.
function _pg_sw_search_attach_sections($rows, $query)
{
    if (!$rows || !function_exists('pg_multi_page_design_ready') || !pg_multi_page_design_ready()) return $rows;
    $names = array();
    foreach ($rows as $r) {
        if (isset($r['name']) && (string)$r['name'] !== '') $names[(string)$r['name']] = true;
    }
    if (!$names) return $rows;

    $list = array();
    foreach (array_keys($names) as $n) $list[] = "'" . e($n) . "'";
    $trees = array();
    foreach ((array)db_items(
        "SELECT page_name, page_tree_json FROM page
         WHERE page_name IN (" . implode(',', $list) . ") AND page_tree_json LIKE '%searchSection%'") as $p) {
        $trees[(string)$p['page_name']] = (string)$p['page_tree_json'];
    }
    if (!$trees) return $rows;

    $mark = trim(preg_replace('/\s+/u', ' ', str_replace(array('"', '+'), ' ', (string)$query)));
    foreach ($rows as $i => $r) {
        if (!isset($r['name']) || !isset($trees[(string)$r['name']])) continue;
        $section = pg_search_section_match(pg_search_sections(json_decode($trees[(string)$r['name']], true)), $query);
        if (!$section) continue;
        $url = (string)$r['url'];
        $rows[$i]['url'] = $url . ((strpos($url, '?') === false) ? '?' : '&') . 'highlight=' . rawurlencode($mark)
                         . '#' . rawurlencode($section['id']);
        $rows[$i]['section'] = $section['title'];
        // The excerpt comes from the section, around the phrase or the first
        // of its words found there; a section reached through its topics
        // alone shows its beginning.
        $folded = _pg_search_fold($section['text']);
        $anchor = '';
        foreach (array_merge(array($mark), explode(' ', $mark)) as $w) {
            if (mb_strlen($w) >= 2 && mb_strpos($folded, _pg_search_fold($w)) !== false) {
                $anchor = $w;
                break;
            }
        }
        if ($section['text'] !== '') {
            $rows[$i]['excerpt'] = _pg_sw_snippet($section['text'], ($anchor !== '') ? $anchor : mb_substr($section['text'], 0, 1));
        }
    }
    return $rows;
}

// The page's search sections as structured data: a WebPage whose parts are
// named, with the anchor of each - what lets a search engine point a result
// at the part of the page that answers it. Null when the page has no named
// section. $page_url is the canonical address.
function pg_search_sections_jsonld($page_id, $page_url, $page_name)
{
    if (!function_exists('pg_page_tree_json')) return null;
    $tree = json_decode((string)pg_page_tree_json((int)$page_id), true);
    if (!is_array($tree)) return null;
    $page_url = (string)strtok((string)$page_url, '#');
    $parts = array();
    foreach (pg_search_sections($tree) as $s) {
        // A part without a name says nothing a search engine could show.
        if ($s['title'] === '') continue;
        $part = array(
            '@type'       => 'WebPageElement',
            'name'        => $s['title'],
            'url'         => $page_url . '#' . rawurlencode($s['id']),
            'cssSelector' => '#' . $s['id'],
        );
        if ($s['keywords']) $part['keywords'] = implode(', ', $s['keywords']);
        $parts[] = $part;
    }
    if (!$parts) return null;
    return array(
        '@context' => 'https://schema.org',
        '@type'    => 'WebPage',
        '@id'      => $page_url,
        'url'      => $page_url,
        'name'     => (string)$page_name,
        'hasPart'  => $parts,
    );
}

// pg_search_sections_jsonld() as printed inside <script type=
// "application/ld+json">: < > & written as \u escapes, so no name in it (a
// page title that a list took from its address, a section name) can close
// the element; slashes and non-ASCII text stay as they are.
function pg_search_sections_jsonld_json($data)
{
    return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

// The marking of a search on the page the result led to: the words of
// ?highlight= marked inside the section named by the address's anchor, the
// section outlined (assets/js/pg_search_highlight.js). Only on such a request;
// a page asked for without it gets nothing.
function pg_search_highlight_inject($content)
{
    if (strpos($content, '/assets/js/pg_search_highlight.js') !== false) return $content;
    $file = '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_search_highlight.js?v='
          . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_search_highlight.js') . '" defer></script>' . "\n";
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}

// ============================================================================
// search_results
// ============================================================================

// The site search of the legacy Search Results page (get_search_results.php),
// gathered as rows instead of markup: featured pages (promoted on the query as
// a keyword), catalog items, then the other matching pages — ranked by how
// often the query appears in them (simple search) or by the fulltext score
// over search_items (advanced search, Site Settings → Search).
//
// Same fences as the legacy page: only pages that opted into site search, none
// in an archived folder or the Recycle Bin, none the visitor may not view.
// A page built in the visual editor keeps its text in its layout tree, not in
// page regions, so the simple search reads the tree as well — and the title
// and meta description, which a visitor expects a search to find.
//
// cfg: search_scope ('all' | 'pages' | 'products'), product_group_id (the
// catalog branch searched; 0 = the top-level group), catalog_detail_page_id.
// The advanced search covers the whole site, within what the visitor may view.
// Returns ['featured' => rows, 'catalog' => rows, 'pages' => rows,
// 'limited' => bool]; every row is ['title', 'url', 'full_url', 'excerpt',
// 'type', 'image', 'price'] with raw (unescaped) values, 'price' being HTML;
// a page row adds 'name' and 'section' - a page with a search section that
// answers the query links to it (_pg_sw_search_attach_sections()).
// $advanced: null follows the site setting; true / false picks the search.
function _pg_sw_search_collect($query, $cfg, $advanced = null)
{
    $out = array('featured' => array(), 'catalog' => array(), 'pages' => array(), 'limited' => false);
    $query = trim((string)$query);
    if ($query === '') return $out;
    if (!is_array($cfg)) $cfg = array();

    $scope = (isset($cfg['search_scope']) && in_array($cfg['search_scope'], array('all', 'pages', 'products'), true))
        ? $cfg['search_scope'] : 'all';
    $want_pages   = ($scope !== 'products');
    $want_catalog = ($scope !== 'pages') && defined('ECOMMERCE') && ECOMMERCE;
    if ($advanced === null) $advanced = defined('SEARCH_TYPE') && SEARCH_TYPE === 'advanced';
    $path         = defined('PATH') ? PATH : '/';
    $host         = (defined('URL_SCHEME') ? URL_SCHEME : '') . (defined('HOSTNAME') ? HOSTNAME : '');
    $page_row     = function ($title, $name_or_url, $excerpt, $is_url = false) use ($path, $host) {
        $url = $is_url ? $path . $name_or_url : $path . encode_url_path((string)$name_or_url);
        // On a page in another language the title reads in it; the link gets
        // the language's directory with the rest of the page's links.
        if (function_exists('pg_tr_text')) {
            $title   = pg_tr_text((string)$title, 'text', 'seo');
            $excerpt = pg_tr_text((string)$excerpt, 'text', 'seo');
        }
        return array(
            'title'    => ((string)$title !== '') ? (string)$title : $host . $url,
            'url'      => $url,
            'full_url' => $host . $url,
            'excerpt'  => (string)$excerpt,
            'type'     => 'page',
            'image'    => '',
            'price'    => '',
            // The page, for its search sections (_pg_sw_search_attach_sections());
            // search_items keeps the address encoded.
            'name'     => $is_url ? rawurldecode((string)$name_or_url) : (string)$name_or_url,
            'section'  => '',
        );
    };

    // ── Catalog ──────────────────────────────────────────────────────────
    if ($want_catalog) {
        $group_id = isset($cfg['product_group_id']) ? (int)$cfg['product_group_id'] : 0;
        if ($group_id <= 0) $group_id = (int)db_value("SELECT id FROM product_groups WHERE parent_id = '0' LIMIT 1");
        // Boolean operators belong to the fulltext page search only.
        $catalog_query = $advanced ? str_replace(array('"', '+'), '', $query) : $query;

        $detail_page_id = isset($cfg['catalog_detail_page_id']) ? (int)$cfg['catalog_detail_page_id'] : 0;
        if ($detail_page_id <= 0) {
            $pages = pg_sw_widget_pages('catalog_item_view');
            if ($pages) $detail_page_id = (int)$pages[0]['page_id'];
        }
        if ($detail_page_id <= 0) {
            $detail_page_id = (int)db_value("SELECT page_id FROM page WHERE page_type = 'catalog detail' ORDER BY page_id ASC LIMIT 1");
        }
        $detail_name = ($detail_page_id > 0) ? (string)db_value("SELECT page_name FROM page WHERE page_id = '" . $detail_page_id . "' LIMIT 1") : '';
        $back_query  = ($detail_name !== '') ? '?previous_url_id=' . urlencode(generate_url_id()) : '';
        $discounted  = get_discounted_product_prices();

        foreach ((array)get_catalog_search_results($catalog_query, $group_id) as $item) {
            $price = '';
            if ($item['type'] === 'product group') {
                $range = get_price_range($item['id'], $discounted);
                if (!empty($range['non_donation_products_exist'])) {
                    if ($range['smallest_price'] == $range['largest_price']) {
                        if (($range['number_of_products'] == 1) && !empty($range['discounted_products_exist'])) {
                            $price = prepare_price_for_output($range['original_price'], true, $range['smallest_price'], 'html');
                        } elseif (!empty($range['discounted_products_exist'])) {
                            $price = '<span class="software_discounted_price">' . prepare_price_for_output($range['smallest_price'], false, '', 'html') . '</span>';
                        } else {
                            $price = prepare_price_for_output($range['smallest_price'], false, '', 'html');
                        }
                    } else {
                        $price = prepare_price_for_output($range['smallest_price'], false, '', 'html', false) . ' - '
                               . prepare_price_for_output($range['largest_price'], false, '', 'html');
                        if (!empty($range['discounted_products_exist'])) $price = '<span class="software_discounted_price">' . $price . '</span>';
                    }
                }
            } elseif (!isset($item['selection_type']) || $item['selection_type'] !== 'donation') {
                $is_discounted = isset($discounted[$item['id']]);
                $price = prepare_price_for_output($item['price'], $is_discounted, $is_discounted ? $discounted[$item['id']] : '', 'html');
            }
            $url = '';
            if ($detail_name !== '') {
                $url = $path . encode_url_path($detail_name) . '/'
                     . encode_url_path(get_catalog_item_address_name_from_id($item['id'], $item['type'])) . $back_query;
            }
            $title = ((string)$item['short_description'] !== '') ? (string)$item['short_description'] : (string)$item['name'];
            $out['catalog'][] = array(
                'title'    => $title,
                'url'      => $url,
                // Shown under the title, so without the back-link query.
                'full_url' => ($url !== '') ? $host . strtok($url, '?') : '',
                'excerpt'  => '',
                'type'     => ($item['type'] === 'product group') ? 'product group' : 'product',
                'image'    => ((string)$item['image_name'] !== '') ? $path . encode_url_path($item['image_name']) : '',
                'price'    => $price,
            );
        }
    }

    if (!$want_pages) return $out;

    // On a page served in another language the visitor searches in that
    // language: the translated texts of the visual pages are searched, and
    // their pages come first; what the source text matched follows.
    $tr_pages = array();
    if (function_exists('pg_tr_search_pages')) {
        foreach (pg_tr_search_pages($query) as $hit) {
            if (!check_view_access($hit['folder_id'], true)) continue;
            $tr_pages[] = $page_row($hit['title'], $hit['name'], $hit['excerpt']);
        }
    }

    $binned = pg_designer_not_binned_sql('page.page_folder');

    // ── Advanced: fulltext over search_items ─────────────────────────────
    if ($advanced) {
        // A visitor (not a manager or above) searches only the folders they
        // may view — the same narrowing the legacy page applies.
        $sql_folders = '';
        if (!USER_LOGGED_IN || USER_ROLE == 3) {
            $root    = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' LIMIT 1");
            $folders = array_merge(array($root), get_child_folders($root, db_items("SELECT folder_id AS id, folder_parent AS parent_folder_id FROM folder")));
            $folders = array_values(array_filter($folders, function ($f) { return check_view_access($f, true); }));
            if (!$folders) return $out;
            $sql_folders = "(search_items.folder_id IN (" . implode(',', array_map('intval', $folders)) . ")) AND ";
        }
        // A single word without quotes is searched as an exact phrase, so
        // "multi-recipient" does not match "multi" and "recipient" apart.
        $filter = $query;
        if (mb_strpos($filter, ' ') === false && mb_strpos($filter, '"') === false) $filter = '"' . $filter . '"';
        $score   = $filter;
        $boolean = '';
        if (mb_strpos($filter, '"') !== false || mb_strpos($filter, '+') !== false) {
            $boolean = ' IN BOOLEAN MODE';
            $score   = str_replace(array('"', '+'), '', $score);
        }
        $featured = db_items(
            "SELECT id, url, title, description,
                    MATCH(keywords) AGAINST ('" . e($score) . "') AS score
             FROM search_items
             WHERE $sql_folders (MATCH(keywords) AGAINST('" . e($filter) . "'$boolean))
             ORDER BY score DESC LIMIT 100");
        $not_featured = '';
        foreach ((array)$featured as $item) {
            $out['featured'][] = $page_row($item['title'], $item['url'], $item['description'], true);
            $not_featured .= "(id != '" . (int)$item['id'] . "') AND ";
        }
        $results = db_items(
            "SELECT url, title, description,
                    ((3 * (MATCH(title) AGAINST ('" . e($score) . "')))
                   + (2 * (MATCH(description) AGAINST ('" . e($score) . "')))
                   + (1 * (MATCH(content) AGAINST ('" . e($score) . "')))) AS score
             FROM search_items
             WHERE $sql_folders $not_featured (MATCH(content) AGAINST('" . e($filter) . "'$boolean))
             ORDER BY score DESC LIMIT 100");
        foreach ((array)$results as $item) {
            $out['pages'][] = $page_row($item['title'], $item['url'], $item['description'], true);
        }
        $out['limited'] = (is_array($results) && count($results) === 100);
        $out['pages'] = _pg_sw_search_merge_pages($tr_pages, $out['pages']);
        $out['featured'] = _pg_sw_search_attach_sections($out['featured'], $query);
        $out['pages']    = _pg_sw_search_attach_sections($out['pages'], $query);
        return $out;
    }

    // ── Simple: featured, then pages ranked by matches ───────────────────
    $needle   = mb_strtolower($query);
    $like     = "'%" . e(escape_like($query)) . "%'";
    $promoted = get_page_id_list_for_sql(get_page_ids_promoted_on_keyword($query));
    $featured = db_items(
        "SELECT page.page_id AS id, page.page_name AS name, page.page_folder AS folder_id,
                page.page_title AS title, page.page_meta_description AS description
         FROM page
         LEFT JOIN folder ON page.page_folder = folder.folder_id
         WHERE page.page_search = '1' AND folder.folder_archived = '0'
           AND page.page_id IN ($promoted)$binned
         ORDER BY page.page_name ASC", 'id');
    $featured_ids = array();
    foreach ((array)$featured as $id => $item) {
        if (!check_view_access($item['folder_id'], true)) continue;
        $featured_ids[] = (int)$id;
        $out['featured'][] = $page_row($item['title'], $item['name'], $item['description']);
    }
    $not_featured = get_page_id_list_for_sql($featured_ids);

    $scores  = array();   // page_id => number of matches
    $texts   = array();   // page_id => searchable text, for the excerpt
    $info    = array();   // page_id => page row
    $allowed = function ($folder_id) {
        static $seen = array();
        if (!isset($seen[$folder_id])) $seen[$folder_id] = check_view_access($folder_id, true);
        return $seen[$folder_id];
    };
    $base_sql = "FROM page LEFT JOIN folder ON page.page_folder = folder.folder_id
                 WHERE page.page_search = '1' AND folder.folder_archived = '0'
                   AND page.page_id NOT IN ($not_featured)$binned";

    // Classic pages: occurrences in the regions of the page's own collection.
    $region_rows = db_items(
        "SELECT page.page_id, page.page_style, page.page_folder, pregion.collection, pregion.pregion_content
         FROM pregion INNER JOIN page ON page.page_id = pregion.pregion_page
         LEFT JOIN folder ON page.page_folder = folder.folder_id
         WHERE page.page_search = '1' AND folder.folder_archived = '0'
           AND page.page_id NOT IN ($not_featured)$binned
           AND pregion.pregion_content LIKE $like");
    $collections = array();
    foreach ((array)$region_rows as $r) {
        $pid = (int)$r['page_id'];
        if (!$allowed($r['page_folder'])) continue;
        if (!isset($collections[$pid])) {
            $style_id = (int)$r['page_style'] ?: (int)get_style($r['page_folder']);
            $c = (string)db_value("SELECT collection FROM style WHERE style_id = '" . $style_id . "'");
            $collections[$pid] = ($c !== '') ? $c : 'a';
        }
        if ((string)$r['collection'] !== $collections[$pid]) continue;
        $plain = html_entity_decode(strip_tags((string)$r['pregion_content']), ENT_QUOTES, 'UTF-8');
        $hits  = mb_substr_count(mb_strtolower($plain), $needle);
        if ($hits > 0) {
            $scores[$pid] = (isset($scores[$pid]) ? $scores[$pid] : 0) + $hits;
            $texts[$pid]  = (isset($texts[$pid]) ? $texts[$pid] . ' ' : '') . $plain;
        }
    }

    // Title, meta description, keywords and — for a page built in the visual
    // editor — the text of its layout tree.
    $tree_ready = function_exists('pg_multi_page_design_ready') && pg_multi_page_design_ready();
    $candidates = db_items(
        "SELECT page.page_id, page.page_name, page.page_folder, page.page_title,
                page.page_meta_description, page.page_search_keywords"
             . ($tree_ready ? ", page.page_tree_json" : "") . "
         $base_sql
           AND (page.page_title LIKE $like OR page.page_meta_description LIKE $like
                OR page.page_search_keywords LIKE $like"
             . ($tree_ready ? " OR page.page_tree_json LIKE $like" : "") . ")");
    foreach ((array)$candidates as $r) {
        $pid = (int)$r['page_id'];
        if (!$allowed($r['page_folder'])) continue;
        $info[$pid] = $r;
        $hits = 0;
        if ($tree_ready && (string)$r['page_tree_json'] !== '') {
            $tree      = json_decode((string)$r['page_tree_json'], true);
            $tree_text = _pg_sw_tree_text($tree);
            $hits += mb_substr_count(mb_strtolower($tree_text), $needle);
            if ($tree_text !== '') $texts[$pid] = (isset($texts[$pid]) ? $texts[$pid] . ' ' : '') . $tree_text;
            // The topics of its search sections count as the page's words.
            if (strpos((string)$r['page_tree_json'], 'searchSection') !== false) {
                $hits += 2 * mb_substr_count(_pg_search_fold(pg_search_section_topics($tree)), _pg_search_fold($query));
            }
        }
        $hits += 3 * mb_substr_count(mb_strtolower((string)$r['page_title']), $needle);
        $hits += 2 * mb_substr_count(mb_strtolower((string)$r['page_meta_description']), $needle);
        // A keyword match alone still lists the page, as the legacy search
        // does: searching "tasarim" reaches a page tagged "web tasarim".
        if ($hits === 0 && mb_stripos((string)$r['page_search_keywords'], $query) !== false) $hits = 1;
        if ($hits > 0) $scores[$pid] = (isset($scores[$pid]) ? $scores[$pid] : 0) + $hits;
    }
    arsort($scores);

    // Comments on pages not matched yet, ranked after them.
    $comment_rows = db_items(
        "SELECT comments.page_id, comments.name, comments.message, page.page_folder
         FROM comments LEFT JOIN page ON page.page_id = comments.page_id
         LEFT JOIN folder ON page.page_folder = folder.folder_id
         WHERE comments.item_id = '0' AND comments.published = '1'
           AND page.page_search = '1' AND folder.folder_archived = '0'
           AND page.page_id NOT IN ($not_featured)$binned
           AND LOWER(CONCAT_WS(',', comments.name, comments.message)) LIKE $like");
    $comment_scores = array();
    foreach ((array)$comment_rows as $r) {
        $pid = (int)$r['page_id'];
        if (isset($scores[$pid]) || !$allowed($r['page_folder'])) continue;
        $comment_scores[$pid] = (isset($comment_scores[$pid]) ? $comment_scores[$pid] : 0)
            + mb_substr_count(mb_strtolower((string)$r['name']), $needle)
            + mb_substr_count(mb_strtolower((string)$r['message']), $needle);
    }
    arsort($comment_scores);
    foreach ($comment_scores as $pid => $n) $scores[$pid] = $n;

    // Page rows for the ids that came from regions or comments only.
    $missing = array_diff(array_keys($scores), array_keys($info));
    if ($missing) {
        foreach ((array)db_items(
            "SELECT page_id, page_name, page_folder, page_title, page_meta_description
             FROM page WHERE page_id IN (" . implode(',', array_map('intval', $missing)) . ")") as $r) {
            $info[(int)$r['page_id']] = $r;
        }
    }
    foreach ($scores as $pid => $n) {
        if (!isset($info[$pid])) continue;
        $r = $info[$pid];
        $excerpt = trim((string)$r['page_meta_description']);
        if ($excerpt === '' && isset($texts[$pid])) $excerpt = _pg_sw_snippet($texts[$pid], $query);
        $out['pages'][] = $page_row($r['page_title'], $r['page_name'], $excerpt);
    }
    $out['pages'] = _pg_sw_search_merge_pages($tr_pages, $out['pages']);
    $out['featured'] = _pg_sw_search_attach_sections($out['featured'], $query);
    $out['pages']    = _pg_sw_search_attach_sections($out['pages'], $query);
    return $out;
}

// The pages the translated texts matched, then the others, each page once.
function _pg_sw_search_merge_pages($first, $rest)
{
    if (!$first) return $rest;
    $seen = array();
    $out  = array();
    foreach (array_merge($first, $rest) as $row) {
        if (isset($seen[$row['url']])) continue;
        $seen[$row['url']] = true;
        $out[] = $row;
    }
    return $out;
}

// ============================================================================
// Search index items for the records of the visual editor's lists
// ============================================================================
// update_search_index.php writes one item per record a public list reaches,
// at the record's address on the list's detail page. These helpers decide
// which lists qualify and what each item holds.

// Whether a list's records may be indexed through its detail page's record
// view: both widgets show the same records to every visitor
// (pg_sw_form_widget_public()), and no filter of either compares a field
// with the viewer. The index run has no visitor, so such a filter reads ''
// and would match every anonymous record instead of the viewer's own.
function pg_sw_search_index_eligible($list_cfg, $view_cfg)
{
    if (!pg_sw_form_widget_public($list_cfg) || !pg_sw_form_widget_public($view_cfg)) return false;
    return !pg_sw_filters_compare_viewer(pg_sw_merge_record_filters($list_cfg, $view_cfg));
}

// Whether a list of record filters compares a field with the viewer: a
// filter with a field, no fixed value and the dynamic value 'viewer' or
// 'viewers email address'. Without a signed-in visitor such a filter reads
// '' and matches every record sent while signed out, so a render with no
// visitor of its own (the search index, a list of recent comments drawn for
// a visitor who is not signed in) must not apply it. Pure.
function pg_sw_filters_compare_viewer($filters)
{
    if (!is_array($filters)) return false;
    foreach ($filters as $filter) {
        if (!is_array($filter)) continue;
        $field   = isset($filter['field']) ? trim((string)$filter['field']) : '';
        $value   = isset($filter['value']) ? (string)$filter['value'] : '';
        $dynamic = isset($filter['dynamic_value']) ? (string)$filter['dynamic_value'] : '';
        if ($field === '' || $value !== '') continue;
        if ($dynamic === 'viewer' || $dynamic === 'viewers email address') return true;
    }
    return false;
}

// The address a record's search item is stored under: relative to PATH, as
// page items are (search_items.url, varchar 255, cut silently when longer).
// A pretty address too long for the column falls back to ?r=; '' when even
// that does not fit or the record has no reference code.
function pg_sw_search_index_record_url($page_name, $reference_code, $address_name, $pretty)
{
    $reference_code = trim((string)$reference_code);
    if ($reference_code === '') return '';
    $url = pg_sw_record_url($page_name, $reference_code, $address_name, $pretty, '');
    if (strlen($url) > 255) $url = pg_sw_record_url($page_name, $reference_code, '', false, '');
    return (strlen($url) > 255) ? '' : $url;
}

// The title and description of a record's search item. $rows: the record's
// values in form field order, each with the field's name and rss_field and
// the value's data and type. The title is the one the record page names it
// by (pg_sw_record_title(): the record view's title field, else the form's
// RSS title fields) without the page's title suffix; the description is the
// first RSS description value as text. Both fit the columns' 255.
function pg_sw_search_index_record_texts($view_cfg, $rows, $reference_code)
{
    $values      = array('reference_code' => (string)$reference_code);
    $titles      = array();
    $description = null;
    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (!is_array($row)) continue;
        $text = isset($row['data']) ? (string)$row['data'] : '';
        if ($text !== '' && isset($row['type']) && $row['type'] === 'html') $text = convert_html_to_text($text);
        $text = trim($text);
        if ($text === '') continue;
        $rss = isset($row['rss_field']) ? (string)$row['rss_field'] : '';
        if ($rss === 'title') $titles[] = $text;
        if ($rss === 'description' && $description === null) $description = $text;
        $name = isset($row['name']) ? strtolower(trim((string)$row['name'])) : '';
        if ($name === '') continue;
        $values[$name] = isset($values[$name]) ? $values[$name] . ', ' . $text : $text;
    }

    $cfg = is_array($view_cfg) ? $view_cfg : array();
    unset($cfg['title_suffix']);
    $title = mb_substr(pg_sw_record_title($cfg, $values, implode(' ', $titles)), 0, 255);

    $description = ($description === null) ? '' : $description;
    if (mb_strlen($description) > 255) $description = mb_substr($description, 0, 255) . '...';

    return array('title' => $title, 'description' => $description);
}

// Whether site search shows an anonymous visitor the items of a folder with
// this access control type. Site search narrows a visitor with
// check_view_access($folder, true), which lets registration and guest
// folders through as well as public ones.
function pg_sw_search_index_folder_open($access_control_type)
{
    return in_array((string)$access_control_type, array('public', 'registration', 'guest'), true);
}

// The folder a record's search item is filed under; site search shows a
// visitor only the items of folders they may view. The detail page's when
// the list sits on a page site search opens to everyone
// (pg_sw_search_index_folder_open()); otherwise the list's page decides, so
// a staff or draft page listing records puts them in the search of that
// page's audience only, never of the record page's (everyone, or every
// member or holder of its folder's access).
function pg_sw_search_index_folder($host_folder_id, $host_open, $detail_folder_id, $detail_open)
{
    return $host_open ? (int)$detail_folder_id : (int)$host_folder_id;
}

// Write one search item ($item: url, content, title, description, keywords,
// page_id, folder_id), replacing the one stored under the same address.
function pg_sw_search_index_save_item($item, $timestamp)
{
    $url = (string)$item['url'];
    $set = "content = '" . e($item['content']) . "',
            title = '" . e($item['title']) . "',
            description = '" . e($item['description']) . "',
            keywords = '" . e($item['keywords']) . "',
            page_id = '" . (int)$item['page_id'] . "',
            folder_id = '" . (int)$item['folder_id'] . "',
            timestamp = '" . (int)$timestamp . "'";
    if ((int)db_value("SELECT COUNT(*) FROM search_items WHERE url = '" . e($url) . "'") > 0) {
        db("UPDATE search_items SET " . $set . " WHERE url = '" . e($url) . "'");
    } else {
        db("INSERT INTO search_items SET url = '" . e($url) . "', " . $set);
    }
}

// Render a 'search_results' system widget: the site search for ?query=,
// one loop row per result, paginated.
//
// Static tokens: ^^__search_query^^, ^^__result_count^^ (grouped in the page's
//   language like the heading's number: 1.284 / 1,284), ^^__results_heading^^
//   (the sentence the legacy page prints: found N / none found / enter a
//   search), ^^__empty_message^^ (when a search found nothing), ^^__page_count^^.
// Row tokens: ^^__result_title^^, ^^__result_url^^, ^^__result_full_url^^,
//   ^^__result_excerpt^^, ^^__result_type^^ (page | product | product group),
//   ^^__result_type_label^^, ^^__result_image^^, ^^__result_price^^ (HTML),
//   ^^__result_is_featured^^ ('1' or ''), ^^__result_number^^,
//   ^^__result_section^^ (the search section the result links to, or '').
// Bindings: form action search_form, input value search_query, button action
//   submit_search, section pagination; visibility (eo_visible_if) has_query,
//   has_results, no_results, has_prev_page, has_next_page, has_more_pages.
// Paging tokens, as a form list gives them (pg_sw_list_paging()):
//   ^^__page_number^^, ^^__prev_page_url^^, ^^__next_page_url^^; a list of
//   the page links (paging.pages) replaces the pagination section.
// URL: query (the search text, as the legacy search box sends it), the
//   widget's own `page_param`, else page_number (as the catalog and form
//   lists page). cfg `offset` skips the first results.
function _render_system_widget_search_results($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    // Read-only widget: liveform 'search_results', as on the legacy page.
    // An unnamed node would take the messages of a form beside it (the
    // footer's sign-up form among them).
    _pg_inject_messages_node($tree, 'search_results');

    $param      = 'query';
    $page_param = pg_sw_list_page_param($cfg);
    $query = (isset($_GET[$param]) && is_scalar($_GET[$param])) ? trim(strip_tags((string)$_GET[$param])) : '';
    if (mb_strlen($query) > 100) $query = mb_substr($query, 0, 100);

    $found = _pg_sw_search_collect($query, $cfg);
    $rows  = array();
    foreach ($found['featured'] as $r) { $r['featured'] = '1'; $rows[] = $r; }
    foreach ($found['catalog']  as $r) { $r['featured'] = '';  $rows[] = $r; }
    foreach ($found['pages']    as $r) { $r['featured'] = '';  $rows[] = $r; }
    // The results start after `offset` of them; the count and the pages
    // run through what is left.
    $rows  = array_slice($rows, pg_sw_list_offset($cfg));
    $total = count($rows);

    $per_page = (isset($cfg['results_per_page']) && (int)$cfg['results_per_page'] > 0)
        ? min(200, (int)$cfg['results_per_page']) : 20;
    $total_pages  = max(1, (int)ceil($total / $per_page));
    $current_page = isset($_GET[$page_param]) ? max(1, min($total_pages, (int)$_GET[$page_param])) : 1;
    $offset       = ($current_page - 1) * $per_page;
    $paging       = pg_sw_list_paging($page_param, $current_page, $total_pages);

    _pg_sw_apply_search_form_bindings($tree, array(
        'action_url'  => _pg_sw_current_path(),
        'hidden_html' => _pg_sw_hidden_query_inputs(array($param, $page_param)),
        'param'       => $param,
        'query'       => $query,
    ));
    // The pagination section is filled after the render: a list of the page
    // links, known only once the tree is drawn, replaces it. A single page
    // has no pagination, so its section is left out as before.
    pg_cf_apply_section_bindings($tree, array(
        'pagination' => ($total_pages > 1) ? '<!--pg-sr-pagination-->' : '',
    ));
    // Visibility bindings (eo_visible_if) — e.g. a heading shown only once
    // something was searched.
    _eo_apply_visibility_bindings($tree, array(
        'has_query'   => $query !== '',
        'has_results' => $total > 0,
        'no_results'  => $query !== '' && $total === 0,
    ) + $paging['flags']);

    $split = _split_widget_tree($tree);
    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) $loop_template .= _render_tree_node($child, 0, 0);
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }
    if (pg_sw_list_wanted('paging')) {
        pg_sw_list_publish('paging', pg_sw_list_paging_rows($page_param, $current_page, $total_pages));
        $pagination_html = '';
    } else {
        $pagination_html = pg_sw_pagination_html($page_param, $current_page, $total_pages);
    }
    $loop_template = str_replace('<!--pg-sr-pagination-->', $pagination_html, $loop_template);
    $static_html   = str_replace('<!--pg-sr-pagination-->', $pagination_html, $static_html);

    $type_labels = array('page' => lang('Page'), 'product' => lang('Product'), 'product group' => lang('Product Group'));
    $loop_rendered = '';
    // A tree without a loop_area has nowhere to put rows: it renders once.
    if ($loop_template !== '' && $static_html !== '') {
        foreach (array_slice($rows, $offset, $per_page) as $i => $r) {
            $values = pg_sw_escape_token_values(array(
                '__result_title'        => $r['title'],
                '__result_url'          => ($r['url'] !== '') ? $r['url'] : '#',
                '__result_full_url'     => $r['full_url'],
                '__result_excerpt'      => $r['excerpt'],
                '__result_type'         => $r['type'],
                '__result_type_label'   => isset($type_labels[$r['type']]) ? $type_labels[$r['type']] : '',
                '__result_image'        => $r['image'],
                '__result_price'        => $r['price'],
                '__result_is_featured'  => $r['featured'],
                '__result_number'       => (string)($offset + $i + 1),
                '__result_section'      => isset($r['section']) ? (string)$r['section'] : '',
            ), array('__result_price')) + $paging['tokens'];
            $row_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, $values));
            $loop_rendered .= pg_sw_uniquify_row_ids($row_html, $widget_id, $offset + $i);
        }
    }

    $empty_message = (!empty($cfg['empty_message']) && is_string($cfg['empty_message'])) ? $cfg['empty_message'] : lang('No results found.');
    // The starter tree has no element for ^^__empty_message^^, so a message
    // typed into the settings never showed. Without such an element the
    // heading carries it - unless it is still the default, where the
    // heading's own wording (which repeats the query) says more.
    $empty_has_slot = (strpos($static_html . $loop_template, '^^__empty_message^^') !== false);

    if ($query === '') {
        $heading = lang('Please enter keyword(s) or phrase to search.');
    } elseif ($total === 0) {
        $heading = (!$empty_has_slot && $empty_message !== lang('No results found.')
                    && (!function_exists('pg_tr_source_language') || $empty_message !== lang(array('string' => 'No results found.', 'language' => pg_tr_source_language()))))
            ? $empty_message
            : lang(array('string' => 'No results were found for: {var:1}', 'vars' => $query));
    } elseif (!empty($found['limited'])) {
        $heading = lang(array('string' => 'Showing {var:1} of the most relevant results for: {var:2}', 'vars' => array(pg_format_number($total, 0), $query)));
    } else {
        $heading = lang(array('string' => 'Found {var:1} result(s) for: {var:2}', 'vars' => array(pg_format_number($total, 0), $query)));
    }

    $static_values = pg_sw_escape_token_values(array(
        '__search_query'    => $query,
        '__result_count'    => pg_format_number($total, 0),
        '__results_heading' => $heading,
        '__page_count'      => (string)$total_pages,
        '__empty_message'   => ($query !== '' && $total === 0) ? $empty_message : '',
    )) + $paging['tokens'];

    if ($static_html === '') {
        return pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, $static_values));
    }
    $static_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($static_html, $static_values));
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}

// Render a 'registration' system widget.
//
// Posts to <software>/registration_entrance.php with register=true - the
// script the legacy entrance page posts to; pg_member_register() does the
// rest for both. Controls by name: first_name, last_name, username (optional;
// derived from the address when absent), email, email_verify, password,
// password_verify, password_hint, opt_in, register_remember_me. Any other
// control whose `_cf.contact_field` names an address-book column is written
// to the new contact (see pg_member_widget_register_opts()).
//
// Tokens: ^^__site_name^^, ^^__login_url^^, ^^__google_signup_url^^,
// ^^__password_rules^^, ^^__opt_in_label^^. Sections: captcha,
// password_rules, google_signup (every sign-in button that is on),
// login_link. Errors and the "you are registered" notice: liveform
// 'register', printed by the Messages node.
//
// cfg: redirect_page_id (after sign-up), login_page_id (the "already a
// member" link; the legacy login page type is the fallback), contact_group_id
// (the site setting when empty), show_remember_me (default on).
function _render_system_widget_registration($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();

    $send_to = '';
    if (!empty($cfg['redirect_page_id']) && (int)$cfg['redirect_page_id'] > 0) {
        $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    }
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');

    $login_url = '';
    if (!empty($cfg['login_page_id'])) $login_url = _pg_member_page_url($cfg['login_page_id']);
    if ($login_url === '' && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('login');
        if ($legacy !== false && $legacy !== '') $login_url = (string)$legacy;
    }
    if ($login_url === '') $login_url = $software . '/';

    $lf = new liveform('register');

    // Signed in: the form would open a second account. The notice a
    // sign-up just left ("you are registered") prints with it; an editor
    // still sees the form.
    if ($mode !== 'edit' && function_exists('pg_session_signed_in') && pg_session_signed_in()) {
        return _pg_member_render_signed_in($tree, $lf, 'register', $send_to, $page_url,
            array('captcha', 'password_rules', 'google_signup', 'login_link'),
            array('__login_url', '__google_signup_url'),
            array('__site_name' => h($site_name), '__login_url' => h($login_url),
                  '__opt_in_label' => h(defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '')));
    }

    // First view: consent checked, remember-me as the visitor left it last
    // time. A refused attempt refills everything but the passwords.
    $submitted = $lf->field_in_session('token');
    if (!$submitted) {
        $remembered = ((isset($_COOKIE['software']['remember_me']) ? (string)$_COOKIE['software']['remember_me'] : '') === 'true');
        _pg_member_fill_control($tree, 'opt_in', '1', true);
        _pg_member_fill_control($tree, 'register_remember_me', '1', $remembered);
        _pg_member_fill_control($tree, 'remember_me', '1', $remembered);
    } else {
        _pg_member_fill_from_form($tree, $lf, array('password', 'password_verify', 'captcha_submitted_answer'));
    }

    // Features that are off leave nothing behind.
    if (!(defined('PASSWORD_HINT') && PASSWORD_HINT)) {
        _pg_member_drop_control($tree, 'password_hint');
    }
    $remember_ok = defined('REMEMBER_ME') && REMEMBER_ME
                && !(isset($cfg['show_remember_me']) && ($cfg['show_remember_me'] === false || $cfg['show_remember_me'] === 0 || $cfg['show_remember_me'] === '0'));
    if (!$remember_ok) {
        _pg_member_drop_control($tree, 'register_remember_me');
        _pg_member_drop_control($tree, 'remember_me');
    }

    $rules_html  = (defined('STRONG_PASSWORD') && STRONG_PASSWORD && function_exists('get_strong_password_requirements'))
        ? '<div class="pg-password-rules small text-muted">' . get_strong_password_requirements() . '</div>' : '';
    $captcha     = (defined('CAPTCHA') && CAPTCHA && function_exists('get_captcha_fields')) ? (string)get_captcha_fields($lf) : '';
    $google_url  = function_exists('pg_google_signin_url') ? pg_google_signin_url($send_to, 'create') : '';
    $google_html = function_exists('pg_signin_buttons') ? pg_signin_buttons($send_to, 'create') : '';
    $opt_label   = defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '';

    pg_cf_apply_section_bindings($tree, array(
        'captcha'        => $captcha,
        'password_rules' => $rules_html,
        'google_signup'  => $google_html,
        'login_link'     => '<a href="' . h($login_url) . '" class="pg-member-link pg-login-link">' . h(lang('Already have an account? Sign in')) . '</a>',
    ));
    _pg_member_drop_empty_links($tree, array(
        '__login_url'         => $login_url,
        '__google_signup_url' => $google_url,
    ));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'register');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, $software . '/registration_entrance.php', array(
        'register'        => 'true',
        'send_to'         => $send_to,
        'return_to'       => $page_url,
        'require_cookies' => 'true',
        // Which widget drew the form: its tree says which controls fill
        // which address-book columns.
        'pg_widget_id'    => $widget_id,
    ), 'pg-register-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'         => h($site_name),
        '__login_url'         => h($login_url),
        '__google_signup_url' => h($google_url),
        '__password_rules'    => $rules_html,
        '__opt_in_label'      => h($opt_label),
    ));
}

/**
 * What the page is being drawn for, as the system widgets need to know it.
 *
 * get_page_content() sets 'email' for its whole render (true when the page is
 * the body of an e-mail), and around its widget pass adds 'page_id' and the
 * dynamic properties the sender passed with it - 'form_id' for a form's
 * confirmation or notification, 'order_id' for an order receipt, 'comment_id'
 * for a comment e-mail whose page places a comments widget (mail.php). A
 * widget on an e-mail page has no address to read ?r= or ?order_id= from, and
 * the recipient is chosen by the server, so the checks that keep a visitor to
 * their own records do not apply there. The comments widget draws only the
 * comment named, for nobody. 'page_id' is the page being drawn: a widget
 * whose data is the page's own (the comments widget's thread) reads it here,
 * never through _pg_sw_current_page_id(), which is 0 on the live home page,
 * in e-mails and in the search index, and is cached for the whole request.
 * Pass an array to set (the previous context comes back, for restoring); pass
 * nothing to read.
 *
 * @param array|null $set
 * @return array
 */
function pg_sw_render_context($set = null)
{
    static $ctx = array();
    if (is_array($set)) {
        $old = $ctx;
        $ctx = $set;
        return $old;
    }
    return $ctx;
}

/**
 * The page's address names a record, product or product group that is not
 * there, or a record the visitor may not see: the widget draws its not-found
 * state, and the response says so. The status is 404 with an X-Robots-Tag
 * noindex header, and get_page_content() writes a robots noindex tag and no
 * canonical link, so search engines drop the address instead of indexing the
 * not-found screen under it.
 *
 * Pass true to mark (a widget that found nothing for the address), false to
 * clear (get_page_content() before its widget pass), nothing to read. An
 * e-mail body and the SEO pass (which renders pages inside another request)
 * are never marked: the status there belongs to another response.
 *
 * @param bool|null $mark
 * @return bool
 */
function pg_sw_item_missing($mark = null)
{
    static $missing = false;
    if ($mark === null) return $missing;
    if (!$mark) {
        $missing = false;
        return false;
    }
    $sw_ctx = pg_sw_render_context();
    if (!empty($sw_ctx['email']) || (function_exists('pg_seo_rendering') && pg_seo_rendering())) {
        return false;
    }
    // The address may be one that a short link with a * in its name stands
    // for (includes/fn/short_link_redirects.php); then this does not return.
    // Otherwise it goes into the 404 log (includes/fn/not_found_log.php).
    if (function_exists('pg_short_link_not_found')) {
        pg_short_link_not_found();
        pg_not_found_log_mark();
    }
    $missing = true;
    if (!headers_sent()) {
        set_response_code(404);
        header('X-Robots-Tag: noindex');
    }
    return true;
}

/**
 * A one-line, plain-text summary of a product's rich description, for order
 * lines (cart, express order, order view). products.full_description is the
 * operator's page-sized HTML — headings, galleries, embedded styles — and
 * does not belong under a product name in a table row. Style and script
 * bodies are dropped, tags stripped, entities decoded, whitespace collapsed
 * and the result cut at a word boundary. Returns plain text: escape it.
 *
 * @param string $html Rich description
 * @param int    $max  Maximum length in characters, ellipsis included
 * @return string
 */
function pg_sw_item_summary($html, $max = 140)
{
    $text = (string) $html;
    if ($text === '') return '';
    $text = preg_replace('~<(style|script|noscript|template)\b[^>]*>.*?</\1\s*>~is', ' ', $text);
    $text = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>~i', ' ', (string) $text);
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $text));
    if ($text === '') return '';
    $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($len <= $max) return $text;
    $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max - 1, 'UTF-8') : substr($text, 0, $max - 1);
    $space = strrpos($cut, ' ');
    if ($space !== false && $space > strlen($cut) * 0.6) $cut = substr($cut, 0, $space);
    return rtrim($cut, " ,.;:-") . "\u{2026}";
}

// Render an 'order_view' system widget. Reads a single completed order via
// $_GET['order_id'] (or ?oid=) and iterates order_items in loop_area.
// Security: order must belong to the currently logged-in user (orders.user).
//
// ── ORDER STATIC TOKENS ────────────────────────────────────────────────────
//   ^^__order_id^^              numeric order ID (orders.id)
//   ^^__order_no^^              order number (orders.order_number)
//   ^^__order_date^^            formatted per cfg.date_format
//   ^^__order_status^^          raw status text
//   ^^__order_status_text^^     the status as the badge shows it ("Awaiting
//                               Payment" for an unpaid bank transfer)
//   ^^__order_status_stage^^    complete|pending|shipped|cancelled|refunded|default
//                               (_pg_order_status_stage()); the status badge part
//                               draws one badge per stage (widgets_parts.php)
//   ^^__order_total^^           formatted with currency symbol
//   ^^__order_subtotal^^        formatted
//   ^^__order_discount^^        formatted
//   ^^__order_tax^^             formatted
//   ^^__order_shipping^^        formatted (shipping cost)
//   ^^__order_surcharge^^       formatted (card surcharge)
//   ^^__order_item_count^^      # of distinct line items
//   ^^__order_unit_count^^      total units across all items
//   ^^__order_notes^^           visitor's notes (nl2br'd)
//
//   ^^__payment_method^^        raw key (Credit/Debit Card / PayPal Express / Offline)
//   ^^__payment_method_pretty^^ localised label
//   ^^__card_number_masked^^    masked PAN ("4111********1111"); '' when not resolvable
//                               visibility flag: has_card_number
//   ^^__card_type^^             card brand as stored (Visa / MasterCard / …)
//                               visibility flag: has_card_type
//   ^^__transaction_id^^        gateway transaction ref
//   ^^__special_offer_code^^    applied coupon
//
//   Billing (orders.billing_*):
//     __billing_first_name, __billing_last_name, __billing_name,
//     __billing_salutation, __billing_company, __billing_email, __billing_phone,
//     __billing_address_1, __billing_address_2, __billing_city, __billing_state,
//     __billing_zip, __billing_country,
//     __billing_full_address (one-liner CSV), __billing_address_html (<br>-joined)
//
//   Shipping (first ship_to row):
//     __shipping_first_name, __shipping_last_name, __shipping_name,
//     __shipping_company, __shipping_phone, __shipping_address_1,
//     __shipping_address_2, __shipping_city, __shipping_state, __shipping_zip,
//     __shipping_country, __shipping_full_address, __shipping_address_html,
//     __shipping_method, __arrival_date, __has_shipping (1/0)
//
//   Tracking (parcel numbers, from shipping_tracking_numbers):
//     __tracking_code          first parcel's number (raw)
//     __tracking_codes         every parcel: number, carrier, linked when known
//     __tracking_link          first parcel's carrier page (empty when unknown)
//     __tracking_company_name  first parcel's carrier ("Yurtiçi Kargo" etc.)
//     visibility flags: has_tracking_code, has_tracking_link
//     Carrier from ship_tos.shipping_method_code, then the site's default
//     (the ECOMMERCE_DEFAULT_TRACKING_PROVIDER define), then the number's own
//     shape (pg_shipping_carrier); the keys are those of pg_shipping_carriers().
//     Known: yurtici | surat | aras | mng | ptt | ups | fedex | usps
//     NOT orders.tracking_code - that is the campaign code the visitor
//     arrived with, and was shown here as a parcel number until 2026.4.4.
//
//   Timeline (order lifecycle event list): the order.steps list
//     (widgets_lists_order.php); a layout binding the old __timeline token
//     is given the timeline part (widgets_parts.php).
//     visibility flag: has_timeline (true when at least one event has a timestamp)
//     Events emitted only when their timestamps are real (no hollow rows).
//
//   Legal documents (copies kept at checkout, includes/fn/legal_documents.php):
//     __legal_preinfo_url      the pre-information form's kept copy
//     __legal_contract_url     the distance sales contract's kept copy
//     visibility flags: has_legal_documents, has_legal_preinfo, has_legal_contract
//     Empty in the receipt e-mail (it attaches the copies). A layout that
//     binds neither link gets a card listing them after the widget.
//     __withdrawal_deadline    last day of the right of withdrawal (14 days
//                              from the last delivery; date text)
//     visibility flags: has_withdrawal_deadline, withdrawal_deadline_estimated,
//     withdrawal_ended. None in the receipt e-mail; the card after the widget
//     carries the line when the layout does not bind the token.
//
//   Invoice print:
//     __order_invoice_pdf_url  URL to customer-facing printable invoice endpoint
//     visibility flag: can_print_invoice (true only for the logged-in owner)
//
//   Returns (includes/fn/returns.php, posted to order_return.php):
//     __returns                the order's return requests with their status (HTML)
//     __return_form            the request form (HTML); '' when none may be made
//     visibility flags: show_returns, can_request_return, has_returns
//     Signed-in owner only; asking needs ECOMMERCE_ORDER_RETURNS_ALLOWED. A
//     layout that places neither token gets both after the widget.
//
//   Legacy (kept for backward compat):
//     __shipping_address — same as __shipping_full_address (or billing fallback)
//
// ── ITEM LOOP TOKENS (per order_items row) ────────────────────────────────
//   ^^__item_id^^               order_items.id
//   ^^__item_name^^             oi.product_name
//   ^^__item_number^^           oi.product_name (products.name = Product ID / SKU)
//   ^^__item_short_description^^ products.short_description (preferred display name)
//   ^^__item_description^^      products.full_description
//   ^^__item_summary^^          plain one-line summary of full_description
//   ^^__item_image^^            absolute image URL
//   ^^__item_image_alt^^        alt text (short_description ?: name)
//   ^^__item_qty^^              quantity
//   ^^__item_price^^            unit price, formatted
//   ^^__item_total^^            line total, formatted
//   ^^__item_url^^              detail page link (products.address_name)
//   ^^__item_ship_to^^          per-item shipping address summary (one-liner)
//
// Note: requires ECOMMERCE to be active. Returns empty if ECOMMERCE is off or
// the order is not found / does not belong to the current user.
function _render_system_widget_order_view($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    if (!defined('ECOMMERCE') || ECOMMERCE !== true) return '';

    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Messages: liveform 'order_receipt', as on the legacy receipt page.
    // This widget posts no form of its own; an unnamed node would take the
    // messages of a form beside it (the footer's sign-up form among them).
    _pg_inject_messages_node($tree_decoded, 'order_receipt');

    // NOTE: split + render are DEFERRED to AFTER order data is fetched and
    // visibility bindings are applied. Otherwise nodes with
    // `_bindings.eo_visible_if='has_X'` would render BEFORE we know whether
    // the order actually has X (e.g. the Shipping Address card on a digital
    // order, the Order Notes card when the order has no notes).

    $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    // Currency symbol: cfg overrides site constant
    $currency_symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '$';
    if (!empty($cfg['currency_symbol']) && is_string($cfg['currency_symbol'])) {
        $currency_symbol = $cfg['currency_symbol'];
    }

    // Date format from cfg (validates against safe whitelist)
    // One of the widget's formats, or empty for the site's own
    // (pg_sw_default_date_format()).
    $date_format_choice = (isset($cfg['date_format']) && in_array($cfg['date_format'], array('d.m.Y', 'Y-m-d', 'd M Y')))
                           ? $cfg['date_format'] : '';
    $date_format     = ($date_format_choice !== '') ? $date_format_choice : pg_sw_default_date_format('date');
    $datetime_format = ($date_format_choice !== '') ? $date_format_choice . ' H:i' : pg_sw_default_date_format('date and time');
    // 'd M Y' spells the month out; the name itself comes from lang() so it
    // follows the site language, not date()'s always-English output.
    $use_long_month  = ($date_format === 'd M Y');

    // Not-found message from cfg
    $not_found_message = (!empty($cfg['not_found_message']) && is_string($cfg['not_found_message']))
                             ? $cfg['not_found_message'] : lang('The order could not be found.');

    // Get order ID from URL — support both ?order_id= and ?oid=. An order
    // receipt e-mail names its order in the call (pg_sw_render_context()).
    $order_id = 0;
    $sw_ctx         = pg_sw_render_context();
    $email_order_id = (!empty($sw_ctx['email']) && !empty($sw_ctx['order_id'])) ? (int)$sw_ctx['order_id'] : 0;
    if ($email_order_id > 0) {
        $order_id = $email_order_id;
    } elseif (!empty($_GET['order_id']) && is_numeric($_GET['order_id'])) {
        $order_id = (int)$_GET['order_id'];
    } elseif (!empty($_GET['oid']) && is_numeric($_GET['oid'])) {
        $order_id = (int)$_GET['oid'];
    } elseif (!empty($_GET['id']) && is_numeric($_GET['id'])) {
        // The legacy my account screen and the my account widget's order
        // history link with ?id=, the legacy view order page's parameter.
        $order_id = (int)$_GET['id'];
    }

    // No order to show (no id, no such order, or not this visitor's). A tree
    // that places ^^__not_found_message^^ draws its own empty state; any
    // other tree came out as a page of headings with nothing under them, or
    // as nothing at all, so it shows the message through its message area.
    $render_not_found = function () use ($tree_decoded, $not_found_message) {
        if (strpos(pg_sw_cond_tree_json($tree_decoded), '__not_found_message') === false) {
            return _pg_sw_render_message_only($tree_decoded, 'order_view', h($not_found_message), 'warning');
        }
        $split_empty = _split_widget_tree($tree_decoded);
        if ($split_empty['loop_children'] === null) {
            $rendered = trim(_render_tree_node($tree_decoded, 0, 0));
        } else {
            $rendered = trim(_render_tree_node($split_empty['static_tree'], 0, 0));
        }
        $rendered = str_replace('^^__not_found_message^^', h($not_found_message), $rendered);
        return pg_sw_sweep_tokens($rendered);
    };

    if ($order_id <= 0) {
        return $render_not_found();
    }

    $oid_esc = e($order_id);

    // Fetch order — comprehensive column list. Tokens map 1:1 so designers
    // can drop ^^__order_X^^ anywhere without going back to the docs.
    // Security: verify the order belongs to the current logged-in user
    // (orders.user_id is the FK to users — initialize_order() maintains it).
    //
    // Note on optional columns: `notes` is NOT in the legacy DB schema (only
    // added in some upgrade installs); `member_id` lives on `contacts` not
    // `orders`. Both are queried defensively via a separate query that
    // tolerates missing columns / no contact row — so the order view widget
    // never fatals on a vanilla install. The base SELECT below contains
    // ONLY columns guaranteed by the legacy schema (data/backups/*/sql.sql).
    $current_user_id = defined('USER_ID') ? (int)USER_ID : 0;
    $order = db_item(
        "SELECT id, order_number, order_date, status,
                billing_first_name, billing_last_name, billing_company,
                billing_email_address, billing_phone_number, billing_salutation,
                billing_address_1, billing_address_2,
                billing_city, billing_state, billing_zip_code, billing_country,
                subtotal, discount, tax, shipping, surcharge, total,
                payment_method, transaction_id, special_offer_code,
                payment_installment, installment_charges,
                reference_code, tracking_code,
                card_type, card_number,
                user_id, contact_id
         FROM orders
         WHERE id = '$oid_esc'
         LIMIT 1"
    );

    if (!$order) {
        return $render_not_found();
    }

    // Order ids are sequential, so an arbitrary visitor must never see an order.
    // Only the logged-in owner, or the visitor whose session just completed
    // this order (guest checkout stores user_id = 0 and submit_order.php
    // redirects to the thank-you page with ?order_id=N), may see it.
    $is_owner = ($current_user_id > 0 && (int)$order['user_id'] === $current_user_id);
    $just_completed = ((int)($_SESSION['ecommerce']['completed_order_id'] ?? 0) === (int)$order['id']);
    // The receipt e-mail goes to the order's own billing address.
    $is_receipt_mail = ($email_order_id > 0 && $email_order_id === (int)$order['id']);
    if (!$is_owner && !$just_completed && !$is_receipt_mail) {
        // No logged-in owner and not the order this session just placed:
        // the same answer as a missing order, so ids cannot be probed.
        return $render_not_found();
    }

    // Resolve member_id via contacts table (orders.contact_id → contacts.id).
    // Defensive: missing contact row returns empty, never fatals.
    $member_id = '';
    if (!empty($order['contact_id'])) {
        $member_id = (string)db_value("SELECT member_id FROM contacts WHERE id = '" . (int)$order['contact_id'] . "' LIMIT 1");
    }

    // Resolve order notes — the column doesn't exist on every install.
    // No migration creates orders.notes (or orders.tracking_company) yet;
    // they exist only where they were added by hand. Both are reserved
    // for the ERP module, which will add them through its own migration;
    // once that ships this probe becomes unnecessary. Until then, probe
    // with pg_schema_has() so the SELECT never hard-fails.
    static $_order_view_has_notes_col = null;
    if ($_order_view_has_notes_col === null) {
        $_order_view_has_notes_col = pg_schema_has('orders', 'notes');
    }
    $order_notes = '';
    if ($_order_view_has_notes_col) {
        $order_notes = (string)db_value("SELECT notes FROM orders WHERE id = '$oid_esc' LIMIT 1");
    }

    // Money formatter — reused everywhere so currency symbol + locale are consistent.
    // VISITOR_CURRENCY_SYMBOL is sometimes stored as an HTML entity (e.g. "&#8378;"
    // for ₺) — when the formatted string is later h()'d in the per-item token
    // map, the leading "&" gets escaped to "&amp;" and visitors see raw
    // "&#8378;499.95" text. Decode once here so the symbol is always a real
    // Unicode glyph by the time it hits h(). ENT_QUOTES | ENT_HTML5 covers
    // numeric (&#8378;), hex (&#x20BA;), and named (&#nbsp;) entities.
    $currency_symbol = html_entity_decode((string)$currency_symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $fmt_money = function ($cents) use ($currency_symbol) {
        // Shown in the visitor's currency like the legacy order screens.
        return pg_visitor_money((int)$cents / 100, $currency_symbol);
    };

    // 'YYYY-MM-DD' → display string, honouring cfg.date_format (including the
    // Turkish long-month variant). Used by the read-only gift-card block; kept
    // next to $fmt_money so both formatters are defined before the item loop.
    $fmt_gc_date = function ($ymd) use ($use_long_month, $date_format) {
        $ts = strtotime((string)$ymd);
        if ($ts <= 0) return '';
        if ($use_long_month) {
            return date('d', $ts) . ' ' . pg_widget_month_name(date('n', $ts)) . ' ' . date('Y', $ts);
        }
        return date($date_format, $ts);
    };

    // Build addresses — full one-line (legacy) AND per-line variants
    // (each on its own paragraph for cleaner designer composition).
    $billing_name_parts = array_filter(array(
        trim((string)$order['billing_first_name']),
        trim((string)$order['billing_last_name']),
    ));
    $billing_name = implode(' ', $billing_name_parts);
    // A province named like its city (Izmir / Izmir) is written once, as
    // the address book widget does.
    $billing_city_line_parts = array_filter(array(
        trim((string)$order['billing_zip_code']),
        trim((string)$order['billing_city']),
        (mb_strtolower(trim((string)$order['billing_state'])) === mb_strtolower(trim((string)$order['billing_city'])))
            ? '' : trim((string)$order['billing_state']),
    ));
    $billing_city_line = implode(' ', $billing_city_line_parts);
    $billing_full_parts = array_filter(array(
        $billing_name,
        (string)$order['billing_company'],
        (string)$order['billing_address_1'],
        (string)$order['billing_address_2'],
        $billing_city_line,
        (string)$order['billing_country'],
    ));
    $billing_full_address = implode(', ', $billing_full_parts);

    // Multi-line variant — designer can drop ^^__billing_address_html^^ to get
    // a pre-formatted <br>-separated address (Bootstrap-friendly).
    $billing_address_html = implode('<br>', array_map('h', $billing_full_parts));

    // Shipping address — pull the FIRST ship_to row for this order (most
    // single-recipient orders have exactly one). Multi-recipient orders fall
    // back to this for the top-level summary; per-item shipping is exposed
    // in the loop via __item_ship_to. shipping_method NAME comes from the
    // JOIN with shipping_methods (ship_tos.shipping_method_id → fk).
    $ship_to = db_item(
        "SELECT ship_tos.first_name, ship_tos.last_name, ship_tos.company,
                ship_tos.phone_number, ship_tos.address_1, ship_tos.address_2,
                ship_tos.city, ship_tos.state, ship_tos.zip_code, ship_tos.country,
                ship_tos.arrival_date" . pg_sw_entity_select('ship_to', 'ship_tos') . ",
                shipping_methods.name AS shipping_method
         FROM ship_tos
         LEFT JOIN shipping_methods ON ship_tos.shipping_method_id = shipping_methods.id
         WHERE ship_tos.order_id = '$oid_esc' AND ship_tos.complete = 1
         ORDER BY ship_tos.id ASC
         LIMIT 1"
    );
    if (!is_array($ship_to)) $ship_to = array();
    $shipping_name_parts = array_filter(array(
        trim((string)($ship_to['first_name'] ?? '')),
        trim((string)($ship_to['last_name']  ?? '')),
    ));
    $shipping_name = implode(' ', $shipping_name_parts);
    $shipping_city_line_parts = array_filter(array(
        trim((string)($ship_to['zip_code'] ?? '')),
        trim((string)($ship_to['city']     ?? '')),
        (mb_strtolower(trim((string)($ship_to['state'] ?? ''))) === mb_strtolower(trim((string)($ship_to['city'] ?? ''))))
            ? '' : trim((string)($ship_to['state'] ?? '')),
    ));
    $shipping_city_line = implode(' ', $shipping_city_line_parts);
    $shipping_full_parts = array_filter(array(
        $shipping_name,
        (string)($ship_to['company']   ?? ''),
        (string)($ship_to['address_1'] ?? ''),
        (string)($ship_to['address_2'] ?? ''),
        $shipping_city_line,
        (string)($ship_to['country']   ?? ''),
    ));
    $shipping_full_address = implode(', ', $shipping_full_parts);
    $shipping_address_html = implode('<br>', array_map('h', $shipping_full_parts));
    $has_shipping = !empty($ship_to);

    // Legacy ^^__shipping_address^^ token: prefer the real shipping address
    // when a ship_to row exists; fall back to billing for digital orders.
    $shipping_address = $has_shipping ? $shipping_full_address : $billing_full_address;

    // Format order date using configured format.
    // orders.order_date is stored as a unix timestamp (int), NOT a datetime
    // string — see submit_order.php:3734 ($order_date = time()). Earlier
    // code used strtotime() which returns false for raw int strings, so the
    // formatted date silently came out empty. Cast directly to int instead.
    $order_date_ts = (int)$order['order_date'];
    if ($order_date_ts > 0) {
        if ($use_long_month) {
            $order_date = date('d', $order_date_ts) . ' ' . pg_widget_month_name(date('n', $order_date_ts)) . ' ' . date('Y', $order_date_ts);
        } else {
            $order_date = date($date_format, $order_date_ts);
        }
    } else {
        $order_date = '';
    }

    // Status badge — the TEXT comes from the order, the LOOK comes from the
    // designer: the status badge part draws one badge per lifecycle stage,
    // each with its own classes (widgets_parts.php).
    //
    // paid_at came with the 2026.4.4 upgrade, so it is probed like the other
    // optional columns and read once here; the timeline below reuses it.
    static $_order_view_has_paid_at_col = null;
    if ($_order_view_has_paid_at_col === null) {
        $_order_view_has_paid_at_col = pg_schema_has('orders', 'paid_at');
    }
    $paid_at_ts = 0;
    if ($_order_view_has_paid_at_col) {
        $paid_at_ts = (int)db_value("SELECT paid_at FROM orders WHERE id = '$oid_esc' LIMIT 1");
    }
    $order['paid_at'] = $paid_at_ts;

    // A complete bank transfer order whose money has not arrived reads as
    // "awaiting payment" to the customer, not as complete: the status text is
    // replaced and the badge takes the pending colour, while orders.status
    // itself stays what it is.
    $status_text  = (string)$order['status'];
    $status_stage = _pg_order_status_stage($status_text);
    if ($_order_view_has_paid_at_col && pg_order_awaiting_payment($order)) {
        $status_text  = lang('Awaiting Payment');
        $status_stage = 'pending';
    }

    // Fetch order items — much richer column set. address_name lets designers
    // link to the product detail page; short/full description give them a
    // choice of display name; ship_to_id resolves via JOIN for per-item
    // shipping address.
    //
    // Note: order_items has NO item_number / sku column. The SKU is the
    // product's "Product ID / SKU" (products.name), copied into
    // order_items.product_name when the order is placed; __item_number
    // carries it so the token name stays familiar to designers.
    // products.code is the product's image-slider template (HTML), never
    // an identifier, so it is not selected here.
    // p.form / p.form_name / p.form_quantity_type / p.gift_card drive the
    // per-row READ-ONLY blocks below: a product carrying an order form shows
    // what the visitor typed at checkout, a gift card shows the recipient
    // details. Both are output-only here — the order is already placed.
    // The line's and its product's allow-listed values
    // (pg_sw_entity_fields('order_line') and 'product'), read only when the
    // tree names one.
    $_ov_tree_cond  = pg_sw_cond_tree_json($tree_decoded);
    $_ov_line_want  = pg_sw_entity_wanted('order_line', $_ov_tree_cond);
    $_ov_prod_want  = pg_sw_entity_wanted('product', $_ov_tree_cond);
    $_ov_entity_sql = ($_ov_line_want ? pg_sw_entity_select('order_line', 'oi') : '')
                    . ($_ov_prod_want ? pg_sw_entity_select('product', 'p') : '');
    $_ov_entity_ctx = array(
        'currency_symbol' => $currency_symbol,
        'format_stamp'    => function ($ts) use ($fmt_gc_date) { return $fmt_gc_date(date('Y-m-d', $ts)); },
    );
    $item_rows = db_items(
        "SELECT oi.id AS item_id, oi.product_name,
                oi.quantity, oi.price, oi.ship_to_id,
                p.id AS product_id, p.address_name, p.image_name,
                p.short_description, p.full_description,
                p.form AS product_has_form, p.form_name, p.form_quantity_type,
                p.gift_card AS product_is_gift_card" . $_ov_entity_sql . ",
                st.first_name AS st_first_name, st.last_name AS st_last_name,
                st.address_1 AS st_address_1, st.city AS st_city,
                st.state AS st_state, st.zip_code AS st_zip, st.country AS st_country
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         LEFT JOIN ship_tos st ON oi.ship_to_id = st.id
         WHERE oi.order_id = '$oid_esc'
         ORDER BY oi.id ASC"
    );

    $items = array();
    if (is_array($item_rows)) {
        foreach ($item_rows as $r) {
            $qty        = (int)$r['quantity'];
            $unit_cents = (int)$r['price'];
            $line_cents = $unit_cents * $qty;

            $image_url = !empty($r['image_name'])
                ? $output_base . encode_url_path((string)$r['image_name'])
                : '';

            // Per-item detail URL — when products.address_name is set we can
            // link to /<address_name> (catalog detail page slug).
            $item_url = !empty($r['address_name'])
                ? $output_base . encode_url_path((string)$r['address_name'])
                : '';

            // Per-item shipping summary (compact one-liner for the loop row)
            $item_ship_parts = array_filter(array(
                trim((string)($r['st_first_name'] ?? '') . ' ' . (string)($r['st_last_name'] ?? '')),
                (string)($r['st_address_1'] ?? ''),
                trim((string)($r['st_city'] ?? '') . ' ' . (string)($r['st_state'] ?? '') . ' ' . (string)($r['st_zip'] ?? '')),
                (string)($r['st_country'] ?? ''),
            ));
            $item_ship_to = implode(', ', $item_ship_parts);

            // Raw: the loop escapes them, the items list's pass does too.
            $item_entity = array();
            if ($_ov_line_want) $item_entity += pg_sw_entity_values('order_line', pg_sw_entity_order_line_event($r), $_ov_entity_ctx);
            if ($_ov_prod_want) $item_entity += pg_sw_entity_values('product', !empty($r['product_id']) ? $r : null, $_ov_entity_ctx);

            $items[] = array(
                'id'                => (int)$r['item_id'],
                'product_id'        => (int)($r['product_id'] ?? 0),
                'name'              => (string)$r['product_name'],
                // What the visitor typed into the product's order form at
                // checkout, and a gift card's recipient details: read only
                // when the layout lists them (order.item_forms,
                // order.item_gift_cards), drawn as printed values.
                'has_form'          => !empty($r['product_has_form']),
                'form_name'         => trim((string)($r['form_name'] ?? '')),
                'form_quantity_type' => (string)($r['form_quantity_type'] ?? ''),
                'is_gift_card'      => !empty($r['product_is_gift_card']),
                'has_form_data'     => false,
                'has_gift_card'     => false,
                'item_number'       => (string)$r['product_name'],  // Product ID / SKU
                'short_description' => (string)($r['short_description'] ?? ''),
                'full_description'  => (string)($r['full_description'] ?? ''),
                'qty'               => $qty,
                'unit_cents'        => $unit_cents,
                'line_cents'        => $line_cents,
                'unit_price'        => $fmt_money($unit_cents),
                'line_total'        => $fmt_money($line_cents),
                'image'             => $image_url,
                'url'               => $item_url,
                'ship_to'           => $item_ship_to,
                'ship_to_id'        => (int)$r['ship_to_id'],
                'entity'            => $item_entity,
            );
        }
    }

    // Payment method — translate common machine keys to friendly labels.
    // Computed here (rather than later, with $static_values) so it can
    // feed the visibility-binding context below.
    $payment_method_raw = (string)$order['payment_method'];
    $payment_method_pretty = $payment_method_raw;
    $pm_pretty_map = array(
        'Credit/Debit Card' => lang('Credit/Debit Card'),
        'PayPal Express'    => 'PayPal',
        'Offline'           => lang('Bank Transfer / Wire'),
        // The value checkout stores; shown with the label the checkout
        // offered it under (lang() of the method name).
        'Offline Payment'   => lang('Offline Payment'),
    );
    if (isset($pm_pretty_map[$payment_method_raw])) {
        $payment_method_pretty = $pm_pretty_map[$payment_method_raw];
    }
    // An order put on the customer's account (ERP, 10.60) is stored as an
    // offline payment and says what it was.
    if (($payment_method_raw === 'Offline Payment')
        && function_exists('waf_table_has_column') && waf_table_has_column('orders', 'erp_on_account')
        && ((int)db_value("SELECT erp_on_account FROM orders WHERE id = '$oid_esc' LIMIT 1") === 1)) {
        $payment_method_pretty = lang('Put on account');
    }

    // Masked card number — shown under the payment method so the customer can
    // tell WHICH card they paid with when they hold several. Resolution rules
    // (already-masked / encrypted / plaintext) live in the shared helper so
    // this widget behaves identically to the legacy receipt. Empty string when
    // nothing can be safely displayed — the surrounding block should be wired
    // to the `has_card_number` visibility flag rather than rendering a label
    // with no value after it.
    $card_number_masked = _pg_mask_order_card_number((string)$order['card_number']);
    $card_type_str      = trim((string)$order['card_type']);

    // Arrival date / shipping method from ship_to (when present) — also
    // hoisted up here so visibility binding can know about it.
    $arrival_date_str = '';
    if (!empty($ship_to['arrival_date']) && $ship_to['arrival_date'] !== '0000-00-00') {
        $ad_ts = strtotime((string)$ship_to['arrival_date']);
        if ($ad_ts > 0) $arrival_date_str = $use_long_month
            ? date('d', $ad_ts) . ' ' . pg_widget_month_name(date('n', $ad_ts)) . ' ' . date('Y', $ad_ts)
            : date($date_format, $ad_ts);
    }

    // ── Tracking numbers ──────────────────────────────────────────────
    // The parcel numbers, from shipping_tracking_numbers - the table the
    // order screen, the printed order and the account screen all read, one
    // row per parcel, hanging off the ship_to. An order can go to several
    // addresses and an address can take several parcels, so this is a list.
    //
    // It used to be orders.tracking_code, which is the campaign code the
    // visitor arrived with (the ?t= parameter, stored beside the utm_*
    // fields) and never a carrier's number. The customer was shown their
    // campaign code as a parcel number, under a link to a courier that had
    // never heard of it.
    //
    // Since 11.0.20 the row holds the carrier and the link the panel, the API
    // or a marketplace saved; they win, as on the order screen. Without them
    // the carrier comes from the shipping method the customer chose, failing
    // that from the carrier the site names for the whole shop
    // (ECOMMERCE_DEFAULT_TRACKING_PROVIDER), and failing that from the number's
    // own shape (pg_sw_order_parcels(), pg_shipment_view()).
    $tracking_columns = pg_shipment_rows_ready()
        ? ', shipping_tracking_numbers.carrier, shipping_tracking_numbers.tracking_url, shipping_tracking_numbers.status'
        : '';

    $tracking_rows = db_items("SELECT shipping_tracking_numbers.number,
            shipping_tracking_numbers.ship_to_id,
            ship_tos.shipping_method_code" . $tracking_columns . "
        FROM shipping_tracking_numbers
        LEFT JOIN ship_tos ON ship_tos.id = shipping_tracking_numbers.ship_to_id
        WHERE (shipping_tracking_numbers.order_id = '$oid_esc')
            AND (TRIM(shipping_tracking_numbers.number) != '')
        ORDER BY shipping_tracking_numbers.id ASC");

    $tracking_parcels = pg_sw_order_parcels(is_array($tracking_rows) ? $tracking_rows : array(), pg_shipping_default_carrier());

    // The first parcel answers the singular tokens, which is what a one-parcel
    // order - nearly all of them - needs; __tracking_codes carries the rest.
    $tracking_code_str      = isset($tracking_parcels[0]) ? $tracking_parcels[0]['number'] : '';
    $tracking_link          = isset($tracking_parcels[0]) ? $tracking_parcels[0]['url'] : '';
    $tracking_company_label = isset($tracking_parcels[0]) ? $tracking_parcels[0]['carrier'] : '';

    // Every parcel as one block: the number, linked when the carrier is known.
    $tracking_codes_html = '';

    foreach ($tracking_parcels as $parcel) {
        $shown = h($parcel['number']);
        if ($parcel['carrier'] !== '') {
            $shown .= ' <span class="text-muted">(' . h($parcel['carrier']) . ')</span>';
        }
        if ($parcel['url'] !== '') {
            $shown = '<a href="' . h($parcel['url']) . '" target="_blank" rel="noopener">' . $shown . '</a>';
        }
        $tracking_codes_html .= ($tracking_codes_html === '' ? '' : '<br>') . $shown;
    }

    // ── Timeline events ───────────────────────────────────────────────
    // Aggregate ship_to ship_date / delivery_date across all rows; the
    // earliest non-zero date wins (a multi-recipient order is "shipped"
    // when the first ship_to goes out, "delivered" when the first
    // arrives — closest to what customers actually want to see).
    static $_order_view_has_cancelled_at_col = null;
    if ($_order_view_has_cancelled_at_col === null) {
        $_order_view_has_cancelled_at_col = pg_schema_has('orders', 'cancelled_at');
    }
    $cancelled_at_ts = 0;
    $cancellation_reason = '';
    if ($_order_view_has_cancelled_at_col) {
        // cancellation_reason arrived in the same upgrade as cancelled_at, so
        // one probe covers both.
        $_cancel_row = db_item("SELECT cancelled_at, cancellation_reason FROM orders WHERE id = '$oid_esc' LIMIT 1");
        if (is_array($_cancel_row)) {
            $cancelled_at_ts     = (int)$_cancel_row['cancelled_at'];
            $cancellation_reason = trim((string)($_cancel_row['cancellation_reason'] ?? ''));
        }
    }
    $shipping_ts = 0;
    $delivery_ts = 0;
    $st_dates = db_items(
        "SELECT ship_date, delivery_date FROM ship_tos
         WHERE order_id = '$oid_esc'
           AND complete = 1
         ORDER BY id ASC"
    );
    if (is_array($st_dates)) {
        foreach ($st_dates as $_d) {
            if (!empty($_d['ship_date']) && $_d['ship_date'] !== '0000-00-00') {
                $_t = strtotime((string)$_d['ship_date']);
                if ($_t > 0 && ($shipping_ts === 0 || $_t < $shipping_ts)) $shipping_ts = $_t;
            }
            if (!empty($_d['delivery_date']) && $_d['delivery_date'] !== '0000-00-00') {
                $_t = strtotime((string)$_d['delivery_date']);
                if ($_t > 0 && ($delivery_ts === 0 || $_t < $delivery_ts)) $delivery_ts = $_t;
            }
        }
    }
    // Format helper — same date logic as $order_date above, plus HH:MM
    // for events that have a real time component (cancellation, order
    // creation). Pure-date columns (ship_date, delivery_date) skip the
    // time suffix since the DB only stores YYYY-MM-DD.
    $_fmt_ts = function ($ts, $with_time) use ($use_long_month, $date_format, $datetime_format) {
        $ts = (int)$ts;
        if ($ts <= 0) return '';
        if (!$use_long_month) {
            return date($with_time ? $datetime_format : $date_format, $ts);
        }
        $s = date('d', $ts) . ' ' . pg_widget_month_name(date('n', $ts)) . ' ' . date('Y', $ts);
        if ($with_time) $s .= ' ' . date('H:i', $ts);
        return $s;
    };
    $timeline_events = array();
    // Created — always present (order_date is required to fetch the row)
    if ($order_date_ts > 0) {
        $timeline_events[] = array(
            'label' => lang('Order Created'),
            'ts'    => $_fmt_ts($order_date_ts, true),
            'kind'  => 'created',
        );
    }
    // Paid — paid_at is stamped when the money is confirmed: by the gateway
    // at checkout, by the operator for a bank transfer, or by an ERP receipt
    // that settles the invoice. Orders from before the column existed carry
    // paid_at = 0, so a transaction reference still stands in for it there,
    // proxied to order_date because a gateway-completed order sets both at
    // the same instant (submit_order.php). An unpaid bank transfer has
    // neither and gets no event.
    if ($paid_at_ts > 0) {
        $timeline_events[] = array(
            'label' => lang('Payment Received'),
            'ts'    => $_fmt_ts($paid_at_ts, true),
            'kind'  => 'paid',
        );
    } elseif (trim((string)$order['transaction_id']) !== '' && $order_date_ts > 0) {
        $timeline_events[] = array(
            'label' => lang('Payment Received'),
            'ts'    => $_fmt_ts($order_date_ts, true),
            'kind'  => 'paid',
        );
    }
    // ship_date / delivery_date are worked out at checkout from the
    // shipping method: until the day comes they are a forecast, and a
    // just-placed order listed itself as shipped and delivered.
    $_today_ts = strtotime(date('Y-m-d'));
    if ($shipping_ts > 0) {
        $timeline_events[] = array(
            'label' => ($shipping_ts > $_today_ts) ? lang('Estimated shipping') : lang('Shipped'),
            'ts'    => $_fmt_ts($shipping_ts, false),
            'kind'     => 'shipped',
            'estimate' => ($shipping_ts > $_today_ts),
        );
    }
    if ($delivery_ts > 0) {
        $timeline_events[] = array(
            'label' => ($delivery_ts > $_today_ts) ? lang('Estimated delivery') : lang('Delivered'),
            'ts'    => $_fmt_ts($delivery_ts, false),
            'kind'     => 'delivered',
            'estimate' => ($delivery_ts > $_today_ts),
        );
    }
    if ($cancelled_at_ts > 0) {
        // The reason is part of the event: a customer whose transfer order
        // was cancelled by the timer should read why without asking.
        $timeline_events[] = array(
            'label' => lang('Cancelled') . ($cancellation_reason !== '' ? ' — ' . $cancellation_reason : ''),
            'ts'    => $_fmt_ts($cancelled_at_ts, true),
            // A list of the steps shows the reason in an element of its own.
            'kind'       => 'cancelled',
            'step_label' => lang('Cancelled'),
            'note'       => $cancellation_reason,
        );
    }
    // Drawn by the order.steps list of the timeline part (widgets_parts.php):
    // a step without a date is never listed.
    $has_timeline = false;
    foreach ($timeline_events as $_ov_ev) {
        if (!empty($_ov_ev['ts'])) { $has_timeline = true; break; }
    }

    // ── Invoice print URL ─────────────────────────────────────────────
    // Customer-facing printable invoice endpoint. URL is built only once
    // here so the property panel doesn't need to know the file name. The
    // visitor must already be logged in as the order owner (the endpoint
    // re-checks ownership server-side). For guest orders the URL still
    // surfaces but the endpoint will refuse access.
    $invoice_print_url = '';
    if (defined('OUTPUT_PATH') && (int)$order['id'] > 0) {
        $invoice_print_url = OUTPUT_PATH . SOFTWARE_DIRECTORY . '/order_invoice_print.php?order_id=' . (int)$order['id'];
    }
    $can_print_invoice = ($current_user_id > 0 && $current_user_id === (int)$order['user_id']);

    // ── Legal documents ───────────────────────────────────────────────
    // The pre-information form and the distance sales contract as they were
    // kept with the order at checkout (includes/fn/legal_documents.php).
    // legal_document.php shows a copy to the visitors this widget lets in:
    // the signed-in owner and the guest who has just placed the order. The
    // receipt e-mail carries the copies as attachments and its reader may
    // have no session to open a link with, so it gets none. A kept copy
    // stays readable after the store switches the documents off.
    $legal_urls = pg_legal_document_copy_urls(
        empty($sw_ctx['email']) ? pg_legal_documents_of_order((int)$order['id']) : array(),
        (int)$order['id']
    );
    // The last day of the right of withdrawal: 14 days from the last
    // delivery, an estimate while the deliveries carry only planned dates.
    // The receipt e-mail goes out before anything is shipped, so it gets none.
    $withdrawal = empty($sw_ctx['email']) ? pg_legal_withdrawal_of_order((int)$order['id']) : null;
    $withdrawal_date = is_array($withdrawal) ? $fmt_gc_date($withdrawal['deadline']) : '';
    // A layout made before the links existed binds neither of them: it is
    // given the legal documents card, with the withdrawal line under the
    // links unless the layout places that itself (widgets_parts.php).

    // ── Apply visibility bindings BEFORE tree split + render ──────────
    // Reuses _eo_apply_visibility_bindings (defined in this same file for
    // express_order). The walker is widget-agnostic — it drops any node
    // whose `_bindings.eo_visible_if` resolves to a falsy context flag.
    // Flags reflect the live order data so the Shipping Address /
    // Order Notes cards (etc.) vanish on orders that don\'t have
    // shipping or notes, instead of rendering as empty stubs.
    // ── Cancellation policy resolution ─────────────────────────────────
    // Three gates control whether the cancel UI surfaces on this order:
    //   1. Site setting: ECOMMERCE_ORDER_CANCEL_ALLOWED must be true
    //   2. Order status: must not already be cancelled
    //   3. (optional) Pre-shipment: when ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED
    //      is true (default), a recorded tracking code locks it.
    // Designer wires the cancel button under `eo_visible_if='can_cancel'`
    // so when the gate is closed the button + form vanish from the DOM
    // instead of being rendered then disabled.
    $_ov_cancel_allowed = defined('ECOMMERCE_ORDER_CANCEL_ALLOWED') && ECOMMERCE_ORDER_CANCEL_ALLOWED === true;
    $_ov_already_cancelled = ($order['status'] === 'cancelled');
    $_ov_can_cancel = $_ov_cancel_allowed && !$_ov_already_cancelled;
    // This widget is the CUSTOMER's view, so the guard always applies here.
    // Must stay in lockstep with process_order_cancellation()'s guard — the
    // shared _order_has_shipped() helper is what keeps the button's visibility
    // and the endpoint's answer from disagreeing.
    if ($_ov_can_cancel && (!defined('ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED') || ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED !== false)) {
        if (_order_has_shipped($order_id)) $_ov_can_cancel = false;
    }

    // URL query flag set by cancel_order.php redirect — drives the
    // success/failure notice token. Values: '1' (success), 'already',
    // 'shipped'. Missing = nothing to show.
    $_ov_cancel_flash = isset($_GET['cancelled']) ? (string)$_GET['cancelled'] : '';

    // ── Returns (includes/fn/returns.php) ──────────────────────────────
    // Only the signed-in owner reads or asks for returns; a guest who has
    // just ordered and the receipt e-mail see none. The order.returns list
    // (the returns part) lists every request of the order, the rows built
    // here; __return_form shows while one may still be made
    // (ECOMMERCE_ORDER_RETURNS_ALLOWED, the goods sent, the window open).
    // Both post to order_return.php, whose answer comes back through this
    // widget's messages node (liveform 'order_receipt').
    $_ov_returns      = ($is_owner && empty($sw_ctx['email'])) ? pg_return_customer_view($order_id, $fmt_gc_date) : null;
    $_ov_return_form  = '';
    $_ov_return_rows  = array('order.returns' => array(), 'order.return_lines' => array());
    if (is_array($_ov_returns) && ($_ov_returns['has_returns'] || $_ov_returns['can_request'])) {
        $_ov_return_cfg = array(
            'action'   => (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : 'software') . '/order_return.php',
            'send_to'  => isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '',
            'token'    => function_exists('get_token_field') ? get_token_field() : '',
            'order_id' => (int)$order['id'],
        );
        $_ov_return_form  = $_ov_returns['can_request'] ? pg_return_form_html($_ov_returns['deliveries'], $_ov_return_cfg) : '';
        $_ov_return_rows  = pg_sw_list_order_return_rows($_ov_returns['returns'], $_ov_return_cfg);
    }
    $_ov_has_returns = !empty($_ov_return_rows['order.returns']);
    // A layout made before returns existed places neither token: it is
    // given the returns card, shown while the setting is on
    // (show_returns_card, widgets_parts.php).

    $_ov_vis_ctx = array(
        'has_shipping'             => (bool)$has_shipping,
        'has_notes'                => trim($order_notes) !== '',
        'has_payment_method'       => $payment_method_raw !== '',
        // Card row surfaces only when a maskable number actually resolved —
        // gateway-less / offline orders and undecryptable blobs stay hidden
        // instead of printing "Kart Numarası" above nothing.
        'has_card_number'          => $card_number_masked !== '',
        'has_card_type'            => $card_type_str !== '',
        'has_transaction_id'       => trim((string)$order['transaction_id']) !== '',
        'has_special_offer_code'   => trim((string)$order['special_offer_code']) !== '',
        'has_shipping_method'      => isset($ship_to['shipping_method']) && (string)$ship_to['shipping_method'] !== '',
        'has_arrival_date'         => $arrival_date_str !== '',
        'has_tracking_code'        => $tracking_code_str !== '',
        // Installment-related flags — true when the Iyzipay 3DS flow stored
        // a plan > 1 month (1 = paid up front / single payment, suppress row).
        'has_installment'          => (int)$order['payment_installment'] > 1,
        'has_installment_charges'  => (int)$order['installment_charges'] > 0,
        // Totals row flags (parallel to express_order)
        'has_discount'             => (int)$order['discount']  > 0,
        'has_tax'                  => (int)$order['tax']       > 0,
        'has_shipping_cost'        => (int)$order['shipping']  > 0,
        'has_surcharge'            => (int)$order['surcharge'] > 0,
        // Cancellation gates
        'can_cancel'               => $_ov_can_cancel,
        'is_cancelled'             => $_ov_already_cancelled,
        // Flash-style notice from cancel_order.php redirect
        'cancel_flash_success'     => $_ov_cancel_flash === '1',
        'cancel_flash_already'     => $_ov_cancel_flash === 'already',
        'cancel_flash_shipped'     => $_ov_cancel_flash === 'shipped',
        // Returns: the card shows when there is a request to follow or one
        // may be made; the form part only in the second case.
        'show_returns'             => ($_ov_has_returns || $_ov_return_form !== ''),
        'show_returns_card'        => is_array($_ov_returns) && $_ov_returns['enabled']
                                      && ($_ov_has_returns || $_ov_return_form !== ''),
        'can_request_return'       => $_ov_return_form !== '',
        'has_returns'              => $_ov_has_returns,
        // The carrier link surfaces only when a parcel number is recorded AND
        // its carrier could be identified.
        'has_tracking_link'        => $tracking_link !== '',
        // Timeline card hides on orders with no events (impossible in
        // practice — order_date is always set — but kept for symmetry).
        'has_timeline'             => $has_timeline,
        // Print invoice button: logged-in owner only. Guests viewing a
        // retrieved order won't see the action.
        'can_print_invoice'        => $can_print_invoice,
        // Legal documents: the card while the order has a kept copy, each
        // link while its own copy is kept.
        'has_legal_documents'      => implode('', $legal_urls) !== '',
        'has_legal_preinfo'        => $legal_urls['preinfo'] !== '',
        'has_legal_contract'       => $legal_urls['contract'] !== '',
        // Right of withdrawal: the line while a deadline is known, its note
        // while the deadline is estimated, and once the deadline has passed.
        'has_withdrawal_deadline'       => $withdrawal_date !== '',
        'withdrawal_deadline_estimated' => $withdrawal_date !== '' && $withdrawal['estimated'] && $withdrawal['open'],
        'withdrawal_ended'              => $withdrawal_date !== '' && !$withdrawal['open'],
    );
    if (function_exists('_eo_apply_visibility_bindings')) {
        _eo_apply_visibility_bindings($tree_decoded, $_ov_vis_ctx);
    }

    // ── Now split + render with the visibility-filtered tree ──────────
    $split = _split_widget_tree($tree_decoded);
    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // The order lists' rows (widgets_lists_order.php), filed once the tree
    // is drawn: only a widget that drew such a list keeps them, and only a
    // list of the recipients reads them.
    $_ov_listed = pg_sw_list_wanted('order');
    if ($_ov_listed) {
        $_ov_shipments = array();
        if (isset($_ov_listed['order.shipments'])) {
            $_ov_st_rows = db_items(
                "SELECT ship_tos.id, ship_tos.first_name, ship_tos.last_name, ship_tos.company,
                        ship_tos.phone_number, ship_tos.address_1, ship_tos.address_2,
                        ship_tos.city, ship_tos.state, ship_tos.zip_code, ship_tos.country,
                        ship_tos.arrival_date" . pg_sw_entity_select('ship_to', 'ship_tos') . ",
                        shipping_methods.name AS shipping_method
                 FROM ship_tos
                 LEFT JOIN shipping_methods ON ship_tos.shipping_method_id = shipping_methods.id
                 WHERE ship_tos.order_id = '$oid_esc' AND ship_tos.complete = 1
                 ORDER BY ship_tos.id ASC"
            );
            foreach ((array)$_ov_st_rows as $_ov_st) {
                $_ov_shipments[] = pg_sw_list_order_shipment_row($_ov_st, $fmt_gc_date);
            }
        }
        // First: it marks the items that have form answers or gift cards.
        $_ov_item_rows = pg_sw_list_order_item_parts_read($items, $_ov_listed, $fmt_money, $fmt_gc_date);
        pg_sw_list_publish('order', pg_sw_list_order_rows($_ov_shipments, $items, $tracking_parcels, $timeline_events) + $_ov_item_rows + $_ov_return_rows);
    }

    // Build loop output
    // A tree with no loop_area has nowhere to put rows: the whole tree is the
    // surround and it renders once. Building the rows anyway meant laying out
    // the entire template per record and then discarding it — every starter
    // tree carries a loop_area, so this only ever happened to a tree from
    // before loop_area existed.
    $loop_rendered = '';
    if ($loop_template !== '' && $static_html !== '' && count($items) > 0) {
        foreach ($items as $idx => $item) {
            $row_values = array(
                // Existing tokens — kept for backward compatibility
                '__item_name'              => h($item['name']),
                '__item_qty'               => h((string)$item['qty']),
                '__item_price'             => h($item['unit_price']),
                '__item_total'             => h($item['line_total']),
                '__item_image'             => h($item['image']),
                // New: identifiers + alternate display names
                '__item_id'                => (string)$item['id'],
                '__item_number'            => h($item['item_number']),
                '__item_short_description' => h($item['short_description']),
                // RAW HTML — rich text, same as the cart and catalog widgets.
                '__item_description'       => pg_defuse_php_tags($item['full_description']),
                '__item_summary'           => h(pg_sw_item_summary($item['full_description'])),
                // The image alt text follows the same precedence as cart:
                // prefer the readable short_description, fall back to name.
                '__item_image_alt'         => h($item['short_description'] !== '' ? $item['short_description'] : $item['name']),
                // Detail link (empty when product has no address_name slug)
                '__item_url'               => h($item['url']),
                // Per-item shipping (one-liner; empty for digital products)
                '__item_ship_to'           => h($item['ship_to']),
                // Whether the item's product form answers and gift cards
                // have rows (the item form and gift card parts' boxes).
                '__item_has_form_data'     => !empty($item['has_form_data']) ? '1' : '',
                '__item_has_gift_card'     => !empty($item['has_gift_card']) ? '1' : '',
            ) + array_map('h', $item['entity']);
            $row_html = pg_sw_fill_longest($loop_template, $row_values);
            $row_html = pg_sw_sweep_tokens($row_html);

            // Uniquify Bootstrap component IDs per item row
            $row_html = pg_sw_uniquify_row_ids($row_html, $widget_id, $idx);

            $loop_rendered .= $row_html;
        }
    }

    // Number of distinct line items + total units (across all items)
    $item_count = count($items);
    $unit_count = 0;
    foreach ($items as $_it) { $unit_count += (int)$_it['qty']; }

    // (payment_method_pretty + arrival_date_str were computed earlier so
    // they could feed the visibility-binding context. Reuse them here.)

    // Apply static tokens — comprehensive set covering order, billing,
    // shipping, totals, payment, items count, status badge. Designers can
    // freely drop any of these into the tree without code changes.
    $static_values = array(
        // ── Existing (kept for backward compat) ────────────────────────
        '__order_no'         => h((string)$order['order_number']),
        '__order_date'       => h($order_date),
        '__order_status'     => h((string)$order['status']),
        '__order_total'      => $fmt_money((int)$order['total']),
        '__shipping_address' => h($shipping_address),
        '__not_found_message'        => '',

        // ── Order identifiers / meta ──────────────────────────────────
        '__order_id'          => (string)(int)$order['id'],
        // The status as the badge part shows it ("Awaiting Payment" for a
        // bank transfer whose money has not arrived) and its stage, which
        // picks the badge (pg_sw_part_order_status_badge()).
        '__order_status_text'  => h($status_text),
        '__order_status_stage' => $status_stage,

        // ── Totals breakdown (raw + formatted) ────────────────────────
        '__order_subtotal'   => $fmt_money((int)$order['subtotal']),
        '__order_discount'   => $fmt_money((int)$order['discount']),
        '__order_tax'        => $fmt_money((int)$order['tax']),
        '__order_shipping'   => $fmt_money((int)$order['shipping']),
        '__order_surcharge'  => $fmt_money((int)$order['surcharge']),

        // ── Counts ────────────────────────────────────────────────────
        '__order_item_count' => (string)$item_count,
        '__order_unit_count' => (string)$unit_count,

        // ── Cancellation ──────────────────────────────────────────────
        // __cancel_form: pre-built HTML <form> posting to cancel_order.php
        //   with CSRF token, order_id hidden, optional reason textarea,
        //   and the destructive "Siparişi İptal Et" submit. Designer drops
        //   ^^__cancel_form^^ wherever they want the form. Wrapped in
        //   visibility binding (eo_visible_if='can_cancel') so the form
        //   element disappears when policy disallows cancel.
        // __cancel_status: pre-built alert block shown after the redirect
        //   from cancel_order.php (?cancelled=1 success / =shipped error /
        //   =already info). Empty string when no flash present.
        '__cancel_form'           => _eo_render_cancel_order_form(
            (int)$order['id'],
            (string)$order['payment_method'],
            (int)$order['total']
        ),
        '__cancel_status'         => _eo_render_cancel_status_alert($_ov_cancel_flash),

        // ── Returns ───────────────────────────────────────────────────
        // __return_form: the request form, one per delivery that still has
        //   something to give back, pre-built HTML, '' when empty. The
        //   requests are the order.returns list's (the returns part).
        '__return_form'           => $_ov_return_form,

        // ── Payment ───────────────────────────────────────────────────
        '__payment_method'        => h($payment_method_raw),
        '__payment_method_pretty' => h($payment_method_pretty),
        // Masked PAN — never the raw number. See _pg_mask_order_card_number.
        '__card_number_masked'    => h($card_number_masked),
        '__card_type'             => h($card_type_str),
        '__transaction_id'        => h((string)$order['transaction_id']),
        '__special_offer_code'    => h((string)$order['special_offer_code']),
        '__reference_code'        => h((string)$order['reference_code']),
        '__tracking_code'         => h($tracking_code_str),
        '__tracking_codes'        => $tracking_codes_html,
        // Installment info (Iyzipay 3DS only; PayPal/Offline always = 1).
        '__installment_count'     => (string)(int)$order['payment_installment'],
        '__installment_charges'   => $fmt_money((int)$order['installment_charges']),
        // Per-month amount when paying in installments: total ÷ count (the
        // order total already carries the installment charge).
        // Renders empty for single-payment orders (avoids dividing by 1 noise).
        '__installment_per_month' => ((int)$order['payment_installment'] > 1
            ? $fmt_money((int)round((int)$order['total'] / (int)$order['payment_installment']))
            : ''),
        // Notes/member_id resolved separately (legacy schema may lack them).
        '__order_notes'           => nl2br(h($order_notes)),
        '__member_id'             => h($member_id),

        // ── Billing — individual fields + pre-built composite ─────────
        '__billing_first_name' => h((string)$order['billing_first_name']),
        '__billing_last_name'  => h((string)$order['billing_last_name']),
        '__billing_name'       => h($billing_name),
        '__billing_salutation' => h((string)$order['billing_salutation']),
        '__billing_company'    => h((string)$order['billing_company']),
        '__billing_email'      => h((string)$order['billing_email_address']),
        '__billing_phone'      => h((string)$order['billing_phone_number']),
        '__billing_address_1'  => h((string)$order['billing_address_1']),
        '__billing_address_2'  => h((string)$order['billing_address_2']),
        '__billing_city'       => h((string)$order['billing_city']),
        '__billing_state'      => h((string)$order['billing_state']),
        '__billing_zip'        => h((string)$order['billing_zip_code']),
        '__billing_country'    => h((string)$order['billing_country']),
        '__billing_full_address' => h($billing_full_address),
        '__billing_address_html' => $billing_address_html,   // pre-rendered <br>-joined block (NOT h()'d)

        // ── Shipping — same shape as billing, sourced from ship_tos ──
        '__shipping_first_name' => h((string)($ship_to['first_name'] ?? '')),
        '__shipping_last_name'  => h((string)($ship_to['last_name']  ?? '')),
        '__shipping_name'       => h($shipping_name),
        '__shipping_company'    => h((string)($ship_to['company']   ?? '')),
        '__shipping_phone'      => h((string)($ship_to['phone_number'] ?? '')),
        '__shipping_address_1'  => h((string)($ship_to['address_1'] ?? '')),
        '__shipping_address_2'  => h((string)($ship_to['address_2'] ?? '')),
        '__shipping_city'       => h((string)($ship_to['city']      ?? '')),
        '__shipping_state'      => h((string)($ship_to['state']     ?? '')),
        '__shipping_zip'        => h((string)($ship_to['zip_code']  ?? '')),
        '__shipping_country'    => h((string)($ship_to['country']   ?? '')),
        '__shipping_full_address' => h($shipping_full_address),
        '__shipping_address_html' => $shipping_address_html,
        '__shipping_method'     => h((string)($ship_to['shipping_method'] ?? '')),
        '__arrival_date'        => h($arrival_date_str),
        '__has_shipping'        => $has_shipping ? '1' : '0',

        // ── Tracking (carrier link) ────────────────────────────────────
        // The first parcel's carrier page, when the carrier could be worked
        // out from the shipping method or the number's shape. Empty otherwise
        // — designers should wire the surrounding card under
        // eo_visible_if='has_tracking_link'.
        '__tracking_link'           => h($tracking_link),
        '__tracking_company_name'   => h($tracking_company_label),

        // ── Invoice print ──────────────────────────────────────────────
        // URL to the customer-facing printable invoice endpoint. Owner
        // check is enforced server-side; this token only builds the link.
        '__order_invoice_pdf_url'   => h($invoice_print_url),

        // ── Legal documents ────────────────────────────────────────────
        // The copies kept with the order, the names the checkout's links
        // carry. Empty when the order has no copy of that document.
        '__legal_preinfo_url'       => h($legal_urls['preinfo']),
        '__legal_contract_url'      => h($legal_urls['contract']),
        '__withdrawal_deadline'     => h($withdrawal_date),
        // The whole line, its words in the site's language: "Last day of
        // the right of withdrawal: …", an estimate's note, or that it ended.
        '__withdrawal_text'         => h(pg_legal_withdrawal_text($withdrawal, $withdrawal_date)),
        // The key the order lists read outside a list of recipients: the
        // whole order.
        '__ship_to_key'             => '0',
    );
    // The order's allow-listed values (pg_sw_entity_fields('order')), read
    // in one more query only when the tree names one; dates as the widget's
    // other dates read.
    if (pg_sw_entity_wanted('order', pg_sw_cond_tree_json($tree_decoded))) {
        $static_values += array_map('h', pg_sw_entity_values('order', pg_sw_entity_order_read((int)$order['id']), array(
            'currency_symbol' => $currency_symbol,
            'format_stamp'    => function ($ts) use ($_fmt_ts) { return $_fmt_ts($ts, false); },
        )));
    }
    // The first recipient's allow-listed values (pg_sw_entity_fields(
    // 'ship_to')), read by the ship_tos query above.
    $static_values += array_map('h', pg_sw_entity_values('ship_to', $ship_to, array(
        'format_stamp' => function ($ts) use ($_fmt_ts) { return $_fmt_ts($ts, false); },
    )));
    // The dates a format can read (^^__order_date^^%%d.m.Y H:i%%).
    $_ov_typed = _pg_sw_order_view_dates(
        $order_date_ts,
        isset($ship_to['arrival_date']) ? $ship_to['arrival_date'] : '',
        is_array($withdrawal) ? $withdrawal['deadline'] : ''
    );

    // The timeline part's look; an e-mail carries it inline (widgets_parts.php).
    $_ov_css = (empty($sw_ctx['email']) && strpos($static_html . $loop_template, 'pg-ov-timeline') !== false)
        ? _pg_widget_layout_css_once() : '';

    if ($static_html === '') {
        // No loop_area — replace tokens in the single rendered template
        $rendered = pg_sw_fill_longest($loop_template, $static_values, $_ov_typed);
        return $_ov_css . pg_sw_sweep_tokens($rendered);
    }

    $static_html = pg_sw_fill_longest($static_html, $static_values, $_ov_typed);
    $static_html = pg_sw_sweep_tokens($static_html);

    return $_ov_css . str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}

// Render a 'membership' system widget: the membership activation form.
//
// Posts to <software>/membership_entrance.php with register=true - the
// script the legacy entrance page posts to; pg_member_activate() does the
// rest for both. Controls by name: member_id, first_name, last_name,
// username (optional), email / email_verify (the legacy names
// email_address / email_address_verify work too), password, password_verify,
// password_hint, opt_in, register_remember_me. Any other control whose
// `_cf.contact_field` names an address-book column is written to the
// member's contact (see pg_member_widget_register_opts()).
//
// A Google sign-in that matched no account lands here with a pending record:
// the identity is verified, the membership number is what is missing. The
// form is prefilled from Google and a password is no longer required.
//
// Tokens: ^^__site_name^^, ^^__member_id_label^^, ^^__login_url^^,
// ^^__google_signup_url^^, ^^__password_rules^^, ^^__opt_in_label^^.
// Sections: password_rules, google_signup (every sign-in button that is
// on), login_link. Errors and the "membership active" notice: liveform
// 'membership_entrance', printed by the Messages node.
//
// cfg: redirect_page_id, login_page_id, contact_group_id (the site setting
// when empty), show_remember_me (default on).
function _render_system_widget_membership($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();
    $id_label  = defined('MEMBER_ID_LABEL') ? (string)MEMBER_ID_LABEL : '';

    $send_to = '';
    if (!empty($cfg['redirect_page_id']) && (int)$cfg['redirect_page_id'] > 0) {
        $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    }
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');

    $login_url = '';
    if (!empty($cfg['login_page_id'])) $login_url = _pg_member_page_url($cfg['login_page_id']);
    if ($login_url === '' && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('login');
        if ($legacy !== false && $legacy !== '') $login_url = (string)$legacy;
    }
    if ($login_url === '') $login_url = $software . '/';

    $lf = new liveform('membership_entrance');

    // Signed in: the form would open a second account. The notice an
    // activation just left prints with it; an editor still sees the form.
    if ($mode !== 'edit' && function_exists('pg_session_signed_in') && pg_session_signed_in()) {
        return _pg_member_render_signed_in($tree, $lf, 'membership_entrance', $send_to, $page_url,
            array('password_rules', 'google_signup', 'login_link'),
            array('__login_url', '__google_signup_url'),
            array('__site_name' => h($site_name), '__member_id_label' => h($id_label), '__login_url' => h($login_url),
                  '__opt_in_label' => h(defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '')));
    }

    // Back from Google with no account: say what is left to do, prefill what
    // Google told us, and stop requiring a password.
    $google_pending = $_SESSION['software']['google_membership_pending'] ?? null;
    $google_signup  = ($mode !== 'edit' && is_array($google_pending)
        && ((time() - ((int) ($google_pending['time'] ?? 0))) <= 900));

    $submitted = $lf->field_in_session('token');
    if (!$submitted) {
        $remembered = ((isset($_COOKIE['software']['remember_me']) ? (string)$_COOKIE['software']['remember_me'] : '') === 'true');
        _pg_member_fill_control($tree, 'opt_in', '1', true);
        _pg_member_fill_control($tree, 'register_remember_me', '1', $remembered);
        _pg_member_fill_control($tree, 'remember_me', '1', $remembered);
        if ($google_signup) {
            $g_email = (string)($google_pending['email'] ?? '');
            _pg_member_fill_control($tree, 'first_name', (string)($google_pending['first_name'] ?? ''));
            _pg_member_fill_control($tree, 'last_name', (string)($google_pending['last_name'] ?? ''));
            foreach (array('email', 'email_verify', 'email_address', 'email_address_verify') as $n) {
                _pg_member_fill_control($tree, $n, $g_email);
            }
            _pg_member_fill_control($tree, 'username', get_unique_username((string)strtok($g_email, '@')));
        }
    } else {
        _pg_member_fill_from_form($tree, $lf, array('password', 'password_verify'));
    }
    if ($google_signup) {
        $lf->add_notice(pg_signin_membership_notice($google_pending));
        foreach (array('password', 'password_verify') as $n) {
            _pg_member_edit_control($tree, $n, function (&$node) { _pg_member_set_attr($node, 'required', null); });
        }
    }

    if (!(defined('PASSWORD_HINT') && PASSWORD_HINT)) {
        _pg_member_drop_control($tree, 'password_hint');
    }
    $remember_ok = defined('REMEMBER_ME') && REMEMBER_ME
                && !(isset($cfg['show_remember_me']) && ($cfg['show_remember_me'] === false || $cfg['show_remember_me'] === 0 || $cfg['show_remember_me'] === '0'));
    if (!$remember_ok) {
        _pg_member_drop_control($tree, 'register_remember_me');
        _pg_member_drop_control($tree, 'remember_me');
    }

    $rules_html  = (defined('STRONG_PASSWORD') && STRONG_PASSWORD && function_exists('get_strong_password_requirements'))
        ? '<div class="pg-password-rules small text-muted">' . get_strong_password_requirements() . '</div>' : '';
    $google_url  = (!$google_signup && function_exists('pg_google_signin_url')) ? pg_google_signin_url($send_to, 'membership') : '';
    $google_html = (!$google_signup && function_exists('pg_signin_buttons')) ? pg_signin_buttons($send_to, 'membership') : '';
    $opt_label   = defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '';

    pg_cf_apply_section_bindings($tree, array(
        'password_rules' => $rules_html,
        'google_signup'  => $google_html,
        'login_link'     => '<a href="' . h($login_url) . '" class="pg-member-link pg-login-link">' . h(lang('Already a member? Sign in')) . '</a>',
    ));
    _pg_member_drop_empty_links($tree, array(
        '__login_url'         => $login_url,
        '__google_signup_url' => $google_url,
    ));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'membership_entrance');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, $software . '/membership_entrance.php', array(
        'register'        => 'true',
        'send_to'         => $send_to,
        'return_to'       => $page_url,
        'require_cookies' => 'true',
        'pg_widget_id'    => $widget_id,
    ), 'pg-membership-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'         => h($site_name),
        '__member_id_label'   => h($id_label),
        '__login_url'         => h($login_url),
        '__google_signup_url' => h($google_url),
        '__password_rules'    => $rules_html,
        '__opt_in_label'      => h($opt_label),
    ));
}

// Render a 'custom_form' system widget.
//
// Two sources, chosen in the widget's settings:
//   form_source = 'page'      — the form belongs to the page the widget is on.
//                               The controls in the tree ARE its fields; the
//                               save wrote them to form_fields and this render
//                               resolves their row ids.
//   form_source = 'existing'  — a form built elsewhere (a legacy custom form
//                               page, or another designed page), shown here.
//                               The editor rebuilt the widget from that form's
//                               fields, so the tree carries its controls too.
//
// Tokens: ^^__form_title^^       — form_name from custom_form_pages
//         ^^__error_message^^    — error from session
//         ^^__success_message^^  — success from session
// Sections (_bindings.section): captcha — the site-wide CAPTCHA block.
//
// $mode is the page render mode; 'edit' with edit access shows the
// office-use-only controls and tells custom_form.php they were on the form.
function _render_system_widget_custom_form($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';

    // Which page owns the form?
    //
    // 'page' (the default for a widget drawn in the editor) means the page the
    // widget is rendering on - the fields were drawn here, so the submissions
    // belong here. That resolution has to happen at render time and not in the
    // config, because the same widget can be placed on more than one page and
    // each of those pages then collects its own submissions.
    $form_source = _pg_cf_widget_owns_page_form($cfg) ? 'page' : 'existing';

    $rendered_page_id = function_exists('pg_rendered_page') ? (int)pg_rendered_page() : 0;
    $form_page_id = 0;
    if ($form_source === 'page') {
        $form_page_id = $rendered_page_id;
    } elseif (!empty($cfg['form_id']) && (int)$cfg['form_id'] > 0) {
        $form_page_id = (int)$cfg['form_id'];
    } elseif (!empty($cfg['custom_form_page_id']) && (int)$cfg['custom_form_page_id'] > 0) {
        $form_page_id = (int)$cfg['custom_form_page_id'];
    }

    // Form title
    $form_title  = '';
    $cf_page_row = array();
    $cf_folder   = 0;
    if ($form_page_id > 0) {
        $cf_page_row = db_item(
            "SELECT form_name, confirmation_type, confirmation_message, confirmation_page_id
             FROM custom_form_pages WHERE page_id = '" . e($form_page_id) . "' LIMIT 1"
        );
        if (!is_array($cf_page_row)) $cf_page_row = array();
        if (!empty($cf_page_row['form_name'])) {
            $form_title = (string)$cf_page_row['form_name'];
        }
        // The folder the "use folder name as default" option reads from, and
        // the folder whose edit access decides who is staff here.
        $cf_folder = (int)db_value("SELECT page_folder FROM page WHERE page_id = '$form_page_id' LIMIT 1");
    }

    // Just submitted successfully. custom_form.php sends the visitor back here
    // with `<page_id>_confirmation=true`, and the form must NOT be drawn again
    // — a visitor who sees the empty form return assumes nothing happened and
    // submits a second time. Same branch, same query parameter and same
    // wrapper class as the legacy screen, so existing CSS still applies; a
    // next page that was never chosen or no longer exists shows it too.
    if ($form_page_id > 0
        && isset($_GET[$form_page_id . '_confirmation'])
        && $_GET[$form_page_id . '_confirmation'] === 'true'
        && pg_cf_confirmation_shows_message($cf_page_row['confirmation_type'] ?? '', $cf_page_row['confirmation_page_id'] ?? 0)) {
        $cf_done = pg_cf_confirmation_message_or_default($cf_page_row['confirmation_message'] ?? '');
        return '<div class="confirmation_message alert alert-success">' . $cf_done . '</div>';
    }

    // Error / success messages from session
    $error_message   = isset($_SESSION['form_error'])   ? (string)$_SESSION['form_error']   : '';
    $success_message = isset($_SESSION['form_success']) ? (string)$_SESSION['form_success'] : '';

    // The form's own liveform: what the visitor typed survives a failed
    // submission in this session, and the CAPTCHA answer is validated against
    // values stored in it. custom_form.php constructs it with the same key.
    $cf_liveform = null;
    if ($form_page_id > 0 && class_exists('liveform')) {
        $cf_liveform = new liveform($form_page_id);
    }

    // Staff view: the page in edit mode, seen by someone who may edit it. The
    // same gate custom_form.php applies before it reads an office-use-only
    // field from the POST, so what is shown is what will be stored.
    $cf_staff = ($mode === 'edit' && $form_page_id > 0
                 && defined('USER_LOGGED_IN') && USER_LOGGED_IN
                 && function_exists('check_edit_access') && check_edit_access($cf_folder));

    // Resolve every control to the row custom_form.php will look for, and
    // re-fill what the visitor typed. The map is empty until the page has
    // been saved once - a control whose field does not exist yet renders
    // under its own name and is simply not stored, which is the harmless
    // half of "not saved yet".
    if ($form_page_id > 0 && function_exists('pg_cf_field_map')) {
        $cf_field_map = pg_cf_field_map($form_page_id);
        // A control set to start from a named address parameter
        // (_cf.url_param) is read here, while the controls still carry the
        // names they were drawn with; the bindings rename them to row ids.
        // pg_sw_filter_request() is empty in an e-mail, the SEO pass, the
        // editor's preview and the search indexer, so nothing is filled
        // there. A form without the setting costs one strpos().
        $cf_url_values = (strpos((string)$tree_json, '"url_param"') !== false)
            ? pg_cf_url_prefill_values(_pg_cf_controls($tree_decoded, $form_page_id), $cf_field_map, pg_sw_filter_request())
            : array();
        pg_cf_apply_field_bindings($tree_decoded, $cf_field_map, $cf_liveform, $cf_folder, $cf_staff, $cf_url_values);
    }
    // Ids are final now, including the ones invented above, so a label that
    // names nothing can be pointed at the control beside it.
    pg_cf_link_labels($tree_decoded);

    // Filled in on someone else's behalf: said on the form, as the legacy
    // screen says it. The messages node below prints and consumes it.
    $cf_contact_off = function_exists('pg_cf_connect_to_contact_off') && pg_cf_connect_to_contact_off();
    if ($cf_contact_off && $cf_liveform && $mode !== 'background') {
        $cf_off_notice = lang('The connect to contact feature has been disabled for this visit to this form, so your contact information will not be prefilled and will not be updated.');
        if (!in_array($cf_off_notice, $cf_liveform->get_notices(), true)) $cf_liveform->add_notice($cf_off_notice);
    }

    // Validation errors come back on the liveform, not in $_SESSION['form_error']
    // - custom_form.php marks the offending field and redirects to send_to. The
    // messages node is what prints them, scoped to THIS form so a second form
    // on the page does not show the first one's errors.
    // Unconditional: a widget whose form is not bound yet still has to be able
    // to show an error, and it was the only renderer that skipped the node
    // entirely in that case. With no form, the scope is simply the session's.
    _pg_inject_messages_node($tree_decoded, $form_page_id > 0 ? (string)$form_page_id : '');

    // Section bindings. `captcha` is the site-wide block, honoured only when
    // the CAPTCHA setting is on and the visitor is not staff — the same gate
    // get_captcha_fields() applies, so an empty container disappears
    // rather than leaving a labelled box with nothing in it.
    $cf_sections = array(
        'captcha' => ($cf_liveform && defined('CAPTCHA') && CAPTCHA && function_exists('get_captcha_fields'))
            ? (string)get_captcha_fields($cf_liveform) : '',
    );
    pg_cf_apply_section_bindings($tree_decoded, $cf_sections);

    // Render the full tree (loop_area present but hidden)
    $rendered = trim(_render_tree_node($tree_decoded, 0, 0));

    // Wrap in the <form> custom_form.php expects. The controls are the
    // designer's; nothing is added under them.
    if ($form_page_id > 0) {
        $cf_action  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') .
                      (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY . '/' : '') .
                      'custom_form.php';
        $cf_send_to = function_exists('get_request_uri') ? get_request_uri() : '';

        // A file field needs the multipart envelope.
        $cf_has_file = (bool) db_value(
            "SELECT COUNT(*) FROM form_fields
             WHERE page_id = '" . e($form_page_id) . "' AND type = 'file upload'"
        );
        $cf_enctype = $cf_has_file ? ' enctype="multipart/form-data"' : '';

        // Bootstrap's own validation: `novalidate` stops the browser's native
        // bubbles, `needs-validation` + the snippet turn on the styled
        // :invalid feedback on the first submit attempt. Nothing is prevented
        // that the server would have accepted — the same `required` and
        // `pattern` are checked again in custom_form.php, this only moves the
        // first "no" to before the round trip.
        $form_open = '<form action="' . h($cf_action) . '" method="post" novalidate' .
                     ' class="needs-validation pg-cf-form"' . $cf_enctype . '>' .
                     (function_exists('get_token_field') ? get_token_field() : '') .
                     '<input type="hidden" name="page_id" value="' . $form_page_id . '">' .
                     '<input type="hidden" name="send_to" value="' . h($cf_send_to) . '">' .
                     pg_cf_issued_field() .
                     ($cf_contact_off ? '<input type="hidden" name="connect_to_contact" value="false">' : '') .
                     ($cf_staff ? '<input type="hidden" name="office_use_only" value="true">' : '');

        $rendered = $form_open . $rendered . pg_cf_validation_script() . '</form>';
    }

    $values = array(
        '__form_title'      => h($form_title),
        '__error_message'   => h($error_message),
        '__success_message' => h($success_message),
        // Trees written by earlier editor builds bound the button text to
        // this; the editor drops the binding on load, the page must not go
        // blank in between.
        '__submit_label'    => h(lang('Submit')),
    );

    return _pg_member_apply_tokens($rendered, $values);
}

// ============================================================================
// calendar_view / calendar_event_view
// ============================================================================

// The calendars a calendar widget shows, [['id', 'name']]: the ones its
// config names, or every calendar when it names none.
function _pg_sw_calendars($cfg)
{
    $ids = array();
    if (!empty($cfg['calendar_ids']) && is_array($cfg['calendar_ids'])) {
        foreach ($cfg['calendar_ids'] as $id) if ((int)$id > 0) $ids[] = (int)$id;
    }
    $rows = db_items(
        "SELECT id, name FROM calendars"
        . ($ids ? " WHERE id IN (" . implode(',', array_unique($ids)) . ")" : "")
        . " ORDER BY name ASC");
    return is_array($rows) ? $rows : array();
}

// "Çarşamba, 23 Eylül 2026" — a date the way the site's language writes it.
// date() alone prints English day and month names whatever the site speaks.
function _pg_sw_long_date($timestamp)
{
    $day_month = (defined('DATE_FORMAT') && DATE_FORMAT === 'month_day')
        ? pg_widget_month_name((int)date('n', $timestamp)) . ' ' . date('j', $timestamp)
        : date('j', $timestamp) . ' ' . pg_widget_month_name((int)date('n', $timestamp));
    return lang(date('l', $timestamp)) . ', ' . $day_month . ' ' . date('Y', $timestamp);
}

// A time in the site's clock (Site Settings: 12 or 24 hours), as the legacy
// calendar prints it.
function _pg_sw_time($timestamp)
{
    return date((defined('TIME_FORMAT') && TIME_FORMAT === 'twelve_hours') ? 'g:i A' : 'H:i', $timestamp);
}

// "10:00 – 12:00", or "All day". The legacy time_range always prints a
// 12-hour clock; this one follows the site's setting like the rest.
function _pg_sw_time_range($all_day, $start_ts, $end_ts)
{
    if ($all_day) return lang('All day');
    $range = _pg_sw_time($start_ts);
    if ($end_ts && date('Y-m-d', $end_ts) === date('Y-m-d', $start_ts) && $end_ts > $start_ts) {
        $range .= ' – ' . _pg_sw_time($end_ts);
    }
    return $range;
}

// The whole span of an occurrence in words: "Salı, 8 Eylül 2026, 10:00 – 12:00",
// or two dates for one that runs over several days. Read from the start and
// end timestamps: get_calendar_event() moves those to the requested
// recurrence, but its start_date / end_date stay the first occurrence's.
function _pg_sw_date_range($all_day, $start_ts, $end_ts)
{
    $range = _pg_sw_long_date($start_ts);
    if ($end_ts && date('Y-m-d', $end_ts) !== date('Y-m-d', $start_ts)) {
        $range .= ' – ' . _pg_sw_long_date($end_ts);
        if (!$all_day) $range .= ', ' . _pg_sw_time($start_ts) . ' – ' . _pg_sw_time($end_ts);
    } elseif (!$all_day) {
        $range .= ', ' . _pg_sw_time_range(false, $start_ts, $end_ts);
    }
    return $range;
}

// A date in the widget's format; 'd M Y' spells the month out through lang().
function _pg_sw_short_date($timestamp, $format)
{
    if ($format === 'd M Y') {
        return date('d', $timestamp) . ' ' . pg_widget_month_name((int)date('n', $timestamp)) . ' ' . date('Y', $timestamp);
    }
    return date($format, $timestamp);
}

// The page a calendar widget's events link to: the configured one, else a
// page carrying a calendar event widget, else the legacy calendar event view
// page. '' when there is none — the rows then print without a link.
function _pg_sw_calendar_event_page_url($cfg)
{
    $page_id = isset($cfg['event_page_id']) ? (int)$cfg['event_page_id'] : 0;
    if ($page_id <= 0) {
        $pages = pg_sw_widget_pages('calendar_event_view');
        if ($pages) $page_id = (int)$pages[0]['page_id'];
    }
    if ($page_id <= 0) {
        $page_id = (int)db_value(
            "SELECT page.page_id FROM page
             WHERE page.page_type = 'calendar event view'
             ORDER BY page.page_id ASC LIMIT 1");
    }
    return ($page_id > 0) ? _pg_member_page_url($page_id) : '';
}

// Is the event one the calendar event widget on this page may show — does it
// belong to one of the widget's calendars (to any calendar, when the widget
// names none)? The widget's answer to calendar_event_views_calendars_xref,
// which only the legacy page type has.
function pg_sw_calendar_event_page_allows($page_id, $event_id)
{
    $widget = pg_sw_page_widget($page_id, 'calendar_event_view');
    if (!$widget) return false;
    return _pg_sw_calendar_event_allowed($widget['cfg'], $event_id);
}

// $event_calendars: the event's calendars when the caller has read them
// already (_pg_sw_calendar_event_calendar_ids()).
function _pg_sw_calendar_event_allowed($cfg, $event_id, $event_calendars = null)
{
    if (!is_array($event_calendars)) $event_calendars = _pg_sw_calendar_event_calendar_ids($event_id);
    if (!$event_calendars) return false;
    $allowed = array();
    foreach (_pg_sw_calendars($cfg) as $c) $allowed[] = (int)$c['id'];
    return count(array_intersect($event_calendars, $allowed)) > 0;
}

// The calendars an event is in.
function _pg_sw_calendar_event_calendar_ids($event_id)
{
    $ids = array();
    foreach ((array)db_items("SELECT calendar_id FROM calendar_events_calendars_xref WHERE calendar_event_id = '" . (int)$event_id . "'") as $r) {
        $ids[] = (int)$r['calendar_id'];
    }
    return $ids;
}

// The edit pencil on one occurrence of an event, for whoever may edit the
// event (pg_sw_can_edit_calendar_event()). The edit screen gets the
// occurrence's number, as the legacy event page's edit button gives it: with
// reservations kept per occurrence, it shows that occurrence's spots.
function _pg_sw_calendar_event_edit_chip($html, $event_id, $recurrence_number, $calendar_ids)
{
    if (!pg_sw_can_edit_calendar_event($calendar_ids)) return $html;
    $params = ((int)$recurrence_number > 0) ? array('recurrence_number' => (int)$recurrence_number) : array();
    return pg_sw_add_edit_chip($html, pg_sw_backend_edit_url('edit_calendar_event.php', $event_id, $params), lang('Edit the event'), 'calendar');
}

// One event occurrence as row values (raw). $event is a get_calendar() row;
// $detail, when given, is get_calendar_event() for it.
function _pg_sw_calendar_event_values($event, $detail, $link_base, $date_format, $calendar_names)
{
    $start_ts = strtotime($event['event_start_date'] . ' ' . $event['event_start_time']);
    $all_day  = ((int)$event['all_day'] === 1);
    $url = '';
    if ($link_base !== '') {
        $url = $link_base . '?id=' . (int)$event['id']
             . (!empty($event['recurrence_number']) ? '&recurrence_number=' . (int)$event['recurrence_number'] : '');
    }
    $names = array();
    foreach ((isset($event['calendars']) ? (array)$event['calendars'] : array()) as $cid) {
        if (isset($calendar_names[(int)$cid])) $names[] = $calendar_names[(int)$cid];
    }
    $end_time = ''; $range = ''; $location = ''; $time_range = $all_day ? lang('All day') : _pg_sw_time($start_ts);
    if (is_array($detail)) {
        $end_ts   = strtotime($detail['end_date_and_time']);
        $location = (string)$detail['location'];
        if (!$all_day && $end_ts) $end_time = _pg_sw_time($end_ts);
        $time_range = _pg_sw_time_range($all_day, $start_ts, $end_ts);
        $range = _pg_sw_date_range($all_day, $start_ts, $end_ts);
    }
    return array(
        '__event_title'          => (string)$event['name'],
        '__event_url'            => $url,
        '__event_excerpt'        => (string)$event['short_description'],
        '__event_date'           => _pg_sw_short_date($start_ts, $date_format),
        '__event_day'            => date('j', $start_ts),
        '__event_month'          => pg_widget_month_name((int)date('n', $start_ts)),
        '__event_weekday'        => lang(date('l', $start_ts)),
        '__event_time'           => $all_day ? '' : _pg_sw_time($start_ts),
        '__event_end_time'       => $end_time,
        '__event_time_range'     => $time_range,
        '__event_date_range'     => $range,
        '__event_location'       => $location,
        '__event_calendar_names' => implode(', ', $names),
        '__event_is_all_day'     => $all_day ? '1' : '',
        // The event's series as an .ics file, from the event page.
        '__event_ical_url'       => ($link_base !== '') ? $link_base . '?id=' . (int)$event['id'] . '&icalendar=true' : '',
    );
}

// The dates of one event occurrence as the typed fill reads them
// (pg_sw_fill_typed()): a date format after one of these tokens formats the
// occurrence itself, as the form views format a date field, not the words
// the widget wrote. A timed event is a date and time, so 'd.m.Y H:i' shows
// when it starts; an all-day event is a date, whose time letters read
// 00:00. The end only when it is known.
function _pg_sw_calendar_event_times($start_ts, $end_ts = null, $all_day = false)
{
    $type   = $all_day ? 'date' : 'date and time';
    $layout = $all_day ? 'Y-m-d' : 'Y-m-d H:i:s';
    $times  = array();
    if ($start_ts) {
        $start = array('type' => $type, 'data' => date($layout, $start_ts));
        $times['__event_date']       = $start;
        $times['__event_start_date'] = $start;
    }
    if ($end_ts) {
        $times['__event_end_date'] = array('type' => $type, 'data' => date($layout, $end_ts));
    }
    return $times;
}

// The order view's date tokens typed for a date format (pg_sw_fill_typed()):
// the order's time (a unix time stamp) as a date and time, the planned
// arrival and the last day of withdrawal ('Y-m-d') as dates. A date left
// out (none, or 0000-00-00) leaves its token to the configured format.
function _pg_sw_order_view_dates($order_ts, $arrival_date, $withdrawal_deadline)
{
    $dates = array();
    if ((int)$order_ts > 0) {
        $dates['__order_date'] = array('type' => 'date and time', 'data' => date('Y-m-d H:i:s', (int)$order_ts));
    }
    foreach (array('__arrival_date' => $arrival_date, '__withdrawal_deadline' => $withdrawal_deadline) as $key => $day) {
        $day = substr(trim((string)$day), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) && $day !== '0000-00-00') {
            $dates[$key] = array('type' => 'date', 'data' => $day);
        }
    }
    return $dates;
}

// The month (or week) grid for section=month_grid: a Bootstrap table, one
// cell per day, the day's events listed in it. Days of the neighbouring
// months pad the first and last week, dimmed and without events.
function _pg_sw_calendar_grid_html($range_start, $range_end, $focus_month, $events_by_day, $week_start, $link_base)
{
    $days = ($week_start === 'sunday')
        ? array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
        : array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
    // Fixed layout: seven equal columns whatever a day holds; a long title
    // is cut (text-truncate) instead of widening its column.
    $html = '<div class="table-responsive"><table class="table table-bordered table-sm mb-0 pg-sw-calendar-grid" style="table-layout:fixed;min-width:42rem"><thead><tr>';
    foreach ($days as $d) {
        // The dictionary's own short names (Pzt, Cmt …): cutting the long
        // name to three letters makes Pazartesi and Pazar, Cuma and
        // Cumartesi the same.
        $html .= '<th scope="col" class="text-center small fw-semibold"><abbr title="' . h(lang($d)) . '" class="text-decoration-none">' . h(lang(substr($d, 0, 3))) . '</abbr></th>';
    }
    $html .= '</tr></thead><tbody>';
    $today = date('Y-m-d');
    for ($ts = $range_start, $i = 0; $ts <= $range_end; $ts = strtotime('+1 day', $ts), $i++) {
        if ($i % 7 === 0) $html .= '<tr>';
        $key     = date('Y-m-d', $ts);
        $outside = ($focus_month !== null && (int)date('n', $ts) !== $focus_month);
        $classes = 'align-top p-1' . ($outside ? ' bg-body-tertiary text-body-tertiary' : '') . ($key === $today ? ' table-active' : '');
        $html .= '<td class="' . $classes . '" style="width:14.28%;height:6rem">'
               . '<div class="small fw-semibold mb-1">' . date('j', $ts) . '</div>';
        if (!$outside && !empty($events_by_day[$key])) {
            $html .= '<ul class="list-unstyled small mb-0">';
            foreach ($events_by_day[$key] as $ev) {
                $label = h(($ev['__event_time'] !== '' ? $ev['__event_time'] . ' ' : '') . $ev['__event_title']);
                $html .= '<li class="text-truncate">' . ($ev['__event_url'] !== ''
                    ? '<a href="' . h($ev['__event_url']) . '" class="link-underline link-underline-opacity-0" title="' . h($ev['__event_title']) . '">' . $label . '</a>'
                    : $label) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</td>';
        if ($i % 7 === 6) $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

// The calendar's empty-state sentence. One setting serves every view the
// visitor can switch to (a month, a week, the upcoming list), so the default
// names no period. The editor wrote "There are no events this month." into
// every new widget's settings, in the panel's language; that stored default
// reads as the default too. The editor's twin is _swCalendarEmptyMessage().
function pg_sw_calendar_empty_message($stored)
{
    $stored = is_string($stored) ? $stored : '';
    if ($stored === '' || in_array($stored, array('There are no events this month.', 'Bu ay etkinlik yok.'), true)) {
        return lang('There are no events to show.');
    }
    return $stored;
}

// Render a 'calendar_view' system widget: the events of the widget's
// calendars, monthly, weekly or upcoming, one loop row per occurrence —
// recurring events and their exceptions expanded exactly as the legacy
// calendar view does (get_calendar()).
//
// cfg: calendar_ids (empty = every calendar), default_view ('monthly' |
//   'weekly' | 'upcoming'; upcoming is fixed, the other two can be switched),
//   number_of_upcoming_events, week_start ('monday' | 'sunday'),
//   event_page_id, date_format, empty_message.
// URL (the legacy calendar's own): date (MM-DD-YYYY), view, calendar_id.
// Static tokens: ^^__period_label^^, ^^__month_name^^, ^^__year^^,
//   ^^__prev_url^^, ^^__next_url^^, ^^__today_url^^, ^^__event_count^^,
//   ^^__view^^, ^^__empty_message^^.
// Row tokens: see _pg_sw_calendar_event_values().
// Sections: calendar_picker (calendar + view select), month_grid.
// Visibility (eo_visible_if): has_navigation, has_events, no_events.
function _render_system_widget_calendar_view($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    // Nothing posts to the calendar (the legacy page prints no messages); the
    // name keeps the node from taking the messages of a form beside it.
    _pg_inject_messages_node($tree, 'calendar_view');

    // The event page's "back" link returns here.
    $_SESSION['software']['last_calendar_view_url'] = get_request_uri();

    $calendars = _pg_sw_calendars($cfg);
    $calendar_names = array();
    foreach ($calendars as $c) $calendar_names[(int)$c['id']] = (string)$c['name'];

    $default_view = (isset($cfg['default_view']) && in_array($cfg['default_view'], array('monthly', 'weekly', 'upcoming'), true))
        ? $cfg['default_view'] : 'monthly';
    $view = $default_view;
    if ($default_view !== 'upcoming' && isset($_GET['view']) && in_array($_GET['view'], array('monthly', 'weekly'), true)) {
        $view = $_GET['view'];
    }
    $calendar_id = (isset($_GET['calendar_id']) && isset($calendar_names[(int)$_GET['calendar_id']])) ? (int)$_GET['calendar_id'] : 0;
    $week_start  = (isset($cfg['week_start']) && $cfg['week_start'] === 'sunday') ? 'sunday' : 'monday';
    // One of the widget's formats, or the site's own when none is chosen.
    $date_format = (isset($cfg['date_format']) && in_array($cfg['date_format'], array('d.m.Y', 'Y-m-d', 'd M Y'), true)) ? $cfg['date_format'] : pg_sw_default_date_format('date');
    $limit       = (isset($cfg['number_of_upcoming_events']) && (int)$cfg['number_of_upcoming_events'] > 0) ? min(500, (int)$cfg['number_of_upcoming_events']) : 10;

    // The focus date: ?date=MM-DD-YYYY as the legacy links write it.
    $focus = strtotime(date('Y-m-d'));
    if (isset($_GET['date']) && is_scalar($_GET['date'])
        && preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', (string)$_GET['date'], $m)
        && checkdate((int)$m[1], (int)$m[2], (int)$m[3])) {
        $focus = mktime(0, 0, 0, (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    // The range the view covers.
    if ($view === 'weekly') {
        $offset      = ($week_start === 'sunday') ? (int)date('w', $focus) : ((int)date('N', $focus) - 1);
        $range_start = strtotime('-' . $offset . ' days', $focus);
        $range_end   = strtotime('+6 days', $range_start);
    } else {
        $range_start = mktime(0, 0, 0, (int)date('n', $focus), 1, (int)date('Y', $focus));
        $range_end   = mktime(0, 0, 0, (int)date('n', $focus), (int)date('t', $focus), (int)date('Y', $focus));
    }

    // Occurrences. get_calendar() expands recurrences month by month; a week
    // that straddles two months asks for both.
    $events = array();
    if ($calendars) {
        if ($view === 'upcoming') {
            $events = (array)get_calendar($calendar_id ?: '', $calendars, 'upcoming', 'published', '', '', '', $limit, 'array');
        } else {
            $seen_months = array();
            for ($ts = $range_start; $ts <= $range_end; $ts = strtotime('+1 day', $ts)) {
                $mk = date('m-01-Y', $ts);
                if (isset($seen_months[$mk])) continue;
                $seen_months[$mk] = true;
                foreach ((array)get_calendar($calendar_id ?: '', $calendars, 'monthly', 'published', '', $mk, '', '', 'array') as $ev) {
                    $d = strtotime($ev['event_start_date']);
                    if ($d >= $range_start && $d <= $range_end) $events[] = $ev;
                }
            }
        }
    }
    $events = array_slice($events, 0, 500);

    // Navigation.
    $path  = _pg_sw_current_path();
    $nav   = function ($ts) use ($path) {
        $params = _pg_sw_query_params();
        if ($ts === null) unset($params['date']); else $params['date'] = date('m-d-Y', $ts);
        $qs = http_build_query($params);
        return $path . ($qs !== '' ? '?' . $qs : '');
    };
    $prev_url = $next_url = $today_url = '';
    if ($view === 'monthly') {
        $prev_url  = $nav(mktime(0, 0, 0, (int)date('n', $range_start) - 1, 1, (int)date('Y', $range_start)));
        $next_url  = $nav(mktime(0, 0, 0, (int)date('n', $range_start) + 1, 1, (int)date('Y', $range_start)));
        $today_url = $nav(null);
        $period    = pg_widget_month_name((int)date('n', $range_start)) . ' ' . date('Y', $range_start);
    } elseif ($view === 'weekly') {
        $prev_url  = $nav(strtotime('-7 days', $range_start));
        $next_url  = $nav(strtotime('+7 days', $range_start));
        $today_url = $nav(null);
        $period    = _pg_sw_short_date($range_start, $date_format) . ' – ' . _pg_sw_short_date($range_end, $date_format);
    } else {
        $period = lang('Upcoming Events');
    }

    // Split before the sections go in, so the section markup never lands in
    // the per-event template.
    $link_base = _pg_sw_calendar_event_page_url($cfg);

    // Row values; the details (end time, location, the full range) cost a
    // query per event, so they are read only when the layout asks for them —
    // or when the month grid (the section or the grid lists) needs an
    // event's end to show it on every day it spans.
    $probe = json_encode($tree);
    $has_grid = ($view !== 'upcoming') && (strpos($probe, '"section":"month_grid"') !== false);
    $has_grid_lists = ($view !== 'upcoming') && (strpos($probe, '"source":"calendar.') !== false);
    // The event's allow-listed values (widgets_entity_fields.php) come from
    // the details too, and two columns the details leave out from one query.
    $entity_wanted = pg_sw_entity_wanted('calendar_event', $probe);
    $needs_detail = $has_grid || $has_grid_lists || $entity_wanted || (bool)preg_match('/__event_(end_time|date_range|location|time_range)/', $probe);
    $entity_extra = array();
    if ($entity_wanted) {
        $entity_ids = array();
        foreach ($events as $ev) $entity_ids[] = (int)$ev['id'];
        $entity_extra = pg_sw_entity_event_read($entity_ids);
    }
    $rows = array();
    $row_events = array();
    $by_day = array();
    foreach ($events as $ev) {
        $detail = $needs_detail ? get_calendar_event($ev['id'], $ev['recurrence_number']) : null;
        $vals = _pg_sw_calendar_event_values($ev, $detail, $link_base, $date_format, $calendar_names);
        // Main loop rows only: a grid list's event rows keep the loop's own tokens.
        $rows[] = $entity_wanted
            ? $vals + pg_sw_entity_values('calendar_event',
                is_array($detail) ? $detail + (isset($entity_extra[(int)$ev['id']]) ? $entity_extra[(int)$ev['id']] : array()) : null,
                array('date_format' => $date_format))
            : $vals;
        $row_events[] = $ev;
        $first  = strtotime($ev['event_start_date']);
        $last   = $first;
        $end_ts = is_array($detail) ? strtotime($detail['end_date_and_time']) : false;
        if ($end_ts) {
            // An end at midnight closes the previous day.
            if (date('H:i:s', $end_ts) === '00:00:00') $end_ts--;
            $last = max($first, strtotime(date('Y-m-d', $end_ts)));
        }
        for ($d = $first, $n = 0; $d <= $last && $n < 62; $d = strtotime('+1 day', $d), $n++) {
            $by_day[date('Y-m-d', $d)][] = $vals;
        }
    }

    // Sections.
    $picker = '';
    if ($default_view !== 'upcoming') {
        $opts = '';
        if (count($calendars) > 1) {
            $opts .= '<select name="calendar_id" class="form-select form-select-sm w-auto" aria-label="' . h(lang('Calendar')) . '">'
                   . '<option value="">' . h(lang('All Calendars')) . '</option>';
            foreach ($calendars as $c) {
                $opts .= '<option value="' . (int)$c['id'] . '"' . ((int)$c['id'] === $calendar_id ? ' selected' : '') . '>' . h($c['name']) . '</option>';
            }
            $opts .= '</select>';
        }
        $opts .= '<select name="view" class="form-select form-select-sm w-auto" aria-label="' . h(lang('View')) . '">'
               . '<option value="monthly"' . ($view === 'monthly' ? ' selected' : '') . '>' . h(lang('Monthly')) . '</option>'
               . '<option value="weekly"' . ($view === 'weekly' ? ' selected' : '') . '>' . h(lang('Weekly')) . '</option>'
               . '</select>';
        $picker = '<form method="get" action="' . h($path) . '" class="d-flex flex-wrap align-items-center gap-2">'
                . _pg_sw_hidden_query_inputs(array('calendar_id', 'view'))
                . $opts
                . '<button type="submit" class="btn btn-sm btn-outline-secondary">' . h(lang('Update')) . '</button>'
                . '</form>';
    }
    // The days the grid draws: a week, or a month padded to whole weeks.
    $grid_start  = $range_start;
    $grid_end    = $range_end;
    $focus_month = null;
    if ($view === 'monthly') {
        $focus_month = (int)date('n', $range_start);
        $lead  = ($week_start === 'sunday') ? (int)date('w', $range_start) : ((int)date('N', $range_start) - 1);
        $trail = ($week_start === 'sunday') ? (6 - (int)date('w', $range_end)) : (7 - (int)date('N', $range_end));
        $grid_start = strtotime('-' . $lead . ' days', $range_start);
        $grid_end   = strtotime('+' . $trail . ' days', $range_end);
    }
    $grid = '';
    if ($view !== 'upcoming') {
        $grid = _pg_sw_calendar_grid_html($grid_start, $grid_end, $focus_month, $by_day, $week_start, $link_base);
    }
    pg_cf_apply_section_bindings($tree, array('calendar_picker' => $picker, 'month_grid' => $grid));
    // Visibility bindings (eo_visible_if): the upcoming view has no period
    // to step through, so its navigation goes.
    _eo_apply_visibility_bindings($tree, array(
        'has_navigation' => $view !== 'upcoming',
        'has_events'     => count($rows) > 0,
        'no_events'      => count($rows) === 0,
    ));

    $split = _split_widget_tree($tree);
    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) $loop_template .= _render_tree_node($child, 0, 0);
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }
    // The grid lists' rows (widgets_lists_calendar.php), filed once the
    // tree is drawn: only a widget that drew such a list keeps them.
    if ($has_grid_lists) {
        pg_sw_list_publish('calendar', pg_sw_list_calendar_rows($grid_start, $grid_end, $focus_month, $by_day, $week_start, $date_format, date('Y-m-d')));
    }

    $loop_rendered = '';
    if ($loop_template !== '' && $static_html !== '') {
        foreach ($rows as $i => $vals) {
            if ($vals['__event_url'] === '') $vals['__event_url'] = '#';
            // A date format after the event's date formats the occurrence
            // itself, with its start time unless it lasts all day.
            $row_event = $row_events[$i];
            $times = _pg_sw_calendar_event_times(
                strtotime($row_event['event_start_date'] . ' ' . $row_event['event_start_time']),
                null,
                (int)$row_event['all_day'] === 1
            );
            $row_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, pg_sw_escape_token_values($vals), $times));
            $row_html = pg_sw_uniquify_row_ids($row_html, $widget_id, $i);
            // The pencil on every occurrence, for whoever may edit the event.
            // The month grid's one-line entries get none: they link to the
            // event page, which carries it.
            $ev = $row_events[$i];
            $loop_rendered .= _pg_sw_calendar_event_edit_chip($row_html, $ev['id'],
                isset($ev['recurrence_number']) ? $ev['recurrence_number'] : 0,
                isset($ev['calendars']) ? (array)$ev['calendars'] : array());
        }
    }

    $empty_message = pg_sw_calendar_empty_message(isset($cfg['empty_message']) ? $cfg['empty_message'] : '');
    $static_values = pg_sw_escape_token_values(array(
        '__period_label'  => $period,
        '__month_name'    => pg_widget_month_name((int)date('n', $range_start)),
        '__year'          => date('Y', $range_start),
        '__prev_url'      => $prev_url,
        '__next_url'      => $next_url,
        '__today_url'     => $today_url,
        '__event_count'   => (string)count($rows),
        '__view'          => $view,
        '__empty_message' => $rows ? '' : $empty_message,
        // The key the grid lists read outside a weeks list: the whole grid.
        '__week_key'      => '0',
    ));
    if ($static_html === '') {
        return pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, $static_values));
    }
    $static_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($static_html, $static_values));
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}

// Render a 'calendar_event_view' system widget: one event occurrence, read
// from ?id= and ?recurrence_number= as the calendar's links write them — the
// designer's layout filled once, the widget's answer to the legacy
// Calendar Event View page.
//
// An event outside the widget's calendars, unpublished or missing shows the
// not-found message instead of its fields. Reservations post to the legacy
// reserve_calendar_event.php, which accepts a page carrying this widget.
//
// cfg: calendar_ids (empty = every calendar), not_found_message.
// Tokens: ^^__event_title^^, ^^__event_date_range^^, ^^__event_start_date^^,
//   ^^__event_start_time^^, ^^__event_end_date^^, ^^__event_end_time^^,
//   ^^__event_time_range^^, ^^__event_location^^, ^^__event_excerpt^^,
//   ^^__event_description^^ (HTML), ^^__event_notes^^ (HTML),
//   ^^__back_url^^ (the calendar the visitor came from),
//   ^^__reservation_message^^, ^^__not_found_message^^.
// Section: reserve_form. Visibility (eo_visible_if): event_found,
//   event_not_found, has_location, has_description, has_notes, can_reserve,
//   has_reservation_message, has_back_url.
function _render_system_widget_calendar_event_view($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    _pg_inject_messages_node($tree, 'reserve_calendar_event');

    $event_id   = (isset($_GET['id']) && is_scalar($_GET['id'])) ? (int)$_GET['id'] : 0;
    $recurrence = (isset($_GET['recurrence_number']) && is_scalar($_GET['recurrence_number'])) ? max(0, (int)$_GET['recurrence_number']) : 0;
    $event = ($event_id > 0) ? get_calendar_event($event_id, $recurrence) : false;
    // The event's calendars, read once: for the widget's own calendars and
    // for the pencil's rights.
    $event_calendars = array();
    $shown = false;
    if (is_array($event) && (int)$event['published'] === 1) {
        $event_calendars = _pg_sw_calendar_event_calendar_ids($event_id);
        $shown = _pg_sw_calendar_event_allowed($cfg, $event_id, $event_calendars);
    }

    $values = array(
        '__event_title' => '', '__event_date_range' => '', '__event_start_date' => '', '__event_start_time' => '',
        '__event_end_date' => '', '__event_end_time' => '', '__event_time_range' => '', '__event_location' => '',
        '__event_excerpt' => '', '__event_description' => '', '__event_notes' => '', '__reservation_message' => '',
        '__back_url' => isset($_SESSION['software']['last_calendar_view_url']) ? (string)$_SESSION['software']['last_calendar_view_url'] : '',
        '__not_found_message' => '',
        '__event_ical_url' => '',
    );
    $reserve_form = '';
    $times = array();
    if (!$shown) {
        $values['__not_found_message'] = (!empty($cfg['not_found_message']) && is_string($cfg['not_found_message']))
            ? $cfg['not_found_message'] : lang('The requested calendar event could not be found.');
    } else {
        $start_ts = strtotime($event['start_date_and_time']);
        $end_ts   = strtotime($event['end_date_and_time']);
        $all_day  = ((int)$event['all_day'] === 1);
        $times    = _pg_sw_calendar_event_times($start_ts, $end_ts, $all_day);

        $values = array_merge($values, array(
            '__event_title'       => (string)$event['name'],
            '__event_date_range'  => _pg_sw_date_range($all_day, $start_ts, $end_ts),
            '__event_start_date'  => _pg_sw_long_date($start_ts),
            '__event_start_time'  => $all_day ? '' : _pg_sw_time($start_ts),
            '__event_end_date'    => _pg_sw_long_date($end_ts),
            '__event_end_time'    => $all_day ? '' : _pg_sw_time($end_ts),
            '__event_time_range'  => _pg_sw_time_range($all_day, $start_ts, $end_ts),
            '__event_location'    => (string)$event['location'],
            '__event_excerpt'     => (string)$event['short_description'],
            '__event_description' => (string)$event['full_description'],
            '__event_notes'       => (string)$event['notes_content'],
            // The whole series as an .ics file, served by get_page.php for
            // this page (?icalendar=true), as the legacy event page offers it.
            '__event_ical_url'    => _pg_sw_current_path() . '?id=' . $event_id . '&icalendar=true',
        ));

        // Reservation — the same gates the legacy page applies.
        if ((int)$event['reservations'] === 1 && (string)$event['product_id'] !== ''
            && (int)$event['product_enabled'] === 1 && $end_ts >= time()) {
            $has_spots = ((int)$event['limit_reservations'] === 0 || (int)$event['number_of_remaining_spots'] > 0);
            $in_stock  = ((int)$event['inventory'] === 0 || (int)$event['inventory_quantity'] > 0 || (int)$event['backorder'] === 1);
            if ($has_spots && $in_stock) {
                $label = ((string)$event['reserve_button_label'] !== '') ? (string)$event['reserve_button_label'] : lang('Reserve');
                $reserve_form = '<form action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/reserve_calendar_event.php') . '" method="post" class="d-inline">'
                    . get_token_field()
                    . '<input type="hidden" name="send_to" value="' . h(get_request_uri()) . '">'
                    . '<input type="hidden" name="page_id" value="' . (int)_pg_sw_current_page_id() . '">'
                    . '<input type="hidden" name="calendar_event_id" value="' . (int)$event_id . '">'
                    . '<input type="hidden" name="recurrence_number" value="' . (int)$recurrence . '">'
                    . '<input type="hidden" name="require_cookies" value="true">'
                    . '<button type="submit" class="btn btn-primary">' . h($label) . '</button>'
                    . '</form>';
            } else {
                $msg = array();
                if ((int)$event['limit_reservations'] === 1 && (int)$event['number_of_remaining_spots'] === 0) $msg[] = (string)$event['no_remaining_spots_message'];
                if ((int)$event['inventory'] === 1 && (int)$event['inventory_quantity'] === 0 && (int)$event['backorder'] === 0) $msg[] = (string)$event['out_of_stock_message'];
                $values['__reservation_message'] = trim(implode(' ', array_filter($msg)));
            }
        }
    }

    // The event's allow-listed values (widgets_entity_fields.php); the two
    // columns get_calendar_event() leaves out are read only when bound.
    $entity_row = null;
    if ($shown) {
        $entity_extra = pg_sw_entity_wanted('calendar_event', $tree_json) ? pg_sw_entity_event_read(array($event_id)) : array();
        $entity_row = $event + (isset($entity_extra[$event_id]) ? $entity_extra[$event_id] : array());
        if (!empty($entity_row['last_modified_timestamp'])) {
            $times['__event_updated_date'] = array('type' => 'date and time', 'data' => date('Y-m-d H:i:s', (int)$entity_row['last_modified_timestamp']));
        }
    }
    $values += pg_sw_entity_values('calendar_event', $entity_row, array('date_format' => pg_sw_default_date_format('date')));

    pg_cf_apply_section_bindings($tree, array('reserve_form' => $reserve_form));
    // Visibility bindings (eo_visible_if): the event's own blocks give way to
    // the not-found notice, and a block whose value is empty goes with it
    // instead of leaving a heading over nothing.
    _eo_apply_visibility_bindings($tree, array(
        'event_found'             => $shown,
        'event_not_found'         => !$shown,
        'has_location'            => $values['__event_location'] !== '',
        'has_description'         => trim(strip_tags($values['__event_description'])) !== '',
        'has_notes'               => trim(strip_tags($values['__event_notes'])) !== '',
        'can_reserve'             => $reserve_form !== '',
        'has_reservation_message' => $values['__reservation_message'] !== '',
        'has_back_url'            => $values['__back_url'] !== '',
    ));

    $html = trim(_render_tree_node($tree, 0, 0));
    $values = pg_sw_escape_token_values($values, array('__event_description', '__event_notes'));
    $html = str_replace('<!--pg-loop-slot-->', '', pg_sw_sweep_tokens(_pg_sw_fill_tokens($html, $values, $times)));
    // The pencil on the event, for whoever may edit it.
    if ($shown && pg_sw_edit_chips_on()) {
        $html = _pg_sw_calendar_event_edit_chip($html, $event_id, $recurrence, $event_calendars);
    }
    return $html;
}

// ============================================================================
// Edit chips — the pencil an editor sees on what they may edit
// ============================================================================
// The legacy edit mode drew a pencil on every region, product and comment
// once a switch in the toolbar was on. A designed page has no such switch:
// the page itself is edited in the visual editor, and what its widgets show
// - a product, a product group, a submitted form, a comment - is edited in
// the panel. So the pencil is simply there for a user who may edit that
// record, in the record's top-left corner on every page (the comment card
// keeps its share button top right through Bootstrap's physical utilities,
// so a chip that followed a right-to-left page would cover it), and it
// opens the record's edit screen, which brings them back here.

/**
 * Whether a render draws edit chips, from values already read: a signed-in
 * user's own page view only. A page drawn for the search index or the SEO
 * score (the designer's canvas and Preview too) draws none, and so does a
 * page drawn as an e-mail body: a campaign, a form's confirmation or an order
 * receipt goes to other people even when an editor's request builds it.
 *
 * @param bool $signed_in         a user is signed in
 * @param bool $background_render search index or SEO render
 * @param bool $email             the page is an e-mail body
 */
function pg_sw_edit_chips_rule($signed_in, $background_render, $email)
{
    return (bool)$signed_in && !$background_render && !$email;
}

/** Whether this render draws edit chips (pg_sw_edit_chips_rule()). */
function pg_sw_edit_chips_on()
{
    $ctx = pg_sw_render_context();
    return pg_sw_edit_chips_rule(
        defined('USER_LOGGED_IN') && USER_LOGGED_IN === true && defined('USER_ID') && (int)USER_ID > 0,
        (function_exists('pg_seo_rendering') && pg_seo_rendering())
            || (defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true),
        !empty($ctx['email'])
    );
}

/** Products and product groups: the rule the legacy catalog pencil used. */
function pg_sw_can_edit_products()
{
    return pg_sw_edit_chips_on()
        && ((defined('USER_ROLE') && (int)USER_ROLE < 3) || (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE === true));
}

/**
 * Submitted forms of one custom form: the rule edit_submitted_form.php lets
 * in without a form item view page - a manager, or a user who manages forms
 * and may edit the form's folder.
 */
function pg_sw_can_edit_submitted_forms($custom_form_page_id)
{
    static $folder_ok = array();
    if (!pg_sw_edit_chips_on()) return false;
    if (defined('USER_ROLE') && (int)USER_ROLE <= 2) return true;
    if (!(defined('USER_MANAGE_FORMS') && USER_MANAGE_FORMS === true)) return false;
    $pid = (int)$custom_form_page_id;
    if (!isset($folder_ok[$pid])) {
        $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . $pid . "' LIMIT 1");
        $folder_ok[$pid] = function_exists('check_edit_access') && (check_edit_access($folder) === true);
    }
    return $folder_ok[$pid];
}

/**
 * The rule edit_calendar_event.php applies, from values already read: staff
 * (role 0-2) always; a user (role 3) only with the calendars right, one of
 * the event's calendars among their own and, for a published event, the
 * right to publish events.
 *
 * @param int   $role               USER_ROLE
 * @param bool  $manage_calendars   user_manage_calendars
 * @param bool  $publish_events     user_publish_calendar_events
 * @param array $user_calendar_ids  the user's calendars (users_calendars_xref)
 * @param array $event_calendar_ids the event's calendars
 * @param bool  $published          whether the event is published
 */
function pg_sw_calendar_event_edit_rule($role, $manage_calendars, $publish_events, $user_calendar_ids, $event_calendar_ids, $published)
{
    if ((int)$role < 3) return true;
    if (!$manage_calendars || ($published && !$publish_events)) return false;
    $mine = array_map('intval', (array)$user_calendar_ids);
    foreach ((array)$event_calendar_ids as $cid) {
        if (in_array((int)$cid, $mine, true)) return true;
    }
    return false;
}

/**
 * Calendar events: whether the signed-in user may open edit_calendar_event.php
 * for an event in these calendars. A page shows only published events, so a
 * user (role 3) also needs the right to publish events. Their rights and
 * calendars are read once per request.
 */
function pg_sw_can_edit_calendar_event($event_calendar_ids, $published = true)
{
    static $own = null;
    if (!pg_sw_edit_chips_on()) return false;
    $role = defined('USER_ROLE') ? (int)USER_ROLE : 3;
    if ($role < 3) return true;
    if ($own === null) {
        $own = array('manage' => false, 'publish' => false, 'calendars' => array());
        $row = db_item("SELECT user_manage_calendars, user_publish_calendar_events FROM user WHERE user_id = '" . (int)USER_ID . "' LIMIT 1");
        if (is_array($row) && $row['user_manage_calendars'] === 'yes') {
            $own['manage']  = true;
            $own['publish'] = ($row['user_publish_calendar_events'] === 'yes');
            foreach ((array)db_items("SELECT calendar_id FROM users_calendars_xref WHERE user_id = '" . (int)USER_ID . "'") as $r) {
                $own['calendars'][] = (int)$r['calendar_id'];
            }
        }
    }
    return pg_sw_calendar_event_edit_rule($role, $own['manage'], $own['publish'], $own['calendars'], $event_calendar_ids, $published);
}

/**
 * A control-panel edit screen for one record, returning to this page.
 * $params are further query parameters of the screen, before send_to.
 */
function pg_sw_backend_edit_url($script, $id, $params = array())
{
    $back = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    return (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/')
         . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software')
         . '/' . $script . '?id=' . (int)$id
         . ($params ? '&' . http_build_query($params, '', '&') : '')
         . ($back !== '' ? '&send_to=' . urlencode($back) : '');
}

/**
 * The chips' stylesheet: only what a style attribute cannot say. The record
 * is the chip's positioning box (zero specificity: a design that positions
 * the record itself wins); the chip fades in on hover and focus and grows
 * under the pointer (!important, over the plain opacity and transform the
 * chip carries); it draws no pseudo-element a design gives links; it is not
 * printed (visibility: its display is pinned). The chip's size, shape and
 * colours need none of it. Written once per page by
 * pg_sw_edit_chip_css_inject().
 */
function pg_sw_edit_chip_css()
{
    return '<style id="pg-edit-chip-css">'
        . ':where(.pg-edit-host){position:relative}'
        . '.pg-edit-chip::before,.pg-edit-chip::after{content:none!important;display:none!important}'
        . '.pg-edit-host:hover>.pg-edit-chip,.pg-edit-chip:focus-visible{opacity:1!important}'
        . '.pg-edit-chip:hover{transform:scale(1.08)!important}'
        . '.pg-edit-chip:focus-visible{outline:2px solid #fff!important;outline-offset:2px!important}'
        . '@media (hover:none){.pg-edit-chip{opacity:.9!important}}'
        . '@media print{.pg-edit-chip{visibility:hidden!important}}'
        . '</style>';
}

/**
 * The chips' stylesheet in a finished page that shows a chip: once, in its
 * head (in front of a page without one). Asked of the whole page
 * (get_page_content()), not written with the first chip: that chip may
 * belong to output thrown away later - a list hidden by "Show only when"
 * draws its rows before it is cut - and the stylesheet went with it.
 *
 * @param string $html the page
 * @return string
 */
function pg_sw_edit_chip_css_inject($html)
{
    $html = (string)$html;
    if (strpos($html, 'class="pg-edit-chip ') === false || strpos($html, 'id="pg-edit-chip-css"') !== false) {
        return $html;
    }
    $head_end = stripos($html, '</head>');
    if ($head_end === false) return pg_sw_edit_chip_css() . $html;
    return substr($html, 0, $head_end) . pg_sw_edit_chip_css() . substr($html, $head_end);
}

/**
 * Put an edit chip in the first element of a rendered record
 * (pg_sw_insert_edit_chip()). A record that holds a value condition gets it
 * from the widget's finish pass instead, in the first element the record
 * keeps once its conditions are resolved (pg_sw_finish_defer_chip()): put
 * in at once, it went with an element a condition cut.
 *
 * @param string $html  one record's markup
 * @param string $url   the edit screen
 * @param string $title what the chip says to a screen reader and on hover
 * @param string $tone  page | ecommerce | forms | calendar
 */
function pg_sw_add_edit_chip($html, $url, $title, $tone = 'page')
{
    if ($url !== '' && pg_sw_finish_holds_condition($html)) {
        $deferred = pg_sw_finish_defer_chip($html, $url, $title, $tone);
        if ($deferred !== null) return $deferred;
    }
    return pg_sw_insert_edit_chip($html, $url, $title, $tone);
}

/**
 * Put an edit chip in the first element of a rendered record and mark that
 * element as its host. A record whose first element cannot hold one - a
 * void element, a table row, a link or button (a link in a link is not
 * HTML) - is left as it is; its detail page carries the chip instead.
 *
 * The chip's look and place are written on the chip and its icon with
 * !important, because a design's rules for links, svg and pictures
 * ("a {display:block}", ".card svg {width:100%}", ".row > *", ".ratio > *")
 * reach them too and made the pencil as wide as a card's picture, in the
 * flow above it. It sits .5rem inside the record's top-left corner in
 * either direction (right is auto). The record is its positioning box
 * through the page's stylesheet (pg_sw_edit_chip_css()), not a position
 * written on the record, which would override the design's own. Opacity and transform stay plain
 * declarations, which a design's rules cannot reach either; the stylesheet
 * raises them on hover and focus.
 *
 * @param string $html  one record's markup
 * @param string $url   the edit screen
 * @param string $title what the chip says to a screen reader and on hover
 * @param string $tone  page | ecommerce | forms | calendar
 */
function pg_sw_insert_edit_chip($html, $url, $title, $tone = 'page')
{
    if ($url === '' || !preg_match('/^(\s*(?:<!--.*?-->\s*)*)<([a-zA-Z][a-zA-Z0-9-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/s', (string)$html, $m)) {
        return $html;
    }
    $tag = strtolower($m[2]);
    if (in_array($tag, array('a', 'button', 'img', 'input', 'br', 'hr', 'source', 'meta', 'link', 'area', 'col', 'embed',
                             'wbr', 'track', 'param', 'form', 'script', 'style', 'template', 'tr', 'td', 'th',
                             'thead', 'tbody', 'tfoot', 'select', 'textarea', 'svg', 'picture', 'video', 'iframe'), true)) {
        return $html;
    }
    $attrs = $m[3];
    if (preg_match('/\sclass\s*=\s*"[^"]*"/i', $attrs)) {
        $attrs = preg_replace('/(\sclass\s*=\s*")([^"]*)"/i', '$1$2 pg-edit-host"', $attrs, 1);
    } elseif (preg_match("/\\sclass\\s*=\\s*'[^']*'/i", $attrs)) {
        $attrs = preg_replace("/(\\sclass\\s*=\\s*')([^']*)'/i", "\$1\$2 pg-edit-host'", $attrs, 1);
    } else {
        $attrs = rtrim($attrs, " \t\n\r/") . ' class="pg-edit-host"';
    }
    $backgrounds = array(
        'page'      => 'rgba(25,118,210,.92)',
        'ecommerce' => 'rgba(85,139,47,.94)',
        'forms'     => 'rgba(128,95,167,.94)',
        'calendar'  => 'rgba(93,64,55,.94)',
    );
    $tone = isset($backgrounds[$tone]) ? $tone : 'page';
    // justify-self / align-self: the corner, where a browser that aligns
    // positioned boxes would otherwise follow a design's alignment.
    $chip_style = 'position:absolute!important;top:.5rem!important;right:auto!important;bottom:auto!important;left:.5rem!important;'
                . 'z-index:6!important;display:flex!important;align-items:center!important;justify-content:center!important;'
                . 'justify-self:start!important;align-self:start!important;box-sizing:border-box!important;'
                . 'width:1.9rem!important;height:1.9rem!important;min-width:0!important;min-height:0!important;'
                . 'max-width:none!important;max-height:none!important;margin:0!important;padding:0!important;'
                . 'border:0!important;border-radius:50%!important;background:' . $backgrounds[$tone] . '!important;'
                . 'color:#fff!important;box-shadow:0 2px 6px rgba(0,0,0,.28)!important;font-size:1rem!important;'
                . 'line-height:1!important;text-decoration:none!important;float:none!important;pointer-events:auto!important;'
                . 'transition:opacity .15s,transform .15s!important;opacity:.55;transform:none';
    $svg_style  = 'display:block!important;flex:none!important;position:static!important;float:none!important;'
                . 'width:.9rem!important;height:.9rem!important;min-width:0!important;min-height:0!important;'
                . 'max-width:none!important;max-height:none!important;margin:0!important;padding:0!important;'
                . 'border:0!important;transform:none!important;opacity:1!important;color:inherit!important;'
                . 'fill:currentColor!important;pointer-events:none!important';
    $chip = '<a class="pg-edit-chip pg-edit-chip-' . $tone . '" href="' . h($url) . '" title="' . h($title) . '" aria-label="' . h($title) . '" rel="nofollow" style="' . $chip_style . '">'
          . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false" style="' . $svg_style . '">'
          . '<path style="fill:currentColor!important;stroke:none!important" d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>'
          . '</svg></a>';
    return $m[1] . '<' . $m[2] . $attrs . '>' . $chip . substr((string)$html, strlen($m[0]));
}
