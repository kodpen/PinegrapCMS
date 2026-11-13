<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the flow box that adds a contact to a contact group or takes
 * one out (kind contact_group): the contact of the run's record, or one a
 * value names, and a group filled by hand that the flow's owner reaches.
 * Adding writes the membership only: the contact's marketing consent and
 * the group's opt-in choice stay as they are. A join it makes is announced
 * as every other door's (pg_announce_contact_group_members()), naming its
 * flow, so a flow started by contact_group.joined does not start again from
 * its own add (ws_events_own_group_add()).
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
 * What the box does to the group: add the contact or take it out.
 *
 * @return string[]
 */
function ws_step_contact_group_ways()
{
    return array('add', 'remove');
}

/**
 * The box's settings, as they are kept: group_id, way (add or remove) and
 * contact (the contact's id, one value such as {{trigger.contact_id}}, or
 * '' for the person of the run's record). Pure.
 *
 * @param mixed $config
 * @return array
 */
function ws_step_contact_group_normalize($config)
{
    $config = is_array($config) ? $config : array();
    $group = $config['group_id'] ?? 0;
    $way = (string) ($config['way'] ?? '');
    $contact = $config['contact'] ?? '';

    return array(
        'group_id' => (is_bool($group) || !is_numeric($group)) ? 0 : max(0, (int) $group),
        'way'      => in_array($way, ws_step_contact_group_ways(), true) ? $way : 'add',
        'contact'  => ws_flow_line(is_int($contact) ? (string) $contact : (is_string($contact) ? $contact : ''), 200),
    );
}

/**
 * What the canvas and the publish say is missing. Pure but for lang().
 *
 * @param array  $config ws_step_contact_group_normalize()
 * @param string $name   the box's name
 * @return string[]
 */
function ws_step_contact_group_problems($config, $name)
{
    $config = ws_step_contact_group_normalize($config);
    $problems = array();

    if ((int) $config['group_id'] <= 0) {
        $problems[] = lang(array('string' => 'The box {var:1} needs the contact group.', 'vars' => $name));
    }

    if ((trim((string) $config['contact']) !== '') && !ws_step_row_names_row($config['contact'])) {
        $problems[] = lang(array('string' => 'The box {var:1} names its contact by its id or by one value, such as {{trigger.contact_id}}.', 'vars' => $name));
    }

    return $problems;
}

/**
 * The groups filled by hand somebody may put contacts into or take them
 * out of, by name: every one from the manager role up, below it the groups
 * given to their account, as on the contact screens. None without the
 * contacts right.
 *
 * @param array $viewer
 * @return array[] id, name
 */
function ws_step_contact_group_choices($viewer)
{
    if (!is_array($viewer) || !ws_change_allowed($viewer, 'contact')) {
        return array();
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/audience_doors.php');

    $mine = ((int) $viewer['role'] >= 3)
        ? " AND id IN (SELECT contact_group_id FROM users_contact_groups_xref WHERE user_id = '" . (int) $viewer['id'] . "')"
        : '';
    $out = array();

    foreach ((array) db_items("SELECT id, name FROM contact_groups WHERE " . pg_audience_hand_sql() . $mine . " ORDER BY name LIMIT 300") as $row) {
        $out[] = array('id' => (int) $row['id'], 'name' => (string) $row['name']);
    }

    return $out;
}

/**
 * Checks the box with somebody's rights, the owner as they publish and
 * again each time it runs: the contacts right, a group filled by hand that
 * they reach, and a contact named by its id, by one value or left to the
 * run's record.
 *
 * @param array $viewer the flow's owner
 * @param array $config ws_step_contact_group_normalize()
 * @return array ok, error, paused, action (type, group_id, group_name, way, contact)
 */
function ws_step_contact_group_check($viewer, $config)
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'paused' => false, 'action' => null);
    };

    if (!is_array($viewer) || !ws_change_allowed($viewer, 'contact')) {
        return $refuse(lang('The flow\'s owner may not change contacts.'));
    }

    $name = null;

    foreach (ws_step_contact_group_choices($viewer) as $group) {
        if ($group['id'] === (int) $config['group_id']) {
            $name = $group['name'];
        }
    }

    if ($name === null) {
        return $refuse(lang('Choose a contact group filled by hand that you may change.'));
    }

    $contact = (string) $config['contact'];

    if ((trim($contact) !== '') && !ws_step_row_names_row($contact)) {
        return $refuse(lang('Name the contact by its id or by one value, such as {{trigger.contact_id}}.'));
    }

    return array('ok' => true, 'error' => '', 'paused' => false, 'action' => array(
        'type'       => 'contact_group',
        'group_id'   => (int) $config['group_id'],
        'group_name' => $name,
        'way'        => (string) $config['way'],
        'contact'    => $contact,
    ));
}

/**
 * The contact of a run's record the flow's owner reaches: the run's
 * contact, an order's or a submitted form's contact, the contact of a
 * received e-mail's conversation. 0 for none or one out of reach.
 *
 * @param array $owner
 * @param array $run ws_flow_runs row
 * @return int
 */
