<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - sales quote templates.
 *
 * The lines of the quote editor kept under a name (erp_quote_templates,
 * 2026.11.0 step 11.0.63), to fill a new quote with. A template keeps lines
 * alone: the account, the dates, the currency and the number are always the
 * operator's. A personal template is seen and changed by its owner only; a
 * site template is seen by everyone with the ERP right and changed by anyone
 * who may write quotes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Whether the 11.0.63 table is there.
 *
 * @return bool
 */
function erp_quote_templates_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_quote_templates', 'form_data');
    }

    return $ready;
}

/**
 * @return array  scope => label
 */
function erp_quote_template_scopes()
{
    return array(
        'personal' => lang('Only me'),
        'site' => lang('Everyone'),
    );
}

/**
 * The fields a template keeps of a line, with the type each is read back
 * as. An offer and the source line belong to the moment a document was
 * made, so a template leaves them out.
 *
 * @return array  key => 'int' | 'float' | 'string'
 */
function erp_quote_template_line_keys()
{
    return array(
        'product_id' => 'int',
        'description' => 'string',
        'quantity' => 'float',
        'unit_code' => 'string',
        'unit_price' => 'int',
        'discount_rate' => 'float',
        'tax_rate' => 'float',
        'tax2_rate' => 'float',
        'withholding_code' => 'string',
        'withholding_rate' => 'float',
    );
}

/**
 * One line with the template's fields only, typed.
 *
 * @param array $line  A stored line (erp_invoice_form_read())
 * @return array|null  null for a row with neither product nor description
 */
function erp_quote_template_line($line)
{
    if (!is_array($line)) {
        return null;
    }

    $out = array();

    foreach (erp_quote_template_line_keys() as $key => $type) {
        $value = $line[$key] ?? null;

        if (is_array($value)) {
            $value = null;
        }

        if ($type === 'int') {
            $out[$key] = (int) $value;
        } elseif ($type === 'float') {
            $out[$key] = (float) $value;
        } else {
            $out[$key] = (string) $value;
        }
    }

    if ($out['unit_code'] === '') {
        $out['unit_code'] = 'C62';
    }

    if (($out['product_id'] <= 0) && (trim($out['description']) === '')) {
        return null;
    }

    return $out;
}

/**
 * form_data for a template: {"lines": [...]} and nothing else.
 *
 * @param array $lines  Stored lines (erp_invoice_form_read()['data']['lines'])
 * @return string JSON
 */
function erp_quote_template_encode($lines)
{
    $kept = array();

    foreach ((array) $lines as $line) {
        $line = erp_quote_template_line($line);

        if ($line !== null) {
            $kept[] = $line;
        }
    }

    return (string) json_encode(array('lines' => $kept), JSON_UNESCAPED_UNICODE);
}

/**
 * The lines a template's form_data holds; anything else in it is ignored.
 *
 * @param string|null $form_data
 * @return array  Stored lines
 */
function erp_quote_template_lines($form_data)
{
    $data = json_decode((string) $form_data, true);

    if (!is_array($data) || !is_array($data['lines'] ?? null)) {
        return array();
    }

    $lines = array();

    foreach ($data['lines'] as $line) {
        $line = erp_quote_template_line($line);

        if ($line !== null) {
            $lines[] = $line;
        }
    }

    return $lines;
}

/**
 * Whether a user sees a template.
 *
 * @param array $template  scope, user_id
 * @param int   $user_id
 * @return bool
 */
function erp_quote_template_visible($template, $user_id)
{
    $scope = (string) ($template['scope'] ?? '');

    if ($scope === 'site') {
        return true;
    }

    return ($scope === 'personal') && ((int) $user_id > 0) && ((int) ($template['user_id'] ?? 0) === (int) $user_id);
}

/**
 * Why a user may not write (save or delete) a template, or '' when they may.
 *
 * @param string $scope     'personal' or 'site'
 * @param int    $owner_id  The personal template's owner; the user for a new one
 * @param int    $user_id
 * @param bool   $readonly  The accountant's read-only ERP right
 * @return string
 */
