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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_email_access($user);

validate_token_field();

$email_campaign_profile = db_item(
    "SELECT
        name,
        enabled,
        action,
        action_item_id,
        subject,
        format,
        body,
        page_id,
        from_name,
        from_email_address,
        reply_email_address,
        bcc_email_address,
        schedule_time,
        schedule_length,
        schedule_unit,
        schedule_period,
        schedule_base,
        purpose
    FROM email_campaign_profiles
    WHERE id = '" . e($_GET['id']) . "'");

$original_name = $email_campaign_profile['name'];

$email_campaign_profile['name'] = get_unique_name(array(
    'name' => $email_campaign_profile['name'],
    'type' => 'email_campaign_profile'));

db(
    "INSERT INTO email_campaign_profiles (
        name,
        enabled,
        action,
        action_item_id,
        subject,
        format,
        body,
        page_id,
        from_name,
        from_email_address,
        reply_email_address,
        bcc_email_address,
        schedule_time,
        schedule_length,
        schedule_unit,
        schedule_period,
        schedule_base,
        purpose,
        created_user_id,
        created_timestamp,
        last_modified_user_id,
        last_modified_timestamp)
    VALUES (
        '" . e($email_campaign_profile['name']) . "',
        '" . e($email_campaign_profile['enabled']) . "',
        '" . e($email_campaign_profile['action']) . "',
        '" . e($email_campaign_profile['action_item_id']) . "',
        '" . e($email_campaign_profile['subject']) . "',
        '" . e($email_campaign_profile['format']) . "',
        '" . e($email_campaign_profile['body']) . "',
        '" . e($email_campaign_profile['page_id']) . "',
        '" . e($email_campaign_profile['from_name']) . "',
        '" . e($email_campaign_profile['from_email_address']) . "',
        '" . e($email_campaign_profile['reply_email_address']) . "',
        '" . e($email_campaign_profile['bcc_email_address']) . "',
        '" . e($email_campaign_profile['schedule_time']) . "',
        '" . e($email_campaign_profile['schedule_length']) . "',
        '" . e($email_campaign_profile['schedule_unit']) . "',
        '" . e($email_campaign_profile['schedule_period']) . "',
        '" . e($email_campaign_profile['schedule_base']) . "',
        '" . e($email_campaign_profile['purpose']) . "',
        '" . USER_ID . "',
        UNIX_TIMESTAMP(),
        '" . USER_ID . "',
        UNIX_TIMESTAMP())");

$new_id = mysqli_insert_id(db::$con);

log_activity(lang(array('string'=>'{var:1} ({var:2}) was duplicated','vars'=>array(lang('campaign profile'), $original_name) )), $_SESSION['sessionusername']);

include_once('liveform.class.php');
$liveform = new liveform('edit_email_campaign_profile');
$liveform->add_notice(lang(array('string'=>'The {var:1} has been duplicated, and you are now editing the duplicate.','vars'=>lang('campaign profile') )) );

go(PATH . SOFTWARE_DIRECTORY . '/edit_email_campaign_profile.php?id=' . $new_id);