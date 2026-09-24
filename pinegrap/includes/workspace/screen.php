<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what the screens hand to assets/js/workspace.js: the settings
 * block, every text the script shows (translated here, so the script holds
 * none), and the Workspace button the order, contact and product screens
 * carry.
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
 * A text with numbered holes the script fills: {1}, {2}.
 *
 * @param string $string the English text with {var:N}
 * @param int    $holes
 * @return string
 */
function ws_js_template($string, $holes)
{
    $vars = array();

    for ($i = 1; $i <= $holes; $i++) {
        $vars[] = '{' . $i . '}';
    }

    return lang(array('string' => $string, 'vars' => (count($vars) === 1) ? $vars[0] : $vars));
}

/**
 * Every text the workspace script shows.
 *
 * @return array key => text
 */
function ws_js_strings()
{
    return array(
        'about_records'          => lang('Tagged records'),
        'add_here'               => lang('Add here'),
        'add_note'               => lang('Add a note'),
        'add_people'             => lang('Add people'),
        'add_plan_item'          => lang('Add to the plan'),
        'add_reaction'           => lang('Add a reaction'),
        'all_day'                => lang('All day'),
        'all_departments'        => lang('All departments'),
        'all_items_done'         => lang('Every item is done.'),
        'all_tasks'              => lang('All tasks'),
        'archive'                => lang('Archive the channel'),
        'archived_channels'      => lang('Archived channels'),
        'archived_readonly'      => lang('This channel is archived. It can be read but not written in.'),
        'attach'                 => lang('Attach a file'),
        'audit_bar'              => lang('You are reading this private channel for inspection. The members were told; you cannot write in it.'),
        'audit_confirm'          => ws_js_template('Open #{var:1} for inspection? A line saying you opened it is written into the channel and the log.', 1),
        'audit_help'             => lang('Private channels you are not a member of. Opening one leaves a line in the channel and in the log, so the members know.'),
        'audit_readonly'         => lang('Opened for inspection: reading only.'),
        'audit_row'              => ws_js_template('Owner: {var:1} · {var:2} members', 2),
        'away'                   => lang('Away'),
        'board_empty'            => lang('Nobody to show. Add people to the team in the user settings.'),
        'cancel'                 => lang('Cancel'),
        'cannot_post'            => lang('You cannot write in this channel.'),
        'cell_title'             => ws_js_template('{var:1} h planned of {var:2} h', 2),
        'channel_created'        => lang('The channel was created.'),
        'channel_gone'           => lang('This channel is no longer open to you.'),
        'channel_kind'           => lang('Who can see it'),
        'channel_list'           => ws_js_template('The list in #{var:1}, by {var:2}', 2),
        'channel_name'           => lang('Channel name'),
        'channel_name_help'      => lang('Short and plain: the customer, the project or the subject.'),
        'channel_saved'          => lang('The channel was saved.'),
        'channel_topic'          => lang('What it is about'),
        'channels'               => lang('Channels'),
        'close'                  => lang('Close'),
        'closed_tasks'           => lang('Finished tasks'),
        'commands'               => lang('Slash commands'),
        'completed_on'           => ws_js_template('Finished {var:1}', 1),
        'composer_hint'          => lang('@ someone · # a record · / a command · Shift+Enter for a new line'),
        'composer_placeholder'   => ws_js_template('Write to #{var:1}', 1),
        'conflicts'              => lang('Clashes'),
        'conflicts_help'         => lang('People over their day, and work falling on somebody\'s leave, in the next two weeks.'),
        'conversations'          => lang('Conversations'),
        'create_anyway'          => lang('Create it anyway'),
        'create_channel'         => lang('Create the channel'),
        'create_task'            => lang('Create the task'),
        'created_by'             => ws_js_template('Created by {var:1}, {var:2}', 2),
        'customer'               => lang('Customer'),
        'customer_channel'       => lang('Customer channel'),
        'customer_channels'      => lang('Customer channels'),
        'customer_help'          => lang('A channel about a customer shows on the customer\'s card in the address book.'),
        'day_capacity'           => ws_js_template('{var:1} h a day', 1),
        'decision'               => lang('Decision'),
        'delete'                 => lang('Delete'),
        'delete_confirm'         => lang('Delete this message? The people in the channel will see that a message was deleted.'),
        'delete_item_confirm'    => lang('Remove this item from the plan?'),
        'delete_note_confirm'    => lang('Delete this note?'),
        'department'             => lang('Department'),
        'description'            => lang('Description'),
        'description_help'       => lang('Lines that start with - [ ] become the task\'s checklist.'),
        'description_help_rich'  => lang('A checklist in the description (the list button, or lines that start with - [ ]) becomes the task\'s checklist.'),
        'add_item'               => lang('Add an item'),
        'add_item_placeholder'   => lang('Add an item to the list…'),
        'save_description_first' => lang('Save the change to the description first: the new item goes into it.'),
        'due_date'               => lang('Due'),
        'earlier_message'        => lang('An earlier message'),
        'edit'                   => lang('Edit'),
        'edit_channel'           => lang('Channel settings'),
        'edit_plan_item'         => lang('Plan item'),
        'edit_summary'           => lang('Edit the summary'),
        'edited'                 => lang('(edited)'),
        'editing_message'        => lang('Editing your message. Esc to stop.'),
        'emoji_activity'         => lang('Celebration and activities'),
        'emoji_food'             => lang('Food and drink'),
        'emoji_nature'           => lang('Nature'),
        'emoji_objects'          => lang('Objects'),
        'emoji_people'           => lang('People and hands'),
        'emoji_recent'           => lang('Recently used'),
        'emoji_smileys'          => lang('Smileys'),
        'emoji_symbols'          => lang('Symbols'),
        'emoji_travel'           => lang('Travel and places'),
        'emoji_search'           => lang('Search emoji'),
        'emoji_found'            => lang('Found'),
        'emoji_none'             => lang('No emoji matches that.'),
        'reaction_take_back'     => lang('Take back my reaction'),
        'end_date'               => lang('Last day'),
        'end_time'               => lang('Ends'),
        'error_generic'          => lang('Something went wrong. Please try again.'),
        'estimate'               => lang('Estimate'),
        'estimate_help'          => ws_js_template('Left empty, {var:1} is counted.', 1),
        'estimate_hours'         => lang('Estimate (hours)'),
        'event_title_help'       => lang('Left empty, the kind is used as the title.'),
        'file_too_large'         => ws_js_template('The file is larger than {var:1} MB.', 1),
        'go_to_message'          => lang('Go to the message'),
        'holiday'                => lang('Holiday'),
        'inbox'                  => lang('Inbox'),
        'inbox_empty'            => lang('Nothing waiting for you.'),
        'insert'                 => lang('Insert a table, calculation, checklist or poll'),
        'insert_calc'            => lang('Calculation'),
        'insert_checklist'       => lang('Checklist'),
        'insert_emoji'           => lang('Insert an emoji'),
        'insert_table'           => lang('Table'),
        'invite_people'          => lang('Invite people'),
        'join'                   => lang('Join'),
        'kind'                   => lang('Kind'),
        'kind_private'           => lang('Private'),
        'kind_private_help'      => lang('Only the people invited see it. The owner can open it to everyone later.'),
        'kind_public'            => lang('Everyone in the team'),
        'kind_public_help'       => lang('Everyone in the team can find it, read it and join it.'),
        'leave_blocks'           => lang('Somebody on this task is away then. Only a lead or somebody with the team board can hand it over anyway.'),
        'leave_channel'          => lang('Leave the channel'),
        'leave_confirm'          => lang('Leave this channel? You can join it again at any time.'),
        'leave_private_confirm'  => lang('Leave this private channel? Only a member can bring you back.'),
        'legend_away'            => lang('On leave or a holiday'),
        'legend_full'            => lang('Nearly full'),
        'legend_ok'              => lang('Has room'),
        'legend_over'            => lang('Over the day'),
        'lighter_people'         => lang('Lighter that week:'),
        'list_hidden'            => lang('This list is in a channel you cannot open; it still counts towards the progress.'),
        'list_link_info'         => ws_js_template('The checklist in the channel becomes this task\'s list ({var:1} items). Ticked in the channel or here, the progress of the task follows.', 1),
        'list_task_title'        => lang('Checklist'),
        'list_to_task'           => lang('Make the list a task'),
        'loading'                => lang('Loading…'),
        'make_public'            => lang('Open it to everyone'),
        'make_public_confirm'    => lang('Open this channel to the whole team? Everything written in it so far becomes readable by everyone, and it cannot be made private again.'),
        'mark_all_read'          => lang('Mark everything read'),
        'mark_decision'          => lang('Mark as a decision'),
        'mark_done'              => lang('Mark done'),
        'mark_note'              => lang('Keep as a note'),
        'mention_not_here'       => ws_js_template('Not in this channel: {var:1}', 1),
        'mention_invite'         => lang('Invite to the channel'),
        'mention_invite_help'    => lang('They are added to the channel and the mention reaches them.'),
        'mention_invited'        => ws_js_template('Added to the channel: {var:1}. The mention reached them.', 1),
        'marked_decision'        => lang('Kept as a decision.'),
        'marked_note'            => lang('Kept as a note.'),
        'members'                => lang('Members'),
        'mention'                => lang('Mention someone'),
        'message_deleted'        => lang('This message was deleted.'),
        'minutes_short'          => ws_js_template('{var:1} min', 1),
        'more'                   => lang('More'),
        'move_anyway'            => lang('Move it anyway'),
        'move_despite'           => lang('Moving the task there causes these clashes:'),
        'my_channels'            => lang('My channels'),
        'my_tasks'               => lang('My Tasks'),
        'new_channel'            => lang('New channel'),
        'new_poll'               => lang('New poll'),
        'edit_poll'              => lang('Edit the poll'),
        'new_task'               => lang('New task'),
        'next'                   => lang('Later days'),
        'no_archived'            => lang('No archived channels.'),
        'no_channel'             => lang('No channel'),
        'no_channels'            => lang('No channels yet.'),
        'no_conflicts'           => lang('No clashes in the next two weeks.'),
        'no_customer'            => lang('Not about a customer'),
        'no_decisions'           => lang('No decisions or notes yet. Any message can be kept as one from its menu.'),
        'no_department'          => lang('No department'),
        'no_files'               => lang('No files have been posted here yet.'),
        'no_messages'            => lang('No messages yet. Say what the channel is for.'),
        'no_notes'               => lang('No notes yet.'),
        'no_private_channels'    => lang('No private channels you are not in.'),
        'no_records'             => lang('No records tagged.'),
        'no_tasks'               => lang('No tasks.'),
        'not_discussed'          => lang('Not talked about in any channel you can read.'),
        'not_estimated'          => lang('no estimate'),
        'not_joined'             => lang('You are reading this channel without being a member.'),
        'note'                   => lang('Note'),
        'note_placeholder'       => lang('Write a note… @ mentions someone, # tags a record'),
        'notes'                  => lang('Notes'),
        'notes_count'            => ws_js_template('{var:1} notes', 1),
        'nothing_found'          => lang('Nothing found.'),
        'notify_all'             => lang('Tell me about every message'),
        'notify_mentions'        => lang('Only when I am mentioned'),
        'notify_none'            => lang('Never'),
        'ok'                     => lang('OK'),
        'older_messages'         => lang('Earlier messages'),
        'one_week'               => lang('1 week'),
        'only_me'                => lang('Only me'),
        'open'                   => lang('Go to the channel'),
        'open_for_audit'         => lang('Open for inspection'),
        'open_list_task'         => ws_js_template('Open the task: {var:1}', 1),
        'open_tasks'             => lang('Open tasks'),
        'other_channels'         => lang('Other channels'),
        'owner'                  => lang('Owner'),
        'people'                 => lang('Responsible people'),
        'people_help'            => lang('Everyone chosen is responsible for the task.'),
        'percent'                => ws_js_template('{var:1}%', 1),
        'person'                 => lang('Team member'),
        'pick_channel'           => lang('Choose a channel on the left, or create one.'),
        'plan_saved'             => lang('The plan was saved.'),
        'planning_board'         => lang('Planning Board'),
        'poll_add_option'        => lang('Add an option'),
        'poll_anonymous'         => lang('Anonymous'),
        'poll_anonymous_help'    => lang('Everyone sees the counts; nobody sees who voted for what.'),
        'poll_anonymous_label'   => lang('Anonymous voting'),
        'poll_close'             => lang('Close the poll'),
        'poll_close_confirm'     => lang('Close the poll now? The result is written into the channel.'),
        'poll_closed_on'         => ws_js_template('Closed {var:1}', 1),
        'poll_closes'            => ws_js_template('Closes {var:1}', 1),
        'poll_closes_help'       => lang('Left empty, it stays open until someone closes it.'),
        'poll_closes_label'      => lang('Closes on'),
        'poll_closes_time'       => lang('Closing time'),
        'poll_multiple'          => lang('Multiple choice'),
        'poll_multiple_help'     => lang('Each person may tick more than one option.'),
        'poll_multiple_label'    => lang('Allow more than one choice'),
        'poll_open'              => lang('Open until someone closes it'),
        'poll_option_n'          => ws_js_template('Option {var:1}', 1),
        'poll_options'           => lang('Options'),
        'poll_options_help'      => lang('Between 2 and 10. Enter moves to the next one.'),
        'poll_option_voted'      => lang('Somebody voted for this option, so it stays as it is.'),
        'poll_locked_voted'      => lang('Somebody has voted, so these two no longer change.'),
        'poll_question'          => lang('Question'),
        'poll_result'            => lang('See the result'),
        'poll_result_help'       => lang('When it closes, a clear winner is written into the channel as a decision and lands on the Decisions tab. A tie is left to the team.'),
        'poll_share'             => ws_js_template('{var:1} · {var:2}%', 2),
        'poll_title'             => lang('Poll'),
        'poll_voters'            => ws_js_template('{var:1} people voted', 1),
        'poll_withdraw'          => lang('Take back my vote'),
        'post_card_in'           => lang('Post the card in'),
        'presence_away'          => lang('Away'),
        'presence_offline'       => lang('Offline'),
        'presence_online'        => lang('Online'),
        'previous'               => lang('Earlier days'),
        'priority'               => lang('Task priority'),
        'private_channels_audit' => lang('Private channels'),
        'progress'               => lang('Progress'),
        'progress_text'          => ws_js_template('{var:1} of {var:2} items · {var:3}%', 3),
        'react_with'             => ws_js_template('React with {var:1}', 1),
        'reacted_by'             => ws_js_template('{var:1} reacted with {var:2}', 2),
        'remove'                 => lang('Remove'),
        'reopen'                 => lang('Open it again'),
        'reply'                  => lang('Reply'),
        'replying_to'            => ws_js_template('Replying to {var:1}: {var:2}', 2),
        'save'                   => lang('Save'),
        'save_anyway'            => lang('Save it anyway'),
        'save_despite'           => lang('Saving the task like this causes these clashes:'),
        'scope_all'              => lang('Everyone'),
        'scope_company'          => lang('The whole company'),
        'scope_created'          => lang('Given by me'),
        'scope_department'       => lang('My departments'),
        'scope_dept'             => lang('A department'),
        'scope_mine'             => lang('Mine'),
        'scope_people'           => lang('Chosen people'),
        'search_contacts'        => lang('Search the address book'),
        'search_messages'        => lang('Search messages'),
        'search_records'         => lang('Search to tag a record'),
        'search_results'         => lang('Search results'),
        'search_tasks'           => lang('Search tasks'),
        'searching'              => lang('Searching…'),
        'send'                   => lang('Send'),
        'share'                  => lang('Share'),
        'share_in_channel'       => lang('Share in a channel'),
        'share_placeholder'      => lang('A line about it (optional)'),
        'shared'                 => lang('Shared in the channel.'),
        'show_in_conversation'   => lang('Show in the conversation'),
        'start_date'             => lang('Starts'),
        'start_poll'             => lang('Start a poll'),
        'start_time'             => lang('Starts at'),
        'summary_empty'          => lang('No summary yet. Write down what a newcomer to this channel should know first.'),
        'summary_help'           => lang('The plan, the people to call, the rules. @ and # tags work here too.'),
        'summary_saved'          => lang('The summary was saved.'),
        'tab_decisions'          => lang('Decisions'),
        'tab_files'              => lang('Files'),
        'tab_messages'           => lang('Messages'),
        'tab_summary'            => lang('Summary'),
        'tab_tasks'              => lang('Tasks'),
        'table_column'           => ws_js_template('Column {var:1}', 1),
        'table_size'             => ws_js_template('{var:1} columns × {var:2} rows', 2),
        'tag_record'             => lang('Tag a record'),
        'task'                   => lang('Task'),
        'task_created'           => lang('The task was created.'),
        'task_from_item'         => lang('Make this item a task'),
        'task_from_message'      => lang('Make a task of it'),
        'task_own_list'          => lang('The task\'s own list'),
        'task_preview'           => lang('The task that will be created'),
        'task_saved'             => lang('The task was saved.'),
        'tasks'                  => lang('Tasks'),
        'title'                  => lang('Title'),
        'title_required'         => lang('Give the task a title.'),
        'today'                  => lang('Today'),
        'two_weeks'              => lang('2 weeks'),
        'unarchive'              => lang('Bring back from the archive'),
        'undated'                => ws_js_template('{var:1} undated', 1),
        'unmark'                 => lang('Make it a plain message'),
        'unmarked'               => lang('It is a plain message again.'),
        'uploading'              => lang('Uploading…'),
        'who'                    => lang('Who'),
        'workspace'              => lang('Workspace'),
        'workspace_settings'     => lang('Workspace Settings'),
        'all_types'              => lang('All'),
        'type_to_search'         => lang('Type to search every kind of record, or pick one.'),
        'busiest_channels'       => lang('Busiest channels'),
        'busiest_day'            => ws_js_template('Busiest day: {var:1} ({var:2} tasks, {var:3} messages)', 3),
        'channel_opened_by'      => ws_js_template('#{var:1} opened by {var:2}', 2),
        'chat_unavailable'       => lang('The chat is not available on this screen.'),
        'copied'                 => lang('Copied.'),
        'copy_link'              => lang('Copy the link'),
        'copy_number'            => lang('Copy the task number'),
        'copy_text'              => lang('Copy the text'),
        'direct_message'         => lang('Send a direct message'),
        'direct_message_to'      => ws_js_template('Message {var:1}', 1),
        'documents'              => lang('Documents'),
        'download'               => lang('Download'),
        'drop_here'              => ws_js_template('Drop the files to post them in #{var:1}', 1),
        'drop_rules'             => lang('What the file manager takes: no PHP, .htaccess, web.config, shell scripts or programs.'),
        'everyone'               => lang('Everyone'),
        'fig_channels'           => lang('channels opened'),
        'fig_done'               => lang('done'),
        'fig_messages'           => lang('messages'),
        'fig_open'               => lang('open'),
        'fig_overdue'            => lang('late'),
        'fig_tasks'              => lang('tasks'),
        'file_too_large_named'   => ws_js_template('{var:1} is larger than {var:2} MB.', 2),
        'give_task'              => lang('Give a task'),
        'hand_to'                => ws_js_template('Hand over to {var:1}', 1),
        'images'                 => lang('Pictures'),
        'least_tasks'            => lang('Least work'),
        'mark_leave_day'         => lang('Mark the day as leave'),
        'mark_waiting'           => lang('Mark as waiting'),
        'messages_count'         => ws_js_template('{var:1} messages', 1),
        'month_in_figures'       => lang('The month in figures'),
        'more_count'             => ws_js_template('+{var:1} more', 1),
        'most_tasks'             => lang('Most work'),
        'new_task_about'         => lang('New task about it'),
        'new_task_here'          => lang('New task for this day'),
        'next_month'             => lang('Next month'),
        'open_board_week'        => lang('Open the week on the board'),
        'open_file'              => lang('Open the file'),
        'open_in_new_tab'        => lang('Open in a new tab'),
        'open_channel'           => lang('Open the channel'),
        'open_record'            => lang('Open the record'),
        'open_task'              => lang('Open task'),
        'people_this_month'      => lang('People this month'),
        'person_figures'         => ws_js_template('{var:1} done · {var:2} open · {var:3} late · {var:4}', 4),
        'pin'                    => lang('Pin to the top'),
        'pinned'                 => lang('Pinned'),
        'pinned_done'            => lang('Pinned to the top.'),
        'plan_items'             => lang('Plan items'),
        'postpone_day'           => lang('Put off by a day'),
        'postpone_week'          => lang('Put off by a week'),
        'preview'                => lang('Preview'),
        'previous_month'         => lang('Previous month'),
        'record_workspace'       => lang('Where else it came up'),
        'show_day'               => lang('Show the day'),
        'start_work'             => lang('Start work'),
        'task_count'             => ws_js_template('{var:1} tasks', 1),
        'their_board'            => lang('Their week on the board'),
        'their_calendar'         => lang('Their month'),
        'this_month'             => lang('This month'),
        'unassigned_count'       => ws_js_template('{var:1} tasks have nobody on them.', 1),
        'unpin'                  => lang('Unpin'),
        'unpinned_done'          => lang('Unpinned.'),
        'uploading_count'        => ws_js_template('Uploading {var:1} of {var:2}…', 2),
        'videos'                 => lang('Videos'),
        'view_image'             => lang('View the picture'),
        'who_carries'            => lang('Who carries the work'),
        'work_calendar'          => lang('Work Calendar'),
    );
}

