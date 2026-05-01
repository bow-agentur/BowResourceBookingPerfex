/**
 * PlanningBoard Drag & Drop / Resize Module
 * Uses interact.js for drag and resize on allocation bars.
 *
 * Depends on: PB_Utils (must be loaded first)
 * Context set by: PB_Drag.setContext(config, state, reloadFn)
 *
 * @since 2.0.0
 */
var PB_Drag = (function () {
    'use strict';

    var _cfg = {}, _st = {}, _reload = function () {};

    function setContext(config, state, reloadFn) {
        _cfg    = config;
        _st     = state;
        _reload = reloadFn;
    }

    // =========================================================================
    // INIT DRAG & DROP
    // =========================================================================

    function initDragDrop() {
        // Drag & drop disabled — bars are opened via click (mousedown/mouseup in planning-board.js)
        // Clear any previous interact bindings left over from old code
        if (typeof interact !== 'undefined') {
            try { interact('.rb-project-bar, .rb-task-bar').unset(); } catch (e) {}
        }
    }

    /* ---- dead code below, kept for potential future re-enable ---- */
    function _initDragDropDisabled() {
        if (!_cfg.canEdit) return;
        interact('.rb-project-bar, .rb-task-bar')
            .draggable({
                inertia:    false,
                autoScroll: true,
                modifiers: [
                    interact.modifiers.restrictRect({ restriction: '.rb-board-body', endOnly: true })
                ],
                listeners: {
                    start: function (e) {
                        $(e.target).addClass('rb-dragging');
                    },
                    move: function (e) {
                        var t = e.target;
                        var x = (parseFloat(t.getAttribute('data-x')) || 0) + e.dx;
                        var y = (parseFloat(t.getAttribute('data-y')) || 0) + e.dy;
                        t.style.transform = 'translate(' + x + 'px,' + y + 'px)';
                        t.setAttribute('data-x', x);
                        t.setAttribute('data-y', y);
                    },
                    end: function (e) {
                        var $t    = $(e.target);
                        var rawId = $t.data('id');
                        $t.removeClass('rb-dragging');

                        var x    = parseFloat(e.target.getAttribute('data-x')) || 0;
                        var y    = parseFloat(e.target.getAttribute('data-y')) || 0;
                        var days = Math.round(x / _st.cellWidth);
                        // Approximate lane height: project=32px, task=26px — use 30px
                        var rows = Math.round(y / 30);

                        var isNumeric = typeof rawId === 'number'
                            || (typeof rawId === 'string' && /^\d+$/.test(rawId));

                        if ((days || rows) && isNumeric) {
                            _moveAllocation(parseInt(rawId, 10), days, rows);
                        } else {
                            e.target.style.transform = '';
                            e.target.removeAttribute('data-x');
                            e.target.removeAttribute('data-y');
                        }
                    }
                }
            })
            .resizable({
                edges: {
                    left:   '.rb-resize-left',
                    right:  '.rb-resize-right',
                    top:    false,
                    bottom: false
                },
                listeners: {
                    move: function (e) {
                        var t = e.target;
                        var x = parseFloat(t.getAttribute('data-x')) || 0;
                        t.style.width     = e.rect.width + 'px';
                        x                += e.deltaRect.left;
                        t.style.transform = 'translateX(' + x + 'px)';
                        t.setAttribute('data-x', x);
                    },
                    end: function (e) {
                        var id  = $(e.target).data('id');
                        var x   = parseFloat(e.target.getAttribute('data-x')) || 0;
                        var sd  = Math.round(x / _st.cellWidth);
                        var dur = Math.round(e.rect.width / _st.cellWidth);
                        _resizeAllocation(id, sd, dur);
                    }
                }
            });
    }

    // =========================================================================
    // MOVE (drag)
    // =========================================================================

    function _moveAllocation(id, daysDelta, staffDelta) {
        var alloc = _st.allocations.find(function (a) { return a.id == id; });
        if (!alloc) { _reload(); return; }

        var newStart   = PB_Utils.addDays(new Date(alloc.start_date), daysDelta);
        var newEnd     = PB_Utils.addDays(new Date(alloc.end_date),   daysDelta);
        var newStaffId = alloc.staff_id;

        if (staffDelta) {
            var idx = _st.staff.findIndex(function (s) { return s.staffid == alloc.staff_id; });
            var ni  = idx + staffDelta;
            if (ni >= 0 && ni < _st.staff.length) newStaffId = _st.staff[ni].staffid;
        }

        $.ajax({
            url:      _cfg.apiUrl + '/api_upsert_override',
            type:     'POST',
            data: {
                staff_id:      newStaffId,
                project_id:    alloc.project_id,
                task_id:       alloc.task_id,
                date_from:     PB_Utils.formatDate(newStart),
                date_to:       PB_Utils.formatDate(newEnd),
                hours_per_day: alloc.hours_per_day,
                color:         alloc.project_color,
                note:          alloc.note
            },
            dataType: 'json',
            success:  function (r) {
                if (r && r.success) alert_float('success', 'Verschoben');
                _reload();
            },
            error: function () { _reload(); }
        });
    }

    // =========================================================================
    // RESIZE
    // =========================================================================

    function _resizeAllocation(id, startDaysDelta, newDurationDays) {
        var alloc = _st.allocations.find(function (a) { return a.id == id; });
        if (!alloc) { _reload(); return; }

        var newStart = PB_Utils.addDays(new Date(alloc.start_date), startDaysDelta);
        var newEnd   = PB_Utils.addDays(newStart, Math.max(newDurationDays - 1, 0));

        $.ajax({
            url:  _cfg.apiUrl + '/api_upsert_override',
            type: 'POST',
            data: {
                staff_id:      alloc.staff_id,
                project_id:    alloc.project_id,
                task_id:       alloc.task_id,
                date_from:     PB_Utils.formatDate(newStart),
                date_to:       PB_Utils.formatDate(newEnd),
                hours_per_day: alloc.hours_per_day,
                color:         alloc.project_color,
                note:          alloc.note
            },
            dataType: 'json',
            success:  function () { _reload(); },
            error:    function () { _reload(); }
        });
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return {
        setContext:   setContext,
        initDragDrop: initDragDrop
    };

})();
