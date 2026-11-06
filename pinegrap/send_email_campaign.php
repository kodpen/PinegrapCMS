<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_send.php');

$user = validate_user();
validate_email_access($user);

// The window is drawn once and its script sends the next batches through the
// JSON mode (?format=json), which answers {sent, total, done, error}. A JSON
// caller gets its refusals in JSON: validate_token_field() and output_error()
// print an HTML page, which the script cannot read.
$send_json = (($_GET['format'] ?? '') === 'json');

// Sending is a change, so it carries the CSRF token like the other actions:
// both links that open this window and every request that sends the next
// batch pass it.
if ($send_json) {
    $session_token = (string) ($_SESSION['software']['token'] ?? '');
    $get_token = (isset($_GET['token']) && is_string($_GET['token'])) ? $_GET['token'] : '';
    if (($session_token === '') || !hash_equals($session_token, $get_token)) {
        send_email_campaign_json(array('error' => lang('Your session expired or you logged out. Sign in again and send the campaign again to resume it.')), 403);
    }
} else {
    validate_token_field();
}

// A campaign that is missing, unknown or sent as an array (?id[]=x) stops on
// "not found": escape() failed on the array with an error page, and a missing
// id ran the send loop on an empty row.
$id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM email_campaigns WHERE id = '" . $id . "'") < 1)) {
    if ($send_json) {
        send_email_campaign_json(array('error' => lang('Record not found.')), 404);
    }
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// get e-mail campaign information
$query =
    "SELECT
        type,
        email_campaign_profile_id,
        action,
        action_item_id,
        calendar_event_recurrence_number,
        from_name,
        from_email_address,
        reply_email_address,
        bcc_email_address,
        subject,
        format,
        body,
        page_id,
        status,
        purpose,
        created_user_id,
        created_timestamp
    FROM email_campaigns
    WHERE id = ?";
$result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');

$row = mysqli_fetch_assoc($result);

$type = $row['type'];
$email_campaign_profile_id = $row['email_campaign_profile_id'];
$action = $row['action'];
$action_item_id = $row['action_item_id'];
$calendar_event_recurrence_number = $row['calendar_event_recurrence_number'];
$from_name = $row['from_name'];
$from_email_address = $row['from_email_address'];
$reply_email_address = $row['reply_email_address'];
$bcc_email_address = $row['bcc_email_address'];
$subject_template = $row['subject'];
$format = $row['format'];
$body_template = $row['body'];
$page_id = $row['page_id'];
$status = $row['status'];
$purpose = $row['purpose'];
$email_campaign = $row;
$created_user_id = $row['created_user_id'];
$created_timestamp = $row['created_timestamp'];

// if user has a user role and user is not the creator of the e-mail campaign, then output error
if (($user['role'] == 3) && ($created_user_id != $user['id'])) {
    log_activity(lang('access denied to send e-mail campaign because user is not the creator of the e-mail campaign'), $_SESSION['sessionusername']);
    output_error_in_popup(lang('Access denied.'));
}

// Only a ready campaign is sent: a paused or cancelled one stops here even
// when the window was opened from an earlier link. A complete one skips
// the send and shows its summary.
$send_refusal = pg_email_campaign_send_refusal($status);
if ($send_refusal !== '') {
    output_error_in_popup(h($send_refusal));
}
$already_complete = ($status == 'complete');

// A delivery email() refuses is stored as an attempt, as the campaign job
// does, and the recipient waits out the mail backoff: the next batches skip
// it, so a run never loops on an address the server cannot deliver to. An
// installation without the retry columns (before the 2026.4.8 upgrade) keeps
// the flow it had.
$retry_ready = pg_email_retry_ready();

// The campaign job sends the same recipients: with the retry columns this
// window claims each batch as the job does and skips what a job run holds,
// so an overlapping run and this window never both deliver to one recipient.
// A claim left by a closed or crashed window is freed after fifteen minutes.
$claim_token = null;

if ($retry_ready && !$already_complete) {
    db(pg_email_campaign_stale_claim_query($id));
}

// get next 10 e-mail recipients to send e-mail campaign to
$query =
    "SELECT
        id,
        email_address,
        contact_id" . ($retry_ready ? ",
        attempts" : "") . "
     FROM email_recipients
     WHERE
        (email_campaign_id = ?)
        AND (complete = '0')" . ($retry_ready ? "
        AND (claimed_at = 0)
        AND (next_attempt_at <= UNIX_TIMESTAMP())" : "") . "
     ORDER BY id
     LIMIT 10";
