<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The auto dialog save, shared by the create and the edit screens
 * (add_auto_dialog.php, edit_auto_dialog.php): the required, address and
 * name checks, the page clean-up and the values a save writes. Both screens draw
 * includes/templates/edit_auto_dialog.php; they keep the record load, the
 * preview link, delete, the INSERT/UPDATE, the log and the redirects.
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
 * The checks a save runs: name required, the URL too with the address kind
 * (a designed pop-up asks for its shared component instead,
 * pg_auto_dialog_form_content_problems()), the text fields no longer
 * than their columns, the URL a web address or a path on this site
 * (pg_auto_dialog_url_problem()), the four number fields blank or a whole
 * number inside their limit, and a name not used by another auto dialog. The
 * uniqueness query runs only when a name was given.
 *
 * @param liveform $liveform
 * @param string|null $id  The auto dialog being edited, null on create
 * @return void
 */
function pg_auto_dialog_form_validate($liveform, $id)
{
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required.','vars'=>lang('Name'))));

    // A designed pop-up needs no address: one typed is still checked and
    // kept, so the row can switch back without retyping it.
    $asked = pg_auto_dialog_form_content_asked();
    $content_problems = $asked ? pg_auto_dialog_form_content_problems(pg_auto_dialog_form_content_sent($liveform)) : array();

    if (pg_auto_dialog_form_url_required($asked ? $liveform->get_field_value('content') : null)) {
        $liveform->validate_required_field('url', lang(array('string'=>'{var:1} is required.','vars'=>lang('URL'))));
    }

    foreach ($content_problems as $field => $problem) {
        $liveform->mark_error($field, $problem);
    }

    // The column lengths: a longer value would be cut silently on save.
    foreach (array('name' => array('Name', 100), 'url' => array('URL', 255), 'page' => array('Only on Page', 100)) as $field => $rule) {
        if (!$liveform->check_field_error($field)
            && (mb_strlen((string) $liveform->get_field_value($field), 'UTF-8') > $rule[1])) {
            $liveform->mark_error($field, lang(array('string'=>'{var:1} can be at most {var:2} characters long.','vars'=>array(lang($rule[0]), $rule[1]))));
        }
    }

    if (!$liveform->check_field_error('url') && (trim((string) $liveform->get_field_value('url')) !== '')) {
        $problem = pg_auto_dialog_url_problem($liveform->get_field_value('url'));

        if ($problem !== '') {
            $liveform->mark_error('url', $problem);
        }
    }

    // The columns are INT UNSIGNED and the session sql_mode is not strict,
    // so a negative, decimal or oversized value would be clamped or rounded
    // into another meaning (a negative frequency became 0: one-time).
    $labels = array('width' => lang('Width'), 'height' => lang('Height'), 'delay' => lang('Delay'), 'frequency' => lang('Frequency'));

    foreach (pg_auto_dialog_form_number_limits() as $field => $max) {
        if (pg_auto_dialog_form_whole_number($liveform->get_field_value($field), $max) === null) {
            $liveform->mark_error($field, lang(array('string'=>'{var:1} must be a whole number from {var:2} to {var:3}.','vars'=>array($labels[$field], 0, pg_format_number($max, 0)))));
        }
    }

    // The open settings come from select boxes and one number box: a value
    // that is not offered (a changed page) is refused rather than stored.
    foreach (pg_auto_dialog_form_open_problems($liveform) as $field => $problem) {
        $liveform->mark_error($field, $problem);
    }

    if (!$liveform->check_field_error('name')) {
        $taken = pg_auto_dialog_name_in_use((string) $liveform->get_field_value('name'), ($id === null) ? null : (int) $id);

        if ($taken === null) {
            output_error('Query failed.');
        }

        if ($taken) {
            $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }
    }
}

