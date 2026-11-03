<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Submitted form writes: one create and one delete path that the submitted
 * form screens (add_submitted_form.php, edit_submitted_form.php,
 * delete_submitted_forms.php, view_submitted_forms.php) and the external API
 * (/forms/{id}/submissions) share, so a record made or removed from either
 * place goes through the same checks and leaves the same rows behind.
 *
 * Nothing here checks who is asking: the screens gate on
 * validate_forms_access() and check_edit_access() of the form page's folder,
 * the API on forms:write and api_form_owner_filter().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

/**
 * The fields a new record of a custom form is written for, in form order.
 * Information fields hold no answer and are left out.
 *
 * @param int|string $page_id  The custom form's page
 * @return array
 */
function pg_submitted_form_fields($page_id)
{
    return db_items(
        "SELECT
            id,
            name,
            rss_field,
            label,
            type,
            wysiwyg,
            multiple,
            contact_field,
            required,
            office_use_only,
            upload_folder_id
         FROM form_fields
         WHERE
            (page_id = '" . escape($page_id) . "')
            AND (type != 'information')
         ORDER BY sort_order");
}

/**
 * Whether a field is one the record is written for: an office use only field
 * counts only for someone allowed to see it.
 *
 * @param array $field
 * @param bool  $office_use_only_access
 * @return bool
 */
function pg_submitted_form_field_writable($field, $office_use_only_access)
{
    return !(((int) $field['office_use_only'] === 1) && !$office_use_only_access);
}

/**
 * Checks the answers held in the live form before a new record is written,
 * marking each refusal on its field: required fields (only when the record
 * is completed, so a record saved for later may be partial), a signature that
 * is no PNG, uploads, dates, times, e-mail addresses, a Title whose address
 * name is taken and a collection field's number, image or reference.
 *
 * @param object $liveform
 * @param array  $fields   pg_submitted_form_fields()
 * @param int|string $page_id
 * @param bool   $complete
 * @param bool   $office_use_only_access
 * @param bool   $pretty_urls
 * @param array  $files    The uploads, keyed by field id ($_FILES on the screen)
 * @return void
 */
function pg_submitted_form_create_check($liveform, $fields, $page_id, $complete, $office_use_only_access, $pretty_urls, $files)
{
    foreach ($fields as $field) {
        if (!pg_submitted_form_field_writable($field, $office_use_only_access)) {
            continue;
        }

        if ($field['required'] && $complete) {
            $error_message = '';

            if ($field['label']) {
                $error_message = lang(array('string'=>'{var:1} is required.','vars'=>h($field['label'])));
            }

            if ($field['type'] == 'file upload') {
                if ((isset($files[$field['id']]) == false) || ($files[$field['id']]['name'] == '')) {
                    $liveform->mark_error($field['id'], $error_message);
                }
            } else {
                $liveform->validate_required_field($field['id'], $error_message);
            }
        }

        // A signature that arrived as anything other than a PNG data URL is
        // refused here, the same as on the visitor's form.
        if (
            ($field['type'] == 'signature')
            && function_exists('pg_signature_png_from_data_url')
            && ($liveform->check_field_error($field['id']) == false)
            && ($liveform->get_field_value($field['id']) != '')
            && (pg_signature_png_from_data_url($liveform->get_field_value($field['id'])) === false)
        ) {
            $liveform->mark_error($field['id'], lang('The signature could not be read. Please clear the box and sign again.'));
        }

        pg_submitted_form_check_field($liveform, $field, $page_id, $pretty_urls);
    }
}

/**
 * Writes a new record of a custom form from the answers held in the live
 * form, after pg_submitted_form_create_check() found nothing: the forms row
 * (the creating user as the submitter; no visitor columns), one form_data row
 * per field, the uploads, the signatures, the address name, and on request
 * the notification e-mails a visitor's submission would send.
 *
 * @param object $liveform
 * @param array  $fields   pg_submitted_form_fields()
 * @param int|string $page_id
 * @param bool   $complete  false saves it for later
 * @param int    $user_id   The user creating it
 * @param bool   $office_use_only_access
 * @param bool   $pretty_urls
 * @param array  $files     The uploads, keyed by field id ($_FILES on the screen)
 * @param bool   $send_notifications  Only for a completed record
 * @return array|false  id and reference_code, false when the record could not be written
 */
function pg_submitted_form_create($liveform, $fields, $page_id, $complete, $user_id, $office_use_only_access, $pretty_urls, $files, $send_notifications)
{
    $reference_code = generate_form_reference_code();

    $result = db_q(
        "INSERT INTO forms (
            page_id,
            complete,
            user_id,
            reference_code,
            submitted_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())",
        array((string) $page_id, $complete ? '1' : '0', (string) (int) $user_id, (string) $reference_code, (string) (int) $user_id));

    if (!$result) {
        return false;
    }

    $form_id = (int) mysqli_insert_id(db::$con);

    // The signatures drawn on the form, stored after the loop.
    $signature_pending = array();

    foreach ($fields as $field) {
        if (!pg_submitted_form_field_writable($field, $office_use_only_access)) {
            continue;
        }

        if ($field['type'] == 'file upload') {
            $file_id = 0;

            if ((isset($files[$field['id']]) == true) && ($files[$field['id']]['name'] != '')) {
                $file_id = (int) pg_submitted_form_store_upload($field, $user_id);
            }

            // A field without a file still gets its row, as a visitor's
            // submission without one does.
            db_q(
                "INSERT INTO form_data (form_id, form_field_id, file_id, name) VALUES (?, ?, ?, ?)",
                array((string) $form_id, (string) $field['id'], (string) $file_id, (string) $field['name']));

        // A signature is written through its own path: the drawing becomes a
        // file, form_data points at it, and the record that gives it meaning
        // is written beside it. The row is written without a file here; the
        // record is written once every answer is stored
        // (pg_signature_store_pending()), because the document it is bound
        // to is the answers as stored.
        } else if (($field['type'] == 'signature') && function_exists('pg_signature_ready') && pg_signature_ready()) {
            $signature_png = pg_signature_png_from_data_url($liveform->get_field_value($field['id']));

            if ($signature_png !== false) {
                $signature_pending[] = array(
                    'field_id' => $field['id'],
                    'label' => $field['label'],
                    'png' => $signature_png,
                    'strokes' => pg_signature_clean_strokes(isset($_POST[$field['id'] . '_strokes']) ? $_POST[$field['id'] . '_strokes'] : ''),
                );
            }

            db_q(
                "INSERT INTO form_data (form_id, form_field_id, file_id, name) VALUES (?, ?, '0', ?)",
                array((string) $form_id, (string) $field['id'], (string) $field['name']));

        } else {
            pg_submitted_form_save_value($liveform, $form_id, $field);
        }
    }

    if ($signature_pending) {
        pg_signature_store_pending($form_id, $page_id, $signature_pending);
    }

    if ($pretty_urls == true) {
        update_submitted_form_address_name($form_id);
    }

    // Off unless asked for: the record was made by a member of staff or an
    // integration, not by a visitor waiting for a confirmation.
    if ($complete && $send_notifications) {
        pg_send_custom_form_notifications($page_id, $form_id, 0);
    }

    return array('id' => $form_id, 'reference_code' => (string) $reference_code);
}