function erp_quote_template_write_refusal($scope, $owner_id, $user_id, $readonly)
{
    if ($readonly || ((int) $user_id <= 0)) {
        return lang('You may not change quote templates.');
    }

    if (!array_key_exists((string) $scope, erp_quote_template_scopes())) {
        return lang('Choose who the template is for.');
    }

    if (($scope === 'personal') && ((int) $owner_id !== (int) $user_id)) {
        return lang('Only its owner may change a personal template.');
    }

    return '';
}

/**
 * The templates a user sees: site templates and their own, by name.
 *
 * @param int $user_id
 * @return array[]  id, name, scope, user_id, form_data, updated_at
 */
function erp_quote_templates_for_user($user_id)
{
    if (!erp_quote_templates_ready()) {
        return array();
    }

    return (array) db_items("SELECT id, name, scope, user_id, form_data, updated_at FROM erp_quote_templates
        WHERE (scope = 'site') OR (scope = 'personal' AND user_id = '" . (int) $user_id . "')
        ORDER BY scope = 'site', name, id");
}

/**
 * A template the user sees, or null.
 *
 * @param int $template_id
 * @param int $user_id
 * @return array|null
 */
function erp_quote_template_find($template_id, $user_id)
{
    if (!erp_quote_templates_ready() || ((int) $template_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT id, name, scope, user_id, form_data, updated_at FROM erp_quote_templates WHERE id = '" . (int) $template_id . "'");

    return (is_array($row) && erp_quote_template_visible($row, $user_id)) ? $row : null;
}

/**
 * The key two template names are compared by: spaces tidied, letter case
 * ignored the Turkish way (İ, I, ı and i are one letter, the rule of
 * pg_list_search_fold()). Other letters stay apart, so "şablon" and
 * "sablon" are two names. The column's own collation is never asked: it
 * also ignores accents and calls those two one name.
 *
 * @param string $name
 * @return string
 */
function erp_quote_template_name_key($name)
{
    return pg_list_search_fold(trim((string) preg_replace('/\s+/u', ' ', (string) $name)));
}

/**
 * The template among these rows a save under this name writes over: the
 * oldest whose name has the same key (erp_quote_template_name_key()).
 *
 * @param array  $rows  [['id' => int, 'name' => string], ...]
 * @param string $name
 * @return array|null ['id' => int, 'name' => string] with the stored spelling
 */
function erp_quote_template_name_match(array $rows, $name)
{
    $key = erp_quote_template_name_key($name);
    $found = null;

    foreach ($rows as $row) {
        if (!is_array($row) || ((int) ($row['id'] ?? 0) <= 0) || (erp_quote_template_name_key((string) ($row['name'] ?? '')) !== $key)) {
            continue;
        }
        if (($found === null) || ((int) $row['id'] < $found['id'])) {
            $found = array('id' => (int) $row['id'], 'name' => (string) $row['name']);
        }
    }

    return $found;
}

/**
 * The template a save under this name would write over, or null.
 *
 * @param string $scope
 * @param int    $owner_id  0 for a site template
 * @param string $name
 * @return array|null ['id' => int, 'name' => string]
 */
function erp_quote_template_existing($scope, $owner_id, $name)
{
    $rows = db_items("SELECT id, name FROM erp_quote_templates
        WHERE scope = '" . escape($scope) . "' AND user_id = '" . (int) $owner_id . "'
        ORDER BY id");

    return erp_quote_template_name_match(is_array($rows) ? $rows : array(), $name);
}

/**
 * Everything a save asks before it writes, for the panel and the API alike.
 * A template of the same name in the same scope (and owner) is the row the
 * save writes over (erp_quote_template_name_key()); the answer then carries
 * that row's stored name, so the reply, the log and the event name the
 * template actually written.
 *
 * @param string        $name
 * @param string        $scope
 * @param array         $lines     Stored lines
 * @param int           $user_id
 * @param bool          $readonly  The accountant's read-only ERP right
 * @param callable|null $existing  fn($scope, $owner_id, $name) => ['id', 'name'] or null (an id alone is
 *                                 taken too); erp_quote_template_existing() when null
 * @return array ['error' => string, 'field' => string, 'name', 'scope', 'owner_id', 'form_data', 'replaces' => int]
 */
function erp_quote_template_save_check($name, $scope, $lines, $user_id, $readonly, $existing = null)
{
    $result = array('error' => '', 'field' => '', 'name' => '', 'scope' => (string) $scope, 'owner_id' => 0, 'form_data' => '', 'replaces' => 0);

    $refusal = erp_quote_template_write_refusal($scope, $user_id, $user_id, $readonly);
    if ($refusal !== '') {
        $result['error'] = $refusal;
        $result['field'] = (!$readonly && ((int) $user_id > 0)) ? 'scope' : '';
        return $result;
    }

    $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    if ($name === '') {
        $result['error'] = lang('Give the template a name.');
        $result['field'] = 'name';
        return $result;
    }
    $result['name'] = function_exists('mb_substr') ? mb_substr($name, 0, 100, 'UTF-8') : substr($name, 0, 100);

    $result['form_data'] = erp_quote_template_encode($lines);
    if (empty(erp_quote_template_lines($result['form_data']))) {
        $result['error'] = lang('Enter at least one line.');
        $result['field'] = 'lines';
        return $result;
    }

    $result['owner_id'] = ($scope === 'personal') ? (int) $user_id : 0;

    if ($existing === null) {
        $existing = 'erp_quote_template_existing';
    }
    $match = call_user_func($existing, (string) $scope, $result['owner_id'], $result['name']);
    if (is_array($match)) {
        $result['replaces'] = (int) ($match['id'] ?? 0);
        if (($result['replaces'] > 0) && ((string) ($match['name'] ?? '') !== '')) {
            $result['name'] = (string) $match['name'];
        }
    } else {
        $result['replaces'] = (int) $match;
    }

    return $result;
}

/**
 * Keep lines as a template. A template of the same name the user may see in
 * the same scope is written over, so saving again updates it.
 *
 * @param string $name
 * @param string $scope
 * @param array  $lines     Stored lines
 * @param int    $user_id
 * @param bool   $readonly
 * @return array ['success' => bool, 'template_id' => int, 'name' => string, 'created' => bool, 'error' => string, 'field' => string]
 */
function erp_quote_template_save($name, $scope, $lines, $user_id, $readonly)
{
    $fail = function ($message, $field = '') {
        return array('success' => false, 'template_id' => 0, 'name' => '', 'created' => false, 'error' => $message, 'field' => $field);
    };

    if (!erp_quote_templates_ready()) {
        return $fail(lang('Quote templates come with the software update; run the update to use them.'));
    }

    $check = erp_quote_template_save_check($name, $scope, $lines, $user_id, $readonly);
    if ($check['error'] !== '') {
        return $fail($check['error'], $check['field']);
    }

    $template_id = (int) $check['replaces'];

    if ($template_id > 0) {
        db("UPDATE erp_quote_templates SET form_data = '" . escape($check['form_data']) . "', updated_at = UNIX_TIMESTAMP()
            WHERE id = '" . $template_id . "'");
    } else {
        $ok = db("INSERT INTO erp_quote_templates (name, scope, user_id, form_data, created_at, updated_at) VALUES (
            '" . escape($check['name']) . "', '" . escape($scope) . "', '" . (int) $check['owner_id'] . "', '" . escape($check['form_data']) . "', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())");

        if ($ok === false) {
            return $fail(lang('The template could not be saved.'));
        }

        $template_id = (int) mysqli_insert_id(db::$con);
    }

    erp_event('erp.quote_template.saved', array('id' => $template_id, 'name' => $check['name'], 'scope' => (string) $scope));

    return array('success' => true, 'template_id' => $template_id, 'name' => $check['name'], 'created' => ((int) $check['replaces'] === 0), 'error' => '', 'field' => '');
}

/**
 * Everything a delete asks before it writes. A template the user does not
 * see is not found (404); one they see but may not change is refused (422).
 *
 * @param array|null $template  The row (id, name, scope, user_id), null when there is none
 * @param int        $user_id
 * @param bool       $readonly
 * @return array ['error' => string, 'status' => 0|404|422, 'template' => array|null]
 */
function erp_quote_template_delete_check($template, $user_id, $readonly)
{
    if (!is_array($template) || !erp_quote_template_visible($template, $user_id)) {
        return array('error' => lang('The template could not be found.'), 'status' => 404, 'template' => null);
    }

    $refusal = erp_quote_template_write_refusal((string) $template['scope'], (int) $template['user_id'], $user_id, $readonly);
    if ($refusal !== '') {
        return array('error' => $refusal, 'status' => 422, 'template' => $template);
    }

    return array('error' => '', 'status' => 0, 'template' => $template);
}

/**
 * Delete a template the user may write.
 *
 * @param int  $template_id
 * @param int  $user_id
 * @param bool $readonly
 * @return array ['success' => bool, 'name' => string, 'error' => string]
 */
function erp_quote_template_delete($template_id, $user_id, $readonly)
{
    $check = erp_quote_template_delete_check(erp_quote_template_find($template_id, $user_id), $user_id, $readonly);

    if ($check['error'] !== '') {
        return array('success' => false, 'name' => '', 'error' => $check['error']);
    }

    $template = $check['template'];

    db("DELETE FROM erp_quote_templates WHERE id = '" . (int) $template['id'] . "'");

    erp_event('erp.quote_template.deleted', array('id' => (int) $template['id'], 'name' => (string) $template['name'], 'scope' => (string) $template['scope']));

    return array('success' => true, 'name' => (string) $template['name'], 'error' => '');
}

/**
 * The line list POST /erp/quotes takes, read as template lines: the same
 * fields, unit as unit_code, unit_price in minor units. Empty rows are
 * dropped; at least one line has to stay.
 *
 * @param mixed $raw  The lines parameter
 * @return array ['lines' => stored lines, 'error' => string, 'field' => string]
 */
function erp_quote_template_lines_from_api($raw)
{
    $known = array('product_id', 'description', 'quantity', 'unit', 'unit_price', 'discount_rate', 'tax_rate', 'tax2_rate', 'withholding_code', 'withholding_rate');
    $numbers = array('quantity', 'discount_rate', 'tax_rate', 'tax2_rate', 'withholding_rate');
    $fail = function ($message, $field) {
        return array('lines' => array(), 'error' => $message, 'field' => $field);
    };

    $lines = array();

    foreach (array_values((array) $raw) as $index => $line) {
        $field = 'lines[' . $index . ']';

        if (!is_array($line) || (!empty($line) && (array_keys($line) === range(0, count($line) - 1)))) {
            return $fail(lang('Each line must be an object.'), $field);
        }

        foreach (array_keys($line) as $key) {
            if (!in_array((string) $key, $known, true)) {
                $key = mb_substr((string) $key, 0, 60);
                return $fail(lang(array('string' => 'A line does not take a field called {var:1}.', 'vars' => $key)), $field . '.' . $key);
            }
        }

        foreach ($numbers as $name) {
            if (isset($line[$name]) && ($line[$name] !== '') && (is_bool($line[$name]) || !is_numeric($line[$name]))) {
                return $fail(lang(array('string' => '{var:1} must be a number.', 'vars' => $field . '.' . $name)), $field . '.' . $name);
            }
        }

        if (isset($line['product_id']) && ($line['product_id'] !== '') && (is_bool($line['product_id']) || !preg_match('/^\d+$/', (string) $line['product_id']))) {
            return $fail(lang(array('string' => '{var:1} must be a whole number.', 'vars' => $field . '.product_id')), $field . '.product_id');
        }

        $price = $line['unit_price'] ?? 0;

        if (is_bool($price) || !is_numeric($price) || (strpos((string) $price, '.') !== false) || (strpos((string) $price, ',') !== false)) {
            return $fail(lang(array('string' => '{var:1} must be a whole number of minor units, with no decimal separator.', 'vars' => $field . '.unit_price')), $field . '.unit_price');
        }

        foreach (array('description', 'unit', 'withholding_code') as $name) {
            if (isset($line[$name]) && !is_string($line[$name]) && !is_int($line[$name])) {
                return $fail(lang(array('string' => '{var:1} must be text.', 'vars' => $field . '.' . $name)), $field . '.' . $name);
            }
        }

        $stored = erp_quote_template_line(array(
            'product_id' => (int) ($line['product_id'] ?? 0),
            'description' => mb_substr(trim((string) ($line['description'] ?? '')), 0, 255),
            'quantity' => (isset($line['quantity']) && ($line['quantity'] !== '')) ? (float) $line['quantity'] : 1.0,
            'unit_code' => strtoupper(mb_substr(trim((string) ($line['unit'] ?? '')), 0, 10)),
            'unit_price' => (int) $price,
            'discount_rate' => $line['discount_rate'] ?? 0,
            'tax_rate' => $line['tax_rate'] ?? 0,
            'tax2_rate' => $line['tax2_rate'] ?? 0,
            'withholding_code' => mb_substr(trim((string) ($line['withholding_code'] ?? '')), 0, 10),
            'withholding_rate' => $line['withholding_rate'] ?? 0,
        ));

        if ($stored !== null) {
            $lines[] = $stored;
        }
    }

    if (empty($lines)) {
        return $fail(lang('Enter at least one line.'), 'lines');
    }

    return array('lines' => $lines, 'error' => '', 'field' => '');
}

/**
 * The templates box above the quote editor: fill the lines from a template,
 * keep the typed lines as one, delete one. Its buttons submit the editor's
 * own form with template_action set, so add_erp_quote.php keeps what was
 * typed and redraws it.
 *
 * @param liveform $liveform
 * @param int      $user_id
 * @return string HTML, '' before the update ran
 */
function erp_quote_templates_box($liveform, $user_id)
{
    if (!erp_quote_templates_ready()) {
        return '';
    }

    $templates = erp_quote_templates_for_user($user_id);
    $groups = array('personal' => array(), 'site' => array());

    foreach ($templates as $template) {
        if (isset($groups[(string) $template['scope']])) {
            $groups[(string) $template['scope']][] = $template;
        }
    }

    $options = array('' => '');
    foreach (array('personal' => lang('My templates'), 'site' => lang('Site templates')) as $scope => $label) {
        if (empty($groups[$scope])) {
            continue;
        }

        $options[h($label)] = '<optgroup>';
        foreach ($groups[$scope] as $template) {
            $options['t' . (int) $template['id']] = array('label' => h((string) $template['name']), 'value' => (int) $template['id']);
        }
        $options['/' . $scope] = '</optgroup>';
    }

    $scope_options = array();
    foreach (erp_quote_template_scopes() as $scope => $label) {
        $scope_options[h($label)] = $scope;
    }

    $output_pick = empty($templates)
        ? '<div class="form-text">' . lang('No templates yet. Type the lines below and keep them as a template.') . '</div>'
        : '<div class="d-flex flex-wrap gap-3">
                        ' . $liveform->output_field(array(
                            'type' => 'select', 'id' => 'template_id', 'name' => 'template_id',
                            'class' => 'form-control w-auto flex-grow-1', 'options' => $options)) . '
                        <button type="submit" name="template_action" value="apply" class="btn btn-outline theme-primary" formnovalidate data-confirm-content="' . h(lang('Replace the lines typed below with the lines of this template?')) . '"><i class="bi bi-box-arrow-in-down me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Fill the lines') . '</span></button>
                        <button type="submit" name="template_action" value="delete" class="btn btn-text theme-danger" formnovalidate data-confirm-content="' . h(lang('Delete this template?')) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Delete') . '</span></button>
                    </div>
                    <div class="form-text">' . lang('Replaces the lines below; the account, dates and currency stay as they are.') . '</div>';

    return '
    <div class="card my-7">
        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
            ' . lang('Quote templates') . '
        </div>
        <div class="card-body">
            <div class="row gy-3">
                <div class="col-12 lg:col-6">
                    <label for="template_id" class="form-label">' . lang('From template') . '</label>
                    ' . $output_pick . '
                </div>
                <div class="col-12 lg:col-6">
                    <label for="template_name" class="form-label">' . lang('Keep these lines as a template') . '</label>
                    <div class="d-flex flex-wrap gap-3">
                        ' . $liveform->output_field(array(
                            'type' => 'text', 'id' => 'template_name', 'name' => 'template_name',
                            'class' => 'form-control w-auto flex-grow-1', 'maxlength' => '100', 'autocomplete' => 'off',
                            'placeholder' => lang('Template name'))) . '
                        ' . $liveform->output_field(array(
                            'type' => 'select', 'id' => 'template_scope', 'name' => 'template_scope',
                            'class' => 'form-control w-auto', 'aria-label' => lang('Who sees it'), 'options' => $scope_options)) . '
                        <button type="submit" name="template_action" value="save" class="btn btn-outline theme-secondary" formnovalidate><i class="bi bi-bookmark-plus me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Save as template') . '</span></button>
                    </div>
                    <div class="form-text">' . lang('A template of the same name is written over.') . '</div>
                </div>
            </div>
        </div>
    </div>';
}