/**
 * What is wrong with an auto dialog's URL, or '' when it can be saved. The
 * site opens the URL in an iframe on every page the dialog shows on, so a
 * javascript: or data: address would run as script in the site's own origin
 * for every visitor. It takes the short link destination's rule
 * (pg_web_address_problem()): an http or https address or a path on this
 * site, no other scheme, no "//host", no backslash, no space. The create and
 * edit screens and the external API ask this.
 *
 * @param mixed $url
 * @return string
 */
function pg_auto_dialog_url_problem($url)
{
    return pg_web_address_problem($url);
}

/**
 * The upper limit of each number field. Width and height are pixels (four
 * digits); the delay is seconds and must stay inside setTimeout's 2^31 - 1
 * milliseconds, past which the browser opens the dialog at once; the
 * frequency is hours.
 *
 * @return array  field => maximum
 */
function pg_auto_dialog_form_number_limits()
{
    return array(
        'width' => 9999,
        'height' => 9999,
        'delay' => 2147483,
        'frequency' => 999999,
    );
}

/**
 * A number field's typed value as a whole number from 0 to $max: '' when
 * blank, the digits as a string when valid, null otherwise. Thousands
 * separators are read in groups of three ("2.400" or "2,400" is 2400); a
 * sign, a decimal part or any other character is refused instead of being
 * clamped or rounded.
 *
 * @param mixed $raw
 * @param int $max
 * @return string|null
 */
function pg_auto_dialog_form_whole_number($raw, $max)
{
    $value = trim(str_replace(array("\u{00A0}", ' '), '', (string) $raw));

    if ($value === '') {
        return '';
    }

    if (!preg_match('/^[0-9]+$/', $value) && !preg_match('/^[0-9]{1,3}(?:([.,])[0-9]{3})(?:\1[0-9]{3})*$/', $value)) {
        return null;
    }

    $digits = ltrim(str_replace(array('.', ','), '', $value), '0');

    if ($digits === '') {
        return '0';
    }

    if ((strlen($digits) > strlen((string) $max)) || ((int) $digits > $max)) {
        return null;
    }

    return $digits;
}

/**
 * The page name a save stores: a leading slash (a URL typed instead of a
 * page name) is removed.
 *
 * @param string $page
 * @return string
 */
function pg_auto_dialog_form_page($page)
{
    $page = (string) $page;

    if (mb_substr($page, 0, 1) == '/') {
        $page = mb_substr($page, 1);
    }

    return $page;
}

/**
 * The eight auto dialog values a save writes, in INSERT/UPDATE order: name,
 * enabled, url, width, height, delay, frequency, page. The number fields are
 * stored as read by pg_auto_dialog_form_whole_number() ("2.400" -> 2400).
 *
 * @param liveform $liveform
 * @return array
 */
function pg_auto_dialog_form_values($liveform)
{
    $numbers = array();

    foreach (pg_auto_dialog_form_number_limits() as $field => $max) {
        $numbers[$field] = (string) pg_auto_dialog_form_whole_number($liveform->get_field_value($field), $max);
    }

    return array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('enabled'),
        (string) $liveform->get_field_value('url'),
        $numbers['width'],
        $numbers['height'],
        $numbers['delay'],
        $numbers['frequency'],
        pg_auto_dialog_form_page($liveform->get_field_value('page')),
    );
}

/**
 * The open settings' fields: their labels and, for the three select boxes,
 * the options a form draws (label => value), the default first.
 *
 * @return array  field => array(label, options or null for the number box)
 */
function pg_auto_dialog_form_open_fields()
{
    return array(
        'open_on' => array(lang('Opens'), array(
            lang('After the delay') => 'delay',
            lang('When the page is scrolled') => 'scroll',
            lang('When the visitor is leaving') => 'leave',
        )),
        'open_scroll' => array(lang('Scrolled'), null),
        'audience' => array(lang('Shown To'), array(
            lang('All visitors') => 'all',
            lang('Visitors with a cart') => 'cart',
        )),
        'place' => array(lang('Place'), array(
            lang('In the centre') => 'centre',
            lang('Bar at the top') => 'top',
            lang('Bar at the bottom') => 'bottom',
            lang('Corner') => 'corner',
        )),
    );
}

