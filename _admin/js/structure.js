/**
 * JS functions for site structure
 *
 * Drag & drop, event handlers for the admin panel
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */
const Search = (function () {
    let sSearch = '',
        eInput;

    function DoSearch() {
        $(document).trigger('do_search.s2');
    }

    $(document).on('tree_reload.s2', function () {
        // Cancel search mode
        if (!eInput) {
            return;
        }
        eInput.value = eInput.defaultValue;
        sSearch = '';
    });

    $(function () {
        var iTimer;

        eInput = document.getElementById('search_field');

        function NewSearch() {
            // We have to wait a while for eInput.value to change
            setTimeout(function () {
                if (sSearch === eInput.value) {
                    return;
                }

                sSearch = eInput.value;
                SetWait(true);
                clearTimeout(iTimer);
                iTimer = setTimeout(DoSearch, 250);
            }, 0);
        }

        $(eInput)
            .on('input', NewSearch)
            .on('focus', function () {
                $('#tree').jstree('disable_hotkeys');
            })
            .on('blur', function () {
                $('#tree').jstree('enable_hotkeys');
            })
            .keydown(function (e) {
                if (e.which === 13) {
                    // Immediate search on enter press
                    clearTimeout(iTimer);
                    sSearch = this.value;
                    DoSearch();
                } else {
                    // We have to wait a little for eInput.value to change
                    NewSearch();
                }
            });
    });

    return {
        // Get search string
        string: function () {
            return sSearch;
        }
    };
}());

// Turning animated icon on or off
function SetWait(bWait) {
    $('#loading').css('display', bWait ? 'block' : 'none');
    document.body.style.cursor = bWait ? 'progress' : 'inherit';
}

function CloseAll() {
    $('#tree').jstree('close_all').jstree('open_node', '#node_1', false, true);
}

function OpenAll() {
    $('#tree').jstree('open_all');
}

