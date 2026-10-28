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
        'channels_show'          => lang('Show the channel list'),
        'close'                  => lang('Close'),
        'closed_tasks'           => lang('Finished tasks'),
        'cmd_example'            => ws_js_template('e.g. {var:1}', 1),
        'cmd_extra_estimate'     => lang('How long it takes, like ~2h'),
        'cmd_extra_people'       => lang('Who does it: a person or a department'),
        'cmd_extra_priority'     => lang('How urgent it is, like !urgent'),
        'cmd_keys'               => lang('Tab: write · ↑↓: choose · Esc: close'),
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
        'delete_confirm'         => lang('Delete this message? It goes from the conversation without a trace.'),
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
        'audit_locked'           => lang('A member of staff is in this private channel. It cannot be opened for inspection.'),
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
        'inbox_empty'            => lang('Inbox zero - nothing is waiting for you.'),
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
        'make_private'           => lang('Make it private'),
        'make_private_confirm'   => ws_js_template('Make this channel private? Its {var:1} members keep it; the rest of the team stops seeing it, what was written in it and the notes shared only here. Claude reads a private channel only if it is let in.', 1),
        'make_public'            => lang('Open it to everyone'),
        'make_public_confirm'    => lang('Open this channel to the whole team? Everything written in it so far becomes readable by everyone. Staff can make it private again, but what was read stays read.'),
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
        'no_channels'            => lang('All quiet here - no channels yet.'),
        'no_conflicts'           => lang('No clashes in the next two weeks.'),
        'no_customer'            => lang('Not about a customer'),
        'no_decisions'           => lang('No decisions or notes yet. Any message can be kept as one from its menu.'),
        'no_department'          => lang('No department'),
        'no_files'               => lang('No files have been posted here yet.'),
        'no_messages'            => lang('No messages yet. Say what the channel is for.'),
        'no_notes'               => lang('No notes yet.'),
        'no_private_channels'    => lang('No private channels you are not in.'),
        'no_records'             => lang('No records tagged.'),
        'no_tasks'               => lang('All clear - no tasks here.'),
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
        'preview_keys'           => lang('Enter: create · Esc: cancel'),
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
        'side_collapse'          => lang('Close the section'),
        'side_expand'            => lang('Open the section'),
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
        'task_status_filter'     => lang('Task status'),
        'tab_decisions_count'    => ws_js_template('{var:1} decisions and notes', 1),
        'tab_tasks_count'        => ws_js_template('{var:1} open tasks', 1),
        'tab_summary_written'    => lang('The summary is written'),
        'table_column'           => ws_js_template('Column {var:1}', 1),
        'table_size'             => ws_js_template('{var:1} columns × {var:2} rows', 2),
        'tag_record'             => lang('Tag a record'),
        'task'                   => lang('Task'),
        'task_card_thread_note'  => lang('Posted in the channel it is made from, the card shows in this discussion too.'),
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
        'erp_quote_copy'         => lang('Copy as a new sales quote'),
        'sales_quote_new'        => lang('New sales quote'),
        'copy_all'               => lang('Copy all'),
        'copy_refused'           => lang('The browser did not let the text be copied. Select it and copy it from here.'),
        'copy_select_help'       => lang('Press and hold to select the part you need, then copy it.'),
        'select_text'            => lang('Select the text'),
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
        'task_span'              => ws_js_template('From {var:1} to {var:2}', 2),
        'jump_to_end'            => lang('Go to the newest message'),
        'all_priorities'         => lang('All priorities'),
        'priority_hot'           => lang('Urgent and high'),
        'urgent_soon'            => lang('Pressing, coming up'),
        'urgent_soon_help'       => lang('Urgent and high priority tasks due within a week, the overdue ones included.'),
        'home_tasks_hot'         => ws_js_template('{var:1} pressing', 1),
        'reply_go_to'            => lang('Show the message this answers'),
        'seen_by'                => lang('Who has seen it'),
        'delete_which'           => lang('Delete this message?'),
        'delete_for_me'          => lang('Delete for me'),
        'delete_for_me_help'     => lang('It goes from your view only; the others still see it.'),
        'delete_for_me_confirm'  => lang('Delete this message from your view? The others still see it.'),
        'delete_for_everyone'    => lang('Delete for everyone'),
        'delete_for_everyone_help' => lang('It goes from the conversation for everyone, without a trace.'),
        'seen_by_read'           => lang('Seen'),
        'seen_by_not_yet'        => lang('Not seen yet'),
        'seen_by_none'           => lang('Nobody has seen it yet.'),
        'seen_by_everyone'       => lang('Everyone in the channel has seen it.'),
        'seen_by_help'           => lang('Counted among the members of the channel: seen means the conversation was open on their screen as far as this message.'),
        'edited_at'              => ws_js_template('Edited {var:1}', 1),
        'channel_settings'       => lang('Channel settings'),
        'channel_manage_denied'  => lang('Only the owner of the channel can change it.'),
        'jump_new_count'         => ws_js_template('{var:1} new messages below', 1),
        'their_board'            => lang('Their week on the board'),
        'edit_user'              => lang('Edit the user'),
        'edit_user_title'        => ws_js_template('Open the account of {var:1}', 1),
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
        'brief_recap'            => lang('Sum up what I missed'),
        'brief_recap_wait'       => lang('Pinegrap AI is reading…'),
        'brief_recap_by'         => lang('Summed up by Pinegrap AI'),
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
        'emoji_suggest'             => lang('Matching emoji'),
        'fmt_bold'                  => lang('Bold'),
        'fmt_code'                  => lang('Code'),
        'fmt_italic'                => lang('Italic'),
        'fmt_link'                  => lang('Link'),
        'fmt_strike'                => lang('Strikethrough'),
        'fmt_highlight'             => lang('Highlight'),
        'insert_code'               => lang('Code block'),
        'insert_to_message'         => lang('Add to the message'),
        'item_done'                 => lang('Mark as done'),
        'item_placeholder'          => lang('What needs doing?'),
        'keys_autoformat'           => lang('Format as you type'),
        'keys_autoformat_help'      => lang('==words== becomes a highlight; "- ", "1. " or "> " at the start of a line becomes a list or a quote. Ctrl+Z gives back what you typed.'),
        'keys_close'                => lang('Close'),
        'keys_newline'              => lang('New line'),
        'keys_redo'                 => lang('Redo'),
        'keys_send'                 => lang('Send the message'),
        'keys_title'                => lang('Keyboard shortcuts'),
        'keys_undo'                 => lang('Undo'),
        'link_invalid'              => lang('Only http, https and mailto addresses can be linked.'),
        'link_open'                 => lang('Open the link'),
        'link_placeholder'          => lang('Paste or type the address'),
        'link_remove'               => lang('Remove the link'),
        'link_save'                 => lang('Apply the link'),
        'link_text'                 => lang('Text to show (the address when left empty)'),
        'move_down'                 => lang('Move down'),
        'move_up'                   => lang('Move up'),
        'row_above'                 => lang('Insert a row above'),
        'row_below'                 => lang('Insert a row below'),
        'table_hint'                => lang('Write in the cells. Tab moves to the next one; right-click a cell for rows, columns and alignment.'),
        'table_formula_hint'        => lang('A cell that starts with = is worked out: =B2*C2, =SUM(D2:D5) (TOPLA), + - * / and %, IF and comparisons; the names of the calculations in the same text work too (=B2*Rate). The header is row 1.'),
        'table_formula_bar'         => lang('The formula or the value of the cell'),
        'insert_to_note'            => lang('Add to the note'),
        'block_calc'                => ws_js_template('Calculation · {var:1} line(s)', 1),
        'designer_calc'             => lang('Calculation'),
        'calc_placeholder'          => lang('Total = Rent + Dues'),
        'calc_hint'                 => lang('One value a line, as "Name = expression"; a later line may use the names above it, and the tables of the same text may use them too. "Name:" starts a group whose name is the total of the lines under it, up to a blank line. A line starting with _ is worked out but not shown.'),
        'calc_template'             => lang('Template'),
        'calc_functions'            => lang('Functions'),
        'calc_add'                  => lang('Add'),
        'calc_subtract'             => lang('Subtract'),
        'calc_multiply'             => lang('Multiply'),
        'calc_divide'               => lang('Divide'),
        'calc_percent'              => lang('Percent'),
        'calc_brackets'             => lang('Brackets'),
        'calc_group'                => lang('Group'),
        'calc_group_help'           => lang('A group around the selected lines: its name becomes their total'),
        'calc_group_name'           => lang('Group'),
        'calc_hide'                 => lang('Hide'),
        'calc_hide_help'            => lang('Worked out but not shown (a _ at the start of the line)'),
        'calc_gap'                  => lang('Blank line'),
        'calc_gap_help'             => lang('A gap in the table; it also ends a group'),
        'calc_remark'               => lang('Remark'),
        'calc_remark_help'          => lang('A line of words (# at the start)'),
        'calc_decimals'             => lang('Decimals'),
        'calc_decimals_auto'        => lang('Automatic'),
        'calc_unit'                 => lang('Unit'),
        'calc_unit_placeholder'     => lang('e.g. ₺, kg'),
        'calc_names'                => lang('Names'),
        'calc_live_try'             => lang('Another figure'),
        'calc_live_reset'           => lang('Reset'),
        'calc_live_note'            => lang('The figures you change are only for you to see and are not saved.'),
        'table_fx'                  => lang('Formula'),
        'table_fx_col_sum'          => lang('Total of the column above'),
        'table_fx_col_avg'          => lang('Average of the column above'),
        'table_fx_col_max'          => lang('Largest in the column above'),
        'table_fx_col_min'          => lang('Smallest in the column above'),
        'table_fx_row_sum'          => lang('Total of the row to the left'),
        'table_fx_mul'              => lang('The two cells to the left multiplied'),
        'table_fx_diff'             => lang('The difference of the two cells to the left'),
        'table_fx_change'           => lang('Change between the two cells to the left (%)'),
        'table_fx_add_pct'          => lang('The cell to the left plus a percentage (VAT)'),
        'table_fx_discount'         => lang('The cell to the left less a discount'),
        'table_fx_share'            => lang('Share of the column total (%)'),
        'table_fx_if'               => lang('A condition (IF)'),
        'table_total_row'           => lang('Add a total row'),
        'table_total'               => lang('Total'),
        'table_fill_down'           => lang('Copy the formula to the cells below'),
        'update_block'              => lang('Update'),
        // The rows of tools under a box (PGWsEditor.toolbar()).
        'tool_bar'                  => lang('Text tools'),
        'tool_bar_message'          => lang('Message tools'),
        'tool_bar_note'             => lang('Note tools'),
        'tool_undo'                 => lang('Undo (Ctrl+Z)'),
        'tool_redo'                 => lang('Redo (Ctrl+Y)'),
        'tool_bold'                 => lang('Bold (Ctrl+B)'),
        'tool_italic'               => lang('Italic (Ctrl+I)'),
        'tool_strike'               => lang('Strikethrough (Ctrl+Shift+X)'),
        'tool_highlight'            => lang('Highlight (Ctrl+Shift+H)'),
        'tool_code'                 => lang('Code (Ctrl+E)'),
        'tool_link'                 => lang('Link (Ctrl+K)'),
        'tool_bullets'              => lang('Bulleted list'),
        'tool_numbers'              => lang('Numbered list'),
        'tool_quote'                => lang('Quote'),
        'tool_heading1'             => lang('Heading 1 (# at a line\'s start)'),
        'tool_heading2'             => lang('Heading 2 (## at a line\'s start)'),
        'tool_heading3'             => lang('Heading 3 (### at a line\'s start)'),
        'tool_rule'                 => lang('Divider line'),
        'tool_checklist'            => lang('Checklist'),
        'tool_table'                => lang('Table'),
        'tool_calc'                 => lang('Calculation'),
        'tool_codeblock'            => lang('Code block'),
        'slash_inserts'             => lang('Insert into the note'),
        // The composer's length meter near WS_MESSAGE_MAX, spoken to screen
        // readers, and the server's own refusal past it.
        'meter_near'                => ws_js_template('{var:1} characters left', 1),
        'meter_over'                => ws_js_template('{var:1} characters over the limit of {var:2}', 2),
        'msg_too_long'              => ws_js_template('A message can be at most {var:1} characters long.', 1),
        // The composer's "Rewrite with AI" (the ai_write panel action).
        'tool_ai_rewrite'           => lang('Rewrite with AI'),
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
 * The model that rewrites a message in the composer of the channel screen,
 * '' where the screen has no composer or the site writes no drafts.
 *
 * @param string $mode
 * @return string pinegrap | browser | ''
 */