/**
 * What is wrong with the open settings sent, field => message: a select
 * value that is not offered, a scroll share that is not a whole number from
 * 1 to 100. A blank field is the default. Pure but for lang().
 *
 * @param array|liveform $source  the values sent, or the liveform holding them
 * @return array
 */
function pg_auto_dialog_form_open_problems($source)
{
    $problems = array();
    $choices = pg_auto_dialog_open_choices();

    foreach (pg_auto_dialog_form_open_fields() as $field => $rule) {
        $raw = is_array($source) ? ($source[$field] ?? '') : $source->get_field_value($field);
        $value = is_scalar($raw) ? trim((string) $raw) : null;

        if ($value === '') {
            continue;
        }

        if ($field === 'open_scroll') {
            $number = ($value === null) ? null : pg_auto_dialog_form_whole_number($value, 100);

            if (($number === null) || ((int) $number < 1)) {
                $problems[$field] = lang(array('string'=>'{var:1} must be a whole number from {var:2} to {var:3}.','vars'=>array($rule[0], 1, 100)));
            }

            continue;
        }

        if (($value === null) || !in_array($value, $choices[$field], true)) {
            $problems[$field] = lang(array('string'=>'{var:1} must be one of: {var:2}','vars'=>array($rule[0], implode(', ', $choices[$field]))));
        }
    }

    return $problems;
}

/**
 * The open settings a save writes (pg_auto_dialog_save()'s $open): the
 * fields sent, a blank one read as its default.
 *
 * @param liveform $liveform
 * @return array  open_on, open_scroll, audience, place
 */
function pg_auto_dialog_form_open_values($liveform)
{
    $sent = array();

    foreach (array_keys(pg_auto_dialog_form_open_fields()) as $field) {
        $sent[$field] = $liveform->get_field_value($field);
    }

    if (is_scalar($sent['open_scroll'])) {
        $sent['open_scroll'] = (string) pg_auto_dialog_form_whole_number($sent['open_scroll'], 100);
    }

    return pg_auto_dialog_open_settings($sent);
}

/**
 * What is wrong with what a pop-up shows, field => message: a content value
 * that is not offered (url, component); for a designed pop-up, a component
 * that is not given, missing or a system widget. A blank content is the
 * address. The component is looked up through $found (its rows => the ids
 * that exist; pg_auto_dialog_components_found() by default).
 *
 * @param array         $sent   content, component_id
 * @param callable|null $found
 * @return array
 */
function pg_auto_dialog_form_content_problems($sent, $found = null)
{
    $raw = $sent['content'] ?? '';
    $value = is_scalar($raw) && !is_bool($raw) ? strtolower(trim((string) $raw)) : null;

    if (($value === null) || (($value !== '') && !in_array($value, pg_auto_dialog_content_choices(), true))) {
        return array('content' => lang(array('string'=>'{var:1} must be one of: {var:2}','vars'=>array(lang('Content'), implode(', ', pg_auto_dialog_content_choices())))));
    }

    if ($value !== 'component') {
        return array();
    }

    $content = pg_auto_dialog_content($sent);
    $ids = ($content['component_id'] > 0) ? call_user_func(($found === null) ? 'pg_auto_dialog_components_found' : $found, array($content)) : array();

    if (!in_array($content['component_id'], array_map('intval', (array) $ids), true)) {
        return array('component_id' => lang('Choose a shared component for the designed pop-up.'));
    }

    return array();
}

/**
 * Whether the create and edit screens ask "What does it show?": the content
 * columns are on auto_dialogs and the site has a visual design to design a
 * pop-up in. A site with no visual design keeps the address kind and saves
 * the row without touching the two columns. Pure.
 *
 * @param bool  $columns_ready  pg_auto_dialog_content_ready()
 * @param array $design_ids     the visual designs' style ids
 * @return bool
 */
