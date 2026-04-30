/**
 * PlanningBoard Render Module
 * All board rendering: date header, staff groups, lane-packing, allocation bars.
 *
 * Depends on: PB_Utils (must be loaded first)
 * Context set by: PB_Render.setContext(config, state)
 *
 * Layout structure per staff group:
 *
 *   .rb-staff-group
 *     .rb-staff-header
 *       .rb-staff-header-info  ← 200 px, position:sticky left:0
 *       .rb-header-cells       ← flex:1, background day cells
 *     .rb-lanes-wrapper        ← hidden when .rb-collapsed
 *       .rb-lane  (one per overlap level)
 *         .rb-lane-stub        ← 200 px spacer, sticky left:0
 *         .rb-lane-cells       ← position:relative, bars inside
 *
 * @since 2.0.0
 */
var PB_Render = (function () {
    'use strict';

    var _cfg = {}, _st = {};
    var CAPACITY_H = 64; // px — full lane height = 8 h/day visual scale

    function setContext(config, state) {
        _cfg = config;
        _st  = state;
    }

    // =========================================================================
    // PUBLIC ENTRY
    // =========================================================================

    function renderBoard() {
        _renderDateHeader();
        _renderStaffGroups();
        syncBoardWidth();
    }

    function syncBoardWidth() {
        var dates = PB_Utils.getDateRange(_st.startDate, _st.endDate, true);
        var w     = dates.length * _st.cellWidth;
        $('#rb-dates-container').css('min-width', w + 'px');
        $('#rb-board-body .rb-lane-cells, #rb-board-body .rb-header-cells').css('min-width', w + 'px');
    }

    // =========================================================================
    // DATE HEADER (above board)
    // =========================================================================

    function _renderDateHeader() {
        var html     = '';
        var dates    = PB_Utils.getDateRange(_st.startDate, _st.endDate, true);
        var dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        var today    = PB_Utils.formatDate(new Date());

        // Month spans
        var months = {}, monthOrder = [];
        dates.forEach(function (d) {
            var k = d.substr(0, 7);
            if (!months[k]) {
                months[k] = 0;
                monthOrder.push(k);
                var pts = d.split('-');
                var dt  = new Date(+pts[0], +pts[1] - 1, 1);
                months[k + '_label'] = dt.toLocaleString('de-DE', { month: 'long', year: 'numeric' });
            }
            months[k]++;
        });

        html += '<div class="rb-month-row">';
        monthOrder.forEach(function (k) {
            html += '<div class="rb-month-cell" style="width:' + (months[k] * _st.cellWidth) + 'px">'
                  + months[k + '_label'] + '</div>';
        });
        html += '</div>';

        html += '<div class="rb-day-row">';
        dates.forEach(function (date) {
            var d         = new Date(date + 'T00:00:00');
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isToday   = date === today;
            var cls       = 'rb-date-cell' + (isWeekend ? ' weekend' : '') + (isToday ? ' today' : '');
            html += '<div class="' + cls + '" data-date="' + date
                  + '" style="width:' + _st.cellWidth + 'px">'
                  + '<span class="rb-day-name">' + dayNames[d.getDay()] + '</span>'
                  + '<span class="rb-day-num">' + d.getDate() + '</span>'
                  + '</div>';
        });
        html += '</div>';

        $('#rb-dates-container').html(html);
    }

    // =========================================================================
    // STAFF GROUPS
    // =========================================================================

    function _renderStaffGroups() {
        var $body = $('#rb-board-body');
        var list  = _st.staff;

        if (_st.filterStaff) {
            list = list.filter(function (s) {
                return String(s.staffid) === String(_st.filterStaff);
            });
        }

        if (!list.length) {
            $body.html('<div class="rb-empty-state">'
                + '<i class="fa fa-users fa-2x"></i>'
                + '<p>' + ((_cfg.lang && _cfg.lang.noAllocations) || 'Keine Mitarbeiter') + '</p>'
                + '</div>');
            return;
        }

        var html = '';
        list.forEach(function (s) { html += _renderStaffGroup(s); });
        $body.html(html);
    }

    function _renderStaffGroup(staff) {
        var staffId     = staff.staffid;
        var isOwn       = _cfg.isEmployee && String(staffId) === String(_cfg.ownStaffId);
        var isCollapsed = !!_st.collapsedStaff[staffId];

        // Filter allocations
        var allocs = _st.allocations.filter(function (a) {
            return String(a.staff_id) === String(staffId);
        });
        if (_st.filterProject) {
            allocs = allocs.filter(function (a) {
                return String(a.project_id) === String(_st.filterProject);
            });
        }

        var projAllocs = allocs.filter(function (a) { return a.type === 'project'; })
                               .sort(function (a, b) { return a.start_date > b.start_date ? 1 : -1; });
        var taskAllocs = allocs.filter(function (a) { return a.type === 'task'; })
                               .sort(function (a, b) { return a.start_date > b.start_date ? 1 : -1; });

        var timeOffList = _st.timeOff.filter(function (t) {
            return String(t.staff_id) === String(staffId);
        });

        // Capacity summary
        var wdDates = PB_Utils.getDateRange(_st.startDate, _st.endDate, false);
        var totalH  = _calcStaffTotalHours(allocs, wdDates);
        var utilPct = _calcUtilization(staffId, totalH);

        // Employee own-row goals (up to 3 chips)
        var ownGoalsHtml = '';
        if (isOwn && allocs.length) {
            var chips = allocs.slice(0, 3).map(function (a) {
                var label = a.type === 'task' ? (a.task_name || 'Task') : (a.project_name || 'Projekt');
                var showH = a.type === 'task' ? a.hours_per_day : (a.is_override && a.hours_per_day > 0 ? a.hours_per_day : 0);
                var h     = showH ? ' · ' + showH + 'h/d' : '';
                return '<span class="rb-goal-chip">' + PB_Utils.escHtml(label + h) + '</span>';
            });
            if (allocs.length > 3) {
                chips.push('<span class="rb-goal-more">+' + (allocs.length - 3) + '</span>');
            }
            ownGoalsHtml = '<div class="rb-staff-goals">' + chips.join('') + '</div>';
        }

        var groupCls = 'rb-staff-group'
            + (isCollapsed ? ' rb-collapsed' : '')
            + (isOwn       ? ' rb-own-row'   : '');

        var chevron = isCollapsed ? 'right' : 'down';

        var html = '<div class="' + groupCls + '" data-staff-id="' + staffId + '">';

        // ── Staff header row ──────────────────────────────────────────────────
        html += '<div class="rb-staff-header">';

        // Left sticky panel (200 px)
        html += '<div class="rb-staff-header-info">';
        html += '<span class="rb-staff-toggle" title="Auf-/Zuklappen">'
              + '<i class="fa fa-chevron-' + chevron + '"></i></span>';
        html += _renderAvatar(staff);
        html += '<div class="rb-staff-info">';
        html += '<div class="rb-staff-name">' + PB_Utils.escHtml(staff.firstname + ' ' + staff.lastname);
        if (isOwn) {
            html += ' <span class="label label-info" style="font-size:9px;vertical-align:middle">Ich</span>';
        }
        html += '</div>';
        html += '<div class="rb-staff-util ' + _utilClass(utilPct) + '">'
              + utilPct + '% &bull; ' + totalH + 'h</div>';
        if (ownGoalsHtml) html += ownGoalsHtml;
        html += '</div>'; // .rb-staff-info
        html += '</div>'; // .rb-staff-header-info

        // Right: background day cells (for today highlight + scrolling alignment)
        html += '<div class="rb-header-cells">' + _renderDayCells(staffId, true) + '</div>';
        html += '</div>'; // .rb-staff-header

        // ── Collapsible lanes ─────────────────────────────────────────────────
        html += '<div class="rb-lanes-wrapper">';

        // Time off sits in its own thin lane above the capacity area
        if (timeOffList.length) {
            html += _buildLane('rb-timeoff-lane', staffId, [], timeOffList);
        }

        // Single capacity lane: project backgrounds + proportional task bars
        html += _buildCapacityLane(staffId, projAllocs, taskAllocs);

        html += '</div>'; // .rb-lanes-wrapper
        html += '</div>'; // .rb-staff-group

        return html;
    }

    /** Build a single lane div with stub + cells + bars. */
    function _buildLane(extraCls, staffId, allocBars, timeOffBars) {
        var h = '<div class="rb-lane ' + extraCls + '">';
        h += '<div class="rb-lane-stub"></div>';
        h += '<div class="rb-lane-cells">';
        h += _renderDayCells(staffId, false);
        allocBars.forEach(function (a) { h += _renderAllocationBar(a); });
        timeOffBars.forEach(function (t) { h += _renderTimeOffBar(t); });
        h += '</div></div>';
        return h;
    }

    /**
     * Build the capacity lane: project-colour backgrounds + proportional task bars.
     * Height = CAPACITY_H (64 px = 8 h). Bars are bottom-aligned so empty space
     * at the top visualises spare capacity for that day.
     */
    function _buildCapacityLane(staffId, projAllocs, taskAllocs) {
        var h = '<div class="rb-lane rb-capacity-lane">';
        h += '<div class="rb-lane-stub rb-capacity-stub">';
        h += '<span class="rb-capacity-scale">8h</span>';
        h += '</div>';
        h += '<div class="rb-lane-cells rb-capacity-cells">';
        h += _renderDayCells(staffId, false);
        // Project backgrounds (behind tasks, z-index 0)
        projAllocs.forEach(function (p) { h += _renderProjectBg(p); });
        // Task bars (proportional height, z-index 2)
        taskAllocs.forEach(function (t) { h += _renderAllocationBar(t); });
        h += '</div></div>';
        return h;
    }

    /**
     * Render a project as a full-height translucent background band.
     * Not a clickable bar — just visual context for which project covers which days.
     */
    function _renderProjectBg(alloc) {
        var dates    = PB_Utils.getDateRange(_st.startDate, _st.endDate, true);
        var visStart = PB_Utils.formatDate(_st.startDate);
        var visEnd   = PB_Utils.formatDate(_st.endDate);

        var sd = alloc.start_date < visStart ? visStart : alloc.start_date;
        var ed = alloc.end_date   > visEnd   ? visEnd   : alloc.end_date;
        if (ed < visStart || sd > visEnd) return '';

        var si = -1, ei = -1;
        for (var x = 0; x < dates.length; x++) {
            if (si === -1 && dates[x] >= sd) si = x;
            if (dates[x] <= ed) ei = x;
        }
        if (si === -1 || ei === -1 || ei < si) return '';

        var left  = si * _st.cellWidth;
        var width = (ei - si + 1) * _st.cellWidth;
        var color = alloc.project_color || '#95a5a6';

        return '<div class="rb-project-bg" '
            + 'title="' + PB_Utils.escHtml(alloc.project_name || 'Projekt') + '" '
            + 'data-project-id="' + (alloc.project_id || '') + '" '
            + 'data-staff-id="'   + alloc.staff_id           + '" '
            + 'style="left:' + left + 'px;width:' + width + 'px;'
            + 'background-color:' + color + '"></div>';
    }

    /** Generate day-cell divs for the full visible date range. */
    function _renderDayCells(staffId, isHeader) {
        var html  = '';
        var dates = PB_Utils.getDateRange(_st.startDate, _st.endDate, true);
        var today = PB_Utils.formatDate(new Date());

        dates.forEach(function (date) {
            var d         = new Date(date + 'T00:00:00');
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isToday   = date === today;
            var cls       = 'rb-lane-cell'
                + (isWeekend ? ' weekend' : '')
                + (isToday   ? ' today'   : '');
            if (!isHeader && !isWeekend && staffId) {
                var cs = _getCapacityStatus(staffId, date);
                if (cs === 'overbooked') cls += ' rb-cell-over';
                else if (cs === 'full')  cls += ' rb-cell-full';
                else if (cs === 'warn')  cls += ' rb-cell-warn';
            }
            html += '<div class="' + cls + '" data-date="' + date
                  + '" style="width:' + _st.cellWidth + 'px"></div>';
        });
        return html;
    }

    function _renderAvatar(staff) {
        var initials = PB_Utils.getInitials(staff.firstname, staff.lastname);
        if (staff.profile_image) {
            return '<div class="rb-staff-avatar rb-has-image"><img src="'
                + _cfg.baseUrl + 'uploads/staff_profile_images/'
                + staff.staffid + '/thumb_' + staff.profile_image
                + '" alt="' + PB_Utils.escHtml(staff.firstname) + '"></div>';
        }
        return '<div class="rb-staff-avatar">' + PB_Utils.escHtml(initials) + '</div>';
    }

    // =========================================================================
    // LANE PACKING (greedy interval scheduling)
    // =========================================================================

    function _packLanes(allocations) {
        var lanes = [];
        allocations.forEach(function (alloc) {
            var startD = new Date((alloc.start_date || '1970-01-01') + 'T00:00:00');
            var placed = false;
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
    // ALLOCATION BAR
    // =========================================================================

    function _renderAllocationBar(alloc) {
        // Projects are rendered as background bands by _renderProjectBg, not here
        if (alloc.type !== 'task') return '';

        var dates    = PB_Utils.getDateRange(_st.startDate, _st.endDate, true);
        var visStart = PB_Utils.formatDate(_st.startDate);
        var visEnd   = PB_Utils.formatDate(_st.endDate);

        // Clip to visible range
        var sd = alloc.start_date < visStart ? visStart : alloc.start_date;
        var ed = alloc.end_date   > visEnd   ? visEnd   : alloc.end_date;
        if (ed < visStart || sd > visEnd) return '';

        var si = -1, ei = -1;
        for (var x = 0; x < dates.length; x++) {
            if (si === -1 && dates[x] >= sd) si = x;
            if (dates[x] <= ed) ei = x;
        }
        if (si === -1 || ei === -1 || ei < si) return '';

        var left  = si * _st.cellWidth;
        var width = (ei - si + 1) * _st.cellWidth - 2;

        // ── Proportional height: 8 h/day = full lane, spare capacity visible at top ──
        var usable = CAPACITY_H - 8;  // 56 px = 8 h (4 px padding top + bottom)
        var hpd    = parseFloat(alloc.hours_per_day) || 8;
        var barH   = Math.max(Math.round(hpd / 8 * usable), 6); // min 6 px
        var barTop = CAPACITY_H - 4 - barH; // bottom-aligned

        var color     = alloc.project_color || '#5ba3d9';
        var textColor = PB_Utils.isLightColor(color) ? '#333' : '#fff';
        var isOver    = _checkAllocationOverbooking(alloc);

        var rawId       = alloc.id;
        var isNumericId = typeof rawId === 'number' || /^\d+$/.test(String(rawId));

        var hoursLabel = alloc.daily_avg       ? alloc.daily_avg + 'h/d'
                       : alloc.estimated_hours ? alloc.estimated_hours + 'h'
                       : (hpd ? hpd + 'h/d' : '');

        var barLabel = alloc.task_name || 'Task';
        var tooltip  = PB_Utils.escHtml(
            barLabel
            + (hoursLabel   ? ' · ' + hoursLabel : '')
            + (alloc.note   ? ' \u2014 ' + alloc.note  : '')
        );

        var cls = 'rb-allocation rb-task-bar';
        if (isOver) cls += ' rb-allocation-overbooked';

        var h = '<div class="' + cls + '" '
            + 'data-id="'         + rawId                    + '" '
            + 'data-type="task" '
            + 'data-staff-id="'   + alloc.staff_id           + '" '
            + 'data-project-id="' + (alloc.project_id  || '') + '" '
            + 'data-task-id="'    + (alloc.task_id     || '') + '" '
            + 'data-start="'      + alloc.start_date         + '" '
            + 'data-end="'        + alloc.end_date           + '" '
            + 'title="'           + tooltip                  + '" '
            + 'style="left:'      + left   + 'px;'
            + 'width:'            + width  + 'px;'
            + 'height:'           + barH   + 'px;'
            + 'top:'              + barTop + 'px;'
            + 'background-color:' + color  + ';color:' + textColor + '">';

        if (isOver) h += '<i class="fa fa-exclamation-triangle rb-overbooking-icon"></i> ';

        if (width > 20) {
            h += '<span class="rb-bar-label">'
               + PB_Utils.escHtml(width > 60 ? barLabel : '') + '</span>';
        }

        if (hoursLabel && width > 70) {
            if (alloc.estimated_hours && _cfg.canEdit) {
                h += '<span class="rb-bar-hours rb-inline-editable" '
                   + 'data-task-id="' + alloc.task_id + '" '
                   + 'data-hours="'   + (alloc.estimated_hours || '') + '">'
                   + hoursLabel + '</span>';
            } else {
                h += '<span class="rb-bar-hours">' + hoursLabel + '</span>';
            }
        }

        if (_cfg.canEdit && isNumericId) {
            h += '<div class="rb-resize-handle rb-resize-left"></div>'
               + '<div class="rb-resize-handle rb-resize-right"></div>';
        }

        h += '</div>';
        return h;
    }

    function _renderTimeOffBar(to) {
        var dates    = PB_Utils.getDateRange(_st.startDate, _st.endDate, true);
        var visStart = PB_Utils.formatDate(_st.startDate);
        var visEnd   = PB_Utils.formatDate(_st.endDate);
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

        var left  = si * _st.cellWidth;
        var width = (ei - si + 1) * _st.cellWidth - 2;
        var type  = to.type || 'vacation';
        var labels = { vacation: 'Urlaub', sick: 'Krank', holiday: 'Feiertag', other: 'Abwesend' };
        var label = labels[type] || PB_Utils.capitalizeFirst(type);

        return '<div class="rb-allocation rb-time-off rb-timeoff-' + PB_Utils.escHtml(type) + '" '
            + 'title="' + label + ': ' + sd + ' – ' + ed + '" '
            + 'style="left:' + left + 'px;width:' + width + 'px">'
            + (width > 50 ? '<span class="rb-bar-label">' + label + '</span>' : '')
            + '</div>';
    }

    // =========================================================================
    // CAPACITY HELPERS (read state, no API calls)
    // =========================================================================

    function _checkAllocationOverbooking(alloc) {
        var cap = _st.capacity[String(alloc.staff_id)];
        if (!cap) return false;
        var d   = new Date((alloc.start_date || '') + 'T00:00:00');
        var end = new Date((alloc.end_date   || '') + 'T00:00:00');
        while (d <= end) {
            var ds = PB_Utils.formatDate(d);
            if (cap[ds] && cap[ds].status === 'overbooked') return true;
            d.setDate(d.getDate() + 1);
        }
        return false;
    }

    function _getCapacityStatus(staffId, date) {
        var cap = _st.capacity[String(staffId)];
        return (cap && cap[date]) ? (cap[date].status || 'ok') : 'ok';
    }

    function _calcStaffTotalHours(allocs, visibleDates) {
        var total = 0;
        allocs.forEach(function (a) {
            if (a.type !== 'task') return; // projects are presence-only, no capacity hours
            PB_Utils.getDateRange(a.start_date, a.end_date, false).forEach(function (d) {
                if (visibleDates.indexOf(d) !== -1) total += parseFloat(a.hours_per_day) || 0;
            });
        });
        return Math.round(total * 10) / 10;
    }

    function _calcUtilization(staffId, allocatedHours) {
        var cap = _st.capacity[String(staffId)];
        if (!cap) return 0;
        var avail = 0;
        PB_Utils.getDateRange(_st.startDate, _st.endDate, false).forEach(function (d) {
            if (cap[d]) avail += cap[d].available || 0;
        });
        return avail > 0 ? Math.round((allocatedHours / avail) * 100) : 0;
    }

    function _utilClass(pct) {
        if (pct > 100) return 'rb-util-over';
        if (pct >= 80)  return 'rb-util-high';
        if (pct >= 50)  return 'rb-util-mid';
        if (pct > 0)    return 'rb-util-low';
        return 'rb-util-empty';
    }

    // =========================================================================
    // PUBLIC OVERBOOKING BANNER (board level, not modal)
    // =========================================================================

    function checkOverbooking() {
        var list = [];
        _st.staff.forEach(function (s) {
            var cap = _st.capacity[String(s.staffid)];
            if (!cap) return;
            var cnt = Object.keys(cap).filter(function (d) {
                return cap[d] && cap[d].status === 'overbooked';
            }).length;
            if (cnt) list.push(s.firstname + ' ' + s.lastname + ' (' + cnt + 'd)');
        });

        var $w = $('#rb-board-overbooking-warning');
        if (!$w.length) return;
        if (list.length) {
            $('#rb-board-overbooking-message').text(list.join(', '));
            $w.slideDown();
        } else {
            $w.slideUp();
        }
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return {
        setContext:       setContext,
        renderBoard:      renderBoard,
        syncBoardWidth:   syncBoardWidth,
        checkOverbooking: checkOverbooking
    };

})();