$result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');

// if there is at least one e-mail recipient left
if (!$already_complete && (mysqli_num_rows($result) > 0)) {
    $email_recipients = array();
    
    // loop through e-mail recipients
    while ($row = mysqli_fetch_assoc($result)) {
        $email_recipients[] = $row;
    }

    if ($retry_ready) {
        $claim_token = pg_email_campaign_claim_token();
        $claim_ids = array_map('intval', array_column($email_recipients, 'id'));

        db(pg_email_campaign_claim_query($claim_ids, $claim_token));

        // Keep only the rows this window won.
        $won = array_flip(array_map('intval', db_values(
            "SELECT id FROM email_recipients
            WHERE
                (id IN (" . implode(',', $claim_ids) . "))
                AND (claim_token = '" . e($claim_token) . "')")));

        $email_recipients = array_values(array_filter($email_recipients, function ($email_recipient) use ($won) {
            return isset($won[(int) $email_recipient['id']]);
        }));
    }
    
    foreach ($email_recipients as $email_recipient) {

        // If this is a commercial campaign and this contact is not the manually entered email
        // address (i.e. "Also send message to the following e-mail address"), and we can't find
        // and opted-in contact for this recipient's email address, then delete this recipient,
        // and skip to the next.  This is necessary because a campaign might have been created and
        // scheduled in the future, and then a contact might have opted-out after the campaign was
        // created.  We look for any contact with the same email that is opted-in, because
        // the original contact might have been deleted or merged.

        if (
            ($purpose == 'commercial')
            and $email_recipient['contact_id']
            and !db_value(
                "SELECT id
                FROM contacts
                WHERE
                    (email_address = '" . e($email_recipient['email_address']) . "')
                    AND (opt_in = '1')
                LIMIT 1")
        ) {

            db("DELETE FROM email_recipients WHERE id = '" . $email_recipient['id'] . "'");

            // Skip to the next recipient.
            continue;
        }

        // An address İYS holds a refusal for gets no commercial mail while
        // İYS is on for e-mail (includes/iys/client.php).
        if (($purpose == 'commercial') and pg_iys_email_refused($email_recipient['email_address'])) {

            db("DELETE FROM email_recipients WHERE id = '" . $email_recipient['id'] . "'");

            continue;
        }

        // An address that bounced or complained gets nothing, whatever the
        // purpose (includes/fn/mail_bounces.php); the finish counts it.
        if (pg_email_campaign_skip_suppressed($email_recipient['id'], $email_recipient['email_address'], $retry_ready, $claim_token)) {
            continue;
        }

        // prepare email body content
        $subject = $subject_template;
        $body = $body_template;

        // Create an array that will store the fields for variables that need to be replaced.
        $fields = array();
        
        // setup variables
        $mail_merge_first_name = '';
        $mail_merge_last_name = '';
        $mail_merge_nickname = '';
        $mail_merge_salutation = '';
        $mail_merge_suffix = '';
        
        // get contact information for email recipient
        $query =
            "SELECT
                first_name,
                last_name,
                nickname,
                salutation,
                suffix
             FROM contacts
             WHERE id = ?";
        $row = null;
        // Manually entered recipients are stored with contact_id 0 and have no contact row.
        if ((int) $email_recipient['contact_id'] > 0) {
            $result = db_q($query, array((int) $email_recipient['contact_id'])) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
        }
        if (!$row) {
            $row = array('first_name' => '', 'last_name' => '', 'nickname' => '', 'salutation' => '', 'suffix' => '');
        }

        $first_name = trim($row['first_name']);
        $last_name = trim($row['last_name']);
        
        // if there is a first name in the database, update the variable
        if ($row['first_name'] != '') {
            $mail_merge_first_name = ' ' . trim($row['first_name']);
        }
        
        // if there is a last name in the database, update the variable
        if ($row['last_name'] != '') {
            $mail_merge_last_name = ' ' . trim($row['last_name']);
        }
        
        // if there is a nickname in the database, update the variable
        if ($row['nickname'] != '') {
            $mail_merge_nickname = trim($row['nickname']);
        }
        
        // if there is a salutation in the database, update the variable
        if ($row['salutation'] != '') {
            $mail_merge_salutation = trim($row['salutation']);
        }
        
        // if there is a suffix in the database, update the variable
        if ($row['suffix'] != '') {
            $mail_merge_suffix = ', ' . trim($row['suffix']);
        }
        
        // if there is a nickname for the current email recipient, use that for the dynamic data
        if ($mail_merge_nickname != '') {
            $mail_merge_dynamic_data = trim($mail_merge_nickname);
            
        // else, there was not a nickname, so display the users full name
        } else {
            $mail_merge_dynamic_data = trim($mail_merge_salutation . $mail_merge_first_name . $mail_merge_last_name . $mail_merge_suffix);
        }

        // Add name field so that variable is replaced.
        $fields[] = array(
            'name' => 'name',
            'data' => $mail_merge_dynamic_data,
            'type' => '');

        // Add first_name field so that variable is replaced.
        $fields[] = array(
            'name' => 'first_name',
            'data' => $first_name,
            'type' => '');

        // Add last_name field so that variable is replaced.
        $fields[] = array(
            'name' => 'last_name',
            'data' => $last_name,
            'type' => '');

        // If this is an auto email campaign, then add fields for that type of campaign.
        if ($type == 'automatic') {
            // Add field for action date and time.
            $fields[] = array(
                'name' => 'action_date_and_time',
                'data' => date('Y-m-d H:i:s', $created_timestamp),
                'type' => 'date and time');

            // If this email campaign was created due to a calendar event being reserved, then add fields for that.
            if ($action == 'calendar_event_reserved') {
                $calendar_event = get_calendar_event($action_item_id, $calendar_event_recurrence_number);

                // Add field for calendar event date and time range.
                $fields[] = array(
                    'name' => 'calendar_event_date_and_time_range',
                    'data' => $calendar_event['date_and_time_range'],
                    'type' => '');

                // Add field for calendar event start date and time.
                $fields[] = array(
                    'name' => 'calendar_event_start_date_and_time',
                    'data' => $calendar_event['start_date_and_time'],
                    'type' => 'date and time');

                // Add field for calendar event end date and time.
                $fields[] = array(
                    'name' => 'calendar_event_end_date_and_time',
                    'data' => $calendar_event['end_date_and_time'],
                    'type' => 'date and time');
            }
        }

        // Replace variables in subject (e.g. ^^name^^).
        $subject = replace_variables(array(
            'content' => $subject,
            'fields' => $fields,
            'format' => 'plain_text'));

        // Replace variables in body (e.g. ^^name^^).
        $body = replace_variables(array(
            'content' => $body,
            'fields' => $fields,
            'format' => $format));
        
        // create email address reference code
        $email_recipient['reference_code'] = generate_email_recipient_reference_code();

        // if the format of the e-mail should be plain text, then prepare that
        if ($format == 'plain_text') {

            // If this is a commercial email, then prepare organization and email preferences info.
            if ($purpose == 'commercial') {

                $organization = '';

                // if there is an organization name, then add it to the organization
                if (ORGANIZATION_NAME != '') {
                    $organization .= ORGANIZATION_NAME;
                }

                // if there is an organization address 1, then add it to the organization
                if (ORGANIZATION_ADDRESS_1 != '') {
                    // if the organization is not blank, then add a space for separation
                    if ($organization != '') {
                        $organization .= ' ';
                    }

                    $organization .= ORGANIZATION_ADDRESS_1;
                }

                // if there is an organization address 2, then add it to the organization
                if (ORGANIZATION_ADDRESS_2 != '') {
                    // if the organization is not blank, then add a space for separation
                    if ($organization != '') {
                        $organization .= ' ';
                    }
                    
                    $organization .= ORGANIZATION_ADDRESS_2;
                }

                // if there is an organization city, then add it to the organization
                if (ORGANIZATION_CITY != '') {
                    // if the organization is not blank, then add a space for separation
                    if ($organization != '') {
                        $organization .= ' ';
                    }
                    
                    $organization .= ORGANIZATION_CITY;
                }

                // if there is an organization state, then add it to the organization
                if (ORGANIZATION_STATE != '') {
                    // if the organization is not blank, then add a space for separation
                    if ($organization != '') {
                        $organization .= ' ';
                    }
                    
                    $organization .= ORGANIZATION_STATE;
                }

                // if there is an organization zip code, then add it to the organization
                if (ORGANIZATION_ZIP_CODE != '') {
                    // if the organization is not blank, then add a space for separation
                    if ($organization != '') {
                        $organization .= ' ';
                    }
                    
                    $organization .= ORGANIZATION_ZIP_CODE;
                }

                // if there is an organization country, then add it to the organization
                if (ORGANIZATION_COUNTRY != '') {
                    // if the organization is not blank, then add a space for separation
                    if ($organization != '') {
                        $organization .= ' ';
                    }
                    
                    $organization .= ORGANIZATION_COUNTRY;
                }

                $body .=
                "\n" .
                "\n" .
                "\n" .
                $organization . "\n" .
                "\n" .
                '' . lang('Update email preferences or unsubscribe') . ':' . "\n" .
                "\n" .
                URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/email_preferences.php?' . pg_email_preferences_query($email_recipient['email_address']);

			}

        // else the format of the e-mail should be HTML, so prepare that
        } else {

            $body = preg_replace('/<reference_code><\/reference_code>/', $email_recipient['reference_code'], $body);
            $body = preg_replace('/<email_address_id><\/email_address_id>/', pg_email_preferences_placeholder_value($email_recipient['email_address']), $body);

        }
        
        $properties = pg_email_campaign_mail_properties(array('id' => $id) + $email_campaign, $email_recipient['email_address'], $mail_merge_dynamic_data, $subject, $body);

        if ($retry_ready) {

            // The sender is told about an undeliverable message once, on the
            // last attempt, as in the job.
            $properties['notify_sender'] = (((int) $email_recipient['attempts'] + 1) >= pg_mail_max_attempts());

            if (!email($properties)) {
                pg_email_campaign_record_failure($email_recipient['id'], $email_recipient['email_address'], $email_recipient['attempts'], $claim_token, $_SESSION['sessionusername']);
                continue;
            }

        } else {
            email($properties);
        }

        // mark e-mail recipient as complete and set reference code, and
        // release the claim
        $query = 
            "UPDATE email_recipients
            SET
                complete = '1',
                reference_code = ?" . ($retry_ready ? ",
                claimed_at = 0,
                claim_token = ''" : "") . "
            WHERE id = ?";
        $result = db_q($query, array((string) $email_recipient['reference_code'], (string) $email_recipient['id'])) or output_error('Query failed.');
    }

    // Whatever this batch claimed and did not finish goes straight back.
    if ($claim_token !== null) {
        db("UPDATE email_recipients SET claimed_at = 0, claim_token = '' WHERE (email_campaign_id = '" . $id . "') AND (claim_token = '" . e($claim_token) . "')");
    }
    
    // get total number of recipients
    $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_email_recipients = $row[0];
    
    // Only accepted deliveries count as sent: a given-up recipient is
    // complete but failed, and one waiting for another attempt is not complete.
    $number_of_completed_email_recipients = send_email_campaign_delivered_count($id, $retry_ready);
    $percent_complete = pg_email_campaign_send_summary($number_of_email_recipients, $number_of_completed_email_recipients, 0, 0)['percent'];

    if ($send_json) {
        send_email_campaign_json(array(
            'sent' => (int) $number_of_completed_email_recipients,
            'total' => (int) $number_of_email_recipients,
            'done' => false,
            'percent' => (int) $percent_complete,
            'percent_text' => lang(array('string' => '{var:1}%', 'vars' => array($percent_complete))),
            'count' => pg_format_number($number_of_completed_email_recipients, 0) . lang(' of ') . pg_format_number($number_of_email_recipients, 0)));
    }

    // The next batch is requested by the script below, one second after the
    // previous answer, as the refresh did; without script the window still
    // reloads itself.
    $send_json_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/send_email_campaign.php?id=' . $id . '&format=json&token=' . urlencode((string) ($_SESSION['software']['token'] ?? ''));

    echo
    output_header_secure(array('title'=>lang('Send E-mail Campaign'),'icon'=>'campaign')) . '
    <noscript><meta http-equiv="refresh" content="1;url=' . URL_SCHEME . h($_SERVER['HTTP_HOST']) . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/send_email_campaign.php?id=' . h(urlencode($_GET['id'])) . get_token_query_string_field() . '"></noscript>
    <nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
        <div class="container-fluid">
            <span class="navbar-text me-auto">' . lang('Send E-mail Campaign') . '</span>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <button id="campaign_send_header_close" title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="pgSendClose(false)" aria-label="' . h(lang('Close')) . '">
                        <span class="bi bi-x-lg"></span>
                    </button>
                </li>
            </ul>
        </div>
    </nav>


    <main id="content" class="container-fluid">
        <!-- Edit screen layout of product_builder.php: the progress in the form column, the campaign in the sticky status column, the buttons in the sticky save bar. -->
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Progress') . '</h2>
                    <div class="card-body pt-0">
                        <div id="campaign_sending">
                            <h3 id="campaign_send_heading" class="h5">' . lang('Sending Campaign Now') . '...</h3>
                            <p>' . lang('Sending the e-mail message to each subscriber now.') . '</p>

                            <div class="d-flex align-items-center gap-3">
                                <div class="progress flex-grow-1">
                                    <div id="campaign_send_bar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" aria-labelledby="campaign_send_heading" aria-valuenow="' . $percent_complete . '" aria-valuemin="0" aria-valuemax="100" style="width: ' . $percent_complete . '%"></div>
                                </div>
                                <span id="campaign_send_percent" class="fw-bold" aria-hidden="true">' . lang(array('string' => '{var:1}%', 'vars' => array($percent_complete))) . '</span>
                            </div>
                            <p id="campaign_send_count" class="h5 my-5">(' . pg_format_number($number_of_completed_email_recipients, 0) . lang(' of ') . pg_format_number($number_of_email_recipients, 0) . ')</p>
                            <div class="alert theme-danger mb-0"><p>' . lang('If you close this window before sending ends, sending stops; Send Campaign resumes it where it stopped.') . '</p></div>
                        </div>
                        <div id="campaign_sent" hidden>
                            <h3 id="campaign_sent_heading" class="h5">' . lang('Campaign Sent') . '.</h3>
                            <div id="campaign_sent_alert" class="alert theme-success"><p>' . lang('The e-mail campaign has been sent to all recipients.') . '</p><p id="campaign_sent_problem" class="mb-0" hidden></p><p id="campaign_sent_smtp" class="mb-0" hidden><a href="#" target="_blank" rel="noopener">' . lang('SMTP Settings') . '</a></p></div>
                        </div>
                        <div>
                            <p id="campaign_send_status" role="status" class="visually-hidden"></p>
                            <div id="campaign_send_error" class="alert theme-danger" role="alert" hidden><p></p></div>
                        </div>
                    </div>
                </div>
            </div>
            ' . send_email_campaign_information_html($subject_template, $from_name, $from_email_address, $reply_email_address) . '
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0">
                            <button id="campaign_send_stop" type="button" name="submit_save" onclick="pgSendClose(false)" value="Stop &amp; Close" class="btn mb-1 mt-1  btn-solid theme-danger submit-primary" ><span class="bi bi-x-lg me-3" aria-hidden="true"></span>' . lang(array('string'=>'Stop & Close') ) . '</button>
                            <button id="campaign_send_close" type="button" name="submit_save" value="Close" onclick="pgSendClose(true)" class="btn mb-1 mt-1  btn-solid theme-secondary submit-primary" hidden><span class="bi bi-x-lg me-3" aria-hidden="true"></span>' . lang(array('string'=>'Close') ) . '</button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </main>
    ' . send_email_campaign_close_script($id) . '
    <script>
    (function () {
        var url = ' . json_encode($send_json_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
        var failed = ' . json_encode(lang('The campaign stopped because the server did not answer. Close this window and send the campaign again to resume it.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
        var bar = document.getElementById(\'campaign_send_bar\');
        var percent = document.getElementById(\'campaign_send_percent\');
        var count = document.getElementById(\'campaign_send_count\');
        var status = document.getElementById(\'campaign_send_status\');
        var error = document.getElementById(\'campaign_send_error\');
        var stop = document.getElementById(\'campaign_send_stop\');
        var close = document.getElementById(\'campaign_send_close\');

        // The count is announced once per tenth of the campaign, not after
        // every batch of ten.
        var announced = -1;

        function show(answer) {
            bar.style.width = answer.percent + \'%\';
            bar.setAttribute(\'aria-valuenow\', answer.percent);
            percent.textContent = answer.percent_text;
            count.textContent = \'(\' + answer.count + \')\';
            var step = Math.floor(answer.percent / 10);
            if (step !== announced) {
                announced = step;
                status.textContent = answer.count;
            }
        }

        function fail(message) {
            bar.classList.remove(\'progress-bar-animated\');
            error.querySelector(\'p\').textContent = message;
            error.hidden = false;
        }

        // Done: the finished screen replaces the progress, Close (which
        // reloads the campaign list) replaces Stop & Close and takes focus.
        // The finish names how many e-mails could not be delivered; the
        // success text is kept only when every recipient got the message.
        function finish(answer) {
            var heading = document.getElementById(\'campaign_sent_heading\');
            var alert = document.getElementById(\'campaign_sent_alert\');
            if (answer.message) {
                heading.textContent = answer.heading + \'.\';
                alert.querySelector(\'p\').textContent = answer.message;
            }
            if (answer.all_sent === false) {
                alert.classList.remove(\'theme-success\');
                alert.classList.add(\'theme-warning\');
            }
            if (answer.problem) {
                var problem = document.getElementById(\'campaign_sent_problem\');
                problem.textContent = answer.problem;
                problem.hidden = false;
            }
            if (answer.smtp_url) {
                var smtp = document.getElementById(\'campaign_sent_smtp\');
                smtp.querySelector(\'a\').href = answer.smtp_url;
                smtp.hidden = false;
            }
            document.getElementById(\'campaign_sending\').hidden = true;
            document.getElementById(\'campaign_sent\').hidden = false;
            document.getElementById(\'campaign_send_header_close\').onclick = close.onclick;
            stop.hidden = true;
            close.hidden = false;
            status.textContent = heading.textContent + \' \' + alert.querySelector(\'p\').textContent;
            close.focus();
        }

        function next() {
            fetch(url, {credentials: \'same-origin\', cache: \'no-store\', headers: {\'Accept\': \'application/json\'}})
                .then(function (response) { return response.json(); })
                .then(function (answer) {
                    if (!answer || answer.error) {
                        fail((answer && answer.error) ? answer.error : failed);
                        return;
                    }
                    show(answer);
                    if (answer.done) {
                        finish(answer);
                    } else {
                        setTimeout(next, 1000);
                    }
                })
                .catch(function () { fail(failed); });
        }

        setTimeout(next, 1000);
    })();
    </script>' . output_footer_secure();
    
// else there is not at least one e-mail recipient left, so e-mail campaign is complete
} else {
    // get total number of recipients
    $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_email_recipients = $row[0];

    $number_of_delivered_email_recipients = send_email_campaign_delivered_count($id, $retry_ready);
    $number_of_failed_email_recipients = $retry_ready ? (int) db_value("SELECT COUNT(*) FROM email_recipients WHERE (email_campaign_id = '" . $id . "') AND (failed = '1')") : 0;
    $number_of_suppressed_email_recipients = $retry_ready ? (int) db_value("SELECT COUNT(*) FROM email_recipients WHERE (email_campaign_id = '" . $id . "') AND (failed = '1') AND (attempts = 0)") : 0;
    $query = "SELECT COUNT(*) FROM email_recipients WHERE (email_campaign_id = ?) AND (complete = '0')";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_waiting_email_recipients = (int) $row[0];

    // The stored reason of the deliveries that failed, and when a waiting
    // recipient may be tried again: without the job only another Send now
    // retries them, so the text names that instead of an automatic retry.
    $delivery_problem = pg_email_campaign_delivery_problem($id);
    $next_attempt_text = ($delivery_problem['next_attempt_at'] > time()) ? date('H:i', $delivery_problem['next_attempt_at']) : '';

    $summary = pg_email_campaign_send_summary($number_of_email_recipients, $number_of_delivered_email_recipients, $number_of_failed_email_recipients, $number_of_waiting_email_recipients, $number_of_suppressed_email_recipients, email_campaign_job_enabled(), $next_attempt_text);
    $problem_line = $summary['all_sent'] ? '' : pg_email_campaign_problem_line($delivery_problem['error'], $delivery_problem['count']);

    // SMTP Settings is where a refused delivery is usually fixed; it opens
    // for a manager and above.
    $smtp_link = (($problem_line !== '') && ($user['role'] <= 2)) ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/smtp_settings.php' : '';

    // A campaign that was already complete was finished by an earlier run:
    // it is not finished, logged or followed up again.
    if ($already_complete) {
        $summary['complete'] = false;
    }

    // Recipients waiting for another attempt keep the campaign open: the
    // job, or the next Send now, tries them again.
    $became_complete = false;

    if ($summary['complete']) {
        $query = "UPDATE email_campaigns SET status = 'complete' WHERE id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
        $became_complete = (mysqli_affected_rows(db::$con) === 1);

        // Announced once, by the run that finished it.
        if ($became_complete) {
            pg_announce('campaign.sent', pg_campaign_sent_event('email', $id, (string) db_value("SELECT type FROM email_campaigns WHERE id = '" . (int) $id . "'")));
        }
    }

    $log_page = '';

    // if HTML was selected for the format, then add page info to log message
    if ($format == 'html') {
        $log_page = ', ' . lang('page') . ': ' . get_page_name($page_id);
    }
    
    // log end of sending e-mails
    if ($already_complete) {
        // Nothing was sent in this run.
    } elseif ($summary['all_sent']) {
        log_activity(lang(array('string'=>'e-mail campaign (subject: {var:1}) was sent to {var:2} recipients','vars'=>array($subject_template . $log_page, pg_format_number($number_of_email_recipients, 0)))), $_SESSION['sessionusername']);
    } else {
        log_activity(lang(array('string'=>'e-mail campaign (subject: {var:1}) was delivered to {var:2} of {var:3} recipients; {var:4} could not be delivered','vars'=>array($subject_template . $log_page, pg_format_number($number_of_delivered_email_recipients, 0), pg_format_number($number_of_email_recipients, 0), pg_format_number($number_of_failed_email_recipients + $number_of_waiting_email_recipients, 0)))), $_SESSION['sessionusername']);
    }

    // If this is an automatic campaign, then check if auto campaigns need to
    // be created: only by the run that finished it, so a job run that
    // finished it at the same time does not see the follow-ups queued twice.
    if (($type == 'automatic') && $became_complete) {
        // Get contact id from the campaign that was just sent so it can be used for new auto campaigns if necessary.
        $contact_id = db_value("SELECT contact_id FROM email_recipients WHERE email_campaign_id = '" . escape($_GET['id']) . "'");

        // Check if there are auto e-mail campaigns that should be created based on this auto e-mail campaign being sent.
        create_auto_email_campaigns(array(
            'action' => 'email_campaign_sent',
            'action_item_id' => $email_campaign_profile_id,
            'contact_id' => $contact_id));
    }

    if ($send_json) {
        send_email_campaign_json(array(
            'sent' => (int) $number_of_delivered_email_recipients,
            'total' => (int) $number_of_email_recipients,
            'failed' => (int) ($number_of_failed_email_recipients + $number_of_waiting_email_recipients),
            'done' => true,
            'all_sent' => $summary['all_sent'],
            'heading' => $summary['heading'],
            'message' => $summary['message'],
            'problem' => $problem_line,
            'smtp_url' => $smtp_link,
            'percent' => $summary['percent'],
            'percent_text' => lang(array('string' => '{var:1}%', 'vars' => array($summary['percent']))),
            'count' => pg_format_number($number_of_delivered_email_recipients, 0) . lang(' of ') . pg_format_number($number_of_email_recipients, 0)));
    }

    echo
    output_header_secure(array('title'=>lang('Send E-mail Campaign'),'icon'=>'campaign')) . '
    <nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
        <div class="container-fluid">
            <span class="navbar-text me-auto">' . lang('Send E-mail Campaign') . '</span>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="pgSendClose(true)" aria-label="' . h(lang('Close')) . '">
                        <span class="bi bi-x-lg"></span>
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <main id="content" class="container-fluid">
        <!-- Edit screen layout of product_builder.php: the result in the form column, the campaign in the sticky status column, Close in the sticky save bar. -->
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h($summary['heading']) . '</h2>
                    <div class="card-body pt-0">
                        <div class="alert ' . ($summary['all_sent'] ? 'theme-success' : 'theme-warning') . ' mb-0"><p>' . h($summary['message']) . '</p>' . send_email_campaign_problem_html($problem_line, $smtp_link) . '</div>
                    </div>
                </div>
            </div>
            ' . send_email_campaign_information_html($subject_template, $from_name, $from_email_address, $reply_email_address) . '
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0">
                            <button type="button" name="submit_save" value="Close" onclick="pgSendClose(true)" class="btn mb-1 mt-1  btn-solid theme-secondary submit-primary" ><span class="bi bi-x-lg me-3" aria-hidden="true"></span>' . lang(array('string'=>'Close') ) . '</button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </main>' . send_email_campaign_close_script($id) . output_footer_secure();
}

// Refusal inside the window's own frame (panel styles, theme, Close button),
// not a bare page: the window reloads itself while sending, so a cancel made
// meanwhile lands here.
function output_error_in_popup($error_message)
{
    global $send_json;

    if ($send_json) {
        send_email_campaign_json(array('error' => $error_message), 403);
    }

    echo
    output_header_secure(array('title'=>lang('Send E-mail Campaign'),'icon'=>'campaign')) . '
    <main id="content" class="container-fluid">
        <div class="row">
            <div class="col-12 mt-5">
                <div class="alert theme-danger" role="alert"><p>' . lang('Error') . ': ' . $error_message . '</p></div>
            </div>
        </div>
        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
            <div class="container">
                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0">
                    <button type="button" name="submit_save" value="Close" onclick="pgSendClose(true)" class="btn mb-1 mt-1  btn-solid theme-secondary submit-primary" ><span class="bi bi-x-lg me-3" aria-hidden="true"></span>' . lang(array('string'=>'Close') ) . '</button>
                </div>
            </div>
        </nav>
    </main>' . send_email_campaign_close_script(0) . output_footer_secure();
    exit();
}

// The campaign the window sends, in the status column: subject, sender and
// reply address, read-only.
function send_email_campaign_information_html($subject, $from_name, $from_email_address, $reply_email_address)
{
    $rows = array(
        array(lang('Subject'), $subject),
        array(lang('From Name'), $from_name),
        array(lang('From E-mail Address'), $from_email_address),
        array(lang('Reply to E-mail Address'), $reply_email_address),
    );
    $html = '';
    foreach ($rows as $i => $row) {
        $html .= '
                            <div class="d-flex justify-content-between gap-3 py-2' . ($i < count($rows) - 1 ? ' border-bottom' : '') . '">
                                <dt class="fw-normal">' . h($row[0]) . '</dt>
                                <dd class="mb-0 fw-bold text-end text-break">' . h((string) $row[1]) . '</dd>
                            </div>';
    }

    return '<div class="col-12 lg:col-4 2xl:col-3 order-2">
                <!-- 3.3rem: the fixed header of the window covers its top. -->
                <div class="position-sticky" style="top:3.3rem;">
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Information') . '</h2>
                        <div class="card-body pt-0">
                            <dl class="mb-0">' . $html . '
                            </dl>
                        </div>
                    </div>
                </div>
            </div>';
}

// The window's Close and Stop & Close. Opened as a pop-up it reloads the
// screen that opened it (on Close) and closes. Opened as a tab (middle-click,
// a pop-up blocker) it has no opener and a script may not close it, so it
// goes to the campaign, or to My Campaigns when there is none, instead of
// throwing on window.opener or staying on a dead page.
function send_email_campaign_close_script($id)
{
    $fallback = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . (((int) $id > 0) ? '/edit_email_campaign.php?id=' . (int) $id : '/view_email_campaigns.php');

    return '<script>
    function pgSendClose(reload) {
        if (window.opener && !window.opener.closed) {
            if (reload) {
                try { window.opener.location.reload(); } catch (e) {}
            }
            window.close();
            return;
        }
        window.location.href = ' . json_encode($fallback, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
    }
    </script>';
}

// Recipients that accepted the message: complete and, where the retry
// columns exist, not given up.
function send_email_campaign_delivered_count($id, $retry_ready)
{
    return (int) db_value(
        "SELECT COUNT(*)
        FROM email_recipients
        WHERE
            (email_campaign_id = '" . (int) $id . "')
            AND (complete = '1')" . ($retry_ready ? "
            AND (failed = '0')" : ""));
}

// One answer of the JSON mode, always with its four keys, and the end of the
// request.
function send_email_campaign_json($answer, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo pg_json_encode_answer(array_merge(array('sent' => 0, 'total' => 0, 'done' => false, 'error' => ''), $answer));
    exit();
}

// The delivery problem under the finish message: the most frequent stored
// error and, for a manager, a link to SMTP Settings.
function send_email_campaign_problem_html($problem_line, $smtp_link)
{
    $html = '';

    if ($problem_line !== '') {
        $html .= '<p class="mb-0">' . h($problem_line) . '</p>';
    }

    if ($smtp_link !== '') {
        $html .= '<p class="mb-0"><a href="' . h($smtp_link) . '" target="_blank" rel="noopener">' . lang('SMTP Settings') . '</a></p>';
    }

    return $html;
}
