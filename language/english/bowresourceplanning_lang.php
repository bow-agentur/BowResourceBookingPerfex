<?php

$lang['bowresourceplanning'] = 'Resource Planning';

// ============================================================
// Planning Board (Float-like Resource Planning) - NEW
// ============================================================

// Menu & Navigation
$lang['planning_board'] = 'Planning Board';
$lang['rb_reports'] = 'Reports';
$lang['rb_settings'] = 'Settings';

// Allocations
$lang['allocation'] = 'Allocation';
$lang['allocations'] = 'Allocations';
$lang['new_allocation'] = 'New Allocation';
$lang['edit_allocation'] = 'Edit Allocation';
$lang['allocation_created'] = 'Allocation created successfully';
$lang['allocation_updated'] = 'Allocation updated successfully';
$lang['allocation_deleted'] = 'Allocation deleted successfully';
$lang['allocation_moved'] = 'Allocation moved successfully';
$lang['total_hours'] = 'Total hours';
$lang['include_weekends'] = 'Include weekends';
$lang['note'] = 'Note';

// Capacity & Overbooking
$lang['capacity'] = 'Capacity';
$lang['capacity_available'] = 'Available Capacity';
$lang['capacity_allocated'] = 'Allocated';
$lang['capacity_remaining'] = 'Remaining';
$lang['overbooking'] = 'Overbooking';
$lang['overbooking_warning'] = 'Warning: Overbooking detected!';
$lang['overbooking_detected'] = 'This allocation exceeds available capacity';
$lang['capacity_utilization'] = 'Utilization';
$lang['capacity_percent'] = '%s%% utilized';
$lang['allocated'] = 'allocated';
$lang['utilization'] = 'Utilization';

// Work Patterns
$lang['work_pattern'] = 'Work Pattern';
$lang['work_patterns'] = 'Work Patterns';
$lang['new_work_pattern'] = 'New Work Pattern';
$lang['edit_work_pattern'] = 'Edit Work Pattern';
$lang['default_pattern'] = 'Default Pattern';
$lang['hours_monday'] = 'Monday';
$lang['hours_tuesday'] = 'Tuesday';
$lang['hours_wednesday'] = 'Wednesday';
$lang['hours_thursday'] = 'Thursday';
$lang['hours_friday'] = 'Friday';
$lang['hours_saturday'] = 'Saturday';
$lang['hours_sunday'] = 'Sunday';
$lang['weekly_hours'] = 'Weekly Hours';

// Time Off
$lang['time_off'] = 'Time Off';
$lang['time_off_request'] = 'Time Off Request';
$lang['new_time_off'] = 'New Time Off';
$lang['edit_time_off'] = 'Edit Time Off';
$lang['time_off_created'] = 'Time off request created';
$lang['time_off_updated'] = 'Time off request updated';
$lang['time_off_deleted'] = 'Time off request deleted';
$lang['time_off_approved'] = 'Time off approved';
$lang['time_off_rejected'] = 'Time off rejected';
$lang['time_off_pending'] = 'Pending approval';
$lang['time_off_type'] = 'Type';
$lang['vacation'] = 'Vacation';
$lang['sick_leave'] = 'Sick Leave';
$lang['holiday'] = 'Holiday';
$lang['personal'] = 'Personal';
$lang['other'] = 'Other';
$lang['half_day'] = 'Half Day';
$lang['full_day'] = 'Full Day';

// Board UI
$lang['today'] = 'Scroll';
$lang['this_week'] = 'This Week';
$lang['this_month'] = 'This Month';
$lang['zoom_in'] = 'Zoom In';
$lang['zoom_out'] = 'Zoom Out';
$lang['zoom_level'] = 'Zoom Level';
$lang['view_day'] = 'Day View';
$lang['view_week'] = 'Week View';
$lang['view_month'] = 'Month View';
$lang['filter_staff'] = 'Filter Staff';
$lang['filter_project'] = 'Filter Project';
$lang['filter_department'] = 'Filter Department';
$lang['drag_to_move'] = 'Drag to move';
$lang['drag_to_resize'] = 'Drag edge to resize';
$lang['double_click_edit'] = 'Double-click to edit';
$lang['no_allocations'] = 'No allocations found';
$lang['loading_board'] = 'Loading planning board...';