function ws_screen_ai_rewrite($mode)
{
    return (($mode === 'channels') && function_exists('pg_ai_write_engine')) ? pg_ai_write_engine() : '';
}

/**
 * What the script is told when a screen opens.
 *
 * @param array  $viewer
 * @param string $mode  channels | tasks | board | calendar | timeline | notes | flows | record
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

    // Asking Pinegrap AI (includes/workspace/ai.php).
    if (function_exists('ws_ai_js_strings')) {
        $strings = array_merge($strings, ws_ai_js_strings());
    }

    // Asking an MCP server by its alias (includes/workspace/mcp_ask.php).
    if (function_exists('ws_mcp_js_strings')) {
        $strings = array_merge($strings, ws_mcp_js_strings());
    }

    // Page changes the assistants propose (includes/designer_ai.php).
    if (function_exists('ws_design_ai') && ws_design_ai()) {
        $strings = array_merge($strings, pg_design_ai_ws_strings());
    }

    // The tour of the channel screen (includes/workspace/tour.php).
    if (function_exists('ws_tour_js_strings')) {
        $strings = array_merge($strings, ws_tour_js_strings());
    }

    // The time and e-mail reminder box of the task drawer (reminders.php).
    if (function_exists('ws_task_reminders_js_strings')) {
        $strings = array_merge($strings, ws_task_reminders_js_strings());
    }

    // Talking with a guest (guests.php).
    if (function_exists('ws_guests_js_strings')) {
        $strings = array_merge($strings, ws_guests_js_strings());
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

    // A file of the file manager picked under the paperclip (library.php).
    $strings = array_merge($strings, ws_library_js_strings());

    // Scheduled actions (includes/workspace/scheduled.php) and the messages
    // scheduled from the writing box (scheduled_messages.php).
    if (function_exists('ws_scheduled_js_strings')) {
        $strings = array_merge($strings, ws_scheduled_js_strings(), ws_scheduled_messages_js_strings(), ws_scheduled_draft_js_strings());
    }

    // The event rule of a scheduled action and a channel's watch switch
    // (includes/workspace/watch.php, assets/js/workspace_events.js).
    if (function_exists('ws_events_js_strings')) {
        $strings = array_merge($strings, ws_events_js_strings());
    }

    // Discussions (includes/workspace/threads.php) and the assistants' bulk
    // changes (includes/workspace/bulk.php).
    $strings = array_merge($strings, ws_threads_js_strings(), ws_bulk_js_strings());

    // Channel templates (includes/workspace/templates.php).
    $strings = array_merge($strings, ws_templates_js_strings());

    // Colours and groups of channels, versions of a conversation.
    $strings = array_merge($strings, ws_groups_js_strings(), ws_channel_icon_js_strings(),ws_eras_js_strings(), ws_pins_js_strings(), ws_forward_js_strings(), ws_blocks_js_strings(), ws_customer_js_strings(), ws_quick_edit_js_strings());

    // The search box of every screen (palette.php) and a channel's board
    // (channel_board.php).
    $strings = array_merge($strings, ws_palette_js_strings(), ws_channel_board_js_strings());
    // The time spent on tasks and the tasks they wait for
    // (assets/js/workspace_task_time.js, workspace_task_links.js).
    $strings = array_merge($strings, ws_task_time_js_strings(), ws_task_links_js_strings());
    // Approval requests, the people outside the team they ask, and read
    // receipts (approvals.php, approval_guests.php, acks.php).
    $strings = array_merge($strings, ws_approvals_js_strings(), ws_approval_guests_js_strings(), ws_acks_js_strings());
    // Voice messages: the recording row and the player (voice.php,
    // assets/js/workspace_voice.js).
    $strings = array_merge($strings, ws_voice_js_strings());
    // Status updates and check-ins (status.php, assets/js/workspace_status.js).
    $strings = array_merge($strings, ws_status_js_strings());
    // A task handed to an assistant (delegation.php,
    // assets/js/workspace_task_delegate.js).
    $strings = array_merge($strings, ws_delegation_js_strings());
    // Meetings on the plan: the call, the meeting note (meetings.php).
    $strings = array_merge($strings, ws_meetings_js_strings());
    // Flows and their canvas (flows.php, assets/js/workspace_flow.js).
    $strings = array_merge($strings, ws_flow_js_strings());

    return array_merge(array(
        'mode'         => (string) $mode,
        'api_url'      => $base . 'api.php',
        'token'        => isset($_SESSION['software']['token']) ? (string) $_SESSION['software']['token'] : '',
        'me'           => (int) $viewer['id'],
        'me_role'      => (int) $viewer['role'],
        'locale'       => (string) lang(array('info' => '')),
        // The site's own clock, which the time labels are written in.
        'timezone'     => date_default_timezone_get(),
        'today'        => date('Y-m-d'),
        'board_from'   => ws_screen_board_from(),
        'event_kinds'  => ws_event_kinds(),
        'meetings'     => ws_meetings_ready(),
        'poll'         => array('active' => 4),
        'open_channel' => 0,
        // The longest message body the server takes (the composer's meter).
        'message_max'  => WS_MESSAGE_MAX,
        // Which model rewrites a message in the composer (pg_ai_write_engine():
        // pinegrap | browser), '' for no button. The screen's gate has
        // already let only the team in (pg_ai_write_user_may()'s rule).
        'ai_rewrite'   => ws_screen_ai_rewrite($mode),
        'urls'         => array(
            'workspace' => $base . 'workspace.php',
            'tasks'     => $base . 'workspace_tasks.php',
            'board'     => $base . 'workspace_board.php',
            'calendar'  => $base . 'workspace_calendar.php',
            'timeline'  => $base . 'workspace_timeline.php',
            'notes'     => $base . 'workspace_notes.php',
            'flows'     => $base . 'workspace_flows.php',
            'notes_export' => $base . 'workspace_notes_export.php',
            'notes_import' => $base . 'workspace_notes_import.php',
            'file_save' => $base . 'workspace_file_save.php',
            'settings'  => $base . 'workspace_settings.php',
            'edit_user' => $base . 'edit_user.php',
            // The quote editor's copy (?copy=<id>), for whoever may write
            // ERP documents: the accountant's read-only right saves none.
            'erp_quote_copy' => (!empty($viewer['erp']) && empty($viewer['erp_readonly'])) ? $base . 'add_erp_quote.php' : '',
        ),
        'notes'        => function_exists('ws_notes_ready') && ws_notes_ready(),
        // What one import of notes may send (workspace_notes_import.php): its
        // size, how many files the server takes in one request and the kinds
        // of file it reads; and how many notes a person keeps.
        'notes_import' => function_exists('ws_note_import_upload_max') ? array(
            'max'   => ws_note_import_upload_max(),
            'files' => ws_note_import_files_max(),
            'types' => ws_note_import_extensions(),
            'limit' => WS_NOTE_LIMIT,
        ) : null,
        // The paperclip also picks from the file manager (library.php).
        'library'      => function_exists('ws_library_browse'),
        // Word and Excel files of a conversation, drawn by a script fetched
        // on first use (includes/fn/office.php).
        'office'       => function_exists('pg_office_view_includes') ? array(
            'script'  => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_office_view.js?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js'),
            'strings' => pg_office_view_strings(),
        ) : null,
        'strings'      => $strings,
        'recurrence'   => function_exists('ws_recurrence_js_config') ? ws_recurrence_js_config() : array('ready' => false),
        'reminders'    => function_exists('ws_task_reminders_js_config') ? ws_task_reminders_js_config() : array('ready' => false),
        'scheduled'    => function_exists('ws_scheduled_js_config') ? ws_scheduled_js_config($viewer) : null,
        'scheduled_messages' => function_exists('ws_can_schedule_messages') && ws_can_schedule_messages($viewer),
        'threads'      => ws_threads_ready(),
        'palette'      => ws_channel_colors_ready() ? ws_palette_js() : array(),
        'channel_icons' => ws_channel_icons_ready() ? ws_channel_icon_choices_js() : array(),
        'groups'       => ws_can_manage_groups($viewer) ? array('manage' => true, 'depth' => WS_GROUP_DEPTH) : array('manage' => false, 'depth' => WS_GROUP_DEPTH),
        'eras'         => ws_eras_ready(),
        'pins'         => ws_pins_ready(),
        'approvals'    => ws_approvals_ready() ? array('max' => WS_APPROVAL_MAX_PEOPLE, 'guests' => ws_approval_can_ask_guests($viewer) ? WS_APPROVAL_MAX_GUESTS : 0) : null,
        'acks'         => ws_acks_ready(),
        'status'       => ws_status_js_config(),
        'blocks'       => ws_blocks_ready(),
        'customer'     => ws_customer_ready(),
        'templates'    => ws_templates_js_config($viewer),
        'calc'         => function_exists('ws_calc_js_config') ? ws_calc_js_config() : array(),
        'task_time'    => ws_task_time_ready() ? array('ready' => true, 'manager' => ws_task_time_is_manager($viewer)) : array('ready' => false),
        'task_links'   => ws_task_links_ready(),
        'delegation'   => ws_delegation_js_config(),
        'guests'       => function_exists('ws_guests_js_config') ? ws_guests_js_config($viewer) : array('can_host' => false, 'durations' => array()),
        'forwards'     => ws_forwards_ready() ? array('max' => WS_FORWARD_MAX) : null,
        'flows'        => ws_flow_js_config($viewer, (string) $mode),
        'tour'         => (($mode === 'channels') && function_exists('ws_tour_js_config')) ? ws_tour_js_config($viewer) : null,
    ), $extra);
}

/**
 * The settings block and the script, once per page.
 *
 * The scripts come from pg_js_bundles() (includes/fn/output.php): one
 * minified file for what every screen runs, plus the search box on the
 * workspace's own screens, the channel board and tour on the channel screen
 * and the canvas on the flows screen. A record's drawer (ws_record_button()) loads nothing up front: the
 * order, product and contact screens it sits on are opened far more often
 * than the drawer is, so the script waits until the Workspace button is
 * first clicked.
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

    $output = $lightbox . '
<script type="application/json" id="ws-config">' . $json . '</script>';

    if ($mode === 'record') {
        return $output . ws_record_loader(pg_js_bundle_urls('assets/js/workspace.min.js'));
    }

    // "Rewrite with AI" in the composer: the draft panel's script and words
    // (assets/js/pg_ai_write.js), before the composer is drawn.
    if (ws_screen_ai_rewrite($mode) !== '') {
        pg_ai_write_buttons_drawn(true);
        $output .= pg_ai_write_assets();
    }

    $output .= pg_js_bundle_tags('assets/js/workspace.min.js')
        // The search box, on every workspace screen.
        . pg_js_bundle_tags('assets/js/workspace_palette.min.js');

    if ($mode === 'channels') {
        $output .= pg_js_bundle_tags('assets/js/workspace_board_channel.min.js')
            . pg_js_bundle_tags('assets/js/workspace_tour.min.js');
    }

    // The flow canvas (assets/js/workspace_flow.js), started by workspace.js
    // once the team is known.
    if ($mode === 'flows') {
        $output .= pg_js_bundle_tags('assets/js/workspace_flow.min.js');
    }

    return $output;
}

/**
 * What a record's screen carries in place of the workspace script: a click
 * on a Workspace button (data-ws-record) is held, the script is fetched,
 * file after file in order, and once it has started it opens the drawer of
 * the button that was clicked (window.PGWsPendingRecord, read by
 * assets/js/workspace.js). Clicks that come while the files are on their way
 * replace the held one; once the script has taken over (PGWsRecordReady)
 * its own listener answers and this one stands aside. A file that fails to
 * load lets the next click try again.
 *
 * @param array $urls pg_js_bundle_urls() of the workspace script
 * @return string
 */
function ws_record_loader($urls)
{
    $files = json_encode(array_values($urls), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    return '
<script>
(function () {
    var files = ' . $files . ';
    var loading = false;

    document.addEventListener("click", function (event) {
        var trigger = event.target.closest ? event.target.closest("[data-ws-record]") : null;

        if (!trigger || window.PGWsRecordReady) {
            return;
        }

        event.preventDefault();
        window.PGWsPendingRecord = trigger;

        if (loading) {
            return;
        }

        loading = true;

        (function next(index) {
            if (index >= files.length) {
                return;
            }

            var script = document.createElement("script");
            script.src = files[index];
            script.onload = function () { next(index + 1); };
            script.onerror = function () { loading = false; };
            document.head.appendChild(script);
        })(0);
    });
})();
</script>';
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
function ws_record_button($user, $type, $id, $label = '', $class = 'btn btn-sm btn-outline theme-secondary')
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
        . (($count > 0) ? ' <span class="badge rounded-pill theme-secondary ms-1">' . (int) $count . '</span>' : '')
        . '</a>' . ws_screen_assets($viewer, 'record');
}
