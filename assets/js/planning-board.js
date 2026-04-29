/**
 * Planning Board v2.0 — Float-like Resource Timeline
 * Lane-packing, task sub-bars, live Perfex data, HR integration
 *
 * @since 2.0.0
 */
var PlanningBoard = (function () {
    'use strict';

    var config = {
        baseUrl:    '',
        apiUrl:     '',
        csrfToken:  '',
        canEdit:    false,
        canDelete:  false,
        canCreate:  false,
        isEmployee: false,
        ownStaffId: null,
        lang:       {}
    };

    var state = {
        currentView:    'month',
        startDate:      null,
        endDate:        null,
        staff:          [],
        allocations:    [],
        timeOff:        [],
        projects:       [],
        capacity:       {},
        cellWidth:      40,
        isLoading:      false,
        filterStaff:    null,
        filterProject:  null,
        collapsedStaff: {}
    };

    var $boardBody, $datesContainer;

    // =========================================================================
    // INIT
    // =========================================================================

    function init(options) {
        $.extend(config, options);

        var today = new Date();
        if (state.currentView === 'month') {
            state.startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            state.endDate   = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else {
            var day  = today.getDay();
            var diff = today.getDate() - day + (day === 0 ? -6 : 1);
            state.startDate = new Date(today.setDate(diff));
            state.endDate   = new Date(state.startDate);
            state.endDate.setDate(state.endDate.getDate() + 6);
        }

        $boardBody      = $('#rb-board-body');
        $datesContainer = $('#rb-dates-container');

        bindEvents();
        bindScrollSync();
        updateDateInputs();
        loadBoardData();
    }

    // =========================================================================
    // SCROLL SYNC
    // =========================================================================

    function bindScrollSync() {
        $boardBody.on('scroll', function () {
            $datesContainer.css('transform', 'translateX(-' + $(this).scrollLeft() + 'px)');
        });
    }

    // =========================================================================
    // EVENTS
    // =========================================================================

    function bindEvents() {
        $('#rb-prev').on('click', navigatePrev);
        $('#rb-next').on('click', navigateNext);
        $('#rb-today').on('click', navigateToday);
        $('#rb-apply-dates').on('click', applyCustomDateRange);

        $('.rb-view-btn').on('click', function () { changeView($(this).data('view')); });

        $('#rb-filter-staff').on('change', function () {
            state.filterStaff = $(this).val() || null;
            renderBoard();
        });

        $('#rb-filter-project').on('change', function () {
            state.filterProject = $(this).val() || null;
            renderBoard();
        });

        if (config.canCreate) {
            $('#rb-add-allocation').on('click', function () { openAllocationModal(); });
        }

        $('#rb-save-allocation').on('click', saveAllocation);
        $('#rb-save-timeoff').on('click', saveTimeOff);
        $('#rb-delete-allocation').on('click', deleteAllocation);

        $('#rb-alloc-start, #rb-alloc-end, #rb-alloc-hours').on('change', updateTotalHoursDisplay);

        $('#rb-alloc-project').on('changed.bs.select change', function () {
            var pid = $(this).val();
            if (pid) {
                fetchProjectDates(pid);
                loadTasksDropdown(pid, null);
            } else {
                $('#rb-alloc-task').empty()
                    .append('<option value="">— kein Task —</option>')
                    .selectpicker('refresh');
            }
        });

        $('#rb-alloc-task').on('changed.bs.select change', function () {
            var tid = $(this).val();
            if (tid) fetchTaskDates(tid);
        });

        $boardBody.on('dblclick', '.rb-allocation[data-id]', function (e) {
            e.stopPropagation();
            var rawId = $(this).data('id');
            if (!config.isEmployee && (typeof rawId === 'number' || /^\d+$/.test(rawId))) {
                openAllocationModal(rawId);
            }
        });

        if (config.canCreate) {
            $boardBody.on('dblclick', '.rb-lane-cell', function (e) {
                if ($(e.target).closest('.rb-allocation').length) return;
                var staffId = $(this).closest('.rb-staff-group').data('staff-id');
                var date    = $(this).data('date');
                openAllocationModal(null, staffId, date);
            });
        }

        $boardBody.on('click', '.rb-staff-toggle', function () {
            var $group  = $(this).closest('.rb-staff-group');
            var staffId = $group.data('staff-id');
            state.collapsedStaff[staffId] = !state.collapsedStaff[staffId];
            $group.toggleClass('rb-collapsed', !!state.collapsedStaff[staffId]);
        });

        if (config.canEdit) {
            $boardBody.on('dblclick', '.rb-inline-editable', function (e) {
                e.stopPropagation();
                var $el    = $(this);
                var taskId = $el.data('task-id');
                var hours  = $el.data('hours') || '';
                var $input = $('<input type="number" min="0.5" step="0.5" style="width:60px;padding:0 2px" value="' + hours + '">');
                $el.replaceWith($input);
                $input.focus().select();
                $input.on('blur keydown', function (ev) {
                    if (ev.type === 'keydown' && ev.key !== 'Enter' && ev.key !== 'Escape') return;
                    var val = parseFloat($input.val());
                    $input.replaceWith($el);
                    if (!isNaN(val) && val > 0 && ev.key !== 'Escape') {
                        $.ajax({
                            url:  config.apiUrl + '/api_update_task_hours',
                            type: 'POST',
                            data: { task_id: taskId, estimated_hours: val },
                            dataType: 'json',
                            success: function (r) {
                                if (r.success) { alert_float('success', 'Stunden aktualisiert'); loadBoardData(); }
                            }
                        });
                    }
                });
            });
        }

        $('.datepicker').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true });
    }

    // =========================================================================
    // DATE NAVIGATION
    // =========================================================================

    function updateDateInputs() {
        $('#rb-date-from').val(formatDate(state.startDate));
        $('#rb-date-to').val(formatDate(state.endDate));
    }

    function applyCustomDateRange() {
        var from = $('#rb-date-from').val();
        var to   = $('#rb-date-to').val();
        if (from && to) {
            state.startDate = new Date(from);
            state.endDate   = new Date(to);
            if (state.startDate > state.endDate) {
                alert_float('warning', 'Startdatum muss vor Enddatum liegen');
                return;
            }
            loadBoardData();
        }
    }

    function navigatePrev() {
        if (state.currentView === 'month') {
            state.startDate.setMonth(state.startDate.getMonth() - 1);
            state.endDate = new Date(state.startDate.getFullYear(), state.startDate.getMonth() + 1, 0);
        } else {
            state.startDate.setDate(state.startDate.getDate() - 7);
            state.endDate.setDate(state.endDate.getDate() - 7);
        }
        updateDateInputs();
        loadBoardData();
    }

    function navigateNext() {
        if (state.currentView === 'month') {
            state.startDate.setMonth(state.startDate.getMonth() + 1);
            state.endDate = new Date(state.startDate.getFullYear(), state.startDate.getMonth() + 1, 0);
        } else {
            state.startDate.setDate(state.startDate.getDate() + 7);
            state.endDate.setDate(state.endDate.getDate() + 7);
        }
        updateDateInputs();
        loadBoardData();
    }

    function navigateToday() {
        var today = new Date();
        if (state.currentView === 'month') {
            state.startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            state.endDate   = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else {
            var day  = today.getDay();
            var diff = today.getDate() - day + (day === 0 ? -6 : 1);
            state.startDate = new Date(today);
            state.startDate.setDate(diff);
            state.endDate   = new Date(state.startDate);
            state.endDate.setDate(state.endDate.getDate() + 6);
        }
        updateDateInputs();
        loadBoardData();
    }

    function changeView(view) {
        if (view === state.currentView) return;
        state.currentView = view;
        $('.rb-view-btn').removeClass('active');
        $('.rb-view-btn[data-view="' + view + '"]').addClass('active');
        navigateToday();
    }

    // =========================================================================
    // DATA LOADING
    // =========================================================================

    function loadBoardData() {
        if (state.isLoading) return;
        state.isLoading = true;
        showLoading(true);

        $.ajax({
            url:      config.apiUrl + '/api_board_data',
            type:     'GET',
            data:     { start_date: formatDate(state.startDate), end_date: formatDate(state.endDate) },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    state.staff       = response.data.staff       || [];
                    state.allocations = response.data.allocations || [];
                    state.timeOff     = response.data.time_off    || [];
                    state.projects    = response.data.projects    || [];
                    state.capacity    = response.data.capacity    || {};
                    renderBoard();
                    checkOverbooking();
                } else {
                    alert_float('danger', response.message || config.lang.errorLoading || 'Fehler');
                }
            },
            error: function () {
                alert_float('danger', config.lang.errorLoading || 'Fehler beim Laden');
            },
            complete: function () {
                state.isLoading = false;
                showLoading(false);
            }
        });
    }

    function showLoading(show) {
        $('#rb-loading').toggleClass('hidden', !show);
    }

    // =========================================================================
    // BOARD RENDERING
    // =========================================================================

    function renderBoard() {
        renderDateHeader();
        renderStaffGroups();
        syncBoardWidth();
        if (config.canEdit) initDragDrop();
    }

    function syncBoardWidth() {
        var dates = getDateRange(state.startDate, state.endDate, true);
        var w     = dates.length * state.cellWidth;
        $datesContainer.css('min-width', w + 'px');
        $boardBody.find('.rb-timeline-grid, .rb-lane').css('min-width', w + 'px');
    }

    // -------------------------------------------------------------------------
    // DATE HEADER
    // -------------------------------------------------------------------------

    function renderDateHeader() {
        var html     = '';
        var dates    = getDateRange(state.startDate, state.endDate, true);
        var dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        var today    = formatDate(new Date());

        // Month labels
        var months = {}, monthOrder = [];
        dates.forEach(function (d) {
            var k = d.substr(0, 7);
            if (!months[k]) {
                months[k] = 0;
                monthOrder.push(k);
                var parts = d.split('-');
                var dt    = new Date(+parts[0], +parts[1] - 1, 1);
                months[k + '_label'] = dt.toLocaleString('de-DE', { month: 'long', year: 'numeric' });
            }
            months[k]++;
        });

        html += '<div class="rb-month-row">';
        monthOrder.forEach(function (k) {
            html += '<div class="rb-month-cell" style="width:' + (months[k] * state.cellWidth) + 'px">' + months[k + '_label'] + '</div>';
        });
        html += '</div><div class="rb-day-row">';

        dates.forEach(function (date) {
            var d         = new Date(date + 'T00:00:00');
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isToday   = date === today;
            var cls       = 'rb-date-cell' + (isWeekend ? ' weekend' : '') + (isToday ? ' today' : '');
            html += '<div class="' + cls + '" data-date="' + date + '" style="width:' + state.cellWidth + 'px">';
            html += '<span class="rb-day-name">' + dayNames[d.getDay()] + '</span>';
            html += '<span class="rb-day-num">' + d.getDate() + '</span>';
            html += '</div>';
        });
        html += '</div>';

        $datesContainer.html(html);
    }

    // -------------------------------------------------------------------------
    // STAFF GROUPS
    // -------------------------------------------------------------------------

    function renderStaffGroups() {
        var html = '';
        var list = state.staff;

        if (state.filterStaff) {
            list = list.filter(function (s) { return String(s.staffid) === String(state.filterStaff); });
        }

        if (!list.length) {
            $boardBody.html('<div class="rb-empty-state"><i class="fa fa-users fa-2x"></i><p>' + (config.lang.noAllocations || 'Keine Mitarbeiter') + '</p></div>');
            return;
        }

        list.forEach(function (s) { html += renderStaffGroup(s); });
        $boardBody.html(html);
    }

    function renderStaffGroup(staff) {
        var staffId     = staff.staffid;
        var isOwn       = config.isEmployee && String(staffId) === String(config.ownStaffId);
        var isCollapsed = !!state.collapsedStaff[staffId];

        var allocs = state.allocations.filter(function (a) { return String(a.staff_id) === String(staffId); });
        if (state.filterProject) {
            allocs = allocs.filter(function (a) { return String(a.project_id) === String(state.filterProject); });
        }

        var projAllocs = allocs.filter(function (a) { return a.type === 'project'; })
            .sort(function (a, b) { return a.start_date > b.start_date ? 1 : -1; });
        var taskAllocs = allocs.filter(function (a) { return a.type === 'task'; })
            .sort(function (a, b) { return a.start_date > b.start_date ? 1 : -1; });

        var projLanes = packAllocationsIntoLanes(projAllocs);
        var taskLanes = packAllocationsIntoLanes(taskAllocs);

        var timeOffList = state.timeOff.filter(function (t) { return String(t.staff_id) === String(staffId); });

        var dates       = getDateRange(state.startDate, state.endDate, false);
        var totalH      = calculateStaffTotalHours(allocs, dates);
        var utilPct     = calcUtilizationPercent(staffId, totalH);

        var groupCls = 'rb-staff-group' + (isCollapsed ? ' rb-collapsed' : '') + (isOwn ? ' rb-own-row' : '');

        var html = '<div class="' + groupCls + '" data-staff-id="' + staffId + '">';

        // Staff header row
        html += '<div class="rb-staff-header">';
        html += '<span class="rb-staff-toggle"><i class="fa fa-chevron-' + (isCollapsed ? 'right' : 'down') + '"></i></span>';
        html += renderAvatar(staff);
        html += '<div class="rb-staff-info">';
        html += '<div class="rb-staff-name">' + escHtml(staff.firstname + ' ' + staff.lastname);
        if (isOwn) html += ' <span class="label label-info" style="font-size:10px">Ich</span>';
        html += '</div>';
        html += '<div class="rb-staff-util ' + utilClass(utilPct) + '">' + utilPct + '% &bull; ' + totalH + 'h</div>';
        html += '</div>';

        // Header timeline cells (greyed background)
        html += '<div class="rb-timeline-grid rb-header-timeline">';
        html += renderLaneCells(staffId, true);
        html += '</div>';

        html += '</div>'; // .rb-staff-header

        // Collapsible lanes
        html += '<div class="rb-lanes-wrapper">';

        if (timeOffList.length) {
            html += '<div class="rb-lane rb-timeoff-lane">';
            html += renderLaneCells(staffId, false);
            timeOffList.forEach(function (to) { html += renderTimeOffBar(to); });
            html += '</div>';
        }

        projLanes.forEach(function (lane, li) {
            html += '<div class="rb-lane rb-project-lane" data-lane="' + li + '">';
            html += renderLaneCells(staffId, false);
            lane.forEach(function (a) { html += renderAllocationBar(a); });
            html += '</div>';
        });

        taskLanes.forEach(function (lane, li) {
            html += '<div class="rb-lane rb-task-lane" data-lane="' + li + '">';
            html += renderLaneCells(staffId, false);
            lane.forEach(function (a) { html += renderAllocationBar(a); });
            html += '</div>';
        });

        if (!projLanes.length && !taskLanes.length) {
            html += '<div class="rb-lane rb-empty-lane">';
            html += renderLaneCells(staffId, false);
            html += '</div>';
        }

        html += '</div>'; // .rb-lanes-wrapper
        html += '</div>'; // .rb-staff-group

        return html;
    }

    function renderLaneCells(staffId, isHeader) {
        var html  = '';
        var dates = getDateRange(state.startDate, state.endDate, true);
        var today = formatDate(new Date());

        dates.forEach(function (date) {
            var d         = new Date(date + 'T00:00:00');
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isToday   = date === today;
            var cls       = 'rb-lane-cell' + (isWeekend ? ' weekend' : '') + (isToday ? ' today' : '');

            if (!isHeader && !isWeekend && staffId) {
                var capStatus = getCapacityStatus(staffId, date);
                if (capStatus === 'overbooked') cls += ' rb-cell-over';
                else if (capStatus === 'full')   cls += ' rb-cell-full';
                else if (capStatus === 'warning') cls += ' rb-cell-warn';
            }

            html += '<div class="' + cls + '" data-date="' + date + '" style="width:' + state.cellWidth + 'px"></div>';
        });
        return html;
    }

    function renderAvatar(staff) {
        var initials = getInitials(staff.firstname, staff.lastname);
        if (staff.profile_image) {
            return '<div class="rb-staff-avatar rb-has-image"><img src="' + config.baseUrl + 'uploads/staff_profile_images/' + staff.staffid + '/thumb_' + staff.profile_image + '" alt="' + escHtml(staff.firstname) + '"></div>';
        }
        return '<div class="rb-staff-avatar">' + escHtml(initials) + '</div>';
    }

    // =========================================================================
    // LANE PACKING
    // =========================================================================

    function packAllocationsIntoLanes(allocations) {
        var lanes = [];
        allocations.forEach(function (alloc) {
            var startD  = new Date((alloc.start_date || '1970-01-01') + 'T00:00:00');
            var placed  = false;
            for (var i = 0; i < lanes.length; i++) {
                var last    = lanes[i][lanes[i].length - 1];
                var lastEnd = new Date((last.end_date || '1970-01-01') + 'T00:00:00');
                if (startD > lastEnd) {
                    lanes[i].push(alloc);
                    placed = true;
                    break;
                }
            }
            if (!placed) lanes.push([alloc]);
        });
        return lanes;
    }

    // =========================================================================
    // BAR RENDERING
    // =========================================================================

    function renderAllocationBar(alloc) {
        var dates    = getDateRange(state.startDate, state.endDate, true);
        var visStart = formatDate(state.startDate);
        var visEnd   = formatDate(state.endDate);

        var sd = alloc.start_date < visStart ? visStart : alloc.start_date;
        var ed = alloc.end_date   > visEnd   ? visEnd   : alloc.end_date;
        if (ed < visStart || sd > visEnd) return '';

        var si = -1, ei = -1;
        for (var x = 0; x < dates.length; x++) {
            if (si === -1 && dates[x] >= sd) si = x;
            if (dates[x] <= ed) ei = x;
        }
        if (si === -1 || ei === -1 || ei < si) return '';

        var left  = si * state.cellWidth;
        var width = (ei - si + 1) * state.cellWidth - 4;

        var isTask    = alloc.type === 'task';
        var color     = alloc.project_color || (isTask ? '#5ba3d9' : '#3498db');
        var textColor = isLightColor(color) ? '#222' : '#fff';
        var isOver    = checkAllocationOverbooking(alloc);

        var rawId       = alloc.id;
        var isNumericId = typeof rawId === 'number' || /^\d+$/.test(String(rawId));

        var hoursLabel = isTask
            ? (alloc.daily_avg ? alloc.daily_avg + 'h/d' : (alloc.estimated_hours ? alloc.estimated_hours + 'h' : ''))
            : (alloc.hours_per_day ? alloc.hours_per_day + 'h/d' : '');

        var barLabel = isTask ? (alloc.task_name || 'Task') : (alloc.project_name || 'Projekt');
        var tooltip  = escHtml(barLabel + (hoursLabel ? ' · ' + hoursLabel : '') + (alloc.note ? ' — ' + alloc.note : ''));

        var cls = 'rb-allocation ' + (isTask ? 'rb-task-bar' : 'rb-project-bar');
        if (isOver) cls += ' rb-allocation-overbooked';

        var html = '<div class="' + cls + '" ';
        html += 'data-id="' + rawId + '" ';
        html += 'data-type="' + alloc.type + '" ';
        html += 'data-staff-id="' + alloc.staff_id + '" ';
        html += 'data-project-id="' + (alloc.project_id || '') + '" ';
        html += 'data-task-id="' + (alloc.task_id || '') + '" ';
        html += 'data-start="' + alloc.start_date + '" ';
        html += 'data-end="' + alloc.end_date + '" ';
        html += 'title="' + tooltip + '" ';
        html += 'style="left:' + left + 'px;width:' + width + 'px;background-color:' + color + ';color:' + textColor + '">';

        if (isOver) html += '<i class="fa fa-exclamation-triangle rb-overbooking-icon"></i> ';

        if (width > 20) {
            html += '<span class="rb-bar-label">' + escHtml(width > 60 ? barLabel : '') + '</span>';
        }
        if (hoursLabel && width > 80) {
            if (isTask && alloc.estimated_hours && config.canEdit) {
                html += '<span class="rb-bar-hours rb-inline-editable" data-task-id="' + alloc.task_id + '" data-hours="' + (alloc.estimated_hours || '') + '">' + hoursLabel + '</span>';
            } else {
                html += '<span class="rb-bar-hours">' + hoursLabel + '</span>';
            }
        }

        if (config.canEdit && isNumericId) {
            html += '<div class="rb-resize-handle rb-resize-left"></div>';
            html += '<div class="rb-resize-handle rb-resize-right"></div>';
        }

        html += '</div>';
        return html;
    }

    function renderTimeOffBar(to) {
        var dates    = getDateRange(state.startDate, state.endDate, true);
        var visStart = formatDate(state.startDate);
        var visEnd   = formatDate(state.endDate);
        var sd       = to.start_date || to.date_from;
        var ed       = to.end_date   || to.date_to;
        if (!sd || !ed || ed < visStart || sd > visEnd) return '';

        sd = sd < visStart ? visStart : sd;
        ed = ed > visEnd   ? visEnd   : ed;

        var si = -1, ei = -1;
        for (var x = 0; x < dates.length; x++) {
            if (si === -1 && dates[x] >= sd) si = x;
            if (dates[x] <= ed) ei = x;
        }
        if (si === -1 || ei === -1) return '';

        var left  = si * state.cellWidth;
        var width = (ei - si + 1) * state.cellWidth - 2;
        var type  = to.type || 'vacation';
        var label = { vacation: 'Urlaub', sick: 'Krank', holiday: 'Feiertag', other: 'Abwesend' }[type] || capitalizeFirst(type);

        return '<div class="rb-allocation rb-time-off rb-timeoff-' + escHtml(type) + '" ' +
               'title="' + label + ': ' + sd + ' – ' + ed + '" ' +
               'style="left:' + left + 'px;width:' + width + 'px">' +
               (width > 50 ? '<span class="rb-bar-label">' + label + '</span>' : '') +
               '</div>';
    }

    // =========================================================================
    // DRAG & DROP
    // =========================================================================

    function initDragDrop() {
        if (!config.canEdit || typeof interact === 'undefined') return;

        try { interact('.rb-project-bar, .rb-task-bar').unset(); } catch(e) {}

        interact('.rb-project-bar, .rb-task-bar')
            .draggable({
                inertia:    false,
                autoScroll: true,
                modifiers:  [interact.modifiers.restrictRect({ restriction: '.rb-board-body', endOnly: true })],
                listeners: {
                    start: function (e) { $(e.target).addClass('rb-dragging'); },
                    move:  function (e) {
                        var t = e.target;
                        var x = (parseFloat(t.getAttribute('data-x')) || 0) + e.dx;
                        var y = (parseFloat(t.getAttribute('data-y')) || 0) + e.dy;
                        t.style.transform = 'translate(' + x + 'px,' + y + 'px)';
                        t.setAttribute('data-x', x); t.setAttribute('data-y', y);
                    },
                    end: function (e) {
                        var $t = $(e.target);
                        $t.removeClass('rb-dragging');
                        var x = parseFloat(e.target.getAttribute('data-x')) || 0;
                        var y = parseFloat(e.target.getAttribute('data-y')) || 0;
                        var days = Math.round(x / state.cellWidth);
                        var rows = Math.round(y / 46);
                        var rawId = $t.data('id');
                        if ((days || rows) && typeof rawId === 'number') {
                            moveAllocation(rawId, days, rows);
                        } else {
                            e.target.style.transform = '';
                            e.target.removeAttribute('data-x');
                            e.target.removeAttribute('data-y');
                        }
                    }
                }
            })
            .resizable({
                edges: { left: '.rb-resize-left', right: '.rb-resize-right', top: false, bottom: false },
                listeners: {
                    move: function (e) {
                        var t = e.target;
                        var x = parseFloat(t.getAttribute('data-x')) || 0;
                        t.style.width = e.rect.width + 'px';
                        x += e.deltaRect.left;
                        t.style.transform = 'translateX(' + x + 'px)';
                        t.setAttribute('data-x', x);
                    },
                    end: function (e) {
                        var id  = $(e.target).data('id');
                        var x   = parseFloat(e.target.getAttribute('data-x')) || 0;
                        resizeAllocation(id, Math.round(x / state.cellWidth), Math.round(e.rect.width / state.cellWidth));
                    }
                }
            });
    }

    function moveAllocation(id, daysDelta, staffDelta) {
        var alloc = state.allocations.find(function (a) { return a.id == id; });
        if (!alloc) return;
        var newStart   = addDays(new Date(alloc.start_date), daysDelta);
        var newEnd     = addDays(new Date(alloc.end_date),   daysDelta);
        var newStaffId = alloc.staff_id;
        if (staffDelta) {
            var idx = state.staff.findIndex(function (s) { return s.staffid == alloc.staff_id; });
            var ni  = idx + staffDelta;
            if (ni >= 0 && ni < state.staff.length) newStaffId = state.staff[ni].staffid;
        }
        $.ajax({
            url: config.apiUrl + '/api_upsert_override', type: 'POST',
            data: { staff_id: newStaffId, project_id: alloc.project_id, task_id: alloc.task_id,
                    date_from: formatDate(newStart), date_to: formatDate(newEnd),
                    hours_per_day: alloc.hours_per_day, color: alloc.project_color, note: alloc.note },
            dataType: 'json',
            success: function (r) { if (r.success) alert_float('success', 'Verschoben'); loadBoardData(); },
            error:   function () { loadBoardData(); }
        });
    }

    function resizeAllocation(id, startDaysDelta, newDurationDays) {
        var alloc = state.allocations.find(function (a) { return a.id == id; });
        if (!alloc) { loadBoardData(); return; }
        var newStart = addDays(new Date(alloc.start_date), startDaysDelta);
        var newEnd   = addDays(newStart, Math.max(newDurationDays - 1, 0));
        $.ajax({
            url: config.apiUrl + '/api_upsert_override', type: 'POST',
            data: { staff_id: alloc.staff_id, project_id: alloc.project_id, task_id: alloc.task_id,
                    date_from: formatDate(newStart), date_to: formatDate(newEnd),
                    hours_per_day: alloc.hours_per_day, color: alloc.project_color, note: alloc.note },
            dataType: 'json',
            success: function () { loadBoardData(); },
            error:   function () { loadBoardData(); }
        });
    }

    // =========================================================================
    // ALLOCATION MODAL
    // =========================================================================

    function openAllocationModal(id, staffId, date) {
        if (config.isEmployee) return;

        var $modal = $('#rb-allocation-modal');
        $('#rb-allocation-form')[0].reset();
        $('#rb-overbooking-warning').hide();
        $('#rb-alloc-task').empty().append('<option value="">— kein Task —</option>').selectpicker('refresh');

        if (id) {
            var alloc = state.allocations.find(function (a) { return a.id == id; });
            if (!alloc) return;
            $('#rb-modal-title').text('Zuweisung bearbeiten');
            $('#rb-alloc-id').val(alloc.id);
            $('#rb-alloc-staff').selectpicker('val', alloc.staff_id);
            $('#rb-alloc-project').selectpicker('val', alloc.project_id || '');
            $('#rb-alloc-start').val(alloc.start_date);
            $('#rb-alloc-end').val(alloc.end_date);
            $('#rb-alloc-hours').val(alloc.hours_per_day || 8);
            $('#rb-alloc-weekends').prop('checked', alloc.include_weekends == 1);
            $('#rb-alloc-note').val(alloc.note || '');
            if (alloc.project_id) loadTasksDropdown(alloc.project_id, alloc.task_id);
            if (config.canDelete) $('#rb-delete-allocation').show();
        } else {
            $('#rb-modal-title').text('Neue Zuweisung');
            $('#rb-alloc-id').val('');
            $('#rb-delete-allocation').hide();
            if (staffId) $('#rb-alloc-staff').selectpicker('val', staffId);
            if (date)    { $('#rb-alloc-start').val(date); $('#rb-alloc-end').val(date); }
        }

        $('.selectpicker').selectpicker('refresh');
        updateTotalHoursDisplay();
        $modal.modal('show');
    }

    function fetchProjectDates(projectId) {
        $.ajax({
            url: config.apiUrl + '/api_get_project/' + projectId, type: 'GET', dataType: 'json',
            success: function (r) {
                if (r.project) {
                    if (r.project.start_date) $('#rb-alloc-start').val(r.project.start_date);
                    if (r.project.deadline)   $('#rb-alloc-end').val(r.project.deadline);
                    updateTotalHoursDisplay();
                }
            }
        });
    }

    function fetchTaskDates(taskId) {
        var ta = state.allocations.find(function (a) {
            return a.type === 'task' && String(a.task_id) === String(taskId);
        });
        if (ta) {
            if (ta.start_date) $('#rb-alloc-start').val(ta.start_date);
            if (ta.end_date)   $('#rb-alloc-end').val(ta.end_date);
            if (ta.estimated_hours) {
                var days = countWorkingDays(new Date($('#rb-alloc-start').val()), new Date($('#rb-alloc-end').val()));
                if (days > 0) $('#rb-alloc-hours').val(Math.round(ta.estimated_hours / days * 10) / 10);
            }
            updateTotalHoursDisplay();
        }
    }

    function loadTasksDropdown(projectId, selectedTaskId) {
        $.ajax({
            url:  config.apiUrl + '/api_get_tasks',
            type: 'GET',
            data: { project_id: projectId, staff_id: $('#rb-alloc-staff').val() || '' },
            dataType: 'json',
            success: function (r) {
                var $s = $('#rb-alloc-task');
                $s.empty().append('<option value="">— kein Task —</option>');
                (r.tasks || []).forEach(function (t) {
                    $s.append('<option value="' + t.id + '"' + (String(t.id) === String(selectedTaskId) ? ' selected' : '') + '>'
                        + escHtml(t.name) + (t.estimated_hours ? ' (' + t.estimated_hours + 'h)' : '') + '</option>');
                });
                $s.selectpicker('refresh');
            }
        });
    }

    function saveAllocation() {
        var id   = $('#rb-alloc-id').val();
        var data = {
            staff_id:   $('#rb-alloc-staff').val(),
            project_id: $('#rb-alloc-project').val() || null,
            task_id:    $('#rb-alloc-task').val() || null,
            date_from:  $('#rb-alloc-start').val(),
            date_to:    $('#rb-alloc-end').val(),
            hours_per_day: $('#rb-alloc-hours').val(),
            include_weekends: $('#rb-alloc-weekends').is(':checked') ? 1 : 0,
            note:       $('#rb-alloc-note').val()
        };

        if (!data.staff_id)              { alert_float('warning', 'Mitarbeiter wählen'); return; }
        if (!data.date_from||!data.date_to) { alert_float('warning', 'Datum wählen'); return; }

        if (id && /^\d+$/.test(id)) {
            // Update existing override
            data.id = id;
            $.ajax({
                url: config.apiUrl + '/api_upsert_override', type: 'POST', data: data, dataType: 'json',
                success: function (r) {
                    if (r.success) { $('#rb-allocation-modal').modal('hide'); alert_float('success', 'Aktualisiert'); loadBoardData(); }
                    else alert_float('danger', r.error || config.lang.errorSaving);
                },
                error: function () { alert_float('danger', config.lang.errorSaving); }
            });
        } else {
            // New: assign + override
            $.ajax({
                url: config.apiUrl + '/api_assign_member', type: 'POST',
                data: { staff_id: data.staff_id, project_id: data.project_id, task_id: data.task_id },
                dataType: 'json',
                success: function (r) {
                    if (r.success) {
                        $.ajax({
                            url: config.apiUrl + '/api_upsert_override', type: 'POST', data: data, dataType: 'json',
                            success: function () { $('#rb-allocation-modal').modal('hide'); alert_float('success', 'Erstellt'); loadBoardData(); },
                            error:   function () { $('#rb-allocation-modal').modal('hide'); loadBoardData(); }
                        });
                    } else {
                        alert_float('danger', r.error || config.lang.errorSaving);
                    }
                },
                error: function () { alert_float('danger', config.lang.errorSaving); }
            });
        }
    }

    function deleteAllocation() {
        var id = $('#rb-alloc-id').val();
        if (!id || !confirm(config.lang.confirmDelete || 'Wirklich löschen?')) return;

        var alloc = state.allocations.find(function (a) { return String(a.id) === String(id); });
        $.ajax({
            url:  config.apiUrl + '/api_remove_member', type: 'POST',
            data: { staff_id: alloc ? alloc.staff_id : '', project_id: alloc ? alloc.project_id : '', task_id: alloc ? alloc.task_id : '' },
            dataType: 'json',
            success: function () { $('#rb-allocation-modal').modal('hide'); alert_float('success', 'Gelöscht'); loadBoardData(); },
            error:   function () { alert_float('danger', 'Fehler beim Löschen'); }
        });
    }

    function saveTimeOff() {
        $.ajax({
            url: config.apiUrl + '/api_time_off', type: 'POST',
            data: { staff_id: $('#rb-timeoff-staff').val(), type: $('#rb-timeoff-type').val(),
                    date_from: $('#rb-timeoff-start').val(), date_to: $('#rb-timeoff-end').val(),
                    note: $('#rb-timeoff-note').val() },
            dataType: 'json',
            success: function (r) {
                if (r.success) { $('#rb-timeoff-modal').modal('hide'); alert_float('success', 'Abwesenheit eingetragen'); loadBoardData(); }
                else alert_float('danger', r.message || 'Fehler');
            },
            error: function () { alert_float('danger', 'Fehler'); }
        });
    }

    function updateTotalHoursDisplay() {
        var start = $('#rb-alloc-start').val();
        var end   = $('#rb-alloc-end').val();
        var hpd   = parseFloat($('#rb-alloc-hours').val()) || 0;
        if (start && end && hpd > 0) {
            var days = countWorkingDays(new Date(start), new Date(end));
            $('#rb-total-hours-display').text(days + ' Werktage × ' + hpd + 'h = ' + (days * hpd) + 'h gesamt');
        } else {
            $('#rb-total-hours-display').text('');
        }
    }

    // =========================================================================
    // OVERBOOKING
    // =========================================================================

    function checkOverbooking() {
        var list = [];
        state.staff.forEach(function (s) {
            var cap = state.capacity[String(s.staffid)];
            if (!cap) return;
            var cnt = Object.keys(cap).filter(function (d) { return cap[d].status === 'overbooked'; }).length;
            if (cnt) list.push(s.firstname + ' ' + s.lastname + ' (' + cnt + 'd)');
        });
        var $w = $('#rb-overbooking-warning');
        if (list.length) {
            $('#rb-overbooking-message').text(list.join(', '));
            $w.slideDown();
        } else {
            $w.slideUp();
        }
    }

    function checkAllocationOverbooking(alloc) {
        var cap = state.capacity[String(alloc.staff_id)];
        if (!cap) return false;
        var d   = new Date((alloc.start_date || '') + 'T00:00:00');
        var end = new Date((alloc.end_date   || '') + 'T00:00:00');
        while (d <= end) {
            var ds = formatDate(d);
            if (cap[ds] && cap[ds].status === 'overbooked') return true;
            d.setDate(d.getDate() + 1);
        }
        return false;
    }

    function getCapacityStatus(staffId, date) {
        var cap = state.capacity[String(staffId)];
        return (cap && cap[date]) ? (cap[date].status || 'ok') : 'ok';
    }

    // =========================================================================
    // CAPACITY SUMMARY
    // =========================================================================

    function calculateStaffTotalHours(allocs, visibleDates) {
        var total = 0;
        allocs.forEach(function (a) {
            getDateRange(a.start_date, a.end_date, false).forEach(function (d) {
                if (visibleDates.indexOf(d) !== -1) total += parseFloat(a.hours_per_day) || 0;
            });
        });
        return Math.round(total * 10) / 10;
    }

    function calcUtilizationPercent(staffId, allocatedHours) {
        var cap = state.capacity[String(staffId)];
        if (!cap) return 0;
        var avail = 0;
        getDateRange(state.startDate, state.endDate, false).forEach(function (d) {
            if (cap[d]) avail += cap[d].available || 0;
        });
        return avail > 0 ? Math.round((allocatedHours / avail) * 100) : 0;
    }

    function utilClass(pct) {
        if (pct > 100) return 'rb-util-over';
        if (pct >= 80)  return 'rb-util-high';
        if (pct >= 50)  return 'rb-util-mid';
        if (pct > 0)    return 'rb-util-low';
        return 'rb-util-empty';
    }

    // =========================================================================
    // UTILS
    // =========================================================================

    function formatDate(date) {
        var d = new Date(date);
        var m = '' + (d.getMonth() + 1), day = '' + d.getDate();
        if (m.length < 2) m = '0' + m;
        if (day.length < 2) day = '0' + day;
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function getDateRange(start, end, includeWeekends) {
        var dates = [], cur = new Date(start), endD = new Date(end);
        while (cur <= endD) {
            var dow = cur.getDay();
            if (includeWeekends || (dow !== 0 && dow !== 6)) dates.push(formatDate(cur));
            cur.setDate(cur.getDate() + 1);
        }
        return dates;
    }

    function addDays(date, days) { var r = new Date(date); r.setDate(r.getDate() + days); return r; }

    function countWorkingDays(start, end) {
        var n = 0, c = new Date(start);
        while (c <= end) { var d = c.getDay(); if (d !== 0 && d !== 6) n++; c.setDate(c.getDate() + 1); }
        return n;
    }

    function getInitials(fn, ln) {
        return ((fn ? fn.charAt(0).toUpperCase() : '') + (ln ? ln.charAt(0).toUpperCase() : '')) || '??';
    }

    function isLightColor(hex) {
        hex = (hex || '#3498db').replace('#', '');
        if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        var r = parseInt(hex.substr(0,2),16), g = parseInt(hex.substr(2,2),16), b = parseInt(hex.substr(4,2),16);
        return (r*299 + g*587 + b*114) / 1000 > 128;
    }

    function capitalizeFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return { init: init, reload: loadBoardData };

})();