$(function () {
    var selectedId = -1,
        commentNum = 0,
        isRenaming = false;

    function createArticle() {
        tree.jstree('create', null, (new_page_pos === '1') ? 'first' : 'last', {
            data: {
                title: s2_lang.new_page,
                attr: {'class': 'Draft'}
            }
        });
    }

    $('.toolbar .refresh').click(refreshTree);

    var eButtons = $('#context_buttons');
    eButtons.detach();

    function run_search() {
        tree.jstree('refresh', -1);
    }

    function refreshTree() {
        $(document).trigger('tree_reload.s2');
        run_search();
    }

    $(document).on('do_search.s2', run_search);

    function rollback(data) {
        eButtons.remove();
        eButtons = null;
        $.jstree.rollback(data);
        eButtons = $('#context_buttons');
    }

    var tree = $('#tree')
        .bind('before.jstree', function (e, data) {
            if (data.func === 'remove' && !confirm(str_replace('%s', tree.jstree('get_text', data.args[0]), s2_lang.delete_item))) {
                e.stopImmediatePropagation();
                return false;
            }
        })
        .bind('dblclick.jstree', function (e) {
            if (!isRenaming && e.target.nodeName === 'A') {
                isRenaming = true;
                tree.jstree('rename', e.target);
            }
        })
        .bind('select_node.jstree', function (e, d) {
            if (!eButtons) {
                return;
            }

            eButtons.detach();
            const attachButtons = function () {
                selectedId = d.rslt.obj.attr('id').replace('node_', '');
                commentNum = d.rslt.obj.attr('data-comments');
                eButtons.find('.edit').attr('href', '?entity=Article&action=edit&id=' + selectedId);
                eButtons.find('.comments').attr('href', '?entity=Comment&action=list&article_id=' + selectedId);
                $('.jstree-clicked').append(eButtons);
            }
            let attempts = 0;
            const checkComplete = function () {
                if (attempts > 200) {
                    return;
                }
                if (!d.rslt.obj.attr('id')) {
                    // A new node was selected and there is no server data with a new ID has been received yet.
                    attempts++;
                    setTimeout(checkComplete, 200);
                } else {
                    attachButtons();
                }
            };
            setTimeout(checkComplete, 0)
        })
        .bind('deselect_node.jstree', function (e, d) {
            eButtons.detach();
        })
        .bind('rename.jstree', function (e, data) {
            isRenaming = false;
            if (data.rslt.new_name === data.rslt.old_name) {
                return;
            }

            fetch(sUrl + 'action=rename&id=' + data.rslt.obj.attr('id').replace('node_', ''), {
                    method: 'POST',
                    body: new URLSearchParams('csrf_token=' + data.rslt.obj.attr('data-csrf-token') + '&title=' + data.rslt.new_name)
                }
            ).then(function (response) {
                if (!response.ok) {
                    console.warn(response);
                    rollback(data.rlbk);
                }
            }).catch(function (e) {
                console.warn(e);
                rollback(data.rlbk);
            });
        })
        .bind('remove.jstree', function (e, data) {
            fetch(sUrl + 'action=delete&id=' + data.rslt.obj.attr('data-id'), {
                    method: 'POST',
                    body: new URLSearchParams('csrf_token=' + data.rslt.obj.attr('data-csrf-token'))
                }
            ).then(function (response) {
                if (!response.ok) {
                    console.warn(response);
                    rollback(data.rlbk);
                }
            }).catch(function (e) {
                console.warn(e);
                rollback(data.rlbk);
            });
        })
        .bind('create.jstree', function (e, data) {
            $.ajax({
                url: sUrl + 'action=create&id=' + data.rslt.parent.attr('id').replace('node_', ''),
                data: {title: data.rslt.name},
                success: function (d) {
                    if (!d.success)
                        rollback(data.rlbk);
                    else {
                        data.rslt.obj.attr('id', 'node_' + d.id);
                        data.rslt.obj.attr('data-csrf-token', d.csrfToken);
                        data.rslt.obj.attr('data-id', d.id);
                    }
                },
                error: function () {
                    rollback(data.rlbk);
                }
            });
        })
        .bind('move_node.jstree', function (e, data) {
            fetch(sUrl + 'action=move', {
                method: 'POST',
                body: new URLSearchParams(
                    'csrf_token=' + data.rslt.o.attr('data-csrf-token')
                    + '&source_id=' + data.rslt.o.attr('id').replace('node_', '')
                    + '&new_parent_id=' + data.rslt.np.attr('id').replace('node_', '')
                    + '&new_pos=' + data.rslt.cp
                )
            })
                .then(function (response) {
                    if (!response.ok) {
                        console.warn(response);
                        rollback(data.rlbk);
                    }
                })
                .catch(function (e) {
                    console.warn(e);
                    rollback(data.rlbk);
                });
        })
        .bind('loaded.jstree', function (e, data) {
            tree.jstree('open_node', '#node_1');
        })
        .bind('reselect.jstree', function (e, data) {
            var $e = data.inst.get_selected().first();
            $e = $e.length ? $e : $('.Search.Match').first().parent();
            $e = $e.length ? $e : $('#node_1');

            data.inst.hover_node($e);
        })
        .on('click', '#context_edit, #context_comments, #context_add, #context_delete', function (e, data) {
            // Context buttons
            var id = this.id;
            if (id == 'context_edit')
                e.stopPropagation();
            else if (id == 'context_comments')
                e.stopPropagation();
            else if (id == 'context_add')
                createArticle();
            else if (id == 'context_delete')
                tree.jstree('remove');
        })
        .jstree({
            crrm: {
                input_width_limit: 1000,
                move: {
                    check_move: function (m) {
                        return (typeof (m.np.attr('id')) != 'undefined' && m.np.attr('id').substring(0, 5) === 'node_');
                    }
                }
            },
            ui: {
                select_limit: 1,
                initially_select: ['node_1']
            },
            hotkeys: {
                'e': function () {
                    window.location = '?entity=Article&action=edit&id=' + selectedId;
                },
                'c': function () {
                    if (commentNum) {
                        window.location = '?entity=Comment&action=list&articleA_id=' + selectedId;
                    }
                },
                'n': function () {
                    createArticle();
                    return false;
                },
                'f': function () {
                    $('#search_field').focus();
                    return false;
                },
                'r': refreshTree,
                'f2': function () {
                    this.rename(this.data.ui.last_selected || this.data.ui.hovered);
                    return false;
                }
            },
            json_data: {
                ajax: {
                    url: function (node) {
                        return sUrl + 'action=load_tree&id=0&search=' + encodeURIComponent(Search.string());
                    }
                }
            },
            core: {
                animation: 200,
                progressive_render: true,
                open_parents: false,
                strings: {
                    loading: s2_lang.load_tree,
                    new_node: s2_lang.new_page
                }
            },
            plugins: ['json_data', 'dnd', 'ui', 'crrm', 'hotkeys']
        });
})
    .ajaxStart(function () {
        SetWait(true);
    })
    .ajaxStop(function () {
        SetWait(false);
    });

$.ajaxPrefilter(function (options, originalOptions, jqXHR) {
    var successCheck = function (data, textStatus, jqXHR) {
            checkAjaxStatus(jqXHR);
        },
        errorCheck = function (jqXHR, textStatus, errorThrown) {
            checkAjaxStatus(jqXHR);
        };

    options.success = options.success instanceof Array ? options.success.unshift(successCheck) : (typeof (options.success) == 'function' ? [successCheck, options.success] : successCheck);
    options.error = options.error instanceof Array ? options.error.unshift(errorCheck) : (typeof (options.error) == 'function' ? [errorCheck, options.error] : errorCheck);
});
