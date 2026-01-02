/**
 * Tags autocomplete wiring for editor in S2.
 *
 * @copyright 2009-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

import {editorDeps} from './deps.js';

export function initTagsAutocomplete(sInputId, aTagsList) {
    const tagsAutocomplete = new editorDeps.autoComplete(
        {
            selector: "#" + sInputId,
            data: {
                src: aTagsList,
                cache: true
            },
            debounce: 100,
            query: (query) => {
                const querySplit = query.split(",");
                const lastQuery = querySplit.length - 1;
                const newQuery = querySplit[lastQuery].trim();

                return newQuery;
            },
            events: {
                input: {
                    focus() {
                        tagsAutocomplete.start();
                    },
                    selection(event) {
                        const feedback = event.detail;
                        const input = document.getElementById(sInputId);
                        const selection = feedback.selection.value.trim();
                        const query = input.value.split(",").map(item => item.trim());
                        query.pop();
                        query.push(selection);
                        input.value = query.join(", ") + ", ";
                    }
                }
            },
            threshold: 0,
            resultsList: {
                maxResults: undefined
            },
            resultItem: {
                highlight: true
            }
        }
    );
}
