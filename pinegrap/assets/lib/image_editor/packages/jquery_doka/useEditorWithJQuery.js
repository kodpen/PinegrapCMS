/*!
 * Doka Image Editor 7.2.2
 * (c) 2018-2021 PQINA Inc. - All Rights Reserved
 * License: https://pqina.nl/doka/license/
 */
/* eslint-disable */

var useEditorWithJQuery = ($, doka) => {

    // No jQuery or No Doka Image Editor
    if (!$ || !doka) return;

    // Get shortcuts to methods
    const { appendEditor, isSupported, dispatchEditorEvents } = doka;

    // Test if Doka Image Editor is supported
    if (!isSupported()) {
        // if not supported add stub so throws no errors
        $.fn.doka = () => {};
        return;
    }

    // Helpers
    const isFactory = (args) => !args.length || typeof args[0] === 'object';

    const isGetter = (obj, key) => {
        const descriptor = Object.getOwnPropertyDescriptor(obj, key);
        return descriptor ? typeof descriptor.get !== 'undefined' : false;
    };

    const isSetter = (obj, key) => {
        const descriptor = Object.getOwnPropertyDescriptor(obj, key);
        return descriptor ? typeof descriptor.set !== 'undefined' : false;
    };

    const isMethod = (obj, key) => typeof obj[key] === 'function';

    // Setup plugin
    const elementEditorMap = new Map();

    $.fn.doka = function (...args) {
        // method results array
        const results = [];

        // Execute for every item in the list
        const items = this.each(function () {
            // test if is create call
            if (isFactory(args)) {
                const editor = appendEditor(this, args[0]);
                const unsubs = dispatchEditorEvents(editor, this);

                editor.on('destroy', () => {
                    unsubs.forEach((unsub = unsub()));
                    elementEditorMap.delete(this);
                });

                elementEditorMap.set(this, editor);
                return;
            }

            // get a reference to the Doka Image Editor instance based on the element
            const editor = elementEditorMap.get(this);

            // if no Doka Image Editor instance found, exit here
            if (!editor) return;

            // get property name or method name
            const key = args[0];

            // get params to pass
            const params = args.concat().slice(1);

            // run method
            if (isMethod(editor, key)) {
                results.push(editor[key].apply(editor, params));
                return;
            }

            // set setter
            if (isSetter(editor, key) && params.length) {
                editor[key] = params[0];
                return;
            }

            // get getter
            if (isGetter(editor, key)) {
                results.push(editor[key]);
                return;
            }

            console.warn('$().doka("' + key + '") is an unknown property or method.');
        });

        // Returns a jQuery object if no results returned
        return results.length ? (this.length === 1 ? results[0] : results) : items;
    };

    // Proxy static Doka Image Editor API
    Object.keys(doka).forEach((key) => ($.fn.doka[key] = doka[key]));
};

export default useEditorWithJQuery;
