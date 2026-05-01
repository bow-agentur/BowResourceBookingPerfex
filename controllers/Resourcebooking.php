<?php

defined("BASEPATH") or exit("No direct script access allowed");

/**
 * BOW Booking Controller
 * Float-ähnliche Ressourcenplanung für Perfex CRM
 */
class Resourcebooking extends AdminController
{
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Default redirect to planning board
   */
  public function index()
  {
    redirect(admin_url('resourcebooking/planning_board'));
  }

  // ==========================================================================
  // PLANNING BOARD - Float-ähnliche Ressourcenplanung
  // ==========================================================================

  /**
   * Planning Board View - Timeline für Ressourcenplanung
   */
  public function planning_board()
  {
    if (!has_permission('resourcebooking', '', 'view')) {
      access_denied('resourcebooking');
    }

    $this->load->model('resourcebooking/rb_planning_model');

    // Default: aktuelle Woche
    $date_from = $this->input->get('date_from') ?: date('Y-m-d', strtotime('monday this week'));
    $date_to   = $this->input->get('date_to') ?: date('Y-m-d', strtotime('sunday this week'));

    // Staff als Array laden
    $staff_objects = $this->staff_model->get('', ['active' => 1]);
    $staff = [];
    if (is_array($staff_objects)) {
      foreach ($staff_objects as $s) {
        $staff[] = (array) $s;
      }
    } elseif (is_object($staff_objects)) {
      $staff[] = (array) $staff_objects;
    }

    // Projects laden
    $projects = $this->rb_planning_model->get_active_projects();

    $data['title']     = _l('planning_board');
    $data['date_from'] = $date_from;
    $data['date_to']   = $date_to;
    $data['staff']     = $staff;
    $data['projects']  = $projects;
    // v2.0: employee vs. admin distinction
    // isEmployee = true only when the user has NO edit/create/delete access at all → pure read-only
    $data['is_employee']  = !has_permission('resourcebooking', '', 'create')
                         && !has_permission('resourcebooking', '', 'edit')
                         && !has_permission('resourcebooking', '', 'delete');
    $data['own_staff_id'] = get_staff_user_id();

    $this->load->view('planning_board', $data);
  }

  /**
   * Reports View - Auslastungsübersicht
   */
  public function reports()
  {
    if (!has_permission('resourcebooking', '', 'view')) {
      access_denied('resourcebooking');
    }

    $this->load->model('resourcebooking/rb_planning_model');

    // Staff als Array laden
    $staff_objects = $this->staff_model->get('', ['active' => 1]);
    $staff = [];
    if (is_array($staff_objects)) {
      foreach ($staff_objects as $s) {
        $staff[] = (array) $s;
      }
    }

    // Time Off für aktuellen Monat
    $date_from = date('Y-m-01');
    $date_to   = date('Y-m-t');
    $time_off  = $this->rb_planning_model->get_time_off([
      'date_from' => $date_from,
      'date_to'   => $date_to
    ]);

    $data['title']    = _l('rb_reports');
    $data['staff']    = $staff;
    $data['time_off'] = $time_off;
    $data['projects'] = $this->rb_planning_model->get_active_projects();

    $this->load->view('reports', $data);
  }

  // ==========================================================================
  // API ENDPOINTS - JSON Responses für Board
  // ==========================================================================

