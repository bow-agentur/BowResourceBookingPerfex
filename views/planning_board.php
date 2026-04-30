<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <!-- Planning Board Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h4 class="no-margin font-bold">
                                    <i class="fa fa-th" aria-hidden="true"></i> 
                                    <?php echo _l($title); ?>
                                </h4>
                            </div>
                            <div class="col-md-8 text-right">
                                <!-- Toolbar -->
                                <div class="btn-group rb-date-nav">
                                    <button type="button" class="btn btn-default" id="rb-prev">
                                        <i class="fa fa-chevron-left"></i>
                                    </button>
                                    <button type="button" class="btn btn-primary" id="rb-today">
                                        <?php echo _l('today'); ?>
                                    </button>
                                    <button type="button" class="btn btn-default" id="rb-next">
                                        <i class="fa fa-chevron-right"></i>
                                    </button>
                                </div>
                                
                                <div class="btn-group rb-view-toggle mright10 mleft10">
                                    <button type="button" class="btn btn-default rb-view-btn" data-view="week">
                                        <?php echo _l('view_week'); ?>
                                    </button>
                                    <button type="button" class="btn btn-default rb-view-btn active" data-view="month">
                                        <?php echo _l('view_month'); ?>
                                    </button>
                                </div>

                                <?php if(has_permission('resourcebooking', '', 'create')): ?>
                                <button type="button" class="btn btn-success" id="rb-add-allocation">
                                    <i class="fa fa-plus"></i> <?php echo _l('new_allocation'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Row -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body ptop10 pbottom10">
                        <div class="row">
                            <div class="col-md-2">
                                <select name="filter_staff" id="rb-filter-staff" class="selectpicker" 
                                        data-live-search="true" data-width="100%" 
                                        title="<?php echo _l('filter_staff'); ?>">
                                    <option value=""><?php echo _l('all_staff'); ?></option>
                                    <?php foreach($staff as $member): ?>
                                    <option value="<?php echo $member['staffid']; ?>">
                                        <?php echo $member['firstname'] . ' ' . $member['lastname']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_project" id="rb-filter-project" class="selectpicker" 
                                        data-live-search="true" data-width="100%" 
                                        title="<?php echo _l('filter_project'); ?>">
                                    <option value=""><?php echo _l('all_projects'); ?></option>
                                    <?php foreach($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>">
                                        <?php echo $project['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group">
                                    <span class="input-group-addon"><?php echo _l('from'); ?></span>
                                    <input type="text" class="form-control datepicker" id="rb-date-from" 
                                           placeholder="<?php echo _l('start_date'); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group">
                                    <span class="input-group-addon"><?php echo _l('to'); ?></span>
                                    <input type="text" class="form-control datepicker" id="rb-date-to" 
                                           placeholder="<?php echo _l('end_date'); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary btn-block" id="rb-apply-dates">
                                    <i class="fa fa-search"></i> <?php echo _l('filter'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Board-level overbooking banner -->
        <div class="row">
            <div class="col-md-12">
                <div id="rb-board-overbooking-warning">
                    <span class="rb-board-overbooking-close">&times;</span>
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong><?php echo _l('overbooking_warning'); ?></strong>
                    <span id="rb-board-overbooking-message"></span>
                </div>
            </div>
        </div>

        <!-- Planning Board Grid -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body rb-board-container">
                        <!-- Date Header -->
                        <div class="rb-date-header" id="rb-date-header">
                            <div class="rb-staff-column-header">
                                <?php echo _l('staff_member'); ?>
                                <i class="fa fa-info-circle rb-legend-info" 
                                   data-toggle="tooltip" 
                                   data-html="true"
                                   data-placement="right"
                                   title="<div class='rb-legend-tooltip'>
                                       <div><span class='dot dot-grey'></span> <?php echo _l('no_allocations'); ?></div>
                                       <div><span class='dot dot-green'></span> &lt;50% <?php echo _l('utilization'); ?></div>
                                       <div><span class='dot dot-orange'></span> 50-80% <?php echo _l('utilization'); ?></div>
                                       <div><span class='dot dot-darkorange'></span> 80-100% <?php echo _l('utilization'); ?></div>
                                       <div><span class='dot dot-red'></span> <?php echo _l('overbooking'); ?></div>
                                   </div>"></i>
                            </div>
                            <div class="rb-dates-scroll" id="rb-dates-container">
                                <!-- Dates will be rendered by JS -->
                            </div>
                        </div>
                        
                        <!-- Staff Rows -->
                        <div class="rb-board-body" id="rb-board-body">
                            <div class="rb-loading" id="rb-loading">
                                <i class="fa fa-spinner fa-spin fa-2x"></i>
                                <p><?php echo _l('loading_board'); ?></p>
                            </div>
                            <!-- Staff rows will be rendered by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Allocation Modal -->
<div class="modal fade" id="rb-allocation-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="rb-modal-title"><?php echo _l('new_allocation'); ?></h4>
            </div>
            <div class="modal-body">
                <form id="rb-allocation-form">
                    <input type="hidden" name="id" id="rb-alloc-id">
                    
                    <div class="form-group">
                        <label for="rb-alloc-staff"><?php echo _l('staff_member'); ?> <span class="text-danger">*</span></label>
                        <select name="staff_id" id="rb-alloc-staff" class="selectpicker" 
                                data-live-search="true" data-width="100%" required>
                            <option value=""><?php echo _l('dropdown_non_selected_tex'); ?></option>
                            <?php foreach($staff as $member): ?>
                            <option value="<?php echo $member['staffid']; ?>">
                                <?php echo $member['firstname'] . ' ' . $member['lastname']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="rb-alloc-project"><?php echo _l('project'); ?></label>
                        <select name="project_id" id="rb-alloc-project" class="selectpicker" 
                                data-live-search="true" data-width="100%">
                            <option value=""><?php echo _l('no_project'); ?></option>
                            <?php foreach($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" data-color="<?php echo $project['color'] ?? '#3498db'; ?>">
                                <?php echo $project['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="rb-task-group">
                        <label for="rb-alloc-task"><?php echo _l('task'); ?></label>
                        <select name="task_id" id="rb-alloc-task" class="selectpicker"
                                data-live-search="true" data-width="100%"
                                title="<?php echo _l('select_task_optional'); ?>">
                            <option value=""><?php echo _l('no_task'); ?></option>
                        </select>
                        <small class="text-muted"><?php echo _l('task_auto_fills_dates'); ?></small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rb-alloc-start"><?php echo _l('start_date'); ?> <span class="text-danger">*</span></label>
                                <div class="input-group date">
                                    <input type="text" class="form-control datepicker" name="start_date" 
                                           id="rb-alloc-start" required>
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rb-alloc-end"><?php echo _l('end_date'); ?> <span class="text-danger">*</span></label>
                                <div class="input-group date">
                                    <input type="text" class="form-control datepicker" name="end_date" 
                                           id="rb-alloc-end" required>
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rb-alloc-hours"><?php echo _l('hours_per_day'); ?> <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="hours_per_day" id="rb-alloc-hours"
                                       min="0.5" max="24" step="0.5" value="8" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rb-alloc-total-hours"><?php echo _l('estimated_hours'); ?></label>
                                <input type="number" class="form-control" id="rb-alloc-total-hours"
                                       min="0" step="0.5" placeholder="auto">
                                <small class="text-muted"><?php echo _l('total_hours_auto_hint'); ?></small>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted" id="rb-total-hours-display" style="margin-top:-8px;margin-bottom:10px;font-size:12px;"></p>
                    
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="include_weekends" id="rb-alloc-weekends" value="1">
                        <label for="rb-alloc-weekends"><?php echo _l('include_weekends'); ?></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="rb-alloc-note"><?php echo _l('note'); ?></label>
                        <textarea class="form-control" name="note" id="rb-alloc-note" rows="3"></textarea>
                    </div>
                    
                    <!-- Overbooking Warning -->
                    <div class="alert alert-warning" id="rb-overbooking-warning" style="display: none;">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong><?php echo _l('overbooking_warning'); ?></strong>
                        <span id="rb-overbooking-message"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <?php if(has_permission('resourcebooking', '', 'delete')): ?>
                <button type="button" class="btn btn-danger pull-left" id="rb-delete-allocation" style="display: none;">
                    <i class="fa fa-trash"></i> <?php echo _l('delete'); ?>
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="rb-save-allocation">
                    <?php echo _l('save_allocation'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Time Off Modal -->
<div class="modal fade" id="rb-timeoff-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><?php echo _l('new_time_off'); ?></h4>
            </div>
            <div class="modal-body">
                <form id="rb-timeoff-form">
                    <input type="hidden" name="id" id="rb-timeoff-id">
                    
                    <div class="form-group">
                        <label for="rb-timeoff-staff"><?php echo _l('staff_member'); ?> <span class="text-danger">*</span></label>
                        <select name="staff_id" id="rb-timeoff-staff" class="selectpicker" 
                                data-live-search="true" data-width="100%" required>
                            <?php foreach($staff as $member): ?>
                            <option value="<?php echo $member['staffid']; ?>">
                                <?php echo $member['firstname'] . ' ' . $member['lastname']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="rb-timeoff-type"><?php echo _l('time_off_type'); ?></label>
                        <select name="type" id="rb-timeoff-type" class="selectpicker" data-width="100%">
                            <option value="vacation"><?php echo _l('vacation'); ?></option>
                            <option value="sick"><?php echo _l('sick_leave'); ?></option>
                            <option value="holiday"><?php echo _l('holiday'); ?></option>
                            <option value="personal"><?php echo _l('personal'); ?></option>
                            <option value="other"><?php echo _l('other'); ?></option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rb-timeoff-start"><?php echo _l('start_time'); ?></label>
                                <div class="input-group date">
                                    <input type="text" class="form-control datepicker" name="start_date" 
                                           id="rb-timeoff-start" required>
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rb-timeoff-end"><?php echo _l('end_time'); ?></label>
                                <div class="input-group date">
                                    <input type="text" class="form-control datepicker" name="end_date" 
                                           id="rb-timeoff-end" required>
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="rb-timeoff-note"><?php echo _l('note'); ?></label>
                        <textarea class="form-control" name="note" id="rb-timeoff-note" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="rb-save-timeoff">
                    <?php echo _l('submit'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<!-- Planning Board Assets -->
<link rel="stylesheet" href="<?php echo module_dir_url('resourcebooking', 'assets/css/planning-board.css'); ?>?v=<?php echo time(); ?>">
<script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.18/dist/interact.min.js"></script>
<script src="<?php echo module_dir_url('resourcebooking', 'assets/js/modules/pb-utils.js'); ?>?v=<?php echo time(); ?>"></script>
<script src="<?php echo module_dir_url('resourcebooking', 'assets/js/modules/pb-render.js'); ?>?v=<?php echo time(); ?>"></script>
<script src="<?php echo module_dir_url('resourcebooking', 'assets/js/modules/pb-drag.js'); ?>?v=<?php echo time(); ?>"></script>
<script src="<?php echo module_dir_url('resourcebooking', 'assets/js/modules/pb-modal.js'); ?>?v=<?php echo time(); ?>"></script>
<script src="<?php echo module_dir_url('resourcebooking', 'assets/js/planning-board.js'); ?>?v=<?php echo time(); ?>"></script>

<script>
// Initialize Planning Board with server data
$(function() {
    if (typeof PlanningBoard !== 'undefined') {
        PlanningBoard.init({
            baseUrl: '<?php echo site_url(); ?>',
            apiUrl: '<?php echo admin_url("resourcebooking"); ?>',
            csrfToken: '<?php echo $this->security->get_csrf_hash(); ?>',
            canEdit: <?php echo has_permission('resourcebooking', '', 'edit') ? 'true' : 'false'; ?>,
            canDelete: <?php echo has_permission('resourcebooking', '', 'delete') ? 'true' : 'false'; ?>,
            canCreate: <?php echo has_permission('resourcebooking', '', 'create') ? 'true' : 'false'; ?>,
            isEmployee: <?php echo isset($is_employee) && $is_employee ? 'true' : 'false'; ?>,
            ownStaffId: <?php echo isset($own_staff_id) ? (int)$own_staff_id : 'null'; ?>,
            lang: {
                loading: '<?php echo _l("loading_board"); ?>',
                noAllocations: '<?php echo _l("no_allocations"); ?>',
                confirmDelete: '<?php echo _l("confirm_delete_allocation"); ?>',
                overbookingWarning: '<?php echo _l("overbooking_warning"); ?>',
                errorSaving: '<?php echo _l("error_saving_allocation"); ?>',
                allocated: '<?php echo _l("allocated"); ?>'
            }
        });
    }
});
</script>

</body>
</html>