/**
 * The texts of the writing box and its design windows
 * (assets/js/workspace_editor.js).
 *
 * @return array key => text
 */
function ws_editor_js_strings()
{
    return array(
        'add_column'                => lang('Add a column'),
        'add_item'                  => lang('Add an item'),
        'add_row'                   => lang('Add a row'),
        'align_center'              => lang('Align center'),
        'align_left'                => lang('Align left'),
        'align_right'               => lang('Align right'),
        'block_checklist'           => ws_js_template('Checklist · {var:1} items', 1),
        'block_code'                => ws_js_template('Code · {var:1} lines', 1),
        'block_edit'                => lang('Click to edit'),
        'block_remove'              => lang('Remove from the message'),
        'block_table'               => ws_js_template('Table · {var:1} columns, {var:2} rows', 2),
        'checklist_hint'            => lang('Enter starts the next item; Backspace on an empty item removes it.'),
        'code_language'             => lang('Code language'),
        'code_language_placeholder' => lang('e.g. php, sql, js'),
        'code_placeholder'          => lang('Paste or write the code here'),
        'col_left'                  => lang('Insert a column on the left'),
        'col_right'                 => lang('Insert a column on the right'),
        'delete_column'             => lang('Delete the column'),
        'delete_row'                => lang('Delete the row'),
        'designer_checklist'        => lang('Design a checklist'),
        'designer_code'             => lang('Share code'),
        'designer_table'            => lang('Design a table'),
        'fmt_bold'                  => lang('Bold'),
        'fmt_code'                  => lang('Code'),
        'fmt_italic'                => lang('Italic'),
        'fmt_link'                  => lang('Link'),
        'fmt_strike'                => lang('Strikethrough'),
        'insert_code'               => lang('Code block'),
        'insert_to_message'         => lang('Add to the message'),
        'item_done'                 => lang('Mark as done'),
        'item_placeholder'          => lang('What needs doing?'),
        'link_invalid'              => lang('Only http, https and mailto addresses can be linked.'),
        'link_open'                 => lang('Open the link'),
        'link_placeholder'          => lang('Paste or type the address'),
        'link_remove'               => lang('Remove the link'),
        'link_save'                 => lang('Apply the link'),
        'move_down'                 => lang('Move down'),
        'move_up'                   => lang('Move up'),
        'row_above'                 => lang('Insert a row above'),
        'row_below'                 => lang('Insert a row below'),
        'table_hint'                => lang('Write in the cells. Tab moves to the next one; right-click a cell for rows, columns and alignment.'),
        'table_formula_hint'        => lang('A cell that starts with = is worked out: =B2*C2, =SUM(D2:D5) (TOPLA), + - * / and %. The header is row 1.'),
        'table_formula_bar'         => lang('The formula or the value of the cell'),
        'insert_to_note'            => lang('Add to the note'),
        'block_calc'                => ws_js_template('Calculation · {var:1} line(s)', 1),
        'designer_calc'             => lang('Calculation'),
        'calc_placeholder'          => lang('Total = Rent + Dues'),
        'calc_hint'                 => lang('One value a line, as "Name = expression"; a later line may use the names above it. + - * / ^, brackets, % and SUM, AVERAGE, MIN, MAX, ROUND work.'),
        'update_block'              => lang('Update'),
    );
}

