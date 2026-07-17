'use strict';

$(document).ready(function () {
    loadGuide();
});

function loadGuide() {
    var fd = new FormData();
    fd.append('request', 'get-guide');

    $.ajax({
        url: 'controller/RoleGuideController.php',
        type: 'POST',
        data: fd,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function () {
            $('#guide_container').html(
                '<div class="text-center py-5 text-muted">' +
                '<i class="bx bx-loader-alt bx-spin bx-lg"></i>' +
                '<p class="mt-2">Loading permissions…</p></div>'
            );
        },
        success: function (r) {
            if (!r.success) {
                $('#guide_container').html('<p class="text-danger p-3">' + (r.error || 'Failed to load.') + '</p>');
                return;
            }

            var roles    = r.roles;    // { "1": "Admin", "2": "HR", ... }
            var pageMap  = r.page_map; // { "80": ["Dashboard","Dashboard",false], ... }
            var pages    = r.pages;    // { "1": [80,81,...], "2": [...], ... }
            var roleIds  = Object.keys(roles);

            // ── Summary badge strip ──────────────────────────────────────
            var summaryHtml = '<div class="row g-3 mb-4">';
            roleIds.forEach(function (rid) {
                var cnt = (pages[rid] || []).length;
                summaryHtml +=
                    '<div class="col-6 col-sm-4 col-lg-2">' +
                    '<div class="card text-center border-primary h-100">' +
                    '<div class="card-body py-3">' +
                    '<h4 class="mb-0 text-primary">' + cnt + '</h4>' +
                    '<small class="text-muted fw-semibold">' + roles[rid] + '</small>' +
                    '</div></div></div>';
            });
            summaryHtml += '</div>';

            // ── Build matrix table ───────────────────────────────────────
            // Column headers
            var thead = '<thead class="table-dark sticky-top"><tr>' +
                        '<th style="min-width:200px">Module / Page</th>';
            roleIds.forEach(function (rid) {
                thead += '<th class="text-center">' + roles[rid] + '</th>';
            });
            thead += '</tr></thead>';

            // Row body — iterate page_map in server-returned order
            var tbody = '<tbody>';
            var lastGroup = null;
            var pageIds = Object.keys(pageMap);

            pageIds.forEach(function (pid) {
                var entry   = pageMap[pid];
                var label   = entry[0];
                var group   = entry[1];
                var isSub   = entry[2];

                // Group separator row
                if (group !== lastGroup) {
                    tbody +=
                        '<tr class="table-secondary">' +
                        '<td colspan="' + (roleIds.length + 1) + '" ' +
                        'class="fw-bold text-uppercase small py-1 px-3" ' +
                        'style="letter-spacing:.05em;font-size:.72rem">' +
                        '<i class="bx bx-folder-open me-1"></i>' + group +
                        '</td></tr>';
                    lastGroup = group;
                }

                // Module row
                var nameCell = isSub
                    ? '<td class="ps-4 text-muted" style="font-size:.875rem">' +
                      '<i class="bx bx-subdirectory-right me-1 text-muted"></i>' + label + '</td>'
                    : '<td class="fw-semibold">' + label + '</td>';

                tbody += '<tr>' + nameCell;

                roleIds.forEach(function (rid) {
                    var hasAccess = (pages[rid] || []).indexOf(parseInt(pid)) !== -1;
                    if (hasAccess) {
                        tbody += '<td class="text-center">' +
                                 '<span class="badge bg-success rounded-pill px-2">' +
                                 '<i class="bx bx-check"></i></span></td>';
                    } else {
                        tbody += '<td class="text-center text-muted" style="opacity:.3">—</td>';
                    }
                });

                tbody += '</tr>';
            });

            tbody += '</tbody>';

            var tableHtml =
                '<div class="card">' +
                '<div class="card-header d-flex justify-content-between align-items-center">' +
                '<h6 class="mb-0"><i class="bx bx-list-check me-1 text-primary"></i>Role &amp; Access Matrix</h6>' +
                '<small class="text-muted">Auto-generated from live config · ' + r.generated + '</small>' +
                '</div>' +
                '<div class="table-responsive">' +
                '<table class="table table-sm table-bordered table-hover mb-0" style="font-size:.875rem">' +
                thead + tbody +
                '</table></div></div>';

            $('#guide_container').html(summaryHtml + tableHtml);
        },
        error: function () {
            $('#guide_container').html('<p class="text-danger p-3">Failed to load the Role &amp; Access Guide.</p>');
        }
    });
}
