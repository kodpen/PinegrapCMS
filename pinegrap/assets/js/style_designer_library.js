/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - My library's file part: "Export my library" writes
 * every item of the site's library into one file and offers it as a
 * download; "Import into my library" reads such a file chosen on this
 * computer and sends its text to the server, which runs the import's checks
 * (no script, no event attribute, no address outside the allowed kinds),
 * refuses a file that fails with the reason, and keeps the items under free
 * names (designer/library_export, designer/library_import). Not needed to
 * open a page: the core runs this file the first time one of the two
 * command palette rows is chosen (_sdPartLoad(), pg_designer_parts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('library', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // The largest file the server reads (PG_LIBRARY_EXPORT_MAX); a larger
    // one is refused here before it is sent.
    var MAX_BYTES = 16777216;
    var busy = false;

    function _lbFail(res) {
        K.sdToast(esc((res && res.message) || _sdT('The library could not be reached. Try again.')), 'danger');
    }

    function exportAll() {
        if (busy) return Promise.resolve();
        busy = true;
        return K._pgApiPost('library_export', {}).then(function (res) {
            busy = false;
            if (!res || res.status !== 'success') return _lbFail(res);
            if (!res.count) {
                K.sdToast(esc(_sdT('My library is empty: there is nothing to export.')), 'info');
                return;
            }
            var url = URL.createObjectURL(new Blob([res.text], { type: 'application/json' }));
            var a = document.createElement('a');
            a.href = url;
            a.download = res.name || 'my-library.json';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            K.sdToast(esc(_sdT('{var} items exported.', res.count)), 'success');
        }, function () {
            busy = false;
            _lbFail(null);
        });
    }

    function _lbSend(file) {
        if (file.size > MAX_BYTES) {
            K.sdToast(esc(_sdT('The file is too large.')), 'danger');
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            busy = true;
            K._pgApiPost('library_import', { text: String(reader.result || '') }).then(function (res) {
                busy = false;
                if (!res || res.status !== 'success') return _lbFail(res);
                K.sdToast(esc(_sdT('{var} items added to My library.', res.count)), 'success');
            }, function () {
                busy = false;
                _lbFail(null);
            });
        };
        reader.onerror = function () {
            K.sdToast(esc(_sdT('The file could not be read.')), 'danger');
        };
        reader.readAsText(file);
    }

    function importFile() {
        if (busy) return;
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json,application/json';
        input.hidden = true;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            input.remove();
            if (file) _lbSend(file);
        });
        document.body.appendChild(input);
        input.click();
    }

    return { exportAll: exportAll, importFile: importFile };
});
