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
                            <div class="col-md-4">
                                <h4 class="no-margin font-bold">
                                    <i class="fa fa-bar-chart" aria-hidden="true"></i> 
                                    <?php echo _l($title); ?>
                                </h4>
                            </div>
                            <div class="col-md-8">
                                <form class="form-inline pull-right" id="rb-report-filters">
                                    <div class="form-group mright10">
                                        <label class="mright5"><?php echo _l('date_range'); ?>:</label>
                                        <div class="input-group date" style="width: 130px;">
                                            <input type="text" class="form-control datepicker" name="start_date" 
                                                   id="rb-report-start" value="<?php echo date('Y-m-01'); ?>">
                                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-group mright10">
                                        <div class="input-group date" style="width: 130px;">
                                            <input type="text" class="form-control datepicker" name="end_date" 
                                                   id="rb-report-end" value="<?php echo date('Y-m-t'); ?>">
                                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-group mright10">
                                        <select name="staff_id" id="rb-report-staff" class="selectpicker" 
                                                data-live-search="true" data-width="180px"
                                                title="<?php echo _l('all_staff'); ?>">
                                            <option value=""><?php echo _l('all_staff'); ?></option>
                                            <?php foreach($staff as $member): ?>
                                            <option value="<?php echo $member['staffid']; ?>">
                                                <?php echo $member['firstname'] . ' ' . $member['lastname']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> <?php echo _l('filter'); ?>
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

        <!-- Charts Row -->
        <div class="row">
            <!-- Utilization by Staff -->
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="bold"><?php echo _l('rb_report_utilization'); ?></h5>
                        <hr class="hr-panel-heading">
                        <div id="rb-chart-utilization" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Hours by Project -->
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="bold"><?php echo _l('rb_report_project_hours'); ?></h5>
                        <hr class="hr-panel-heading">
                        <div id="rb-chart-projects" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="bold"><?php echo _l('rb_report_staff_hours'); ?></h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" class="btn btn-default btn-sm" id="rb-export-csv">
                                    <i class="fa fa-download"></i> <?php echo _l('export_csv'); ?>
                                </button>
                            </div>
                        </div>
                        <hr class="hr-panel-heading">
                        
                        <table class="table table-striped dt-table" id="rb-report-table">
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
                                <!-- Populated by JS -->
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

        <!-- Time Off Summary -->
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
                                        <td><?php echo $to['firstname'] . ' ' . $to['lastname']; ?></td>
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
                                            <?php if($to['approved']): ?>
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
</div>