function pg_auto_dialog_form_asks_content($columns_ready, $design_ids)
{
    return $columns_ready && (count(array_filter(array_map('intval', (array) $design_ids))) > 0);
}

/**
 * pg_auto_dialog_form_asks_content() for this site. The designs are read
 * only once the columns are there.
 *
 * @return bool
 */
function pg_auto_dialog_form_content_asked()
{
    return pg_auto_dialog_content_ready() && pg_auto_dialog_form_asks_content(true, array_keys(pg_visual_design_style_ids()));
}

/**
 * The kinds "What does it show?" offers (label => value), the address first.
 *
 * @return array
 */
function pg_auto_dialog_form_content_kinds()
{
    return array(
        lang('An address') => 'url',
        lang('A designed pop-up') => 'component',
    );
}

/**
 * Whether the URL is required: always with the address kind, never with a
 * designed pop-up. $content is the kind sent, null when the screen does not
 * ask (the address kind). Pure.
 *
 * @param mixed $content
 * @return bool
 */
function pg_auto_dialog_form_url_required($content)
{
    return !(is_scalar($content) && (strtolower(trim((string) $content)) === 'component'));
}

/**
 * The kind and the shared component sent, for
 * pg_auto_dialog_form_content_problems() (unread, as sent).
 *
 * @param array|liveform $source
 * @return array  content, component_id
 */
function pg_auto_dialog_form_content_sent($source)
{
    $sent = array();

    foreach (array('content', 'component_id') as $field) {
        $sent[$field] = is_array($source) ? ($source[$field] ?? '') : $source->get_field_value($field);
    }

    return $sent;
}

/**
 * What a save writes to the content columns (pg_auto_dialog_save()'s
 * $content): null when the screen does not ask, so the columns stay as
 * stored; otherwise the kind and the component read by
 * pg_auto_dialog_content(). The component chosen is kept with the address
 * kind too, so a switch back finds it.
 *
 * @param array|liveform $source
 * @param bool           $asked  pg_auto_dialog_form_asks_content()
 * @return array|null
 */
function pg_auto_dialog_form_content_values($source, $asked)
{
    return $asked ? pg_auto_dialog_content(pg_auto_dialog_form_content_sent($source)) : null;
}

/**
 * The shared component select's options: a blank prompt, then each
 * component by name (escaped: the form prints labels as they are). Pure but
 * for lang().
 *
 * @param array $rows  id, name (pg_auto_dialog_component_rows())
 * @return array  label => value, or array('label', 'value') for a name used twice
 */
function pg_auto_dialog_form_component_options($rows)
{
    $options = array(lang('Choose a shared component') => '');

    foreach ((array) $rows as $row) {
        $id = (int) ($row['id'] ?? 0);

        if ($id > 0) {
            $options['c' . $id] = array('label' => h((string) ($row['name'] ?? '')), 'value' => (string) $id);
        }
    }

    return $options;
}

/**
 * The designer links beside the shared component select: open, the
 * component opened alone (edit_system_style.php?component=, '' with none
 * chosen; the script follows the select), and new, the first visual design
 * to design a new pop-up in, only for those who may open it in full. Pure.
 *
 * @param int   $component_id
 * @param array $design_ids
 * @param bool  $may_design   pg_designer_is_full()
 * @return array  open, new (paths under the software directory)
 */
function pg_auto_dialog_form_designer_links($component_id, $design_ids, $may_design)
{
    $designs = array_values(array_filter(array_map('intval', (array) $design_ids)));
    sort($designs);

    return array(
        'open' => ((int) $component_id > 0) ? 'edit_system_style.php?component=' . (int) $component_id : '',
        'new' => ($may_design && $designs) ? 'edit_system_style.php?id=' . $designs[0] : '',
    );
}

