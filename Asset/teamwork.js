/**
 * TeamWork plugin — jQuery behaviors for multi-assignee management.
 *
 * Handles: + button toggle, type-ahead picker, individual/group/team
 * removal, and group/team expand/collapse.
 */
(function ($) {
    'use strict';

    var debounceTimer = null;

    // ---------------------------------------------------------------
    // Icon map for search result types (FA4 syntax)
    // ---------------------------------------------------------------
    var typeIcons = {
        user:  'fa fa-user',
        group: 'fa fa-users',
        team:  'fa fa-sitemap'
    };

    // ---------------------------------------------------------------
    // Helper: build role HTML based on assignment mode
    // ---------------------------------------------------------------
    function buildRoleHtml(assignee, mode) {
        if (mode === 'equal') {
            return '';
        }
        var aid = parseInt(assignee.id, 10);
        if (assignee.role) {
            return ' <span class="teamwork-role-label teamwork-role-clickable" data-assignee-id="' + aid + '">' +
                   escapeHtml(assignee.role) + '</span>';
        }
        return ' <a href="#" class="teamwork-set-role" data-assignee-id="' + aid + '">Set role</a>';
    }

    // ---------------------------------------------------------------
    // Helper: build the assignee list HTML from a flat assignees array
    // (same structure as the PHP template loop in show.php)
    // ---------------------------------------------------------------
    function renderAssigneeList($extension, assignees) {
        var $list = $extension.find('.teamwork-assignee-list');
        var mode = $extension.attr('data-assignment-mode') || 'equal';

        if (!$list.length) {
            $list = $('<ul class="teamwork-assignee-list"></ul>');
            $extension.find('.teamwork-picker').before($list);
        }

        $list.empty();

        if (!assignees || !assignees.length) {
            $list.remove();
            return;
        }

        // Group by source key (same logic as PHP)
        var grouped = {};
        var groupOrder = [];
        $.each(assignees, function (_, a) {
            var key = a.source_type + '_' + (a.source_id || 0);
            if (!grouped[key]) {
                grouped[key] = {
                    source_type: a.source_type,
                    source_id:   a.source_id,
                    members:     []
                };
                groupOrder.push(key);
            }
            grouped[key].members.push(a);
        });

        $.each(groupOrder, function (_, key) {
            var g = grouped[key];

            if (g.source_type === 'user') {
                $.each(g.members, function (_, a) {
                    var label = a.name || a.username;
                    var roleHtml = buildRoleHtml(a, mode);
                    $list.append(
                        '<li class="teamwork-assignee-item" data-assignee-id="' + parseInt(a.id, 10) + '">' +
                            '<i class="fa fa-user teamwork-type-icon"></i>' +
                            '<span class="teamwork-assignee-name">' + escapeHtml(label) + roleHtml + '</span>' +
                            '<a href="#" class="teamwork-remove-individual" data-assignee-id="' + parseInt(a.id, 10) + '" title="Remove">' +
                                '<i class="fa fa-times"></i>' +
                            '</a>' +
                        '</li>'
                    );
                });
            } else {
                // group or team
                var iconClass = g.source_type === 'group' ? 'fa fa-users' : 'fa fa-sitemap';
                var first = g.members[0];
                var firstLabel = first.name || first.username;
                var countLabel = g.members.length > 1 ? '&nbsp;(' + g.members.length + ')' : '';

                var membersHtml = '';
                $.each(g.members, function (_, a) {
                    membersHtml += '<li>' + escapeHtml(a.name || a.username) + '</li>';
                });

                $list.append(
                    '<li class="teamwork-group-row" data-source-type="' + g.source_type + '" data-source-id="' + parseInt(g.source_id, 10) + '">' +
                        '<a href="#" class="teamwork-group-toggle">' +
                            '<i class="' + iconClass + ' teamwork-type-icon"></i>' +
                            '<span class="teamwork-group-label">' + escapeHtml(firstLabel) + countLabel + '</span>' +
                            '<i class="fa fa-caret-down teamwork-caret"></i>' +
                        '</a>' +
                        '<a href="#" class="teamwork-remove-source" data-source-type="' + g.source_type + '" data-source-id="' + parseInt(g.source_id, 10) + '" title="Remove all">' +
                            '<i class="fa fa-times"></i>' +
                        '</a>' +
                        '<ul class="teamwork-group-members" style="display:none;">' + membersHtml + '</ul>' +
                    '</li>'
                );
            }
        });
    }

    // ---------------------------------------------------------------
    // Minimal HTML escaping
    // ---------------------------------------------------------------
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ---------------------------------------------------------------
    // + button toggle
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-add-btn', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.teamwork-extension').find('.teamwork-picker');
        $picker.toggle();
        if ($picker.is(':visible')) {
            $picker.find('.teamwork-picker-input').val('').focus();
            $picker.find('.teamwork-picker-results').empty().hide();
        }
    });

    // ---------------------------------------------------------------
    // Type-ahead: debounced keyup on the picker input
    // ---------------------------------------------------------------
    $(document).on('keyup', '.teamwork-picker-input', function () {
        var $input = $(this);
        var $picker = $input.closest('.teamwork-picker');
        var $results = $picker.find('.teamwork-picker-results');
        var searchUrl = $picker.attr('data-search-url');
        var query = $.trim($input.val());

        clearTimeout(debounceTimer);

        if (query.length < 1) {
            $results.empty().hide();
            return;
        }

        debounceTimer = setTimeout(function () {
            // Extract project_id from the search URL or pass via query param
            $.getJSON(searchUrl, { q: query }, function (data) {
                $results.empty();
                if (!data || !data.length) {
                    $results.hide();
                    return;
                }
                $.each(data, function (_, item) {
                    var iconClass = typeIcons[item.type] || 'fa fa-question';
                    $results.append(
                        '<li data-type="' + item.type + '" data-entity-id="' + parseInt(item.id, 10) + '">' +
                            '<i class="' + iconClass + '"></i>' +
                            '<span>' + escapeHtml(item.label) + '</span>' +
                        '</li>'
                    );
                });
                $results.show();
            });
        }, 200);
    });

    // ---------------------------------------------------------------
    // Picker result click: add assignee/group/team
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-picker-results li', function (e) {
        e.preventDefault();
        var $item = $(this);
        var $extension = $item.closest('.teamwork-extension');
        var $picker = $extension.find('.teamwork-picker');
        var addUrl = $extension.attr('data-add-url');
        var csrf = $extension.attr('data-csrf');

        $.post(addUrl, {
            csrf_token: csrf,
            type:       $item.attr('data-type'),
            entity_id:  $item.attr('data-entity-id')
        }, function (response) {
            renderAssigneeList($extension, response.assignees);
            $picker.hide();
            $picker.find('.teamwork-picker-input').val('');
            $picker.find('.teamwork-picker-results').empty().hide();
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Individual removal: click X on a single assignee
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-remove-individual', function (e) {
        e.preventDefault();
        var $link = $(this);
        var $extension = $link.closest('.teamwork-extension');
        var csrf = $extension.attr('data-csrf');
        var assigneeId = $link.attr('data-assignee-id');
        var removeUrl = $extension.attr('data-remove-url').replace('__AID__', assigneeId);

        $.post(removeUrl, { csrf_token: csrf }, function (response) {
            renderAssigneeList($extension, response.assignees);
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Group/team removal: click X on the group/team header
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-remove-source', function (e) {
        e.preventDefault();
        var $link = $(this);
        var $extension = $link.closest('.teamwork-extension');
        var csrf = $extension.attr('data-csrf');
        var sourceType = $link.attr('data-source-type');
        var sourceId = $link.attr('data-source-id');
        var url;

        if (sourceType === 'group') {
            url = $extension.attr('data-remove-group-url').replace('__GID__', sourceId);
        } else {
            url = $extension.attr('data-remove-team-url').replace('__TID__', sourceId);
        }

        $.post(url, { csrf_token: csrf }, function (response) {
            renderAssigneeList($extension, response.assignees);
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Expand/collapse group/team members
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-group-toggle', function (e) {
        e.preventDefault();
        var $toggle = $(this);
        var $row = $toggle.closest('.teamwork-group-row');
        var $members = $row.find('.teamwork-group-members');
        var $caret = $toggle.find('.teamwork-caret');

        $members.slideToggle(150);
        $caret.toggleClass('fa-caret-down fa-caret-up');
    });

    // ---------------------------------------------------------------
    // Role label click: show inline role dropdown
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-role-clickable, .teamwork-set-role', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $label = $(this);
        var $extension = $label.closest('.teamwork-extension');
        var mode = $extension.attr('data-assignment-mode') || 'equal';
        var customRolesStr = $extension.attr('data-custom-roles') || '';
        var assigneeId = $label.attr('data-assignee-id');

        // Build role options based on mode
        var roles = [];
        if (mode === 'primary_helpers') {
            roles = ['Primary', 'Helper'];
        } else if (mode === 'custom') {
            roles = ['Primary', 'Helper', 'Reviewer'];
            if (customRolesStr) {
                var customs = customRolesStr.split(',');
                $.each(customs, function (_, r) {
                    var trimmed = $.trim(r);
                    if (trimmed) {
                        roles.push(trimmed);
                    }
                });
            }
        }

        // Remove any existing dropdown
        $('.teamwork-role-dropdown').remove();

        // Build dropdown
        var $dropdown = $('<div class="teamwork-role-dropdown" data-assignee-id="' + parseInt(assigneeId, 10) + '"></div>');
        $.each(roles, function (_, role) {
            $dropdown.append('<a href="#" class="teamwork-role-option" data-role="' + escapeHtml(role) + '">' + escapeHtml(role) + '</a>');
        });
        $dropdown.append('<a href="#" class="teamwork-role-option teamwork-role-clear" data-role=""><em>Clear role</em></a>');

        // Position dropdown below the clicked element
        var offset = $label.offset();
        $dropdown.css({
            top: offset.top + $label.outerHeight() + 2,
            left: offset.left
        });

        $('body').append($dropdown);
    });

    // ---------------------------------------------------------------
    // Role option click: AJAX save role
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-role-option', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $option = $(this);
        var $dropdown = $option.closest('.teamwork-role-dropdown');
        var assigneeId = $dropdown.attr('data-assignee-id');
        var role = $option.attr('data-role');
        var $extension = $('.teamwork-extension');
        var csrf = $extension.attr('data-csrf');
        var updateUrl = $extension.attr('data-update-role-url');

        $.post(updateUrl, {
            csrf_token: csrf,
            assignee_id: assigneeId,
            role: role
        }, function (response) {
            $dropdown.remove();
            renderAssigneeList($extension, response.assignees);
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Outside click: hide picker and role dropdown
    // ---------------------------------------------------------------
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.teamwork-extension').length) {
            $('.teamwork-picker').hide();
            $('.teamwork-picker-input').val('');
            $('.teamwork-picker-results').empty().hide();
        }
        if (!$(e.target).closest('.teamwork-role-dropdown').length &&
            !$(e.target).closest('.teamwork-role-clickable').length &&
            !$(e.target).closest('.teamwork-set-role').length) {
            $('.teamwork-role-dropdown').remove();
        }
    });

    // ===============================================================
    // Team Management — CRUD and member management
    // ===============================================================

    var teamDebounceTimer = null;

    // ---------------------------------------------------------------
    // Create team form submit
    // ---------------------------------------------------------------
    $(document).on('submit', '.teamwork-create-team-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var name = $.trim($form.find('[name="name"]').val());
        if (!name) return;

        $.post($form.attr('data-create-url'), {
            csrf_token: $form.attr('data-csrf'),
            name: name
        }, function (response) {
            if (response.error) {
                alert(response.error);
                return;
            }
            window.location.reload();
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Rename team (inline edit)
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-team-rename', function (e) {
        e.preventDefault();
        var $card = $(this).closest('.teamwork-team-card');
        var $nameSpan = $card.find('.teamwork-team-name');
        var currentName = $nameSpan.text();
        var $list = $card.closest('.teamwork-team-list');
        var renameUrl = $list.attr('data-rename-url');
        var csrf = $list.attr('data-csrf');
        var teamId = $card.attr('data-team-id');

        // Replace span with input
        var $input = $('<input type="text" class="teamwork-team-name-input teamwork-rename-input" value="' + escapeHtml(currentName) + '">');
        $nameSpan.replaceWith($input);
        $input.focus().select();

        function doRename() {
            var newName = $.trim($input.val());
            if (!newName || newName === currentName) {
                $input.replaceWith('<span class="teamwork-team-name">' + escapeHtml(currentName) + '</span>');
                return;
            }
            $.post(renameUrl, {
                csrf_token: csrf,
                team_id: teamId,
                name: newName
            }, function (response) {
                if (response.error) {
                    alert(response.error);
                    $input.replaceWith('<span class="teamwork-team-name">' + escapeHtml(currentName) + '</span>');
                    return;
                }
                $input.replaceWith('<span class="teamwork-team-name">' + escapeHtml(newName) + '</span>');
            }, 'json');
        }

        $input.on('keydown', function (ev) {
            if (ev.which === 13) { // Enter
                ev.preventDefault();
                doRename();
            } else if (ev.which === 27) { // Escape
                $input.replaceWith('<span class="teamwork-team-name">' + escapeHtml(currentName) + '</span>');
            }
        });

        $input.on('blur', function () {
            doRename();
        });
    });

    // ---------------------------------------------------------------
    // Delete team
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-team-delete', function (e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this team?')) return;

        var $card = $(this).closest('.teamwork-team-card');
        var $list = $card.closest('.teamwork-team-list');
        var removeUrl = $list.attr('data-remove-url');
        var csrf = $list.attr('data-csrf');
        var teamId = $card.attr('data-team-id');

        $.post(removeUrl, {
            csrf_token: csrf,
            team_id: teamId
        }, function (response) {
            if (response.error) {
                alert(response.error);
                return;
            }
            $card.fadeOut(200, function () { $card.remove(); });
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Toggle team members (expand/collapse)
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-team-toggle', function (e) {
        e.preventDefault();
        var $card = $(this).closest('.teamwork-team-card');
        var $body = $card.find('.teamwork-team-body');
        var $caret = $(this).find('.fa');

        $body.slideToggle(150);
        $caret.toggleClass('fa-caret-down fa-caret-up');
    });

    // ---------------------------------------------------------------
    // Add member type-ahead: debounced search
    // ---------------------------------------------------------------
    $(document).on('keyup', '.teamwork-member-search', function () {
        var $input = $(this);
        var $addMember = $input.closest('.teamwork-add-member');
        var $results = $addMember.find('.teamwork-member-results');
        var $list = $input.closest('.teamwork-team-list');
        var searchUrl = $list.attr('data-search-members-url');
        var query = $.trim($input.val());

        clearTimeout(teamDebounceTimer);

        if (query.length < 1) {
            $results.empty().hide();
            return;
        }

        teamDebounceTimer = setTimeout(function () {
            $.getJSON(searchUrl, { q: query }, function (data) {
                $results.empty();
                if (!data || !data.length) {
                    $results.hide();
                    return;
                }
                $.each(data, function (_, item) {
                    $results.append(
                        '<a href="#" class="teamwork-member-result-item" data-user-id="' + parseInt(item.id, 10) + '">' +
                            escapeHtml(item.label) +
                        '</a>'
                    );
                });
                $results.show();
            });
        }, 300);
    });

    // ---------------------------------------------------------------
    // Add member: click search result
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-member-result-item', function (e) {
        e.preventDefault();
        var $item = $(this);
        var userId = $item.attr('data-user-id');
        var $card = $item.closest('.teamwork-team-card');
        var $list = $card.closest('.teamwork-team-list');
        var addMemberUrl = $list.attr('data-add-member-url');
        var csrf = $list.attr('data-csrf');
        var teamId = $card.attr('data-team-id');

        $.post(addMemberUrl, {
            csrf_token: csrf,
            team_id: teamId,
            user_id: userId
        }, function (response) {
            if (response.error) {
                alert(response.error);
                return;
            }
            // Re-render member list from response
            var $body = $card.find('.teamwork-team-body');
            var $memberList = $body.find('.teamwork-member-list');
            $memberList.empty();
            $.each(response.members, function (_, m) {
                var label = m.name || m.username;
                $memberList.append(
                    '<li class="teamwork-member-item" data-user-id="' + parseInt(m.user_id, 10) + '">' +
                        '<span>' + escapeHtml(label) + '</span>' +
                        '<a href="#" class="teamwork-member-remove" title="Remove"><i class="fa fa-times"></i></a>' +
                    '</li>'
                );
            });
            // Update member count
            var $count = $card.find('.teamwork-team-count');
            $count.text('(' + response.members.length + ' members)');
            // Clear search
            $body.find('.teamwork-member-search').val('');
            $body.find('.teamwork-member-results').empty().hide();
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Remove member
    // ---------------------------------------------------------------
    $(document).on('click', '.teamwork-member-remove', function (e) {
        e.preventDefault();
        var $link = $(this);
        var $card = $link.closest('.teamwork-team-card');
        var $list = $card.closest('.teamwork-team-list');
        var removeMemberUrl = $list.attr('data-remove-member-url');
        var csrf = $list.attr('data-csrf');
        var teamId = $card.attr('data-team-id');
        var userId = $link.closest('.teamwork-member-item').attr('data-user-id');

        $.post(removeMemberUrl, {
            csrf_token: csrf,
            team_id: teamId,
            user_id: userId
        }, function (response) {
            if (response.error) {
                alert(response.error);
                return;
            }
            // Re-render member list from response
            var $body = $card.find('.teamwork-team-body');
            var $memberList = $body.find('.teamwork-member-list');
            $memberList.empty();
            $.each(response.members, function (_, m) {
                var label = m.name || m.username;
                $memberList.append(
                    '<li class="teamwork-member-item" data-user-id="' + parseInt(m.user_id, 10) + '">' +
                        '<span>' + escapeHtml(label) + '</span>' +
                        '<a href="#" class="teamwork-member-remove" title="Remove"><i class="fa fa-times"></i></a>' +
                    '</li>'
                );
            });
            // Update member count
            var $count = $card.find('.teamwork-team-count');
            $count.text('(' + response.members.length + ' members)');
        }, 'json');
    });

    // ---------------------------------------------------------------
    // Hide member search results on outside click
    // ---------------------------------------------------------------
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.teamwork-add-member').length) {
            $('.teamwork-member-results').empty().hide();
        }
    });

})(jQuery);