  /**
   * API: Get board data (staff, allocations, capacity)
   */
  public function api_board_data()
  {
    header('Content-Type: application/json');
    
    try {
      if (!has_permission('resourcebooking', '', 'view')) {
        $this->output->set_status_header(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
      }

      if (!$this->db->table_exists(db_prefix() . 'rb_allocations')) {
        echo json_encode([
          'success' => false, 
          'error' => 'Database tables not found. Please deactivate and reactivate the module.'
        ]);
        return;
      }

      $this->load->model('resourcebooking/rb_planning_model');

      $date_from = $this->input->get('start_date') ?: $this->input->get('date_from') ?: date('Y-m-d', strtotime('monday this week'));
      $date_to   = $this->input->get('end_date') ?: $this->input->get('date_to') ?: date('Y-m-d', strtotime('sunday this week'));

      $filters = [];
      if ($this->input->get('staff_ids')) {
        $filters['staff_ids'] = explode(',', $this->input->get('staff_ids'));
      }

      $data = $this->rb_planning_model->get_board_data($date_from, $date_to, $filters);
      echo json_encode(['success' => true, 'data' => $data]);
      
    } catch (Exception $e) {
      $this->output->set_status_header(500);
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
  }

  /**
   * API: Get/Create allocations
   */
  public function api_allocations()
  {
    header('Content-Type: application/json');
    
    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    if ($this->input->method() === 'post') {
      if (!has_permission('resourcebooking', '', 'create')) {
        $this->output->set_status_header(403);
        echo json_encode(['error' => 'Access denied']);
        return;
      }

      $date_from = $this->input->post('start_date') ?: $this->input->post('date_from');
      $date_to   = $this->input->post('end_date') ?: $this->input->post('date_to');

      $data = [
        'staff_id'         => $this->input->post('staff_id'),
        'project_id'       => $this->input->post('project_id') ?: null,
        'task_id'          => $this->input->post('task_id') ?: null,
        'date_from'        => $date_from,
        'date_to'          => $date_to,
        'hours_per_day'    => $this->input->post('hours_per_day') ?: 8,
        'allocation_type'  => $this->input->post('allocation_type') ?: 'hours',
        'note'             => $this->input->post('note'),
        'color'            => $this->input->post('color')
      ];
      
      if ($this->db->field_exists('include_weekends', db_prefix() . 'rb_allocations')) {
        $data['include_weekends'] = $this->input->post('include_weekends') ? 1 : 0;
      }

      if (empty($data['staff_id']) || empty($data['date_from']) || empty($data['date_to'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
      }

      $id = $this->rb_planning_model->add_allocation($data);

      echo json_encode([
        'success' => (bool) $id,
        'id'      => $id,
        'message' => $id ? _l('allocation_created') : _l('error_occurred')
      ]);
      return;
    }

    // GET: List allocations
    $filters = array_filter([
      'staff_id'   => $this->input->get('staff_id'),
      'project_id' => $this->input->get('project_id'),
      'date_from'  => $this->input->get('date_from'),
      'date_to'    => $this->input->get('date_to')
    ]);

    $allocations = $this->rb_planning_model->get_allocations($filters);
    echo json_encode(['allocations' => $allocations]);
  }

  /**
   * API: Update/Delete/Get single allocation
   */
  public function api_allocation($id = '')
  {
    header('Content-Type: application/json');
    
    if (!$id || !is_numeric($id)) {
      $this->output->set_status_header(400);
      echo json_encode(['error' => 'Invalid ID']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    // DELETE
    if ($this->input->method() === 'delete' || $this->input->post('_method') === 'DELETE') {
      if (!has_permission('resourcebooking', '', 'delete')) {
        $this->output->set_status_header(403);
        echo json_encode(['error' => 'Access denied']);
        return;
      }

      $success = $this->rb_planning_model->delete_allocation($id);
      echo json_encode([
        'success' => $success,
        'message' => $success ? _l('allocation_deleted') : _l('error_occurred')
      ]);
      return;
    }

    // PUT/PATCH - Update
    if ($this->input->method() === 'post' || $this->input->method() === 'put') {
      if (!has_permission('resourcebooking', '', 'edit')) {
        $this->output->set_status_header(403);
        echo json_encode(['error' => 'Access denied']);
        return;
      }

      $data = [];
      $fields = ['staff_id', 'project_id', 'task_id', 'date_from', 'date_to', 'hours_per_day', 'allocation_type', 'note', 'color'];
      
      if ($this->db->field_exists('include_weekends', db_prefix() . 'rb_allocations')) {
        $fields[] = 'include_weekends';
      }
      
      foreach ($fields as $field) {
        $value = $this->input->post($field);
        if ($value !== null) {
          $data[$field] = $value;
        }
      }
      
      if ($this->input->post('start_date') && !isset($data['date_from'])) {
        $data['date_from'] = $this->input->post('start_date');
      }
      if ($this->input->post('end_date') && !isset($data['date_to'])) {
        $data['date_to'] = $this->input->post('end_date');
      }
      
      if (isset($data['include_weekends'])) {
        $data['include_weekends'] = $data['include_weekends'] ? 1 : 0;
      }

      $success = $this->rb_planning_model->update_allocation($id, $data);
      echo json_encode([
        'success' => $success,
        'message' => $success ? _l('allocation_updated') : _l('error_occurred')
      ]);
      return;
    }

    // GET single allocation
    $allocation = $this->rb_planning_model->get_allocation($id);
    echo json_encode(['allocation' => $allocation]);
  }

  /**
   * API: Move allocation (Drag & Drop)
   */
  public function api_allocation_move($id = '')
  {
    header('Content-Type: application/json');
    
    if (!$id || !is_numeric($id)) {
      $this->output->set_status_header(400);
      echo json_encode(['error' => 'Invalid ID']);
      return;
    }

    if (!has_permission('resourcebooking', '', 'edit')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    $data = [
      'date_from' => $this->input->post('date_from'),
      'date_to'   => $this->input->post('date_to')
    ];

    if ($this->input->post('staff_id')) {
      $data['staff_id'] = $this->input->post('staff_id');
    }

    $success = $this->rb_planning_model->update_allocation($id, $data);

    echo json_encode([
      'success' => $success,
      'message' => $success ? _l('allocation_moved') : _l('error_occurred')
    ]);
  }

  /**
   * API: Time Off CRUD
   */
  public function api_time_off($id = '')
  {
    header('Content-Type: application/json');
    $this->load->model('resourcebooking/rb_planning_model');

    // DELETE
    if ($id && ($this->input->method() === 'delete' || $this->input->post('_method') === 'DELETE')) {
      if (!has_permission('resourcebooking', '', 'delete')) {
        $this->output->set_status_header(403);
        echo json_encode(['error' => 'Access denied']);
        return;
      }

      $success = $this->rb_planning_model->delete_time_off($id);
      echo json_encode(['success' => $success]);
      return;
    }

    // POST: Create
    if ($this->input->method() === 'post' && !$id) {
      if (!has_permission('resourcebooking', '', 'create')) {
        $this->output->set_status_header(403);
        echo json_encode(['error' => 'Access denied']);
        return;
      }

      $data = [
        'staff_id'      => $this->input->post('staff_id'),
        'date_from'     => $this->input->post('date_from'),
        'date_to'       => $this->input->post('date_to'),
        'type'          => $this->input->post('type') ?: 'vacation',
        'hours_per_day' => $this->input->post('hours_per_day'),
        'note'          => $this->input->post('note'),
        'approved'      => $this->input->post('approved') ?: 0
      ];

      $new_id = $this->rb_planning_model->add_time_off($data);
      echo json_encode(['success' => (bool) $new_id, 'id' => $new_id]);
      return;
    }

    // PUT: Update
    if ($id && $this->input->method() === 'post') {
      if (!has_permission('resourcebooking', '', 'edit')) {
        $this->output->set_status_header(403);
        echo json_encode(['error' => 'Access denied']);
        return;
      }

      $data = [];
      foreach (['staff_id', 'date_from', 'date_to', 'type', 'hours_per_day', 'note', 'approved'] as $field) {
        $value = $this->input->post($field);
        if ($value !== null) {
          $data[$field] = $value;
        }
      }

      $success = $this->rb_planning_model->update_time_off($id, $data);
      echo json_encode(['success' => $success]);
      return;
    }

    // GET
    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $filters = array_filter([
      'staff_id'  => $this->input->get('staff_id'),
      'date_from' => $this->input->get('date_from'),
      'date_to'   => $this->input->get('date_to'),
      'type'      => $this->input->get('type')
    ]);

    $time_off = $this->rb_planning_model->get_time_off($filters);
    echo json_encode(['time_off' => $time_off]);
  }

  /**
   * API: Work Patterns
   */
  public function api_work_patterns($staff_id = '')
  {
    header('Content-Type: application/json');
    
    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');
    $patterns = $this->rb_planning_model->get_work_patterns($staff_id ?: null);
    echo json_encode(['work_patterns' => $patterns]);
  }

  /**
   * API: Report data
   */
  public function api_report_data()
  {
    header('Content-Type: application/json');
    
    try {
      if (!has_permission('resourcebooking', '', 'view')) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
      }

      $this->load->model('resourcebooking/rb_planning_model');

      $date_from = $this->input->get('start_date') ?: date('Y-m-01');
      $date_to   = $this->input->get('end_date') ?: date('Y-m-t');
      $staff_id  = $this->input->get('staff_id') ?: null;
      $project_id = $this->input->get('project_id') ?: null;

      $filters = $staff_id ? ['staff_ids' => [$staff_id]] : [];
      $staff_list = $this->rb_planning_model->get_staff_with_capacity($date_from, $date_to, $filters);
      
      $staff_summary = [];
      $total_available = 0;
      $total_allocated = 0;
      $overbooking_count = 0;
      
      foreach ($staff_list as $staff) {
        $cap = isset($staff['capacity']) ? $staff['capacity'] : [];
        $available = floatval($cap['total_available'] ?? 0);
        $allocated = floatval($cap['total_allocated'] ?? 0);
        $has_overbooking = ($cap['overbooked_days'] ?? 0) > 0;
        
        if ($has_overbooking) $overbooking_count++;
        
        $staff_summary[] = [
          'id' => $staff['staffid'] ?? 0,
          'name' => ($staff['firstname'] ?? '') . ' ' . ($staff['lastname'] ?? ''),
          'profile_image' => $staff['profile_image'] ?? null,
          'available' => $available,
          'allocated' => $allocated,
          'remaining' => $available - $allocated,
          'utilization' => $available > 0 ? round(($allocated / $available) * 100, 1) : 0,
          'has_overbooking' => $has_overbooking,
          'overbooked_days' => $cap['overbooked_days'] ?? 0
        ];
        
        $total_available += $available;
        $total_allocated += $allocated;
      }
      
      $project_hours = $this->rb_planning_model->get_allocations_by_project($date_from, $date_to, $staff_id);
      $task_details  = $this->rb_planning_model->get_tasks_for_report($date_from, $date_to, $staff_id, $project_id);
      $time_off_summary = $this->rb_planning_model->get_time_off_summary($date_from, $date_to, $staff_id);

      echo json_encode([
        'success' => true,
        'data' => [
          'date_range' => ['from' => $date_from, 'to' => $date_to],
          'totals' => [
            'available' => $total_available,
            'allocated' => $total_allocated,
            'remaining' => $total_available - $total_allocated,
            'utilization' => $total_available > 0 ? round(($total_allocated / $total_available) * 100, 1) : 0,
            'staff_count' => count($staff_summary),
            'overbooking_count' => $overbooking_count
          ],
          'staff_summary' => $staff_summary,
          'project_hours' => $project_hours,
          'task_details'  => $task_details,
          'time_off_summary' => $time_off_summary
        ]
      ]);
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
  }

  /**
   * API: Capacity calculation
   */
  public function api_capacity()
  {
    header('Content-Type: application/json');
    
    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    $staff_ids = $this->input->get('staff_ids');
    $date_from = $this->input->get('date_from') ?: date('Y-m-d', strtotime('monday this week'));
    $date_to   = $this->input->get('date_to') ?: date('Y-m-d', strtotime('sunday this week'));

    if ($staff_ids) {
      $staff_ids = explode(',', $staff_ids);
    } else {
      $staff = $this->staff_model->get('', ['active' => 1]);
      $staff_ids = array_column((array)$staff, 'staffid');
    }

    $capacity = $this->rb_planning_model->get_capacity($staff_ids, $date_from, $date_to);
    echo json_encode(['capacity' => $capacity]);
  }

  /**
   * API: Staff list with capacity
   */
  public function api_staff_list()
  {
    header('Content-Type: application/json');
    
    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    $date_from = $this->input->get('date_from') ?: date('Y-m-d', strtotime('monday this week'));
    $date_to   = $this->input->get('date_to') ?: date('Y-m-d', strtotime('sunday this week'));

    $staff = $this->rb_planning_model->get_staff_with_capacity($date_from, $date_to);
    echo json_encode(['staff' => $staff]);
  }

  /**
   * API: Projects list
   */
  public function api_projects_list()
  {
    header('Content-Type: application/json');
    
    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');
    $projects = $this->rb_planning_model->get_active_projects();
    echo json_encode(['projects' => $projects]);
  }

  // ===========================================================================
  // PLANNING BOARD v2.0 — Live-Write API
  // ===========================================================================

  /**
   * API: Assign staff member to project or task (writes to Perfex)
   * POST: staff_id, project_id [, task_id]
   */
  public function api_assign_member()
  {
    header('Content-Type: application/json');

    if (!has_permission('resourcebooking', '', 'create')) {
      $this->output->set_status_header(403);
      echo json_encode(['success' => false, 'error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    $staff_id   = (int)$this->input->post('staff_id');
    $project_id = (int)$this->input->post('project_id');
    $task_id    = (int)$this->input->post('task_id');

    if (!$staff_id) {
      $this->output->set_status_header(400);
      echo json_encode(['success' => false, 'error' => 'staff_id required']);
      return;
    }

    if ($task_id) {
      $ok = $this->rb_planning_model->assign_staff_to_task($staff_id, $task_id);
    } elseif ($project_id) {
      $ok = $this->rb_planning_model->assign_staff_to_project($staff_id, $project_id);
    } else {
      $this->output->set_status_header(400);
      echo json_encode(['success' => false, 'error' => 'project_id or task_id required']);
      return;
    }

    // Automatically add the staff member as a project follower when a project is assigned
    if ($project_id) {
      $this->rb_planning_model->add_project_follower($staff_id, $project_id);
    }

    echo json_encode(['success' => (bool)$ok]);
  }

  /**
   * API: Remove staff member from project or task
   * POST: staff_id, project_id [, task_id]
   */
  public function api_remove_member()
  {
    header('Content-Type: application/json');

    if (!has_permission('resourcebooking', '', 'delete')) {
      $this->output->set_status_header(403);
      echo json_encode(['success' => false, 'error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    $staff_id   = (int)$this->input->post('staff_id');
    $project_id = (int)$this->input->post('project_id');
    $task_id    = (int)$this->input->post('task_id');

    if (!$staff_id) {
      $this->output->set_status_header(400);
      echo json_encode(['success' => false, 'error' => 'staff_id required']);
      return;
    }

    if ($task_id) {
      $ok = $this->rb_planning_model->remove_staff_from_task($staff_id, $task_id);
    } elseif ($project_id) {
      $ok = $this->rb_planning_model->remove_staff_from_project($staff_id, $project_id);
    } else {
      $this->output->set_status_header(400);
      echo json_encode(['success' => false, 'error' => 'project_id or task_id required']);
      return;
    }

    echo json_encode(['success' => (bool)$ok]);
  }

  /**
   * API: Update estimated_hours on a task
   * POST: task_id, estimated_hours
   */
  public function api_update_task_hours()
  {
    header('Content-Type: application/json');

    if (!has_permission('resourcebooking', '', 'edit')) {
      $this->output->set_status_header(403);
      echo json_encode(['success' => false, 'error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    $task_id         = (int)$this->input->post('task_id');
    $estimated_hours = $this->input->post('estimated_hours');

    if (!$task_id || $estimated_hours === null) {
      $this->output->set_status_header(400);
      echo json_encode(['success' => false, 'error' => 'task_id and estimated_hours required']);
      return;
    }

    $ok = $this->rb_planning_model->update_task_hours($task_id, $estimated_hours);
    echo json_encode(['success' => (bool)$ok]);
  }

  /**
   * API: UPSERT planning override in rb_allocations
   * POST: staff_id, project_id [, task_id], hours_per_day, color, note, date_from, date_to
   */
  public function api_upsert_override()
  {
    header('Content-Type: application/json');

    if (!has_permission('resourcebooking', '', 'edit')) {
      $this->output->set_status_header(403);
      echo json_encode(['success' => false, 'error' => 'Access denied']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');

    $data = [
      'staff_id'        => $this->input->post('staff_id'),
      'project_id'      => $this->input->post('project_id') ?: null,
      'task_id'         => $this->input->post('task_id') ?: null,
      'hours_per_day'   => $this->input->post('hours_per_day'),
      'color'           => $this->input->post('color'),
      'note'            => $this->input->post('note'),
      'date_from'       => $this->input->post('date_from'),
      'date_to'         => $this->input->post('date_to'),
      'include_weekends'=> $this->input->post('include_weekends'),
    ];

    if (empty($data['staff_id'])) {
      $this->output->set_status_header(400);
      echo json_encode(['success' => false, 'error' => 'staff_id required']);
      return;
    }

    $id = $this->rb_planning_model->upsert_override($data);

    // If a task is linked, write total estimated hours back to the task
    // so the task always reflects the planned effort from the board.
    if ($id && !empty($data['task_id']) && !empty($data['hours_per_day'])
        && !empty($data['date_from']) && !empty($data['date_to'])) {
      $this->load->helper('resourcebooking/rb_capacity');
      $include_weekends = !empty($data['include_weekends']);
      if ($include_weekends) {
        $d1   = new DateTime($data['date_from']);
        $d2   = new DateTime($data['date_to']);
        $days = (int)$d1->diff($d2)->days + 1;
      } else {
        $days = rb_count_working_days($data['date_from'], $data['date_to']);
      }
      if ($days > 0) {
        $total_hours = round((float)$data['hours_per_day'] * $days, 2);
        $this->rb_planning_model->update_task_hours((int)$data['task_id'], $total_hours);
      }
    }

    echo json_encode(['success' => (bool)$id, 'id' => $id]);
  }

  /**
   * API: Get single project with start_date and deadline (for auto-fill)
   * GET: project_id
   */
  public function api_get_project($project_id = '')
  {
    header('Content-Type: application/json');

    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $id = $project_id ?: $this->input->get('project_id');

    if (!$id || !is_numeric($id)) {
      $this->output->set_status_header(400);
      echo json_encode(['error' => 'Invalid project_id']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');
    $project = $this->rb_planning_model->get_project_dates((int)$id);

    if (!$project) {
      $this->output->set_status_header(404);
      echo json_encode(['error' => 'Project not found']);
      return;
    }

    echo json_encode(['project' => $project]);
  }

  /**
   * API: Get tasks for a project (optionally filtered by staff)
   * GET: project_id [, staff_id]
   */
  public function api_get_tasks()
  {
    header('Content-Type: application/json');

    if (!has_permission('resourcebooking', '', 'view')) {
      $this->output->set_status_header(403);
      echo json_encode(['error' => 'Access denied']);
      return;
    }

    $project_id = (int)$this->input->get('project_id');
    $staff_id   = (int)$this->input->get('staff_id') ?: null;

    if (!$project_id) {
      $this->output->set_status_header(400);
      echo json_encode(['error' => 'project_id required']);
      return;
    }

    $this->load->model('resourcebooking/rb_planning_model');
    $tasks = $this->rb_planning_model->get_tasks_for_project($project_id, $staff_id);
    echo json_encode(['tasks' => $tasks]);
  }
}
