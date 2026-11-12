// A screen script with one function name declared twice in one scope: the
// later declaration replaces the earlier for every caller, and the file
// still parses. The scanner must name newPost at lines 18 and 34 only.
(function () {
    'use strict';

    var words = { reply: 'Reply by e-mail', note: "function fake() {}" };
    var pattern = /function notReal\(/g;
    var template = `function inTemplate() { ${ words.reply } }`;

    // function inComment() {}
    /* function inBlockComment() {} */

    function replyForm(message) {
        return newPost('ws_mail_reply', { text: message });
    }

    function newPost(action, fields) {
        return { action: action, fields: fields };
    }

    function newForm(post) {
        var send = function newPost() {
            return post;
        };

        if (post) {
            function inBlock() {}
        }

        return send();
    }

    function newPost(action, fields, files) {
        return { action: action, fields: fields, files: files };
    }

    function inBlock() {}

    window.fixture = { replyForm: replyForm, newForm: newForm, pattern: pattern, template: template };
})();
