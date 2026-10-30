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
 * @return array[]  id, name, scope, user_id, updated_at
 */
function erp_quote_templates_for_user($user_id)
{
    if (!erp_quote_templates_ready()) {
        return array();
    }

    return (array) db_items("SELECT id, name, scope, user_id, updated_at FROM erp_quote_templates
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

    $row = db_item("SELECT id, name, scope, user_id, form_data FROM erp_quote_templates WHERE id = '" . (int) $template_id . "'");

    return (is_array($row) && erp_quote_template_visible($row, $user_id)) ? $row : null;
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
 * @return array ['success' => bool, 'template_id' => int, 'name' => string, 'error' => string]
 */
function erp_quote_template_save($name, $scope, $lines, $user_id, $readonly)
{
    $fail = function ($message) {
        return array('success' => false, 'template_id' => 0, 'name' => '', 'error' => $message);
    };

    if (!erp_quote_templates_ready()) {
        return $fail(lang('Quote templates come with the software update; run the update to use them.'));
    }

    $refusal = erp_quote_template_write_refusal($scope, $user_id, $user_id, $readonly);
    if ($refusal !== '') {
        return $fail($refusal);
    }

    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    if ($name === '') {
        return $fail(lang('Give the template a name.'));
    }
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 100, 'UTF-8') : substr($name, 0, 100);

    $form_data = erp_quote_template_encode($lines);
    if (empty(erp_quote_template_lines($form_data))) {
        return $fail(lang('Enter at least one line.'));
    }

    $owner_id = ($scope === 'personal') ? (int) $user_id : 0;

    $existing = db_value("SELECT id FROM erp_quote_templates
        WHERE scope = '" . escape($scope) . "' AND user_id = '" . $owner_id . "' AND name = '" . escape($name) . "'
        ORDER BY id LIMIT 1");

    if ((int) $existing > 0) {
        db("UPDATE erp_quote_templates SET form_data = '" . escape($form_data) . "', updated_at = UNIX_TIMESTAMP()
            WHERE id = '" . (int) $existing . "'");

        return array('success' => true, 'template_id' => (int) $existing, 'name' => $name, 'error' => '');
    }

    $ok = db("INSERT INTO erp_quote_templates (name, scope, user_id, form_data, created_at, updated_at) VALUES (
        '" . escape($name) . "', '" . escape($scope) . "', '" . $owner_id . "', '" . escape($form_data) . "', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())");

    if ($ok === false) {
        return $fail(lang('The template could not be saved.'));
    }

    return array('success' => true, 'template_id' => (int) mysqli_insert_id(db::$con), 'name' => $name, 'error' => '');
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
    $template = erp_quote_template_find($template_id, $user_id);

    if ($template === null) {
        return array('success' => false, 'name' => '', 'error' => lang('The template could not be found.'));
    }

    $refusal = erp_quote_template_write_refusal((string) $template['scope'], (int) $template['user_id'], $user_id, $readonly);
    if ($refusal !== '') {
        return array('success' => false, 'name' => '', 'error' => $refusal);
    }

    db("DELETE FROM erp_quote_templates WHERE id = '" . (int) $template['id'] . "'");

    return array('success' => true, 'name' => (string) $template['name'], 'error' => '');
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
                        <button type="submit" name="template_action" value="apply" class="btn btn-outline theme-primary" formnovalidate><i class="bi bi-box-arrow-in-down me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Fill the lines') . '</span></button>
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