<?php init_tail(); ?>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
$(function() {
    // Date pickers
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true
    });
    
    // Load initial report
    loadReport();
    
    // Filter form submit
    $('#rb-report-filters').on('submit', function(e) {
        e.preventDefault();
        loadReport();
    });
    
    // Export CSV
    $('#rb-export-csv').on('click', function() {
        var params = $('#rb-report-filters').serialize();
        window.location.href = '<?php echo admin_url("resourcebooking/api_report_export"); ?>?' + params;
    });
    
    function loadReport() {
        var data = {
            start_date: $('#rb-report-start').val(),
            end_date: $('#rb-report-end').val(),
            staff_id: $('#rb-report-staff').val()
        };
        
        // Show loading state
        $('#rb-total-hours, #rb-avg-utilization, #rb-staff-count, #rb-overbooking-count').text('...');
        $('#rb-report-tbody').html('<tr><td colspan="6" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');
        
        $.get('<?php echo admin_url("resourcebooking/api_report_data"); ?>', data, function(response) {
            if (response.success) {
                renderReport(response.data);
            } else {
                alert_float('danger', response.error || 'Error loading report');
            }
        }).fail(function() {
            alert_float('danger', 'Error loading report data');
        });
    }
    
    function renderReport(data) {
        var totals = data.totals;
        var staffData = data.staff_summary || [];
        var projectHours = data.project_hours || [];
        
        // Update summary cards
        $('#rb-total-hours').text(totals.allocated.toFixed(1) + 'h');
        $('#rb-avg-utilization').text(totals.utilization + '%');
        $('#rb-staff-count').text(totals.staff_count);
        $('#rb-overbooking-count').text(totals.overbooking_count);
        
        // Color code cards based on values
        if (totals.utilization > 100) {
            $('#rb-avg-utilization').removeClass('text-success text-warning').addClass('text-danger');
        } else if (totals.utilization > 80) {
            $('#rb-avg-utilization').removeClass('text-success text-danger').addClass('text-warning');
        } else {
            $('#rb-avg-utilization').removeClass('text-danger text-warning').addClass('text-success');
        }
        
        if (totals.overbooking_count > 0) {
            $('#rb-overbooking-count').addClass('text-danger');
        } else {
            $('#rb-overbooking-count').removeClass('text-danger');
        }
        
        // Update staff table
        var tbody = '';
        staffData.forEach(function(s) {
            var utilClass = s.utilization > 100 ? 'text-danger bold' : 
                           (s.utilization > 80 ? 'text-warning' : 'text-success');
            var overbookingCell = s.has_overbooking ? 
                '<span class="text-danger"><i class="fa fa-warning"></i> ' + s.overbooked_days + ' days</span>' : 
                '<span class="text-muted">-</span>';
            
            tbody += '<tr>' +
                '<td><strong>' + s.name + '</strong></td>' +
                '<td>' + s.available.toFixed(1) + 'h</td>' +
                '<td>' + s.allocated.toFixed(1) + 'h</td>' +
                '<td>' + s.remaining.toFixed(1) + 'h</td>' +
                '<td class="' + utilClass + '">' + s.utilization.toFixed(0) + '%</td>' +
                '<td>' + overbookingCell + '</td>' +
                '</tr>';
        });
        
        if (staffData.length === 0) {
            tbody = '<tr><td colspan="6" class="text-center text-muted"><?php echo _l("no_records_found"); ?></td></tr>';
        }
        
        $('#rb-report-tbody').html(tbody);
        
        // Update totals row
        $('#rb-total-available').text(totals.available.toFixed(1) + 'h');
        $('#rb-total-allocated').text(totals.allocated.toFixed(1) + 'h');
        $('#rb-total-remaining').text(totals.remaining.toFixed(1) + 'h');
        $('#rb-total-utilization').text(totals.utilization + '%');
        $('#rb-total-overbooking').text(totals.overbooking_count > 0 ? 
            totals.overbooking_count + ' <?php echo _l("staff_members"); ?>' : '-');
        
        // Render utilization chart (bar chart)
        if (staffData.length > 0) {
            Highcharts.chart('rb-chart-utilization', {
                chart: { type: 'bar' },
                title: { text: null },
                xAxis: {
                    categories: staffData.map(function(s) { return s.name; }),
                    title: { text: null }
                },
                yAxis: {
                    min: 0,
                    title: { text: '<?php echo _l("capacity_utilization"); ?> (%)' },
                    plotLines: [{
                        color: '#e74c3c',
                        width: 2,
                        value: 100,
                        dashStyle: 'dash',
                        zIndex: 5,
                        label: { 
                            text: '100% Capacity',
                            style: { color: '#e74c3c', fontSize: '10px' }
                        }
                    }]
                },
                legend: { enabled: false },
                tooltip: {
                    formatter: function() {
                        return '<b>' + this.x + '</b><br/>Utilization: ' + this.y.toFixed(1) + '%';
                    }
                },
                series: [{
                    name: '<?php echo _l("capacity_utilization"); ?>',
                    data: staffData.map(function(s) {
                        return {
                            y: s.utilization,
                            color: s.utilization > 100 ? '#e74c3c' : 
                                   (s.utilization > 80 ? '#f39c12' : '#27ae60')
                        };
                    })
                }],
                credits: { enabled: false }
            });
        } else {
            $('#rb-chart-utilization').html('<div class="text-center text-muted" style="padding-top: 150px;"><?php echo _l("no_records_found"); ?></div>');
        }
        
        // Render projects chart (pie chart)
        if (projectHours.length > 0) {
            var projectData = projectHours.map(function(p) {
                return {
                    name: p.project_name || 'Internal',
                    y: parseFloat(p.total_hours) || 0
                };
            });
            
            Highcharts.chart('rb-chart-projects', {
                chart: { type: 'pie' },
                title: { text: null },
                tooltip: {
                    pointFormat: '<b>{point.y:.1f}h</b> ({point.percentage:.1f}%)'
                },
                plotOptions: {
                    pie: {
                        allowPointSelect: true,
                        cursor: 'pointer',
                        dataLabels: {
                            enabled: true,
                            format: '<b>{point.name}</b>: {point.y:.1f}h'
                        },
                        showInLegend: true
                    }
                },
                series: [{
                    name: '<?php echo _l("hours_use"); ?>',
                    colorByPoint: true,
                    data: projectData
                }],
                credits: { enabled: false }
            });
        } else {
            // Show available vs allocated pie if no project breakdown
            Highcharts.chart('rb-chart-projects', {
                chart: { type: 'pie' },
                title: { text: null },
                tooltip: {
                    pointFormat: '<b>{point.y:.1f}h</b> ({point.percentage:.1f}%)'
                },
                plotOptions: {
                    pie: {
                        allowPointSelect: true,
                        cursor: 'pointer',
                        dataLabels: {
                            enabled: true,
                            format: '<b>{point.name}</b>: {point.y:.1f}h'
                        }
                    }
                },
                series: [{
                    name: '<?php echo _l("hours_use"); ?>',
                    colorByPoint: true,
                    data: [
                        { name: '<?php echo _l("capacity_allocated"); ?>', y: totals.allocated, color: '#3498db' },
                        { name: '<?php echo _l("capacity_remaining"); ?>', y: Math.max(0, totals.remaining), color: '#ecf0f1' }
                    ]
                }],
                credits: { enabled: false }
            });
        }
    }
});
</script>

</body>
</html>