function ws_step_contact_group_run_contact($owner, $run)
{
    list($kind, $id) = ws_flow_mail_person_source((string) ($run['subject_type'] ?? ''), (int) ($run['subject_id'] ?? 0), ws_flow_run_context($run));

    switch ($kind) {
        case 'contact':
            return (int) $id;

        case 'order':
        case 'form':
            if (!ws_step_record_get_read($owner, $kind, $id)) {
                return 0;
            }

            return (int) db_value("SELECT contact_id FROM " . (($kind === 'order') ? 'orders' : 'forms') . " WHERE id = '" . (int) $id . "'");

        case 'mail':
            $row = db_item("SELECT contact_id, channel_id FROM ws_mail_conversations WHERE id = '" . (int) $id . "'");
            $channel = is_array($row) ? ws_channel((int) $row['channel_id']) : null;

            return (is_array($channel) && ws_can_read_channel(ws_rights_for_id((int) $owner['id']), $channel)) ? (int) $row['contact_id'] : 0;
    }

    return 0;
}

/**
 * The membership rows a change writes: the contact put in when it is not
 * in the group yet, taken out when it is; nothing else. Pure.
 *
 * @param string $way    add | remove
 * @param int    $contact_id
 * @param bool   $member the contact is in the group now
 * @return array add (int[]), remove (int[])
 */
function ws_step_contact_group_change($way, $contact_id, $member)
{
    $contact_id = (int) $contact_id;
    $none = array('add' => array(), 'remove' => array());

    if ($contact_id <= 0) {
        return $none;
    }

    if ($way === 'remove') {
        return $member ? array('add' => array(), 'remove' => array($contact_id)) : $none;
    }

    return $member ? $none : array('add' => array($contact_id), 'remove' => array());
}

/**
 * Adds the contact to the group or takes it out, with the flow owner's
 * rights checked (ws_step_contact_group_check()). The contact text is
 * filled in already; '' is the person of the run's record. A contact that
 * is not there or out of reach fails the box; one already as asked leaves
 * it done with changed 0. The group's opt-in choice and the contact's
 * consent are never written.
 *
 * @param array $owner  the flow's owner, checked
 * @param array $action ws_step_contact_group_check(), filled
 * @param array $run    ws_flow_runs row
 * @return array ok, text, data (contact_id, changed)
 */
function ws_step_contact_group_do($owner, $action, $run)
{
    $group_id = (int) $action['group_id'];
    $label = (string) $action['group_name'];
    // A value filled in as JSON text keeps its quotes.
    $text = trim((string) $action['contact'], " \"");
    $contact_id = ($text === '') ? ws_step_contact_group_run_contact($owner, $run) : ws_step_row_id($text);

    if (($contact_id <= 0) || !ws_step_record_get_read($owner, 'contact', $contact_id)) {
        return array('ok' => false, 'text' => ($text === '')
            ? lang('The run is about no contact the flow\'s owner reaches.')
            : lang('No contact the flow\'s owner reaches was found.'));
    }

    // Read again as it runs: a group switched to rules since is not changed by hand.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/audience_doors.php');

    if (pg_audience_group_is_dynamic($group_id)) {
        return array('ok' => false, 'text' => pg_audience_door_message());
    }

    $member = (bool) db_value("SELECT contact_id FROM contacts_contact_groups_xref
        WHERE (contact_id = '" . $contact_id . "') AND (contact_group_id = '" . $group_id . "') LIMIT 1");
    $change = ws_step_contact_group_change((string) $action['way'], $contact_id, $member);

    if ($change['add']) {
        db("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id) VALUES ('" . $contact_id . "', '" . $group_id . "')");

        if (mysqli_affected_rows(db::$con) !== 1) {
            return array('ok' => false, 'text' => lang('The contact could not be added to the group.'));
        }
    } elseif ($change['remove']) {
        db("DELETE FROM contacts_contact_groups_xref WHERE (contact_id = '" . $contact_id . "') AND (contact_group_id = '" . $group_id . "')");
    }

    $changed = ($change['add'] || $change['remove']) ? 1 : 0;

    if ($changed) {
        pg_announce_contact_group_members($group_id, $change['add'], $change['remove'], $label, (int) ($run['flow_id'] ?? 0));
    }

    $data = array('contact_id' => $contact_id, 'changed' => $changed);

    if ((string) $action['way'] === 'remove') {
        return array('ok' => true, 'data' => $data, 'text' => $changed
            ? lang(array('string' => 'Took the contact {var:1} out of the contact group {var:2}.', 'vars' => array($contact_id, $label)))
            : lang(array('string' => 'The contact {var:1} was not in the contact group {var:2}.', 'vars' => array($contact_id, $label))));
    }

    return array('ok' => true, 'data' => $data, 'text' => $changed
        ? lang(array('string' => 'Added the contact {var:1} to the contact group {var:2}.', 'vars' => array($contact_id, $label)))
        : lang(array('string' => 'The contact {var:1} was in the contact group {var:2} already.', 'vars' => array($contact_id, $label))));
}

/**
 * What the box does, in words. Pure but for lang().
 *
 * @param array $action ws_step_contact_group_check()
 * @return string
 */
function ws_step_contact_group_text($action)
{
    $group = (string) ($action['group_name'] ?? '');
    $run = (trim((string) ($action['contact'] ?? '')) === '');

    if ((string) ($action['way'] ?? '') === 'remove') {
        return $run
            ? lang(array('string' => 'Takes the contact of the run\'s record out of the contact group {var:1}.', 'vars' => $group))
            : lang(array('string' => 'Takes a contact out of the contact group {var:1}.', 'vars' => $group));
    }

    return $run
        ? lang(array('string' => 'Adds the contact of the run\'s record to the contact group {var:1}.', 'vars' => $group))
        : lang(array('string' => 'Adds a contact to the contact group {var:1}.', 'vars' => $group));
}