/**
 * The Monday the board opens on: this week, or the week of ?from= (a plan
 * item's tag links there).
 *
 * @return string Y-m-d
 */
function ws_screen_board_from()
{
    $asked = (string) ($_GET['from'] ?? '');
    $time = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $asked) ? strtotime($asked . ' 12:00:00') : false;

    if ($time === false) {
        $time = time();
    }

    // The week the day falls in, from its Monday.
    return date('Y-m-d', strtotime('monday this week', $time));
}

/**
 * What the script is told when a screen opens.
 *
 * @param array  $viewer
 * @param string $mode  channels | tasks | board | calendar | timeline | notes | record
 * @param array  $extra merged in (open_channel, ...)
 * @return array
 */
function ws_screen_config($viewer, $mode, $extra = array())
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    // The estimate hint quotes the default the board counts for a task
    // nobody estimated.
    $strings = ws_js_strings();
    $strings['estimate_help'] = str_replace('{1}', ws_minutes_label(WS_DEFAULT_TASK_MINUTES), $strings['estimate_help']);

    // The writing box and its design windows (assets/js/workspace_editor.js).
    $strings = array_merge($strings, ws_editor_js_strings());

    // The repeat field in the task drawer (assets/js/workspace_recurrence.js).
    if (function_exists('ws_recurrence_js_strings')) {
        $strings = array_merge($strings, ws_recurrence_js_strings());
    }

    // Asking Claude in a channel (includes/workspace/claude.php).
    if (function_exists('ws_claude_js_strings')) {
        $strings = array_merge($strings, ws_claude_js_strings());
    }

    // The decision timeline (includes/workspace/timeline.php).
    if (function_exists('ws_timeline_js_strings')) {
        $strings = array_merge($strings, ws_timeline_js_strings());
    }

    // The overview and the sidebar's groups (includes/workspace/home.php).
    if (function_exists('ws_home_js_strings')) {
        $strings = array_merge($strings, ws_home_js_strings());
    }

    // Personal notes (includes/workspace/notes.php) and the windows that edit
    // a file of a conversation (includes/workspace/file_edits.php).
    if (function_exists('ws_notes_js_strings')) {
        $strings = array_merge($strings, ws_notes_js_strings());
    }

    if (function_exists('ws_file_edit_js_strings')) {
        $strings = array_merge($strings, ws_file_edit_js_strings());
    }

    return array_merge(array(
        'mode'         => (string) $mode,
        'api_url'      => $base . 'api.php',
        'token'        => isset($_SESSION['software']['token']) ? (string) $_SESSION['software']['token'] : '',
        'me'           => (int) $viewer['id'],
        'locale'       => (string) lang(array('info' => '')),
        'today'        => date('Y-m-d'),
        'board_from'   => ws_screen_board_from(),
        'event_kinds'  => ws_event_kinds(),
        'poll'         => array('active' => 4),
        'open_channel' => 0,
        'urls'         => array(
            'workspace' => $base . 'workspace.php',
            'tasks'     => $base . 'workspace_tasks.php',
            'board'     => $base . 'workspace_board.php',
            'calendar'  => $base . 'workspace_calendar.php',
            'timeline'  => $base . 'workspace_timeline.php',
            'notes'     => $base . 'workspace_notes.php',
            'file_save' => $base . 'workspace_file_save.php',
            'settings'  => $base . 'workspace_settings.php',
        ),
        'notes'        => function_exists('ws_notes_ready') && ws_notes_ready(),
        'strings'      => $strings,
        'recurrence'   => function_exists('ws_recurrence_js_config') ? ws_recurrence_js_config() : array('ready' => false),
    ), $extra);
}