// Reports
$lang['rb_report_utilization'] = 'Utilization Report';
$lang['rb_report_overbooking'] = 'Overbooking Report';
$lang['rb_report_availability'] = 'Availability Report';
$lang['rb_report_project_hours'] = 'Project Hours';
$lang['rb_report_staff_hours'] = 'Staff Hours';
$lang['rb_report_tab_overview']  = 'Overview';
$lang['rb_report_tab_projects']  = 'Projects';
$lang['rb_report_tab_tasks']     = 'Tasks';
$lang['rb_report_tab_timeoff']   = 'Time Off';
$lang['rb_report_tasks_detail']  = 'Task Details';
$lang['rb_report_projects_detail'] = 'Project Details';
$lang['rb_report_task_name']     = 'Task';
$lang['rb_report_assigned_hours']= 'Planned Hours';
$lang['rb_report_est_hours']     = 'Estimated Hours';
$lang['rb_report_task_status']   = 'Status';
$lang['rb_report_hpd']           = 'h/day';
$lang['rb_report_working_days']  = 'Working Days';
$lang['rb_report_staff_count']   = 'Staff';
$lang['rb_report_all_projects']  = 'All Projects';
$lang['date_range'] = 'Date Range';
$lang['export_csv'] = 'Export CSV';
$lang['export_pdf'] = 'Export PDF';

// Staff
$lang['staff_member'] = 'Staff Member';
$lang['staff_members'] = 'Staff Members';
$lang['all_staff'] = 'All Staff';
$lang['department'] = 'Department';

// Projects
$lang['project'] = 'Project';
$lang['projects'] = 'Projects';
$lang['all_projects'] = 'All Projects';
$lang['project_color'] = 'Project Color';
$lang['no_project'] = 'No Project (Internal)';

// Actions
$lang['save_allocation'] = 'Save Allocation';
$lang['cancel'] = 'Cancel';
$lang['confirm_delete'] = 'Are you sure you want to delete this item?';
$lang['confirm_delete_allocation'] = 'Delete this allocation?';

// Errors
$lang['error_date_range'] = 'End date must be after start date';
$lang['error_hours_invalid'] = 'Hours per day must be between 0.5 and 24';
$lang['error_staff_required'] = 'Please select a staff member';
$lang['error_loading_board'] = 'Error loading planning board';
$lang['error_saving_allocation'] = 'Error saving allocation';

// v2.0 — Task integration, HR, permissions
$lang['task']                       = 'Task';
$lang['tasks']                      = 'Tasks';
$lang['no_task']                    = 'No task';
$lang['select_task_optional']       = 'Select task (optional)';
$lang['task_auto_fills_dates']      = 'Selecting a task auto-fills start/end date.';
$lang['estimated_hours']            = 'Estimated hours';
$lang['daily_avg']                  = 'avg h/d';
$lang['assign_to_project']          = 'Assign to project';
$lang['assign_to_task']             = 'Assign to task';
$lang['remove_from_project']        = 'Remove from project';
$lang['remove_from_task']           = 'Remove from task';
$lang['overload_warning']           = 'Overload detected';
$lang['own_row_label']              = 'Me';
$lang['collapse_expand']            = 'Collapse/expand row';
$lang['task_hours_updated']         = 'Hours updated';
$lang['add_as_follower']            = 'Add as project follower';
$lang['start_date']                 = 'Start date';
$lang['end_date']                   = 'End date';
$lang['from']                       = 'From';
$lang['to']                         = 'To';
$lang['no_records_found']           = 'No records found';
$lang['hours_use']                  = 'Hours used';
$lang['total_hours_auto_hint']      = 'Set total hours to auto-calculate hours/day';
$lang['hours_per_day']              = 'Hours per day';
$lang['estimated_hours_planning_hint'] = 'Used by the Planning Board. Leave empty to use task span x 8h/day.';
$lang['reassign_task']              = 'Reassign task';
$lang['select_new_assignee']        = 'Select new assignee';
$lang['confirm_reassign']           = 'Confirm reassignment';
$lang['view_2week']                 = '2 Weeks';
$lang['view_2month']                = '2 Months';
$lang['kw']                         = 'CW';
$lang['weekly_util']                = 'Weekly utilization';
$lang['day_tooltip_available']      = 'Available';
$lang['day_tooltip_allocated']      = 'Planned';
