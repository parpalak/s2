/**
 * Adds reindexing functions to the admin panel
 *
 * @copyright (C) 2011-2024 Roman Parpalak
 * @license http://opensource.org/licenses/MIT MIT
 * @package s2_search
 */

const s2_search = {
    reindex_query: function () {
        fetch(sUrl + 'action=s2_search_makeindex')
            .then(response => response.json())
            .then(data => {
                if (data.status.startsWith('go_')) {
                    setTimeout(s2_search.reindex_query, 50);
                    document.getElementById('s2_search_progress').innerHTML = `: <b>${data.status.substring(3)}%</b>...`;
                } else {
                    if (data.status === 'stop') {
                        document.getElementById('s2_search_progress').innerHTML = ': 100%';
                    } else if (data) {
                        DisplayError(data);
                    }
                }
            })
            .catch(error => {
                console.warn('Error:', error);
            });
    },

    reindex: function () {
        s2_search.reindex_query();
        document.getElementById('s2_search_progress').innerHTML = ': <b>0%</b>...';

        return false;
    }
};
