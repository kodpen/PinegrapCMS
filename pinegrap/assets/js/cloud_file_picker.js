/**
 * Pinegrap - Enterprise Website Platform
 *
 * The cloud file picker of the Upload File screen (add_file.php): opens the
 * Google Picker or the Dropbox Chooser and sends only the chosen file's id
 * or link to add_file.php, which downloads the file on the server and stores
 * it through the same rules as an upload (includes/fn/cloud_file_picker.php).
 *
 * The settings come from the screen as JSON (#pg_cloud_picker_config): per
 * provider what its picker needs (Google: API key and project number;
 * Dropbox: app key) and the screen's translated sentences.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var configNode = document.getElementById('pg_cloud_picker_config');

    if (!configNode) {
        return;
    }

    var config = JSON.parse(configNode.textContent);
    var loading = {};

    function say(text, isError) {
        var status = document.getElementById('pg_cloud_status');

        if (status) {
            status.textContent = text;
            status.classList.toggle('fg-danger', !!isError);
        }
    }

    // A script of the provider, loaded once.
    function loadScript(src, attributes) {
        if (!loading[src]) {
            loading[src] = new Promise(function (resolve, reject) {
                var script = document.createElement('script');

                script.src = src;
                script.async = true;

                Object.keys(attributes || {}).forEach(function (name) {
                    script.setAttribute(name, attributes[name]);
                });

                script.onload = resolve;
                script.onerror = function () {
                    delete loading[src];
                    reject(new Error(config.texts.load_failed));
                };

                document.head.appendChild(script);
            });
        }

        return loading[src];
    }

    function loadGoogle() {
        return loadScript('https://apis.google.com/js/api.js').then(function () {
            return new Promise(function (resolve) {
                window.gapi.load('picker', resolve);
            });
        });
    }

    function loadDropbox() {
        return loadScript('https://www.dropbox.com/static/api/2/dropins.js', {
            id: 'dropboxjs',
            'data-app-key': config.pick.dropbox.app_key
        });
    }

    function uploadForm() {
        return document.getElementById('dropzone');
    }

    function post(data) {
        return fetch('add_file.php?jsonreturn=true', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            return response.json().catch(function () {
                return {status: 'error', message: config.texts.failed};
            });
        });
    }

    // The chosen file goes to the server with the folder, description and
    // design settings of the upload form, never its file input.
    function send(provider, choice, button) {
        var data = new FormData(uploadForm());

        data.delete('file[]');
        data.set('cloud_provider', provider);
        data.set('cloud_choice', JSON.stringify(choice));
        data.set('jsonreturn', 'true');

        button.disabled = true;
        say(config.texts.downloading);

        post(data).then(function (answer) {
            button.disabled = false;

            if ((answer.status === 'success') && answer.files && answer.files.length) {
                var sendTo = document.getElementById('send_to');

                window.location = (sendTo && sendTo.value) ? sendTo.value : 'view_files.php';

                return;
            }

            say((answer.failed && answer.failed.length && answer.failed[0].message) || answer.message || config.texts.failed, true);
        }).catch(function () {
            button.disabled = false;
            say(config.texts.failed, true);
        });
    }

    // The person's own drive.file token, asked for each time the Picker
    // opens, so a page left open for hours still gets a fresh one.
    function googleToken() {
        var data = new FormData();

        data.set('token', uploadForm().querySelector('input[name="token"]').value);
        data.set('cloud_action', 'token');

        return post(data).then(function (answer) {
            if (answer.status !== 'success') {
                throw new Error(answer.message || config.texts.failed);
            }

            return answer.token;
        });
    }

    function pickGoogle(button) {
        var settings = config.pick.google;

        button.disabled = true;
        say('');

        Promise.all([googleToken(), loadGoogle()]).then(function (results) {
            var picker = window.google.picker;

            button.disabled = false;

            new picker.PickerBuilder()
                .setDeveloperKey(settings.api_key)
                .setAppId(settings.app_id)
                .setOAuthToken(results[0])
                .addView(new picker.DocsView(picker.ViewId.DOCS).setIncludeFolders(true).setSelectFolderEnabled(false))
                .setCallback(function (data) {
                    if (data[picker.Response.ACTION] !== picker.Action.PICKED) {
                        return;
                    }

                    send('google', {id: data[picker.Response.DOCUMENTS][0][picker.Document.ID]}, button);
                })
                .build()
                .setVisible(true);
        }).catch(function (error) {
            button.disabled = false;
            say((error && error.message) ? error.message : config.texts.failed, true);
        });
    }

    // The Chooser opens a window, which a browser allows only straight from
    // the click, so its script is loaded with the page.
    function pickDropbox(button) {
        if (!window.Dropbox || !window.Dropbox.choose) {
            say(config.texts.load_failed, true);
            return;
        }

        say('');

        window.Dropbox.choose({
            linkType: 'direct',
            multiselect: false,
            folderselect: false,
            success: function (files) {
                send('dropbox', {link: files[0].link, name: files[0].name, bytes: files[0].bytes}, button);
            }
        });
    }

    if (config.pick.dropbox) {
        loadDropbox().catch(function () {});
    }

    if (config.pick.google) {
        loadGoogle().catch(function () {});
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-pg-cloud-pick]');

        if (!button || button.disabled) {
            return;
        }

        event.preventDefault();

        if (button.getAttribute('data-pg-cloud-pick') === 'google') {
            pickGoogle(button);
        } else if (button.getAttribute('data-pg-cloud-pick') === 'dropbox') {
            pickDropbox(button);
        }
    });
}());
