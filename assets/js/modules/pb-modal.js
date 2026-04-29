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
        $('#rb-alloc-task')
            .empty()
            .append('<option value="">— kein Task —</option>')
            .selectpicker('refresh');
        $('#rb-alloc-follower').prop('checked', false);

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

            if (alloc.project_id) {
                loadTasksDropdown(alloc.project_id, alloc.task_id);
            }
            if (_cfg.canDelete) {
                $('#rb-delete-allocation').show();
            }
        } else {
            // ── Create new ──────────────────────────────────────────────────
            $('#rb-modal-title').text('Neue Zuweisung');
            $('#rb-alloc-id').val('');
            $('#rb-delete-allocation').hide();

            if (staffId) $('#rb-alloc-staff').selectpicker('val', String(staffId));
            if (date)    { $('#rb-alloc-start').val(date); $('#rb-alloc-end').val(date); }
        }

        $('.selectpicker').selectpicker('refresh');
        updateTotalHoursDisplay();
        $modal.modal('show');
    }

    // =========================================================================
    // PROJECT / TASK AUTO-FILL
    // =========================================================================

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

    function fetchTaskDates(taskId) {
        // Try state first (faster); fall back to API
        var ta = _st.allocations.find(function (a) {
            return a.type === 'task' && String(a.task_id) === String(taskId);
        });
        if (ta) {
            if (ta.start_date) $('#rb-alloc-start').val(ta.start_date);
            if (ta.end_date)   $('#rb-alloc-end').val(ta.end_date);
            if (ta.estimated_hours) {
                var days = PB_Utils.countWorkingDays(
                    new Date($('#rb-alloc-start').val()),
                    new Date($('#rb-alloc-end').val())
                );
                if (days > 0) {
                    $('#rb-alloc-hours').val(
                        Math.round((ta.estimated_hours / days) * 10) / 10
                    );
                }
            }
            updateTotalHoursDisplay();
        }
    }

    function loadTasksDropdown(projectId, selectedTaskId) {
        $.ajax({
            url:      _cfg.apiUrl + '/api_get_tasks',
            type:     'GET',
            data:     { project_id: projectId, staff_id: $('#rb-alloc-staff').val() || '' },
            dataType: 'json',
            success:  function (r) {
                var $s = $('#rb-alloc-task');
                $s.empty().append('<option value="">— kein Task —</option>');
                (r.tasks || []).forEach(function (t) {
                    $s.append(
                        '<option value="' + t.id + '"'
                        + (String(t.id) === String(selectedTaskId) ? ' selected' : '')
                        + '>'
                        + PB_Utils.escHtml(t.name)
                        + (t.estimated_hours ? ' (' + t.estimated_hours + 'h)' : '')
                        + '</option>'
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
            add_as_follower:  $('#rb-alloc-follower').is(':checked') ? 1 : 0,
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
            // ── Update override ──────────────────────────────────────────────
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
            // ── New: assign member, then upsert override ─────────────────────
            $.ajax({
                url:  _cfg.apiUrl + '/api_assign_member',
                type: 'POST',
                data: {
                    staff_id:        data.staff_id,
                    project_id:      data.project_id,
                    task_id:         data.task_id,
                    add_as_follower: data.add_as_follower
                },
                dataType: 'json',
                success: function (r) {
                    if (r && r.success) {
                        $.ajax({
                            url:      _cfg.apiUrl + '/api_upsert_override',
                            type:     'POST',
                            data:     data,
                            dataType: 'json',
                            success:  function () {
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
            success:  function () {
                $('#rb-allocation-modal').modal('hide');
                alert_float('success', 'Gelöscht');
                _reload();
            },
            error: function () { alert_float('danger', 'Fehler beim Löschen'); }
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
    // INLINE EDIT (double-click on hours label in task bar)
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

    function updateTotalHoursDisplay() {
        var start = $('#rb-alloc-start').val();
        var end   = $('#rb-alloc-end').val();
        var hpd   = parseFloat($('#rb-alloc-hours').val()) || 0;
        if (start && end && hpd > 0) {
            var days = PB_Utils.countWorkingDays(new Date(start), new Date(end));
            $('#rb-total-hours-display').text(
                days + ' Werktage × ' + hpd + 'h = ' + (days * hpd) + 'h gesamt'
            );
        } else {
            $('#rb-total-hours-display').text('');
        }
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return {
        setContext:             setContext,
        openAllocationModal:    openAllocationModal,
        fetchProjectDates:      fetchProjectDates,
        fetchTaskDates:         fetchTaskDates,
        loadTasksDropdown:      loadTasksDropdown,
        saveAllocation:         saveAllocation,
        deleteAllocation:       deleteAllocation,
        saveTimeOff:            saveTimeOff,
        initInlineEdit:         initInlineEdit,
        updateTotalHoursDisplay: updateTotalHoursDisplay
    };

})();
