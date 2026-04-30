/**
 * PlanningBoard Modal Module
 * Allocation dialog, time-off dialog, inline hour editing.
 *
 * Depends on: PB_Utils (must be loaded first)
 * Context set by: PB_Modal.setContext(config, state, reloadFn)
 *
 * @since 2.0.0
 */
var PB_Modal = (function () {
    'use strict';

    var _cfg = {}, _st = {}, _reload = function () {};

    // Cache task data keyed by task ID — populated by loadTasksDropdown
    var _taskCache = {};

    function setContext(config, state, reloadFn) {
        _cfg    = config;
        _st     = state;
        _reload = reloadFn;
    }

    // =========================================================================
    // OPEN ALLOCATION MODAL
    // =========================================================================

    function openAllocationModal(id, staffId, date) {
        if (_cfg.isEmployee) return; // read-only for non-admins

        var $modal = $('#rb-allocation-modal');
        var $form  = $('#rb-allocation-form')[0];
        if ($form) $form.reset();

        $('#rb-overbooking-warning').hide();
        // Always reset action buttons before populating — prevents stale state
        $('#rb-delete-allocation').hide();
        $('#rb-reassign-allocation').hide();
        $('#rb-remove-person-allocation').hide();
        _taskCache = {};
        $('#rb-alloc-task')
            .empty()
            .append('<option value="">— kein Task —</option>')
            .selectpicker('refresh');

        if (id) {
            // ── Edit existing ───────────────────────────────────────────────
            var alloc = _st.allocations.find(function (a) { return a.id == id; });
            if (!alloc) return;

            $('#rb-modal-title').text('Zuweisung bearbeiten');
            $('#rb-alloc-id').val(alloc.id);
            $('#rb-alloc-staff').selectpicker('val', String(alloc.staff_id));
            $('#rb-alloc-project').selectpicker('val', String(alloc.project_id || ''));
            $('#rb-alloc-start').val(alloc.start_date);
            $('#rb-alloc-end').val(alloc.end_date);
            $('#rb-alloc-hours').val(alloc.hours_per_day || 8);
            $('#rb-alloc-weekends').prop('checked', alloc.include_weekends == 1);
            $('#rb-alloc-note').val(alloc.note || '');
            // Pre-fill total hours from task estimated_hours if known
            if (alloc.estimated_hours) {
                $('#rb-alloc-total-hours').val(alloc.estimated_hours);
            } else {
                $('#rb-alloc-total-hours').val('');
            }

            if (alloc.project_id) {
                loadTasksDropdown(alloc.project_id, alloc.task_id);
            }
            if (_cfg.canDelete) {
                $('#rb-delete-allocation').show();
            }
            // Show reassign + remove-person buttons for task allocations.
            // Only needs delete + create permission (server enforces both).
            if (alloc.type === 'task' && alloc.task_id
                    && (_cfg.canDelete || _cfg.canCreate || _cfg.canEdit)) {
                $('#rb-reassign-allocation').show();
                $('#rb-remove-person-allocation').show();
            }
        } else {
            // ── Create new ──────────────────────────────────────────────────
            $('#rb-modal-title').text('Neue Zuweisung');
            $('#rb-alloc-id').val('');

            if (staffId) $('#rb-alloc-staff').selectpicker('val', String(staffId));
            if (date)    { $('#rb-alloc-start').val(date); $('#rb-alloc-end').val(date); }
        }

        $('.selectpicker').selectpicker('refresh');
        updateTotalHoursDisplay();
        initHoursBidirectional();
        $modal.modal('show');
    }

    // =========================================================================
    // PROJECT / TASK AUTO-FILL
    // =========================================================================

    /**
     * Fetch project start/deadline and pre-fill the date fields.
     * Called when project dropdown changes.
     */
    function fetchProjectDates(projectId) {
        $.ajax({
            url:      _cfg.apiUrl + '/api_get_project/' + projectId,
            type:     'GET',
            dataType: 'json',
            success:  function (r) {
                if (r && r.project) {
                    if (r.project.start_date) $('#rb-alloc-start').val(r.project.start_date);
                    if (r.project.deadline)   $('#rb-alloc-end').val(r.project.deadline);
                    updateTotalHoursDisplay();
                }
            }
        });
    }

    /**
     * Use cached task data to pre-fill start date, end date, and hours/day.
     * Falls back to searching existing state.allocations for already-saved overrides.
     * Called when task dropdown changes.
     */
    function fetchTaskDates(taskId) {
        var task = _taskCache[taskId];
        if (task) {
            var start = task.startdate || task.start_date || '';
            var end   = task.duedate   || task.end_date   || '';
            if (start) $('#rb-alloc-start').val(start);
            if (end)   $('#rb-alloc-end').val(end);

            // Auto-compute hours/day from estimated_hours ÷ working days
            var est = parseFloat(task.estimated_hours) || 0;
            $('#rb-alloc-total-hours').val(est > 0 ? est : '');
            if (est > 0 && start && end) {
                var days = PB_Utils.countWorkingDays(new Date(start), new Date(end));
                if (days > 0) {
                    $('#rb-alloc-hours').val(Math.round((est / days) * 10) / 10);
                }
            }
            updateTotalHoursDisplay();
            return;
        }

        // Fallback: look for an existing override in state
        var existing = _st.allocations.find(function (a) {
            return a.type === 'task' && String(a.task_id) === String(taskId);
        });
        if (existing) {
            if (existing.start_date) $('#rb-alloc-start').val(existing.start_date);
            if (existing.end_date)   $('#rb-alloc-end').val(existing.end_date);
            if (existing.hours_per_day) $('#rb-alloc-hours').val(existing.hours_per_day);
            updateTotalHoursDisplay();
        }
    }

    /**
     * Load tasks for a project into the task dropdown and populate _taskCache.
     * Optionally pre-selects a task by ID (for edit mode).
     */
    function loadTasksDropdown(projectId, selectedTaskId) {
        _taskCache = {}; // Reset cache for this project
        $.ajax({
            url:      _cfg.apiUrl + '/api_get_tasks',
            type:     'GET',
            data:     { project_id: projectId, staff_id: $('#rb-alloc-staff').val() || '' },
            dataType: 'json',
            success:  function (r) {
                var $s = $('#rb-alloc-task');
                $s.empty().append('<option value="">— kein Task —</option>');
                (r.tasks || []).forEach(function (t) {
                    // Cache task data for date auto-fill
                    _taskCache[t.id] = t;
                    var label = PB_Utils.escHtml(t.name);
                    if (t.estimated_hours) label += ' (' + t.estimated_hours + 'h)';
                    $s.append(
                        '<option value="' + t.id + '"'
                        + (String(t.id) === String(selectedTaskId) ? ' selected' : '')
                        + '>' + label + '</option>'
                    );
                });
                $s.selectpicker('refresh');
            }
        });
    }

    // =========================================================================
    // SAVE ALLOCATION
    // =========================================================================

    function saveAllocation() {
        var id   = $('#rb-alloc-id').val();
        var data = {
            staff_id:         $('#rb-alloc-staff').val(),
            project_id:       $('#rb-alloc-project').val() || null,
            task_id:          $('#rb-alloc-task').val()    || null,
            date_from:        $('#rb-alloc-start').val(),
            date_to:          $('#rb-alloc-end').val(),
            hours_per_day:    $('#rb-alloc-hours').val(),
            include_weekends: $('#rb-alloc-weekends').is(':checked') ? 1 : 0,
            note:             $('#rb-alloc-note').val()
        };

        if (!data.staff_id) {
            alert_float('warning', 'Mitarbeiter wählen');
            return;
        }
        if (!data.date_from || !data.date_to) {
            alert_float('warning', 'Datum wählen');
            return;
        }

        var errLang = (_cfg.lang && _cfg.lang.errorSaving) || 'Fehler beim Speichern';

        if (id && /^\d+$/.test(id)) {
            // ── Update existing override ─────────────────────────────────────
            data.id = id;
            $.ajax({
                url:      _cfg.apiUrl + '/api_upsert_override',
                type:     'POST',
                data:     data,
                dataType: 'json',
                success:  function (r) {
                    if (r && r.success) {
                        $('#rb-allocation-modal').modal('hide');
                        alert_float('success', 'Aktualisiert');
                        _reload();
                    } else {
                        alert_float('danger', (r && r.error) || errLang);
                    }
                },
                error: function () { alert_float('danger', errLang); }
            });
        } else {
            // ── New: assign member (controller adds as follower automatically),
            //        then save override with dates/hours ───────────────────────
            $.ajax({
                url:  _cfg.apiUrl + '/api_assign_member',
                type: 'POST',
                data: {
                    staff_id:   data.staff_id,
                    project_id: data.project_id,
                    task_id:    data.task_id
                },
                dataType: 'json',
                success: function (r) {
                    if (r && r.success) {
                        $.ajax({
                            url:      _cfg.apiUrl + '/api_upsert_override',
                            type:     'POST',
                            data:     data,
                            dataType: 'json',
                            success: function () {
                                $('#rb-allocation-modal').modal('hide');
                                alert_float('success', 'Erstellt');
                                _reload();
                            },
                            error: function () {
                                $('#rb-allocation-modal').modal('hide');
                                _reload();
                            }
                        });
                    } else {
                        alert_float('danger', (r && r.error) || errLang);
                    }
                },
                error: function () { alert_float('danger', errLang); }
            });
        }
    }

    // =========================================================================
    // DELETE ALLOCATION
    // =========================================================================

    function deleteAllocation() {
        var id = $('#rb-alloc-id').val();
        if (!id) return;
        var confirmMsg = (_cfg.lang && _cfg.lang.confirmDelete) || 'Wirklich löschen?';
        if (!confirm(confirmMsg)) return;

        var alloc = _st.allocations.find(function (a) { return String(a.id) === String(id); });
        $.ajax({
            url:  _cfg.apiUrl + '/api_remove_member',
            type: 'POST',
            data: {
                staff_id:   alloc ? alloc.staff_id   : '',
                project_id: alloc ? alloc.project_id : '',
                task_id:    alloc ? alloc.task_id    : ''
            },
            dataType: 'json',
            success: function () {
                $('#rb-allocation-modal').modal('hide');
                alert_float('success', 'Gelöscht');
                _reload();
            },
            error: function () { alert_float('danger', 'Fehler beim Löschen'); }
        });
    }

    // =========================================================================
    // REASSIGN TASK (change person — remove old, add new, move override)
    // =========================================================================

    function reassignAllocation() {
        var id    = $('#rb-alloc-id').val();
        var alloc = _st.allocations.find(function (a) { return String(a.id) === String(id); });
        if (!alloc || !alloc.task_id) {
            alert_float('warning', 'Bitte zuerst eine Task-Zuweisung auswählen');
            return;
        }
        var newStaffId = $('#rb-alloc-staff').val();
        if (!newStaffId) { alert_float('warning', 'Neuen Mitarbeiter wählen'); return; }
        if (String(newStaffId) === String(alloc.staff_id)) {
            alert_float('warning', 'Gleicher Mitarbeiter – bitte anderen wählen');
            return;
        }
        var confirmMsg = 'Task von "' + alloc.staff_id + '" entfernen und "' + newStaffId + '" zuweisen?';
        if (!confirm(confirmMsg)) return;

        var errLang = (_cfg.lang && _cfg.lang.errorSaving) || 'Fehler beim Speichern';

        // Step 1: remove old person from task
        $.ajax({
            url:      _cfg.apiUrl + '/api_remove_member',
            type:     'POST',
            data:     { staff_id: alloc.staff_id, task_id: alloc.task_id, project_id: alloc.project_id || '' },
            dataType: 'json',
            success: function () {
                // Step 2: add new person to task
                $.ajax({
                    url:      _cfg.apiUrl + '/api_assign_member',
                    type:     'POST',
                    data:     { staff_id: newStaffId, task_id: alloc.task_id, project_id: alloc.project_id || '' },
                    dataType: 'json',
                    success: function (r) {
                        if (!r || !r.success) {
                            alert_float('danger', (r && r.error) || errLang);
                            return;
                        }
                        // Step 3: move the override (upsert with new staff_id)
                        $.ajax({
                            url:  _cfg.apiUrl + '/api_upsert_override',
                            type: 'POST',
                            data: {
                                staff_id:         newStaffId,
                                project_id:       $('#rb-alloc-project').val() || null,
                                task_id:          alloc.task_id,
                                date_from:        $('#rb-alloc-start').val(),
                                date_to:          $('#rb-alloc-end').val(),
                                hours_per_day:    $('#rb-alloc-hours').val(),
                                include_weekends: $('#rb-alloc-weekends').is(':checked') ? 1 : 0,
                                note:             $('#rb-alloc-note').val()
                            },
                            dataType: 'json',
                            success: function () {
                                // Remove old override record
                                $.ajax({
                                    url:  _cfg.apiUrl + '/api_allocation/' + id,
                                    type: 'POST',
                                    data: { _method: 'DELETE' },
                                    dataType: 'json'
                                });
                                $('#rb-allocation-modal').modal('hide');
                                alert_float('success', 'Umgebucht');
                                _reload();
                            },
                            error: function () {
                                $('#rb-allocation-modal').modal('hide');
                                _reload();
                            }
                        });
                    },
                    error: function () { alert_float('danger', errLang); }
                });
            },
            error: function () { alert_float('danger', errLang); }
        });
    }

    // =========================================================================
    // REMOVE PERSON FROM TASK (unassign only — keeps task, just removes this person)
    // =========================================================================

    function removePersonFromTask() {
        var id    = $('#rb-alloc-id').val();
        var alloc = _st.allocations.find(function (a) { return String(a.id) === String(id); });
        if (!alloc || !alloc.task_id) return;
        var name = ($('#rb-alloc-staff option:selected').text() || 'Person').trim();
        if (!confirm(name + ' vom Task entfernen?')) return;

        var errLang = (_cfg.lang && _cfg.lang.errorSaving) || 'Fehler';
        $.ajax({
            url:      _cfg.apiUrl + '/api_remove_member',
            type:     'POST',
            data:     { staff_id: alloc.staff_id, task_id: alloc.task_id, project_id: alloc.project_id || '' },
            dataType: 'json',
            success: function () {
                // Also remove the override record
                if (id) {
                    $.ajax({
                        url:  _cfg.apiUrl + '/api_allocation/' + id,
                        type: 'POST',
                        data: { _method: 'DELETE' },
                        dataType: 'json'
                    });
                }
                $('#rb-allocation-modal').modal('hide');
                alert_float('success', 'Person entfernt');
                _reload();
            },
            error: function () { alert_float('danger', errLang); }
        });
    }

    // =========================================================================
    // TIME-OFF MODAL
    // =========================================================================

    function saveTimeOff() {
        $.ajax({
            url:  _cfg.apiUrl + '/api_time_off',
            type: 'POST',
            data: {
                staff_id:  $('#rb-timeoff-staff').val(),
                type:      $('#rb-timeoff-type').val(),
                date_from: $('#rb-timeoff-start').val(),
                date_to:   $('#rb-timeoff-end').val(),
                note:      $('#rb-timeoff-note').val()
            },
            dataType: 'json',
            success: function (r) {
                if (r && r.success) {
                    $('#rb-timeoff-modal').modal('hide');
                    alert_float('success', 'Abwesenheit eingetragen');
                    _reload();
                } else {
                    alert_float('danger', (r && r.message) || 'Fehler');
                }
            },
            error: function () { alert_float('danger', 'Fehler'); }
        });
    }

    // =========================================================================
    // INLINE EDIT (double-click hours label on a task bar)
    // =========================================================================

    function initInlineEdit() {
        if (!_cfg.canEdit) return;

        $('#rb-board-body').on('dblclick', '.rb-inline-editable', function (e) {
            e.stopPropagation();
            var $el    = $(this);
            var taskId = $el.data('task-id');
            var hours  = $el.data('hours') || '';

            var $input = $('<input type="number" class="rb-inline-input" min="0.5" step="0.5">')
                .val(hours);

            $el.replaceWith($input);
            $input.focus().select();

            $input.on('blur keydown', function (ev) {
                if (ev.type === 'keydown' && ev.key !== 'Enter' && ev.key !== 'Escape') return;
                var val = parseFloat($input.val());
                $input.replaceWith($el);

                if (ev.key === 'Escape' || isNaN(val) || val <= 0) return;

                $.ajax({
                    url:      _cfg.apiUrl + '/api_update_task_hours',
                    type:     'POST',
                    data:     { task_id: taskId, estimated_hours: val },
                    dataType: 'json',
                    success:  function (r) {
                        if (r && r.success) {
                            alert_float('success', 'Stunden aktualisiert');
                            _reload();
                        }
                    }
                });
            });
        });
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Update the summary text line and keep total-hours field in sync with
     * hours_per_day × working days (one-way: hpd → total).
     * Only overwrites total-hours if the user hasn't manually edited it.
     */
    function updateTotalHoursDisplay() {
        var start = $('#rb-alloc-start').val();
        var end   = $('#rb-alloc-end').val();
        var hpd   = parseFloat($('#rb-alloc-hours').val()) || 0;
        if (start && end && hpd > 0) {
            var days  = PB_Utils.countWorkingDays(new Date(start), new Date(end));
            var total = Math.round(days * hpd * 10) / 10;
            $('#rb-total-hours-display').text(
                days + ' Werktage × ' + hpd + 'h = ' + total + 'h gesamt'
            );
            // Sync total-hours field if it isn't being manually edited
            if (!$('#rb-alloc-total-hours').data('user-edited')) {
                $('#rb-alloc-total-hours').val(total);
            }
        } else {
            $('#rb-total-hours-display').text('');
        }
    }

    /**
     * Wire up the bidirectional relationship between hours/day ↔ total hours.
     * Called once each time the modal opens.
     */
    function initHoursBidirectional() {
        var $hpd   = $('#rb-alloc-hours');
        var $total = $('#rb-alloc-total-hours');
        var $start = $('#rb-alloc-start');
        var $end   = $('#rb-alloc-end');

        // Reset user-edited flag when modal opens
        $total.data('user-edited', false);

        // When user edits total → recompute hpd
        $total.off('input.rbhours').on('input.rbhours', function () {
            $total.data('user-edited', true);
            var tot  = parseFloat($total.val()) || 0;
            var s    = $start.val();
            var e    = $end.val();
            if (tot > 0 && s && e) {
                var days = PB_Utils.countWorkingDays(new Date(s), new Date(e));
                if (days > 0) {
                    $hpd.val(Math.round((tot / days) * 10) / 10);
                    updateTotalHoursDisplay();
                }
            }
        });

        // When user edits hpd → clear user-edited flag so total syncs
        $hpd.off('input.rbhours').on('input.rbhours', function () {
            $total.data('user-edited', false);
            updateTotalHoursDisplay();
        });

        // When dates change → recompute from whichever side was last set
        $start.add($end).off('change.rbhours').on('change.rbhours', function () {
            if ($total.data('user-edited')) {
                // Recompute hpd from fixed total
                var tot  = parseFloat($total.val()) || 0;
                var s    = $start.val();
                var e    = $end.val();
                if (tot > 0 && s && e) {
                    var days = PB_Utils.countWorkingDays(new Date(s), new Date(e));
                    if (days > 0) {
                        $hpd.val(Math.round((tot / days) * 10) / 10);
                    }
                }
            }
            updateTotalHoursDisplay();
        });
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return {
        setContext:              setContext,
        openAllocationModal:     openAllocationModal,
        fetchProjectDates:       fetchProjectDates,
        fetchTaskDates:          fetchTaskDates,
        loadTasksDropdown:       loadTasksDropdown,
        saveAllocation:          saveAllocation,
        deleteAllocation:        deleteAllocation,
        reassignAllocation:      reassignAllocation,
        removePersonFromTask:    removePersonFromTask,
        saveTimeOff:             saveTimeOff,
        initInlineEdit:          initInlineEdit,
        initHoursBidirectional:  initHoursBidirectional,
        updateTotalHoursDisplay: updateTotalHoursDisplay
    };

})();
