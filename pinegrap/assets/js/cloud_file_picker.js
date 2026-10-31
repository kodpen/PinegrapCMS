/**
 * Pinegrap - Enterprise Website Platform
 *
 * The cloud file picker of the Upload File screen (add_file.php): opens the
 * Google Picker, the OneDrive file picker v8 or the Dropbox Chooser and
 * sends only the chosen file's id or link to add_file.php, which downloads the file on the server and stores
 * it through the same rules as an upload (includes/fn/cloud_file_picker.php).
 *
 * The settings come from the screen as JSON (#pg_cloud_picker_config): per
 * provider what its picker needs (Google: API key and project number;
 * Dropbox: app key; OneDrive: nothing, its address and tokens come from the
 * server when it opens) and the screen's translated sentences.
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

    // The person's own token, asked for each time a picker opens (and the
    // OneDrive picker asks for one per resource), so a page left open for
    // hours still gets a fresh one.
    function pickerToken(extra) {
        var data = new FormData();

        data.set('token', uploadForm().querySelector('input[name="token"]').value);
        data.set('cloud_action', 'token');

        Object.keys(extra || {}).forEach(function (name) {
            data.set(name, extra[name]);
        });

        return post(data).then(function (answer) {
            if (answer.status !== 'success') {
                throw new Error(answer.message || config.texts.failed);
            }

            return answer;
        });
    }

    function googleToken() {
        return pickerToken().then(function (answer) {
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

    // The OneDrive file picker v8: Microsoft's page, opened in a window by a
    // form POST carrying the picker's settings and a token, then talked to
    // over a MessageChannel. The page asks for a token per resource (OneDrive
    // for personal accounts, the SharePoint host for work and school ones),
    // and the server answers each from the person's own connection.
    function pickOneDrive(button) {
        // A browser allows a new window only straight from the click.
        var win = window.open('', 'pg_onedrive_picker', 'width=1080,height=680');
        var channelId = String(Date.now()) + Math.random().toString(36).slice(2);
        var port = null;
        var watch = null;

        if (!win) {
            say(config.texts.popup_blocked, true);
            return;
        }

        button.disabled = true;
        say('');

        function finish() {
            window.removeEventListener('message', initialize);

            if (watch) {
                window.clearInterval(watch);
            }

            if (port) {
                port.close();
            }

            if (!win.closed) {
                win.close();
            }
        }

        function reply(id, data) {
            port.postMessage({type: 'result', id: id, data: data});
        }

        function command(message) {
            var payload = message.data;

            if (payload.type !== 'command') {
                return;
            }

            port.postMessage({type: 'acknowledge', id: payload.id});

            var request = payload.data || {};

            if (request.command === 'authenticate') {
                pickerToken({cloud_provider: 'onedrive', resource: request.resource || ''}).then(function (answer) {
                    reply(payload.id, {result: 'token', token: answer.token});
                }).catch(function (error) {
                    reply(payload.id, {result: 'error', error: {code: 'unableToObtainToken', message: error.message}});
                });
            } else if (request.command === 'pick') {
                var item = (request.items || [])[0];

                if (!item || !item.id || !item.parentReference || !item.parentReference.driveId) {
                    reply(payload.id, {result: 'error', error: {code: 'unusableItem', message: config.texts.failed}});
                    return;
                }

                reply(payload.id, {result: 'success'});
                finish();
                send('onedrive', {drive_id: item.parentReference.driveId, item_id: item.id}, button);
            } else if (request.command === 'close') {
                finish();
                button.disabled = false;
            } else {
                reply(payload.id, {result: 'error', error: {code: 'unsupportedCommand', message: request.command}});
            }
        }

        function initialize(event) {
            if ((event.source !== win) || !event.data || (event.data.type !== 'initialize') || (event.data.channelId !== channelId)) {
                return;
            }

            port = event.ports[0];
            port.addEventListener('message', command);
            port.start();
            port.postMessage({type: 'activate'});
        }

        window.addEventListener('message', initialize);

        // Closed by the person: the button works again.
        watch = window.setInterval(function () {
            if (win.closed) {
                finish();
                button.disabled = false;
            }
        }, 500);

        pickerToken({cloud_provider: 'onedrive'}).then(function (answer) {
            var settings = {
                sdk: '8.0',
                entry: {oneDrive: {files: {}}},
                authentication: {},
                messaging: {origin: window.location.origin, channelId: channelId},
                selection: {mode: 'single'},
                typesAndSources: {mode: 'files', pivots: {oneDrive: true, recent: true}}
            };
            var query = new URLSearchParams({
                filePicker: JSON.stringify(settings),
                locale: document.documentElement.lang || 'en-us'
            });
            var form = win.document.createElement('form');
            var input = win.document.createElement('input');

            form.setAttribute('action', answer.picker_url + '?' + query.toString());
            form.setAttribute('method', 'POST');
            input.setAttribute('type', 'hidden');
            input.setAttribute('name', 'access_token');
            input.setAttribute('value', answer.token);
            form.appendChild(input);
            win.document.body.appendChild(form);
            form.submit();
        }).catch(function (error) {
            finish();
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
        } else if (button.getAttribute('data-pg-cloud-pick') === 'onedrive') {
            pickOneDrive(button);
        } else if (button.getAttribute('data-pg-cloud-pick') === 'dropbox') {
            pickDropbox(button);
        }
    });
}());
