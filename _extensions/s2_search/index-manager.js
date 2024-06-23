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
            .then(response => response.text())
            .then(data => {
                if (data.startsWith('go_')) {
                    setTimeout(s2_search.reindex_query, 50);
                    document.getElementById('s2_search_progress').innerHTML = `: <b>${data.substring(3)}%</b>...`;
                } else {
                    if (data === 'stop') {
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
    },

    refresh_index: function (e, sAction, sId, oldPublished, newPublished, oldRevision, newRevision) {
        // noinspection EqualityComparisonWithCoercionJS
        if (newPublished && (!oldPublished || oldRevision !== newRevision) || !newPublished && oldPublished) {
            fetch(sUrl + 'action=s2_search_makeindex&save_action=' + encodeURIComponent(sAction) + '&id=' + encodeURIComponent(sId));
        }
    }
};

document.addEventListener('save_article_done.s2', function(e) {
    s2_search.refresh_index(e, e.detail.sAction, e.detail.sId, e.detail.oldPublished, e.detail.newPublished, e.detail.oldRevision, e.detail.newRevision);
});
