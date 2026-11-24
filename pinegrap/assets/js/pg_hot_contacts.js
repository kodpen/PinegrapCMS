/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Remind me" on the contacts list's Hot Contacts view (view_contacts.php,
 * pg_contact_list_hot_remind_dialog()): a row's button fills the dialog with
 * the contact, the save asks the Workspace action ws_contact_followup
 * (ws_contact_followup_open()) and says the task's number and day. Rows
 * arrive as the list scrolls, so the click is listened to on the document.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var dialog = document.getElementById('hot_remind_dialog');
    var form = document.getElementById('hot_remind_form');

    if (!dialog || !form) {
        return;
    }

    var error = document.getElementById('hot_remind_error');
    var save = form.querySelector('button[type="submit"]');
    var busy = false;

    function fill(text, values) {
        return String(text || '').replace(/\{(\d)\}/g, function (all, n) {
            return (values[n - 1] === undefined) ? all : String(values[n - 1]);
        });
    }

    function say(message) {
        error.textContent = message || '';
        error.hidden = !message;
    }

    function value(name) {
        var field = form.elements[name];

        if (!field) {
            return '';
        }

        if (name === 'due_date' && window.pgDatePicker) {
            return window.pgDatePicker.get(field) || field.value;
        }

        if (name === 'due_time' && window.pgDateTimeField) {
            return window.pgDateTimeField.get(field) || field.value;
        }

        return field.value;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-pg-remind]') : null;

        if (!button) {
            return;
        }

        event.preventDefault();
        form.elements.contact_id.value = button.getAttribute('data-pg-remind');
        form.elements.title.value = '';
        document.getElementById('hot_remind_who').textContent = button.getAttribute('data-pg-remind-name') || '';
        say('');
        window.pgUI.dialog.show(dialog);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (busy) {
            return;
        }

        busy = true;
        save.disabled = true;
        say('');

        fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'ws_contact_followup',
                token: (typeof software_token !== 'undefined') ? software_token : '',
                contact_id: form.elements.contact_id.value,
                title: value('title'),
                due_date: value('due_date'),
                due_time: value('due_time')
            })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            var task = (json && json.status === 'success' && json.data) ? json.data.task : null;

            busy = false;
            save.disabled = false;

            if (!task) {
                say((json && json.message) ? json.message : dialog.getAttribute('data-pg-failed'));
                return;
            }

            window.pgUI.dialog.hide(dialog).then(function () {
                if (typeof window.pgToast === 'function') {
                    window.pgToast({ message: fill(dialog.getAttribute('data-pg-done'), [task.number, task.due_label]), variant: 'success' });
                }
            });
        }).catch(function () {
            busy = false;
            save.disabled = false;
            say(dialog.getAttribute('data-pg-failed'));
        });
    });
}());