/**
 * Deletes a submitted form with everything that names it: its uploaded
 * files, the forms and form_data rows, its signatures, its view rows and its
 * comment thread (data/submitted-form-delete-thread.md).
 *
 * @param int|string $form_id
 * @return array|false  The names of the files deleted with it, false when
 *                      the record could not be deleted
 */
function pg_submitted_form_delete($form_id)
{
    $form_id = (int) $form_id;

    if ($form_id <= 0) {
        return false;
    }

    $result = db_q(
        "SELECT
            files.id,
            files.name
         FROM form_data
         LEFT JOIN files ON form_data.file_id = files.id
         WHERE (form_data.form_id = ?) AND (form_data.file_id > 0)",
        array((string) $form_id));

    if (!$result) {
        return false;
    }

    $files = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $files[] = $row;
    }

    $deleted_files = array();

    foreach ($files as $file) {
        // A file deleted on its own before has no row left to join.
        if ($file['id']) {
            db_q("DELETE FROM files WHERE id = ?", array((string) $file['id']));

            @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);

            $deleted_files[] = (string) $file['name'];
        }
    }

    if (!db_q("DELETE FROM forms WHERE id = ?", array((string) $form_id))) {
        return false;
    }

    db_q("DELETE FROM form_data WHERE (form_id = ?) AND (form_id != '0')", array((string) $form_id));

    pg_signature_delete($form_id);

    // The view rows the form view directory feature counts.
    pg_sfv_delete_views('submitted_form_id', $form_id);

    // The record's comments, their attachments, watchers and counters.
    pg_comment_delete_record_thread($form_id);

    return $deleted_files;
}