/**
 * The settings block and the script, once per page.
 *
 * @param array  $viewer
 * @param string $mode
 * @param array  $extra
 * @return string
 */
function ws_screen_assets($viewer, $mode, $extra = array())
{
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    $script = PG_FUNCTIONS_DIR . '/assets/js/workspace.js';
    $json = json_encode(ws_screen_config($viewer, $mode, $extra), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    // The panel's picture viewer, for the pictures in a conversation.
    $lightbox = function_exists('_pg_lightbox_assets_once') ? _pg_lightbox_assets_once() : '';

    // The image editor is large and seldom needed: its files wait in a
    // template on the channel screen and are loaded the first time a picture
    // is edited (assets/js/workspace.js).
    if (($mode === 'channels') && function_exists('get_image_editor_includes')) {
        $lightbox .= '
<template id="ws-image-editor-assets">' . get_image_editor_includes() . '</template>';
    }

    return $lightbox . '
<script type="application/json" id="ws-config">' . $json . '</script>
<script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/workspace_editor.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js')) . '" defer></script>
<script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/workspace_recurrence.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/workspace_recurrence.js')) . '" defer></script>
<script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/workspace.js?v=' . @filemtime($script)) . '" defer></script>';
}

/**
 * Stops a workspace screen for somebody who is not in the team, or when the
 * module is off or not installed yet.
 *
 * @param array $user validate_user()
 * @return array the viewer
 */
function ws_screen_gate($user)
{
    $settings_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('features', 'pgset-features');

    if (!ws_ready()) {
        output_error(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (!ws_enabled()) {
        output_error(lang('The workspace is not switched on.') . ' <a href="' . h($settings_url) . '">' . lang('Settings') . '</a>');
    }

    $viewer = ws_viewer($user);

    if (!$viewer['member']) {
        log_activity(lang('access denied to the workspace'), $_SESSION['sessionusername'] ?? '');
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    return $viewer;
}

/**
 * The Workspace button a record's own screen carries: how often the record was
 * talked about, and a drawer with the conversations, the tasks and a way to
 * share it. Empty for anybody outside the team, and when the module is off.
 *
 * @param array  $user  validate_user()
 * @param string $type  one of ws_record_type_keys()
 * @param int    $id
 * @param string $label how the record is called in the drawer's title
 * @param string $class the button's classes
 * @return string
 */
function ws_record_button($user, $type, $id, $label = '', $class = 'btn btn-sm btn-outline-secondary')
{
    if (((int) $id <= 0) || !ws_ready() || !ws_enabled()) {
        return '';
    }

    $viewer = ws_viewer($user);

    if (!$viewer['member'] || !isset(ws_ref_types($viewer)[$type])) {
        return '';
    }

    $count = ws_record_ref_count($viewer, $type, (int) $id);

    return '<a href="#" class="' . h($class) . '" data-ws-record="' . h($type . ':' . (int) $id) . '" data-ws-label="' . h($label) . '" title="' . h(lang('Conversations and tasks about this record')) . '">'
        . '<i class="bi bi-clipboard2-check me-1" aria-hidden="true"></i>' . h(lang('Workspace'))
        . (($count > 0) ? ' <span class="badge rounded-pill text-bg-secondary ms-1">' . (int) $count . '</span>' : '')
        . '</a>' . ws_screen_assets($viewer, 'record');
}
