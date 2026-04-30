<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <!-- Reports Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h4 class="no-margin font-bold">
                                    <i class="fa fa-bar-chart" aria-hidden="true"></i>
                                    <?php echo _l($title); ?>
                                </h4>
                            </div>
                            <div class="col-md-9">
                                <form class="form-inline pull-right" id="rb-report-filters">
                                    <div class="form-group mright5">
                                        <label class="mright5 hidden-xs"><?php echo _l('date_range'); ?>:</label>
                                        <input type="text" class="form-control rb-rp-datepicker" name="start_date"
                                               id="rb-report-start" value="<?php echo date('Y-m-01'); ?>" style="width:120px">
                                    </div>
                                    <div class="form-group mright5">
                                        <span class="mright5 text-muted">&ndash;</span>
                                        <input type="text" class="form-control rb-rp-datepicker" name="end_date"
                                               id="rb-report-end" value="<?php echo date('Y-m-t'); ?>" style="width:120px">
                                    </div>
                                    <div class="form-group mright5">
                                        <select name="staff_id" id="rb-report-staff" class="selectpicker"
                                                data-live-search="true" data-width="160px"
                                                title="<?php echo _l('all_staff'); ?>">
                                            <option value=""><?php echo _l('all_staff'); ?></option>
                                            <?php foreach($staff as $member): ?>
                                            <option value="<?php echo $member['staffid']; ?>">
                                                <?php echo $member['firstname'] . ' ' . $member['lastname']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group mright5">
                                        <select name="project_id" id="rb-report-project" class="selectpicker"
                                                data-live-search="true" data-width="160px"
                                                title="<?php echo _l('rb_report_all_projects'); ?>">
                                            <option value=""><?php echo _l('rb_report_all_projects'); ?></option>
                                            <?php foreach($projects as $proj): ?>
                                            <option value="<?php echo $proj['id']; ?>">
                                                <?php echo htmlspecialchars($proj['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> <?php echo _l('filter'); ?>
                                    </button>
                                    <button type="button" class="btn btn-default" id="rb-export-csv">
                                        <i class="fa fa-download"></i> CSV
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row" id="rb-summary-cards">
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body text-center">
                        <h3 class="bold no-margin text-primary" id="rb-total-hours">-</h3>
                        <span class="text-muted"><?php echo _l('total_hours'); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body text-center">
                        <h3 class="bold no-margin text-success" id="rb-avg-utilization">-</h3>
                        <span class="text-muted"><?php echo _l('capacity_utilization'); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body text-center">
                        <h3 class="bold no-margin text-info" id="rb-staff-count">-</h3>
                        <span class="text-muted"><?php echo _l('staff_members'); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel_s">
                    <div class="panel-body text-center">
                        <h3 class="bold no-margin text-warning" id="rb-overbooking-count">-</h3>
                        <span class="text-muted"><?php echo _l('overbooking'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body" style="padding-bottom:0">
                        <ul class="nav nav-tabs" id="rb-report-tabs">
                            <li class="active"><a href="#rpt-overview"  data-toggle="tab"><?php echo _l('rb_report_tab_overview'); ?></a></li>
                            <li><a href="#rpt-projects" data-toggle="tab"><?php echo _l('rb_report_tab_projects'); ?></a></li>
                            <li><a href="#rpt-tasks"    data-toggle="tab"><?php echo _l('rb_report_tab_tasks'); ?></a></li>
                            <li><a href="#rpt-timeoff"  data-toggle="tab"><?php echo _l('rb_report_tab_timeoff'); ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">

            <!-- TAB: OVERVIEW -->
            <div class="tab-pane active" id="rpt-overview">
                <div class="row">
                    <div class="col-md-6">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="bold"><?php echo _l('rb_report_utilization'); ?></h5>
                                <hr class="hr-panel-heading">
                                <div id="rb-chart-utilization" style="height:320px"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="bold"><?php echo _l('rb_report_project_hours'); ?></h5>
                                <hr class="hr-panel-heading">
                                <div id="rb-chart-projects" style="height:320px"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="bold"><?php echo _l('rb_report_staff_hours'); ?></h5>
                                <hr class="hr-panel-heading">
                                <table class="table table-striped" id="rb-report-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('staff_member'); ?></th>
                                            <th><?php echo _l('capacity_available'); ?></th>
                                            <th><?php echo _l('capacity_allocated'); ?></th>
                                            <th><?php echo _l('capacity_remaining'); ?></th>
                                            <th><?php echo _l('capacity_utilization'); ?></th>
                                            <th><?php echo _l('overbooking'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="rb-report-tbody">
                                        <tr><td colspan="6" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i></td></tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bold">
                                            <td><?php echo _l('total'); ?></td>
                                            <td id="rb-total-available">-</td>
                                            <td id="rb-total-allocated">-</td>
                                            <td id="rb-total-remaining">-</td>
                                            <td id="rb-total-utilization">-</td>
                                            <td id="rb-total-overbooking">-</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: PROJECTS -->
            <div class="tab-pane" id="rpt-projects">
                <div class="row">
                    <div class="col-md-12">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="bold"><?php echo _l('rb_report_projects_detail'); ?></h5>
                                <hr class="hr-panel-heading">
                                <div id="rb-chart-project-detail" style="height:350px"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="panel_s">
                            <div class="panel-body">
                                <table class="table table-striped table-hover" id="rb-projects-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('project'); ?></th>
                                            <th><?php echo _l('rb_report_staff_count'); ?></th>
                                            <th><?php echo _l('rb_report_assigned_hours'); ?></th>
                                            <th style="width:200px"><?php echo _l('capacity_utilization'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="rb-projects-tbody">
                                        <tr><td colspan="4" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: TASKS -->
            <div class="tab-pane" id="rpt-tasks">
                <div class="row">
                    <div class="col-md-12">
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="bold"><?php echo _l('rb_report_tasks_detail'); ?></h5>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <input type="text" id="rb-tasks-search" class="form-control"
                                               placeholder="<?php echo _l('search'); ?>&#8230;"
                                               style="display:inline-block;width:220px">
                                    </div>
                                </div>
                                <hr class="hr-panel-heading">
                                <table class="table table-striped table-hover" id="rb-tasks-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('rb_report_task_name'); ?></th>
                                            <th><?php echo _l('project'); ?></th>
                                            <th><?php echo _l('staff_member'); ?></th>
                                            <th><?php echo _l('start_date'); ?></th>
                                            <th><?php echo _l('end_date'); ?></th>
                                            <th><?php echo _l('rb_report_hpd'); ?></th>
                                            <th><?php echo _l('rb_report_working_days'); ?></th>
                                            <th><?php echo _l('rb_report_assigned_hours'); ?></th>
                                            <th><?php echo _l('rb_report_est_hours'); ?></th>
                                            <th><?php echo _l('rb_report_task_status'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="rb-tasks-tbody">
                                        <tr><td colspan="10" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i></td></tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bold">
                                            <td colspan="7"><?php echo _l('total'); ?></td>
                                            <td id="rb-tasks-total-alloc">-</td>
                                            <td id="rb-tasks-total-est">-</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: TIME OFF -->
            <div class="tab-pane" id="rpt-timeoff">
                <div class="row">
                    <div class="col-md-12">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h5 class="bold"><?php echo _l('time_off'); ?></h5>
                                <hr class="hr-panel-heading">
                                <table class="table table-striped" id="rb-timeoff-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('staff_member'); ?></th>
                                            <th><?php echo _l('time_off_type'); ?></th>
                                            <th><?php echo _l('start_time'); ?></th>
                                            <th><?php echo _l('end_time'); ?></th>
                                            <th><?php echo _l('status'); ?></th>
                                            <th><?php echo _l('note'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="rb-timeoff-tbody">
                                        <?php if(!empty($time_off)): ?>
                                            <?php foreach($time_off as $to): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($to['firstname'] . ' ' . $to['lastname']); ?></td>
                                                <td>
                                                    <span class="label label-<?php
                                                        echo $to['type'] == 'vacation' ? 'success' :
                                                            ($to['type'] == 'sick' ? 'danger' : 'info');
                                                    ?>">
                                                        <?php echo rb_time_off_type_label($to['type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo _d($to['start_date']); ?></td>
                                                <td><?php echo _d($to['end_date']); ?></td>
                                                <td>
                                                    <?php if(!empty($to['approved'])): ?>
                                                        <span class="text-success"><i class="fa fa-check"></i> <?php echo _l('approved'); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-warning"><i class="fa fa-clock-o"></i> <?php echo _l('time_off_pending'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($to['note'] ?? ''); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">
                                                    <?php echo _l('no_records_found'); ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.tab-content -->
    </div>
</div>

<?php init_tail(); ?>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
$(function () {
    // ── Datepickers ──────────────────────────────────────────────────────────
    if ($.fn.datepicker) {
        $('#rb-report-start, #rb-report-end').datepicker({
            format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true
        });
    }
    $('.selectpicker').selectpicker('refresh');

    // ── Task search filter ───────────────────────────────────────────────────
    $('#rb-tasks-search').on('input', function () {
        var q = $(this).val().toLowerCase();
        $('#rb-tasks-tbody tr').each(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
        });
    });

    // ── Form submit & auto-load ──────────────────────────────────────────────
    $('#rb-report-filters').on('submit', function (e) {
        e.preventDefault();
        loadReport();
    });
    loadReport();

    // ── CSV Export ───────────────────────────────────────────────────────────
    $('#rb-export-csv').on('click', function () {
        var params = $('#rb-report-filters').serialize();
        window.location.href = '<?php echo admin_url("resourcebooking/api_report_export"); ?>?' + params;
    });

    // ════════════════════════════════════════════════════════════════════════
    // LOAD REPORT
    // ════════════════════════════════════════════════════════════════════════
    function loadReport() {
        var params = {
            start_date: $('#rb-report-start').val(),
            end_date:   $('#rb-report-end').val(),
            staff_id:   $('#rb-report-staff').val(),
            project_id: $('#rb-report-project').val()
        };
        $('#rb-total-hours, #rb-avg-utilization, #rb-staff-count, #rb-overbooking-count').text('...');
        var spin = '<tr><td colspan="10" class="text-center"><i class="fa fa-spinner fa-spin"></i></td></tr>';
        $('#rb-report-tbody, #rb-projects-tbody, #rb-tasks-tbody').html(spin);

        $.get('<?php echo admin_url("resourcebooking/api_report_data"); ?>', params, function (r) {
            if (r && r.success) { renderReport(r.data); }
            else { alert_float('danger', (r && r.error) || 'Fehler beim Laden'); }
        }).fail(function () { alert_float('danger', 'Fehler beim Laden'); });
    }

    // ════════════════════════════════════════════════════════════════════════
    // RENDER
    // ════════════════════════════════════════════════════════════════════════
    function renderReport(data) {
        var totals       = data.totals        || {};
        var staffData    = data.staff_summary  || [];
        var projectHours = data.project_hours  || [];
        var taskDetails  = data.task_details   || [];

        // Summary cards
        $('#rb-total-hours').text((totals.allocated || 0).toFixed(1) + 'h');
        var util = totals.utilization || 0;
        $('#rb-avg-utilization').text(util + '%')
            .removeClass('text-success text-warning text-danger')
            .addClass(util > 100 ? 'text-danger' : util > 80 ? 'text-warning' : 'text-success');
        $('#rb-staff-count').text(totals.staff_count || 0);
        $('#rb-overbooking-count').text(totals.overbooking_count || 0)
            .toggleClass('text-danger', (totals.overbooking_count || 0) > 0);

        // Staff table
        var tbody = '';
        staffData.forEach(function (s) {
            var uCls = s.utilization > 100 ? 'text-danger bold'
                     : s.utilization > 80  ? 'text-warning'
                     : s.utilization > 0   ? 'text-success' : 'text-muted';
            var over = s.has_overbooking
                ? '<span class="text-danger"><i class="fa fa-warning"></i> ' + s.overbooked_days + 'd</span>'
                : '<span class="text-muted">&mdash;</span>';
            tbody += '<tr>'
                + '<td><strong>' + esc(s.name) + '</strong></td>'
                + '<td>' + s.available.toFixed(1) + 'h</td>'
                + '<td>' + s.allocated.toFixed(1) + 'h</td>'
                + '<td>' + s.remaining.toFixed(1) + 'h</td>'
                + '<td class="' + uCls + '">' + s.utilization.toFixed(0) + '%'
                +   utilBar(s.utilization) + '</td>'
                + '<td>' + over + '</td>'
                + '</tr>';
        });
        $('#rb-report-tbody').html(tbody || '<tr><td colspan="6" class="text-center text-muted"><?php echo _l("no_records_found"); ?></td></tr>');
        $('#rb-total-available').text((totals.available || 0).toFixed(1) + 'h');
        $('#rb-total-allocated').text((totals.allocated || 0).toFixed(1) + 'h');
        $('#rb-total-remaining').text((totals.remaining || 0).toFixed(1) + 'h');
        $('#rb-total-utilization').text(util + '%');
        $('#rb-total-overbooking').text((totals.overbooking_count || 0) > 0
            ? totals.overbooking_count + ' <?php echo _l("staff_members"); ?>' : '&mdash;');

        // Projects table
        var totalPH = projectHours.reduce(function (s, p) { return s + parseFloat(p.total_hours || 0); }, 0);
        var ptbody  = '';
        projectHours.forEach(function (p) {
            var pct = totalPH > 0 ? (p.total_hours / totalPH * 100) : 0;
            ptbody += '<tr>'
                + '<td><strong>' + esc(p.project_name || '&mdash;') + '</strong></td>'
                + '<td>' + (p.staff_count || 0) + '</td>'
                + '<td>' + parseFloat(p.total_hours || 0).toFixed(1) + 'h</td>'
                + '<td>' + shareBar(pct) + '</td>'
                + '</tr>';
        });
        $('#rb-projects-tbody').html(ptbody || '<tr><td colspan="4" class="text-center text-muted"><?php echo _l("no_records_found"); ?></td></tr>');

        // Tasks table
        var taskStatuses = { 1:'Nicht gestartet', 2:'In Bearbeitung', 3:'Fertig', 4:'Offen', 5:'Prüfung' };
        var ttbody = '';
        var totalAlloc = 0, totalEst = 0;
        taskDetails.forEach(function (t) {
            var statusCls   = t.task_status == 3 ? 'text-success' : t.task_status == 2 ? 'text-primary' : 'text-muted';
            var statusLabel = taskStatuses[t.task_status] || ('Status ' + t.task_status);
            totalAlloc += parseFloat(t.allocated_hours) || 0;
            totalEst   += parseFloat(t.estimated_hours) || 0;
            ttbody += '<tr>'
                + '<td><strong>' + esc(t.task_name) + '</strong></td>'
                + '<td><span class="rb-proj-dot" style="background:' + esc(t.project_color || '#888') + '"></span>'
                +   esc(t.project_name) + '</td>'
                + '<td>' + esc(t.staff_name) + '</td>'
                + '<td>' + (t.task_start || '&mdash;') + '</td>'
                + '<td>' + (t.task_due   || '&mdash;') + '</td>'
                + '<td>' + (t.hours_per_day  || '&mdash;') + 'h</td>'
                + '<td>' + (t.working_days   || 0) + 'd</td>'
                + '<td><strong>' + parseFloat(t.allocated_hours || 0).toFixed(1) + 'h</strong></td>'
                + '<td>' + (t.estimated_hours != null ? parseFloat(t.estimated_hours).toFixed(1) + 'h' : '&mdash;') + '</td>'
                + '<td><span class="' + statusCls + '">' + statusLabel + '</span></td>'
                + '</tr>';
        });
        $('#rb-tasks-tbody').html(ttbody || '<tr><td colspan="10" class="text-center text-muted"><?php echo _l("no_records_found"); ?></td></tr>');
        $('#rb-tasks-total-alloc').text(totalAlloc.toFixed(1) + 'h');
        $('#rb-tasks-total-est').text(totalEst > 0 ? totalEst.toFixed(1) + 'h' : '&mdash;');

        // Charts
        renderUtilChart(staffData);
        renderProjectChart(projectHours, totals);
        renderProjectDetailChart(projectHours);
    }

    function renderUtilChart(staffData) {
        if (!staffData.length) {
            $('#rb-chart-utilization').html('<p class="text-center text-muted" style="padding-top:120px"><?php echo _l("no_records_found"); ?></p>');
            return;
        }
        Highcharts.chart('rb-chart-utilization', {
            chart: { type: 'bar', animation: false },
            title: { text: null },
            xAxis: { categories: staffData.map(function (s) { return s.name; }), title: { text: null } },
            yAxis: {
                min: 0, title: { text: '<?php echo _l("capacity_utilization"); ?> (%)' },
                plotLines: [{ color: '#e74c3c', width: 2, value: 100, dashStyle: 'Dash', zIndex: 5,
                    label: { text: '100%', style: { color: '#e74c3c', fontSize: '10px' } } }]
            },
            legend: { enabled: false },
            tooltip: { formatter: function () { return '<b>' + this.x + '</b><br/>' + this.y.toFixed(1) + '%'; } },
            series: [{ name: '<?php echo _l("capacity_utilization"); ?>',
                data: staffData.map(function (s) {
                    return { y: s.utilization,
                        color: s.utilization > 100 ? '#e74c3c' : s.utilization > 80 ? '#f39c12' : '#27ae60' };
                })
            }],
            credits: { enabled: false }
        });
    }

    function renderProjectChart(projectHours, totals) {
        var el = 'rb-chart-projects';
        if (projectHours.length) {
            Highcharts.chart(el, {
                chart: { type: 'pie', animation: false },
                title: { text: null },
                tooltip: { pointFormat: '<b>{point.y:.1f}h</b> ({point.percentage:.1f}%)' },
                plotOptions: { pie: { allowPointSelect: true, cursor: 'pointer',
                    dataLabels: { enabled: true, format: '<b>{point.name}</b>: {point.y:.1f}h' },
                    showInLegend: true } },
                series: [{ name: '<?php echo _l("hours_use"); ?>', colorByPoint: true,
                    data: projectHours.map(function (p) {
                        return { name: p.project_name || 'Intern', y: parseFloat(p.total_hours) || 0 };
                    })
                }],
                credits: { enabled: false }
            });
        } else {
            Highcharts.chart(el, {
                chart: { type: 'pie', animation: false },
                title: { text: null },
                tooltip: { pointFormat: '<b>{point.y:.1f}h</b> ({point.percentage:.1f}%)' },
                plotOptions: { pie: { dataLabels: { enabled: true, format: '<b>{point.name}</b>: {point.y:.1f}h' } } },
                series: [{ name: '<?php echo _l("hours_use"); ?>', colorByPoint: true,
                    data: [
                        { name: '<?php echo _l("capacity_allocated"); ?>', y: totals.allocated || 0, color: '#3498db' },
                        { name: '<?php echo _l("capacity_remaining"); ?>', y: Math.max(0, totals.remaining || 0), color: '#ecf0f1' }
                    ]
                }],
                credits: { enabled: false }
            });
        }
    }

    function renderProjectDetailChart(projectHours) {
        if (!projectHours.length) {
            $('#rb-chart-project-detail').html('<p class="text-center text-muted" style="padding-top:120px"><?php echo _l("no_records_found"); ?></p>');
            return;
        }
        Highcharts.chart('rb-chart-project-detail', {
            chart: { type: 'bar', animation: false },
            title: { text: null },
            xAxis: { categories: projectHours.map(function (p) { return p.project_name || '—'; }), title: { text: null } },
            yAxis: { min: 0, title: { text: '<?php echo _l("hours_use"); ?>' } },
            legend: { enabled: false },
            tooltip: { formatter: function () {
                return '<b>' + this.x + '</b><br/>' + this.y.toFixed(1) + 'h'
                    + ' &mdash; ' + (this.point.staff_count || 0) + ' <?php echo _l("rb_report_staff_count"); ?>';
            }},
            series: [{ name: '<?php echo _l("hours_use"); ?>',
                data: projectHours.map(function (p) {
                    return { y: parseFloat(p.total_hours) || 0, staff_count: p.staff_count || 0, color: '#3498db' };
                })
            }],
            credits: { enabled: false }
        });
    }

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function utilBar(pct) {
        var capped = Math.min(pct, 100);
        var col    = pct > 100 ? '#e74c3c' : pct > 80 ? '#f39c12' : '#27ae60';
        return '<div style="background:#f0f0f0;height:6px;border-radius:3px;margin-top:3px;overflow:hidden">'
             + '<div style="width:' + capped + '%;height:6px;background:' + col + ';border-radius:3px"></div>'
             + '</div>';
    }
    function shareBar(pct) {
        return '<div style="display:flex;align-items:center;gap:6px">'
             + '<div style="background:#f0f0f0;height:10px;border-radius:3px;flex:1;overflow:hidden">'
             + '<div style="width:' + pct.toFixed(1) + '%;height:10px;background:#3498db;border-radius:3px"></div>'
             + '</div>'
             + '<span style="font-size:11px;color:#888">' + pct.toFixed(0) + '%</span>'
             + '</div>';
    }
});
</script>

<style>
.rb-proj-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    margin-right: 5px;
    vertical-align: middle;
}
</style>

</body>
</html>
