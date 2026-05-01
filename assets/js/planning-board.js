/**
 * Planning Board v2.0 — Main Entry Point
 *
 * Module load order (enforced by the view):
 *   1. modules/pb-utils.js   — pure utilities
 *   2. modules/pb-render.js  — all board rendering
 *   3. modules/pb-drag.js    — interact.js drag & resize
 *   4. modules/pb-modal.js   — allocation & time-off dialogs
 *   5. planning-board.js     ← this file (wires everything together)
 *
 * @since 2.0.0
 */
var PlanningBoard = (function () {
    'use strict';

    // =========================================================================
    // SHARED CONFIG & STATE
    // =========================================================================

    var config = {
        baseUrl:    '',
        apiUrl:     '',
        csrfToken:  '',
        canEdit:    false,
        canDelete:  false,
        canCreate:  false,
        isEmployee: false,   // true → read-only (non-admin staff)
        ownStaffId: null,    // staffid of the logged-in user
        lang:       {}
    };

    var state = {
        currentView:    'month',   // 'week' | '2week' | 'month' | '2month'
        startDate:      null,
        endDate:        null,
        staff:          [],
        allocations:    [],
        timeOff:        [],
        holidayDates:   {},        // date string → true, for global column highlight
        projects:       [],
        capacity:       {},        // keyed by staffid → date → { available, allocated, status }
        cellWidth:      40,        // px per day column — recalculated dynamically
        isLoading:      false,
        filterStaff:    null,
        filterProject:  null,
        collapsedStaff: {}         // staffid → true when row is collapsed
    };

    // =========================================================================
    // INIT
    // =========================================================================

    function init(options) {
        $.extend(config, options);

        // Default start/end to current month
        var today = new Date();
        _setDateRangeForView(today);

        // Inject shared context into sub-modules
        PB_Render.setContext(config, state);
        PB_Drag.setContext(config, state, loadBoardData);
        PB_Modal.setContext(config, state, loadBoardData);

        _bindScrollSync();
        _bindEvents();
        _updateDateInputs();
        loadBoardData();
    }

    // =========================================================================
    // SCROLL SYNC  (board body → date header)
    // =========================================================================

    function _bindScrollSync() {
        $('#rb-board-body').on('scroll', function () {
            $('#rb-dates-container').css(
                'transform', 'translateX(-' + $(this).scrollLeft() + 'px)'
            );
        });
    }

    // =========================================================================
    // EVENT BINDINGS
    // =========================================================================

    function _bindEvents() {
        // Navigation
        $('#rb-prev').on('click',        _navigatePrev);
        $('#rb-next').on('click',        _navigateNext);
        $('#rb-today').on('click',       _navigateToday);
        $('#rb-apply-dates').on('click', _applyCustomDateRange);

        $('.rb-view-btn').on('click', function () {
            _changeView($(this).data('view'));
        });

        // Filters
        $('#rb-filter-staff').on('change', function () {
            state.filterStaff = $(this).val() || null;
            PB_Render.renderBoard();
        });
        $('#rb-filter-project').on('change', function () {
            state.filterProject = $(this).val() || null;
            PB_Render.renderBoard();
        });

        // "New allocation" button (admin only)
        if (config.canCreate) {
            $('#rb-add-allocation').on('click', function () {
                PB_Modal.openAllocationModal();
            });
        }

        // Modal action buttons
        $('#rb-save-allocation').on('click',           PB_Modal.saveAllocation);
        $('#rb-save-timeoff').on('click',              PB_Modal.saveTimeOff);
        $('#rb-delete-allocation').on('click',         PB_Modal.deleteAllocation);
        $('#rb-reassign-allocation').on('click',       PB_Modal.reassignAllocation);
        $('#rb-confirm-reassign').on('click',          PB_Modal.confirmReassign);
        $('#rb-cancel-reassign').on('click', function () { $('#rb-reassign-section').hide(); });
        $('#rb-remove-person-allocation').on('click',  PB_Modal.removePersonFromTask);

        // Live total hours recalculation in modal
        $('#rb-alloc-start, #rb-alloc-end, #rb-alloc-hours').on(
            'change', PB_Modal.updateTotalHoursDisplay
        );

        // Project selected → auto-fill dates + load task dropdown
        $('#rb-alloc-project').on('changed.bs.select change', function () {
            var pid = $(this).val();
            if (pid) {
                PB_Modal.fetchProjectDates(pid);
                PB_Modal.loadTasksDropdown(pid, null);
            } else {
                $('#rb-alloc-task')
                    .empty()
                    .append('<option value="">— kein Task —</option>')
                    .selectpicker('refresh');
            }
        });

        // Task selected → auto-fill dates + suggest hours
        $('#rb-alloc-task').on('changed.bs.select change', function () {
            var tid = $(this).val();
            if (tid) PB_Modal.fetchTaskDates(tid);
        });

        // Single click on allocation bar → edit modal (any user with edit/delete/create permission)
        // Note: rawId can be a numeric override ID (e.g. 42) or a synthetic task ID
        // (e.g. "t_5_123") — we open the modal for both cases.
        // Empty-cell dblclick still opens the create-new modal independently.
        if (config.canEdit || config.canDelete || config.canCreate) {
            $('#rb-board-body').on('click', '.rb-allocation[data-id]', function (e) {
                e.stopPropagation();
                var rawId = $(this).data('id');
                if (rawId !== undefined && rawId !== null && rawId !== '') {
                    PB_Modal.openAllocationModal(rawId);
                }
            });
        }

        // Double-click on empty lane cell → create modal (admin only)
        if (config.canCreate) {
            $('#rb-board-body').on('dblclick', '.rb-lane-cell', function (e) {
                if ($(e.target).closest('.rb-allocation').length) return;
                var staffId = $(this).closest('.rb-staff-group').data('staff-id');
                var date    = $(this).data('date');
                PB_Modal.openAllocationModal(null, staffId, date);
            });
        }

        // Collapse / expand staff row
        $('#rb-board-body').on('click', '.rb-staff-toggle', function () {
            var $group  = $(this).closest('.rb-staff-group');
            var staffId = $group.data('staff-id');
            state.collapsedStaff[staffId] = !state.collapsedStaff[staffId];
            $group.toggleClass('rb-collapsed', !!state.collapsedStaff[staffId]);
            $(this).find('i')
                .toggleClass('fa-chevron-down',  !state.collapsedStaff[staffId])
                .toggleClass('fa-chevron-right', !!state.collapsedStaff[staffId]);
        });

        // Inline hour editing for task bars
        PB_Modal.initInlineEdit();

        // Dismiss board-level overbooking banner
        $('body').on('click', '.rb-board-overbooking-close', function () {
            $('#rb-board-overbooking-warning').slideUp();
        });

        // ── Daily capacity hover tooltip on lane cells ───────────────────────
        var $tip = $('<div id="rb-cell-tooltip" class="rb-cell-tooltip"></div>').appendTo('body');
        $('#rb-board-body').on('mouseenter', '.rb-capacity-cells .rb-lane-cell', function (e) {
            var date    = $(this).data('date');
            var staffId = $(this).closest('.rb-staff-group').data('staff-id');
            if (!date || !staffId) return;
            var cap = state.capacity[String(staffId)];
            if (!cap || !cap[date]) return;
            var c      = cap[date];
            var avail  = (c.available  || 0).toFixed(1);
            var alloc  = (c.allocated  || 0).toFixed(1);
            var isOver = c.status === 'overbooked';
            var d      = new Date(date + 'T00:00:00');
            var dayFmt = d.toLocaleDateString('de-DE', { weekday: 'short', day: 'numeric', month: 'short' });
            $tip.html(
                '<div class="rb-tip-date">' + dayFmt + '</div>'
                + '<div class="rb-tip-row"><span>Verfügbar:</span><strong>' + avail + 'h</strong></div>'
                + '<div class="rb-tip-row' + (isOver ? ' rb-tip-over' : '') + '">'
                + '<span>Geplant:</span><strong>' + alloc + 'h</strong></div>'
                + (isOver ? '<div class="rb-tip-warn">⚠ Überbucht</div>' : '')
            ).css({ top: e.clientY - $tip.outerHeight() - 8, left: e.clientX + 12 }).show();
        }).on('mousemove', '.rb-capacity-cells .rb-lane-cell', function (e) {
            $tip.css({ top: e.clientY - $tip.outerHeight() - 8, left: e.clientX + 12 });
        }).on('mouseleave', '.rb-capacity-cells .rb-lane-cell', function () {
            $tip.hide();
        });

        // Init Bootstrap datepickers ONLY on our specific fields, not by class
        // (the .datepicker class also triggers jQuery UI datepicker if that library
        // is loaded, causing a double overlay.)
        var dpOpts = { format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true };
        if ($.fn.datepicker) {
            $('#rb-date-from, #rb-date-to, #rb-alloc-start, #rb-alloc-end').each(function () {
                // Destroy any jQuery UI datepicker that may have latched on first
                if ($.fn.datepicker && $(this).hasClass('hasDatepicker')) {
                    try { $(this).datepicker('destroy'); } catch(e) {}
                }
                $(this).datepicker(dpOpts);
            });
        }
    }

    // =========================================================================
    // NAVIGATION
    // =========================================================================

    /** Set start/end dates based on current view and an anchor date. */
    function _setDateRangeForView(anchor) {
        var d = anchor || new Date();
        switch (state.currentView) {
            case 'week':
                var dow  = d.getDay();
                var diff = d.getDate() - dow + (dow === 0 ? -6 : 1); // Mon
                state.startDate = new Date(d.getFullYear(), d.getMonth(), diff);
                state.endDate   = new Date(state.startDate);
                state.endDate.setDate(state.endDate.getDate() + 6);
                break;
            case '2week':
                var dow2  = d.getDay();
                var diff2 = d.getDate() - dow2 + (dow2 === 0 ? -6 : 1);
                state.startDate = new Date(d.getFullYear(), d.getMonth(), diff2);
                state.endDate   = new Date(state.startDate);
                state.endDate.setDate(state.endDate.getDate() + 13);
                break;
            case '2month':
                state.startDate = new Date(d.getFullYear(), d.getMonth(), 1);
                state.endDate   = new Date(d.getFullYear(), d.getMonth() + 2, 0);
                break;
            default: // month
                state.startDate = new Date(d.getFullYear(), d.getMonth(), 1);
                state.endDate   = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        }
        _recalcCellWidth();
    }

    /**
     * Compute cellWidth so visible columns fill (or closely fill) the board width.
     * Minimum 28 px/col, maximum 60 px/col.
     */
    function _recalcCellWidth() {
        var totalDays = PB_Utils.getDateRange(state.startDate, state.endDate, true).length;
        if (!totalDays) { state.cellWidth = 40; return; }
        var boardW = $('#rb-board-body').width() || ($('.rb-board-container').width() - 200) || 800;
        var avail  = Math.max(boardW - 200, 200); // subtract staff panel width
        var cw     = Math.floor(avail / totalDays);
        state.cellWidth = Math.min(Math.max(cw, 28), 60);
    }

    function _updateDateInputs() {
        $('#rb-date-from').val(PB_Utils.formatDate(state.startDate));
        $('#rb-date-to').val(PB_Utils.formatDate(state.endDate));
    }

    function _applyCustomDateRange() {
        var from = $('#rb-date-from').val();
        var to   = $('#rb-date-to').val();
        if (!from || !to) return;
        // Parse as local midnight
        var fp = from.split('-'), tp = to.split('-');
        var s  = new Date(+fp[0], +fp[1] - 1, +fp[2]);
        var e  = new Date(+tp[0], +tp[1] - 1, +tp[2]);
        if (s > e) { alert_float('warning', 'Startdatum muss vor Enddatum liegen'); return; }
        state.startDate = s;
        state.endDate   = e;
        _recalcCellWidth();
        loadBoardData();
    }

    function _navigatePrev() {
        var anchor = new Date(state.startDate);
        if (state.currentView === 'month') {
            anchor.setMonth(anchor.getMonth() - 1);
        } else if (state.currentView === '2month') {
            anchor.setMonth(anchor.getMonth() - 2);
        } else if (state.currentView === '2week') {
            anchor.setDate(anchor.getDate() - 14);
        } else { // week
            anchor.setDate(anchor.getDate() - 7);
        }
        _setDateRangeForView(anchor);
        _updateDateInputs();
        loadBoardData();
    }

    function _navigateNext() {
        var anchor = new Date(state.startDate);
        if (state.currentView === 'month') {
            anchor.setMonth(anchor.getMonth() + 1);
        } else if (state.currentView === '2month') {
            anchor.setMonth(anchor.getMonth() + 2);
        } else if (state.currentView === '2week') {
            anchor.setDate(anchor.getDate() + 14);
        } else { // week
            anchor.setDate(anchor.getDate() + 7);
        }
        _setDateRangeForView(anchor);
        _updateDateInputs();
        loadBoardData();
    }

    function _navigateToday() {
        _setDateRangeForView(new Date());
        _updateDateInputs();
        loadBoardData();
    }

    function _changeView(view) {
        if (view === state.currentView) return;
        state.currentView = view;
        $('.rb-view-btn').removeClass('active');
        $('.rb-view-btn[data-view="' + view + '"]').addClass('active');
        _navigateToday();
    }

    // =========================================================================
    // DATA LOADING
    // =========================================================================

    function loadBoardData() {
        if (state.isLoading) return;
        state.isLoading = true;
        _showLoading(true);

        $.ajax({
            url:      config.apiUrl + '/api_board_data',
            type:     'GET',
            data: {
                start_date: PB_Utils.formatDate(state.startDate),
                end_date:   PB_Utils.formatDate(state.endDate)
            },
            dataType: 'json',
            success:  function (response) {
                if (response && response.success) {
                    state.staff       = response.data.staff       || [];
                    state.allocations = response.data.allocations || [];
                    state.timeOff     = response.data.time_off    || [];
                    state.projects    = response.data.projects    || [];
                    state.capacity    = response.data.capacity    || {};

                    // Build global holiday date lookup from time_off entries
                    // that have type === 'holiday' (from HR module public holidays).
                    // These will be rendered as orange column highlights, not as bars.
                    var hSet = {};
                    state.timeOff.forEach(function (t) {
                        if (t.type === 'holiday' || t.source === 'hr_holiday') {
                            var d = t.start_date || t.date_from;
                            var e = t.end_date   || t.date_to;
                            if (d && e) {
                                PB_Utils.getDateRange(d, e, true).forEach(function (dt) {
                                    hSet[dt] = true;
                                });
                            } else if (d) {
                                hSet[d] = true;
                            }
                        }
                    });
                    state.holidayDates = hSet;

                    // Re-compute cell width now that DOM is available
                    _recalcCellWidth();

                    PB_Render.renderBoard();
                    PB_Render.checkOverbooking();
                    if (config.canEdit) PB_Drag.initDragDrop();
                } else {
                    alert_float(
                        'danger',
                        (response && response.message)
                            || (config.lang && config.lang.errorLoading)
                            || 'Fehler beim Laden'
                    );
                }
            },
            error: function () {
                alert_float(
                    'danger',
                    (config.lang && config.lang.errorLoading) || 'Fehler beim Laden'
                );
            },
            complete: function () {
                state.isLoading = false;
                _showLoading(false);
            }
        });
    }

    function _showLoading(show) {
        $('#rb-loading').toggleClass('hidden', !show);
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return {
        init:   init,
        reload: loadBoardData
    };

})();
