<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Resource Planning Model - Float-ähnliche Kapazitätsplanung
 * 
 * Handles:
 * - Allocations (Staff auf Projekte/Tasks)
 * - Work Patterns (Arbeitszeitmodelle)
 * - Time Off (Abwesenheiten)
 * - Capacity Calculation (Auslastungsberechnung)
 */
class Rb_planning_model extends App_Model
{
    // Tabellennamen
    private $table_allocations;
    private $table_work_patterns;
    private $table_time_off;

    public function __construct()
    {
        parent::__construct();
        
        $this->table_allocations   = db_prefix() . 'rb_allocations';
        $this->table_work_patterns = db_prefix() . 'rb_work_patterns';
        $this->table_time_off      = db_prefix() . 'rb_time_off';
        
        // Auto-migrations
        $this->_auto_migrate();
    }
    
    /**
     * Run auto-migrations for schema upgrades
     */
    private function _auto_migrate()
    {
        static $done = false;
        if ($done) return;
        $done = true;
        
        // include_weekends on rb_allocations
        if ($this->db->table_exists($this->table_allocations)) {
            if (!$this->db->field_exists('include_weekends', $this->table_allocations)) {
                $this->db->query('ALTER TABLE `' . $this->table_allocations . '` ADD `include_weekends` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allocation_type`');
            }
        }
        
        // estimated_hours on tbltasks (Planning Board v2.0)
        if ($this->db->table_exists(db_prefix() . 'tasks')) {
            if (!$this->db->field_exists('estimated_hours', db_prefix() . 'tasks')) {
                $this->db->query('ALTER TABLE `' . db_prefix() . 'tasks` ADD COLUMN `estimated_hours` DECIMAL(6,2) NULL DEFAULT NULL AFTER `duedate`');
            }
        }
    }

    // ========================================================================
    // ALLOCATIONS CRUD
    // ========================================================================

    /**
     * Get allocations with optional filters
     */
    public function get_allocations($filters = [])
    {
        // Include aliases for JS compatibility: date_from -> start_date, date_to -> end_date
        $this->db->select('a.*, a.date_from as start_date, a.date_to as end_date, s.firstname, s.lastname, s.profile_image, p.name as project_name, p.clientid');
        $this->db->from($this->table_allocations . ' a');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        $this->db->join(db_prefix() . 'projects p', 'p.id = a.project_id', 'left');

        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', $filters['staff_id']);
        }

        if (!empty($filters['project_id'])) {
            $this->db->where('a.project_id', $filters['project_id']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            // Allocations die im Zeitraum liegen (überlappen)
            $this->db->where('a.date_from <=', $filters['date_to']);
            $this->db->where('a.date_to >=', $filters['date_from']);
        }

        if (!empty($filters['staff_ids']) && is_array($filters['staff_ids'])) {
            $this->db->where_in('a.staff_id', $filters['staff_ids']);
        }

        $this->db->order_by('a.date_from', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * Get single allocation by ID
     */
    public function get_allocation($id)
    {
        $this->db->where('id', $id);
        return $this->db->get($this->table_allocations)->row();
    }

    /**
     * Create new allocation
     */
    public function add_allocation($data)
    {
        $data['created_by'] = get_staff_user_id();
        $data['created_at'] = date('Y-m-d H:i:s');

        // Validierung
        if (empty($data['staff_id']) || empty($data['date_from']) || empty($data['date_to'])) {
            return false;
        }

        // Datum normalisieren
        $data['date_from'] = date('Y-m-d', strtotime($data['date_from']));
        $data['date_to']   = date('Y-m-d', strtotime($data['date_to']));

        // Default-Werte
        if (empty($data['hours_per_day'])) {
            $data['hours_per_day'] = 8.00;
        }
        if (empty($data['allocation_type'])) {
            $data['allocation_type'] = 'hours';
        }

        $this->db->insert($this->table_allocations, $data);
        $id = $this->db->insert_id();

        if ($id) {
            log_activity('Allocation Created [ID: ' . $id . ', Staff: ' . $data['staff_id'] . ']');
            return $id;
        }

        return false;
    }

    /**
     * Update allocation
     */
    public function update_allocation($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Datum normalisieren wenn vorhanden
        if (!empty($data['date_from'])) {
            $data['date_from'] = date('Y-m-d', strtotime($data['date_from']));
        }
        if (!empty($data['date_to'])) {
            $data['date_to'] = date('Y-m-d', strtotime($data['date_to']));
        }

        $this->db->where('id', $id);
        $this->db->update($this->table_allocations, $data);

        if ($this->db->affected_rows() > 0) {
            log_activity('Allocation Updated [ID: ' . $id . ']');
            return true;
        }

        return false;
    }

    /**
     * Delete allocation
     */
    public function delete_allocation($id)
    {
        $this->db->where('id', $id);
        $this->db->delete($this->table_allocations);

        if ($this->db->affected_rows() > 0) {
            log_activity('Allocation Deleted [ID: ' . $id . ']');
            return true;
        }

        return false;
    }

    /**
     * Move allocation (Drag & Drop)
     */
    public function move_allocation($id, $date_from, $date_to)
    {
        return $this->update_allocation($id, [
            'date_from' => $date_from,
            'date_to'   => $date_to
        ]);
    }

    // ========================================================================
    // WORK PATTERNS CRUD
    // ========================================================================

    /**
     * Get work pattern for a staff member at a specific date
     */
    public function get_work_pattern($staff_id, $date = null)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        // Erst Staff-spezifisches Pattern suchen
        $this->db->where('staff_id', $staff_id);
        $this->db->where('valid_from <=', $date);
        $this->db->group_start();
            $this->db->where('valid_to >=', $date);
            $this->db->or_where('valid_to IS NULL');
        $this->db->group_end();
        $this->db->order_by('valid_from', 'DESC');
        $this->db->limit(1);
        
        $pattern = $this->db->get($this->table_work_patterns)->row();

        // Fallback auf System-Default
        if (!$pattern) {
            $this->db->where('staff_id', 0);
            $this->db->where('is_default', 1);
            $pattern = $this->db->get($this->table_work_patterns)->row();
        }

        // Letzter Fallback: Hardcoded Default
        if (!$pattern) {
            $pattern = (object) [
                'id'        => 0,
                'staff_id'  => $staff_id,
                'name'      => 'Default',
                'mon_hours' => 8.00,
                'tue_hours' => 8.00,
                'wed_hours' => 8.00,
                'thu_hours' => 8.00,
                'fri_hours' => 8.00,
                'sat_hours' => 0.00,
                'sun_hours' => 0.00
            ];
        }

        return $pattern;
    }

    /**
     * Get all work patterns for a staff member
     */
    public function get_work_patterns($staff_id = null)
    {
        if ($staff_id !== null) {
            $this->db->where('staff_id', $staff_id);
        }
        $this->db->order_by('valid_from', 'DESC');
        return $this->db->get($this->table_work_patterns)->result_array();
    }

    /**
     * Add work pattern
     */
    public function add_work_pattern($data)
    {
        if (empty($data['staff_id']) || empty($data['valid_from'])) {
            return false;
        }

        $data['valid_from'] = date('Y-m-d', strtotime($data['valid_from']));
        if (!empty($data['valid_to'])) {
            $data['valid_to'] = date('Y-m-d', strtotime($data['valid_to']));
        }

        $this->db->insert($this->table_work_patterns, $data);
        return $this->db->insert_id();
    }

    /**
     * Update work pattern
     */
    public function update_work_pattern($id, $data)
    {
        if (!empty($data['valid_from'])) {
            $data['valid_from'] = date('Y-m-d', strtotime($data['valid_from']));
        }
        if (!empty($data['valid_to'])) {
            $data['valid_to'] = date('Y-m-d', strtotime($data['valid_to']));
        }

        $this->db->where('id', $id);
        $this->db->update($this->table_work_patterns, $data);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Delete work pattern
     */
    public function delete_work_pattern($id)
    {
        // Nicht das System-Default löschen
        $pattern = $this->db->where('id', $id)->get($this->table_work_patterns)->row();
        if ($pattern && $pattern->is_default && $pattern->staff_id == 0) {
            return false;
        }

        $this->db->where('id', $id);
        $this->db->delete($this->table_work_patterns);

        return $this->db->affected_rows() > 0;
    }

    // ========================================================================
    // TIME OFF CRUD
    // ========================================================================

    /**
     * Get time off entries
     */
    public function get_time_off($filters = [])
    {
        // Include aliases for JS compatibility: date_from -> start_date, date_to -> end_date
        $this->db->select('t.*, t.date_from as start_date, t.date_to as end_date, s.firstname, s.lastname');
        $this->db->from($this->table_time_off . ' t');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = t.staff_id', 'left');

        if (!empty($filters['staff_id'])) {
            $this->db->where('t.staff_id', $filters['staff_id']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $this->db->where('t.date_from <=', $filters['date_to']);
            $this->db->where('t.date_to >=', $filters['date_from']);
        }

        if (!empty($filters['type'])) {
            $this->db->where('t.type', $filters['type']);
        }

        if (isset($filters['approved'])) {
            $this->db->where('t.approved', $filters['approved']);
        }

        if (!empty($filters['staff_ids']) && is_array($filters['staff_ids'])) {
            $this->db->where_in('t.staff_id', $filters['staff_ids']);
        }

        $this->db->order_by('t.date_from', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * Get single time off entry
     */
    public function get_time_off_entry($id)
    {
        $this->db->where('id', $id);
        return $this->db->get($this->table_time_off)->row();
    }

    /**
     * Add time off
     */
    public function add_time_off($data)
    {
        if (empty($data['staff_id']) || empty($data['date_from']) || empty($data['date_to'])) {
            return false;
        }

        $data['date_from']  = date('Y-m-d', strtotime($data['date_from']));
        $data['date_to']    = date('Y-m-d', strtotime($data['date_to']));
        $data['created_at'] = date('Y-m-d H:i:s');

        if (empty($data['type'])) {
            $data['type'] = 'vacation';
        }

        $this->db->insert($this->table_time_off, $data);
        $id = $this->db->insert_id();

        if ($id) {
            log_activity('Time Off Created [ID: ' . $id . ', Staff: ' . $data['staff_id'] . ']');
            return $id;
        }

        return false;
    }

    /**
     * Update time off
     */
    public function update_time_off($id, $data)
    {
        if (!empty($data['date_from'])) {
            $data['date_from'] = date('Y-m-d', strtotime($data['date_from']));
        }
        if (!empty($data['date_to'])) {
            $data['date_to'] = date('Y-m-d', strtotime($data['date_to']));
        }

        $this->db->where('id', $id);
        $this->db->update($this->table_time_off, $data);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Delete time off
     */
    public function delete_time_off($id)
    {
        $this->db->where('id', $id);
        $this->db->delete($this->table_time_off);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Approve time off
     */
    public function approve_time_off($id, $approved = 1)
    {
        return $this->update_time_off($id, [
            'approved'    => $approved,
            'approved_by' => get_staff_user_id()
        ]);
    }

    // ========================================================================
    // CAPACITY CALCULATION
    // ========================================================================

    /**
     * Calculate capacity for staff members over a date range
     * 
     * Returns: [
     *   staff_id => [
     *     date => [
     *       'available'  => float (Soll-Stunden),
     *       'allocated'  => float (Geplante Stunden),
     *       'percent'    => int (Auslastung %),
     *       'status'     => string (ok|warning|overbooked|off)
     *     ]
     *   ]
     * ]
     */
    public function get_capacity($staff_ids, $date_from, $date_to)
    {
        if (!is_array($staff_ids)) {
            $staff_ids = [$staff_ids];
        }

        $result = [];

        // Zeitraum als DatePeriod
        $period = new DatePeriod(
            new DateTime($date_from),
            new DateInterval('P1D'),
            (new DateTime($date_to))->modify('+1 day')
        );

        // Time Off für alle Staff laden (indexed by staff_id + date)
        // Use HR module if available, else rb_time_off
        $time_off_data = $this->get_time_off_indexed_v2($staff_ids, $date_from, $date_to);

        // Allocations für alle Staff laden (indexed by staff_id + date)
        $allocations_data = $this->get_allocations_indexed($staff_ids, $date_from, $date_to);

        foreach ($staff_ids as $staff_id) {
            $result[$staff_id] = [];

            // Work Pattern für diesen Staff
            $pattern = $this->get_work_pattern($staff_id, $date_from);

            foreach ($period as $date) {
                $day_str = $date->format('Y-m-d');
                $dow     = strtolower($date->format('D')); // mon, tue, wed...

                // Soll-Stunden aus Pattern
                $pattern_field = $dow . '_hours';
                $available = isset($pattern->$pattern_field) ? (float) $pattern->$pattern_field : 8.00;

                // Time Off abziehen
                if (isset($time_off_data[$staff_id][$day_str])) {
                    $off = $time_off_data[$staff_id][$day_str];
                    $off_hours = $off['hours_per_day'] !== null ? (float) $off['hours_per_day'] : $available;
                    $available = max(0, $available - $off_hours);
                }

                // Geplante Stunden aus Allocations
                $allocated = isset($allocations_data[$staff_id][$day_str]) 
                    ? (float) $allocations_data[$staff_id][$day_str] 
                    : 0.00;

                // Prozent & Status berechnen
                if ($available > 0) {
                    $percent = round(($allocated / $available) * 100);
                } else {
                    // Keine Kapazität (z.B. Wochenende, Feiertag)
                    // Wenn trotzdem geplant: "scheduled" (nicht overbooked, da bewusst geplant)
                    $percent = $allocated > 0 ? 0 : 0;
                }

                // Status bestimmen
                $status = 'ok';
                if ($available == 0) {
                    // Keine Kapazität an diesem Tag
                    $status = $allocated > 0 ? 'scheduled_off' : 'off';
                } elseif ($allocated > $available) {
                    // Mehr geplant als verfügbar = echte Überbuchung
                    $status = 'overbooked';
                } elseif ($allocated == $available && $allocated > 0) {
                    // Genau voll
                    $status = 'full';
                } elseif ($percent >= 80) {
                    // Fast voll (80-99%)
                    $status = 'warning';
                }

                $result[$staff_id][$day_str] = [
                    'available' => $available,
                    'allocated' => $allocated,
                    'percent'   => $percent,
                    'status'    => $status
                ];
            }
        }

        return $result;
    }

    /**
     * Get time off indexed by staff_id and date
     */
    private function get_time_off_indexed($staff_ids, $date_from, $date_to)
    {
        $time_off = $this->get_time_off([
            'staff_ids' => $staff_ids,
            'date_from' => $date_from,
            'date_to'   => $date_to
        ]);

        return $this->_index_time_off_entries($time_off, $date_from, $date_to);
    }

    /**
     * Get time off indexed — uses HR module when available (v2.0)
     */
    private function get_time_off_indexed_v2($staff_ids, $date_from, $date_to)
    {
        $hr_table = db_prefix() . 'hr_absences';
        if ($this->db->table_exists($hr_table)) {
            // HR module available
            $rows = $this->db->select('a.staff_id, a.date_from, a.date_to, a.is_half_day')
                ->from($hr_table . ' a')
                ->where('a.status', 'approved')
                ->where('a.date_from <=', $date_to)
                ->where('a.date_to >=', $date_from)
                ->where_in('a.staff_id', $staff_ids)
                ->get()->result_array();

            // Map to common format
            $entries = [];
            foreach ($rows as $r) {
                $entries[] = [
                    'staff_id'     => $r['staff_id'],
                    'date_from'    => $r['date_from'],
                    'date_to'      => $r['date_to'],
                    'hours_per_day'=> !empty($r['is_half_day']) ? 4.0 : null,
                ];
            }

            // Include holidays as full-day off for every staff member
            $holidays = $this->get_hr_holidays($date_from, $date_to);
            foreach ($holidays as $h) {
                foreach ($staff_ids as $sid) {
                    $entries[] = [
                        'staff_id'     => $sid,
                        'date_from'    => $h['date'],
                        'date_to'      => $h['date'],
                        'hours_per_day'=> !empty($h['is_half_day']) ? 4.0 : null,
                    ];
                }
            }

            return $this->_index_time_off_entries($entries, $date_from, $date_to);
        }

        // Fallback
        return $this->get_time_off_indexed($staff_ids, $date_from, $date_to);
    }

    /**
     * Helper: index time off entries by staff_id and date
     */
    private function _index_time_off_entries($time_off, $date_from, $date_to)
    {
        $indexed = [];
        foreach ($time_off as $entry) {
            $staff_id = $entry['staff_id'];
            $start    = new DateTime($entry['date_from']);
            $end      = (new DateTime($entry['date_to']))->modify('+1 day');
            $period   = new DatePeriod($start, new DateInterval('P1D'), $end);

            foreach ($period as $date) {
                $day_str = $date->format('Y-m-d');
                if ($day_str >= $date_from && $day_str <= $date_to) {
                    $indexed[$staff_id][$day_str] = $entry;
                }
            }
        }
        return $indexed;
    }

    /**
     * Get allocations indexed by staff_id and date (summed hours per day)
     * v2.0: reads live from project_members + task_assigned, not just rb_allocations
     */
    private function get_allocations_indexed($staff_ids, $date_from, $date_to)
    {
        // Build live board allocations (re-use get_board_data logic minimally)
        $project_allocs = [];
        $task_allocs    = [];

        // Project memberships
        $this->db->select('pm.staff_id, p.start_date AS date_from, p.deadline AS date_to, COALESCE(a.hours_per_day, 8) AS hours_per_day, COALESCE(a.include_weekends, 0) AS include_weekends')
            ->from(db_prefix() . 'project_members pm')
            ->join(db_prefix() . 'projects p', 'p.id = pm.project_id')
            ->join($this->table_allocations . ' a', 'a.staff_id = pm.staff_id AND a.project_id = pm.project_id AND (a.task_id IS NULL OR a.task_id = 0)', 'left')
            ->where_in('pm.staff_id', $staff_ids)
            ->where('p.status !=', 4);
        $project_allocs = $this->db->get()->result_array();

        // Task assignments
        $this->db->select('ta.staffid AS staff_id, t.startdate AS date_from, t.duedate AS date_to, COALESCE(a.hours_per_day, 0) AS hours_per_day, COALESCE(a.include_weekends, 0) AS include_weekends')
            ->from(db_prefix() . 'task_assigned ta')
            ->join(db_prefix() . 'tasks t', 't.id = ta.taskid')
            ->join($this->table_allocations . ' a', 'a.staff_id = ta.staffid AND a.task_id = ta.taskid', 'left')
            ->where_in('ta.staffid', $staff_ids)
            ->where('t.rel_type', 'project')
            ->where('t.status !=', 5);
        $task_allocs = $this->db->get()->result_array();

        $all_allocs = array_merge($project_allocs, $task_allocs);

        $indexed = [];

        foreach ($all_allocs as $alloc) {
            if (empty($alloc['date_from']) || empty($alloc['date_to'])) continue;

            $staff_id = $alloc['staff_id'];
            $start    = new DateTime($alloc['date_from']);
            $end      = (new DateTime($alloc['date_to']))->modify('+1 day');
            $period   = new DatePeriod($start, new DateInterval('P1D'), $end);
            
            $include_weekends = !empty($alloc['include_weekends']);

            foreach ($period as $date) {
                $day_str = $date->format('Y-m-d');
                if ($day_str >= $date_from && $day_str <= $date_to) {
                    $dow = $date->format('w'); // 0 = Sunday, 6 = Saturday
                    if (!$include_weekends && ($dow == 0 || $dow == 6)) {
                        continue;
                    }
                    
                    if (!isset($indexed[$staff_id][$day_str])) {
                        $indexed[$staff_id][$day_str] = 0;
                    }
                    $indexed[$staff_id][$day_str] += (float)$alloc['hours_per_day'];
                }
            }
        }

        return $indexed;
    }

    /**
     * Get capacity summary for staff list (aggregated per week/month)
     */
    public function get_capacity_summary($staff_ids, $date_from, $date_to)
    {
        $capacity = $this->get_capacity($staff_ids, $date_from, $date_to);
        $summary  = [];

        foreach ($capacity as $staff_id => $days) {
            $total_available = 0;
            $total_allocated = 0;
            $overbooked_days = 0;

            foreach ($days as $day_data) {
                $total_available += $day_data['available'];
                $total_allocated += $day_data['allocated'];
                if ($day_data['status'] === 'overbooked') {
                    $overbooked_days++;
                }
            }

            $summary[$staff_id] = [
                'total_available'  => $total_available,
                'total_allocated'  => $total_allocated,
                'percent'          => $total_available > 0 ? round(($total_allocated / $total_available) * 100) : 0,
                'overbooked_days'  => $overbooked_days
            ];
        }

        return $summary;
    }

    // ========================================================================
    // STAFF & PROJECT HELPERS
    // ========================================================================

    /**
     * Get staff list with capacity data
     */
    public function get_staff_with_capacity($date_from, $date_to, $filters = [])
    {
        // Alle aktiven Staff laden
        $this->db->select('staffid, firstname, lastname, email, profile_image');
        $this->db->from(db_prefix() . 'staff');
        $this->db->where('active', 1);

        if (!empty($filters['staff_ids'])) {
            $this->db->where_in('staffid', $filters['staff_ids']);
        }

        $this->db->order_by('firstname', 'ASC');
        $staff_list = $this->db->get()->result_array();

        // Staff IDs extrahieren
        $staff_ids = array_column($staff_list, 'staffid');

        if (empty($staff_ids)) {
            return [];
        }

        // Kapazitäts-Summary berechnen
        $capacity_summary = $this->get_capacity_summary($staff_ids, $date_from, $date_to);

        // Zusammenführen
        foreach ($staff_list as &$staff) {
            $staff_id = $staff['staffid'];
            $staff['capacity'] = isset($capacity_summary[$staff_id]) 
                ? $capacity_summary[$staff_id] 
                : ['total_available' => 0, 'total_allocated' => 0, 'percent' => 0, 'overbooked_days' => 0];
        }

        return $staff_list;
    }

    /**
     * Get active projects for allocation dropdown
     */
    public function get_active_projects()
    {
        $this->db->select('id, name, clientid, project_cost, start_date, deadline');
        $this->db->from(db_prefix() . 'projects');
        $this->db->where('status !=', 4); // Nicht abgeschlossen
        $this->db->order_by('name', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * Get project tasks for optional task-level allocation
     */
    public function get_project_tasks($project_id)
    {
        $this->db->select('id, name, status, startdate, duedate');
        $this->db->from(db_prefix() . 'tasks');
        $this->db->where('rel_type', 'project');
        $this->db->where('rel_id', $project_id);
        $this->db->where('status !=', 5); // Nicht abgeschlossen
        $this->db->order_by('name', 'ASC');

        return $this->db->get()->result_array();
    }

    // ========================================================================
    // BOARD DATA (für Timeline-Ansicht) — v2.0: Live View über Perfex-Daten
    // ========================================================================

    /**
     * Get all data needed for the planning board — Live JOIN from Perfex tables.
     *
     * rb_allocations stores ONLY planning overrides (h/d, color, note).
     * Project/task memberships are read live from project_members / task_assigned.
     */
    public function get_board_data($date_from, $date_to, $filters = [])
    {
        $this->load->helper('resourcebooking/rb_capacity');

        // 1. Active staff
        $this->db->select('s.staffid, s.firstname, s.lastname, s.email, s.profile_image');
        $this->db->from(db_prefix() . 'staff s');
        $this->db->where('s.active', 1);
        if (!empty($filters['staff_id'])) {
            $this->db->where('s.staffid', $filters['staff_id']);
        }
        $this->db->order_by('s.firstname', 'ASC');
        $staff_list = $this->db->get()->result_array();

        if (empty($staff_list)) {
            return ['staff' => [], 'allocations' => [], 'time_off' => [], 'capacity' => [], 'projects' => [], 'date_from' => $date_from, 'date_to' => $date_to];
        }

        $staff_ids = array_column($staff_list, 'staffid');

        // 2. Project memberships + planning overrides
        $this->db->select('
            pm.staff_id,
            p.id          AS project_id,
            p.name        AS project_name,
            p.start_date  AS project_start,
            p.deadline    AS project_deadline,
            p.color       AS project_color,
            a.id          AS allocation_id,
            a.hours_per_day,
            a.color       AS override_color,
            a.note,
            a.date_from   AS override_from,
            a.date_to     AS override_to,
            a.include_weekends
        ');
        $this->db->from(db_prefix() . 'project_members pm');
        $this->db->join(db_prefix() . 'projects p', 'p.id = pm.project_id');
        $this->db->join(
            $this->table_allocations . ' a',
            'a.staff_id = pm.staff_id AND a.project_id = pm.project_id AND (a.task_id IS NULL OR a.task_id = 0)',
            'left'
        );
        $this->db->where_in('pm.staff_id', $staff_ids);
        $this->db->where('p.status !=', 4); // Not completed/cancelled
        if (!empty($filters['project_id'])) {
            $this->db->where('pm.project_id', $filters['project_id']);
        }
        $project_rows = $this->db->get()->result_array();

        // 3. Task assignments + planning overrides
        $has_estimated_hours = $this->db->field_exists('estimated_hours', db_prefix() . 'tasks');
        $task_select = 'ta.staffid AS staff_id, t.id AS task_id, t.name AS task_name, t.rel_id AS project_id, t.startdate AS task_start, t.duedate AS task_due, t.status AS task_status, ' . ($has_estimated_hours ? 't.estimated_hours, ' : 'NULL AS estimated_hours, ') . 'a.id AS allocation_id, a.hours_per_day, a.color AS override_color, a.note, a.date_from AS override_from, a.date_to AS override_to, a.include_weekends';
        $this->db->select($task_select);
        $this->db->from(db_prefix() . 'task_assigned ta');
        $this->db->join(db_prefix() . 'tasks t', 't.id = ta.taskid');
        $this->db->join(
            $this->table_allocations . ' a',
            'a.staff_id = ta.staffid AND a.task_id = ta.taskid',
            'left'
        );
        $this->db->where_in('ta.staffid', $staff_ids);
        $this->db->where('t.rel_type', 'project');
        $this->db->where('t.status !=', 5); // Not completed
        $task_rows = $this->db->get()->result_array();

        // 4. Build unified allocations array
        $allocations = [];

        foreach ($project_rows as $row) {
            $df = !empty($row['override_from']) ? $row['override_from'] : $row['project_start'];
            $dt = !empty($row['override_to'])   ? $row['override_to']   : $row['project_deadline'];
            if (empty($df) || empty($dt)) continue;

            $color = !empty($row['override_color']) ? $row['override_color']
                   : (!empty($row['project_color']) ? $row['project_color'] : '#3498db');

            $allocations[] = [
                'id'              => !empty($row['allocation_id']) ? (int)$row['allocation_id'] : 'p_' . $row['staff_id'] . '_' . $row['project_id'],
                'staff_id'        => (int)$row['staff_id'],
                'project_id'      => (int)$row['project_id'],
                'task_id'         => null,
                'project_name'    => $row['project_name'],
                'project_color'   => $color,
                'date_from'       => $df,
                'date_to'         => $dt,
                'start_date'      => $df,
                'end_date'        => $dt,
                'hours_per_day'   => !empty($row['hours_per_day']) ? (float)$row['hours_per_day'] : 8.0,
                'note'            => $row['note'],
                'include_weekends'=> !empty($row['include_weekends']) ? 1 : 0,
                'type'            => 'project',
                'is_override'     => !empty($row['allocation_id']),
            ];
        }

        foreach ($task_rows as $row) {
            $df = !empty($row['override_from']) ? $row['override_from'] : $row['task_start'];
            $dt = !empty($row['override_to'])   ? $row['override_to']   : $row['task_due'];
            if (empty($df) || empty($dt)) continue;

            $working_days = rb_count_working_days($df, $dt);
            $est_hours    = !empty($row['estimated_hours']) ? (float)$row['estimated_hours'] : null;
            $daily_avg    = null;
            if ($est_hours && $working_days > 0) {
                $daily_avg = round($est_hours / $working_days, 1);
            }
            $hpd = !empty($row['hours_per_day']) ? (float)$row['hours_per_day']
                 : ($daily_avg ?: 0.0);

            $allocations[] = [
                'id'              => !empty($row['allocation_id']) ? (int)$row['allocation_id'] : 't_' . $row['staff_id'] . '_' . $row['task_id'],
                'staff_id'        => (int)$row['staff_id'],
                'project_id'      => (int)$row['project_id'],
                'task_id'         => (int)$row['task_id'],
                'task_name'       => $row['task_name'],
                'task_status'     => (int)$row['task_status'],
                'estimated_hours' => $est_hours,
                'daily_avg'       => $daily_avg,
                'date_from'       => $df,
                'date_to'         => $dt,
                'start_date'      => $df,
                'end_date'        => $dt,
                'hours_per_day'   => $hpd,
                'project_color'   => !empty($row['override_color']) ? $row['override_color'] : null,
                'note'            => $row['note'],
                'include_weekends'=> !empty($row['include_weekends']) ? 1 : 0,
                'type'            => 'task',
                'is_override'     => !empty($row['allocation_id']),
            ];
        }

        // 5. Time Off (HR module or fallback)
        $time_off = $this->get_hr_time_off_for_board($date_from, $date_to, $staff_ids);

        // 6. Capacity per staff per day
        $capacity = $this->get_capacity($staff_ids, $date_from, $date_to);

        // 7. Projects for dropdown
        $projects = $this->get_active_projects();

        return [
            'staff'       => $staff_list,
            'allocations' => $allocations,
            'time_off'    => $time_off,
            'capacity'    => $capacity,
            'projects'    => $projects,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
        ];
    }

    /**
     * Get HR time off entries for board (with fallback to rb_time_off)
     */
    private function get_hr_time_off_for_board($date_from, $date_to, $staff_ids)
    {
        $hr_absences_table = db_prefix() . 'hr_absences';

        if ($this->db->table_exists($hr_absences_table)) {
            // Read from bowhumanressources HR module
            $rows = $this->db->select('a.staff_id, a.date_from, a.date_to, a.date_from AS start_date, a.date_to AS end_date, "hr" AS source, s.firstname, s.lastname')
                ->from($hr_absences_table . ' a')
                ->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left')
                ->where('a.status', 'approved')
                ->where('a.date_from <=', $date_to)
                ->where('a.date_to >=', $date_from)
                ->where_in('a.staff_id', $staff_ids)
                ->order_by('a.date_from', 'ASC')
                ->get()->result_array();

            // Also include holidays
            $holidays = $this->get_hr_holidays($date_from, $date_to);
            foreach ($holidays as $h) {
                foreach ($staff_ids as $sid) {
                    $rows[] = [
                        'staff_id'   => $sid,
                        'date_from'  => $h['date'],
                        'date_to'    => $h['date'],
                        'start_date' => $h['date'],
                        'end_date'   => $h['date'],
                        'type'       => 'holiday',
                        'source'     => 'hr_holiday',
                    ];
                }
            }
            return $rows;
        }

        // Fallback: rb_time_off
        return $this->get_time_off([
            'staff_ids' => $staff_ids,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        ]);
    }

    /**
     * Get public holidays from HR module
     */
    private function get_hr_holidays($date_from, $date_to)
    {
        $table = db_prefix() . 'hr_holidays';
        if (!$this->db->table_exists($table)) {
            return [];
        }
        return $this->db->select('id, name, date, is_half_day')
            ->where('date >=', $date_from)
            ->where('date <=', $date_to)
            ->get($table)->result_array();
    }

    // ========================================================================
    // WRITE METHODS — Board writes directly to Perfex tables
    // ========================================================================

    /**
     * Assign staff member to a project (writes to project_members)
     */
    public function assign_staff_to_project($staff_id, $project_id)
    {
        // Check if already a member
        $exists = $this->db->where('staff_id', $staff_id)
            ->where('project_id', $project_id)
            ->count_all_results(db_prefix() . 'project_members');

        if (!$exists) {
            $this->db->insert(db_prefix() . 'project_members', [
                'staff_id'   => $staff_id,
                'project_id' => $project_id,
            ]);
        }
        return true;
    }

    /**
     * Assign staff member to a task (writes to task_assigned)
     */
    public function assign_staff_to_task($staff_id, $task_id)
    {
        $exists = $this->db->where('staffid', $staff_id)
            ->where('taskid', $task_id)
            ->count_all_results(db_prefix() . 'task_assigned');

        if (!$exists) {
            $this->db->insert(db_prefix() . 'task_assigned', [
                'staffid' => $staff_id,
                'taskid'  => $task_id,
            ]);
        }
        return true;
    }

    /**
     * Remove staff member from a project
     */
    public function remove_staff_from_project($staff_id, $project_id)
    {
        $this->db->where('staff_id', $staff_id)
            ->where('project_id', $project_id)
            ->delete(db_prefix() . 'project_members');

        // Remove planning override too
        $this->db->where('staff_id', $staff_id)
            ->where('project_id', $project_id)
            ->where('task_id IS NULL', null, false)
            ->delete($this->table_allocations);

        return true;
    }

    /**
     * Remove staff member from a task
     */
    public function remove_staff_from_task($staff_id, $task_id)
    {
        $this->db->where('staffid', $staff_id)
            ->where('taskid', $task_id)
            ->delete(db_prefix() . 'task_assigned');

        // Remove planning override too
        $this->db->where('staff_id', $staff_id)
            ->where('task_id', $task_id)
            ->delete($this->table_allocations);

        return true;
    }

    /**
     * UPSERT a planning override in rb_allocations
     * Stores: hours_per_day, color, note for (staff_id, project_id, task_id)
     */
    public function upsert_override($data)
    {
        $staff_id   = (int)($data['staff_id'] ?? 0);
        $project_id = !empty($data['project_id']) ? (int)$data['project_id'] : null;
        $task_id    = !empty($data['task_id'])    ? (int)$data['task_id']    : null;

        if (!$staff_id) return false;

        // Find existing override
        $this->db->where('staff_id', $staff_id);
        if ($project_id) {
            $this->db->where('project_id', $project_id);
        } else {
            $this->db->where('project_id IS NULL', null, false);
        }
        if ($task_id) {
            $this->db->where('task_id', $task_id);
        } else {
            $this->db->where('task_id IS NULL', null, false);
        }

        $existing = $this->db->get($this->table_allocations)->row();

        $payload = [
            'staff_id'        => $staff_id,
            'project_id'      => $project_id,
            'task_id'         => $task_id,
            'hours_per_day'   => isset($data['hours_per_day']) ? (float)$data['hours_per_day'] : 8.0,
            'color'           => $data['color'] ?? null,
            'note'            => $data['note'] ?? null,
            'date_from'       => $data['date_from'] ?? date('Y-m-d'),
            'date_to'         => $data['date_to'] ?? date('Y-m-d'),
            'allocation_type' => 'hours',
            'include_weekends'=> !empty($data['include_weekends']) ? 1 : 0,
        ];

        if ($existing) {
            $this->db->where('id', $existing->id)->update($this->table_allocations, $payload);
            $this->_check_overload_notify($staff_id, $payload['date_from'], $payload['date_to'], $project_id);
            return (int)$existing->id;
        }

        $payload['created_by'] = get_staff_user_id();
        $this->db->insert($this->table_allocations, $payload);
        $id = $this->db->insert_id();
        if ($id) {
            $this->_check_overload_notify($staff_id, $payload['date_from'], $payload['date_to'], $project_id);
        }
        return $id;
    }

    /**
     * Update estimated_hours on a task
     */
    public function update_task_hours($task_id, $estimated_hours)
    {
        if (!$this->db->field_exists('estimated_hours', db_prefix() . 'tasks')) {
            return false;
        }
        $this->db->where('id', $task_id)
            ->update(db_prefix() . 'tasks', ['estimated_hours' => (float)$estimated_hours]);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Get project with start_date and deadline for auto-fill in dialog
     */
    public function get_project_dates($project_id)
    {
        return $this->db->select('id, name, start_date, deadline, color')
            ->where('id', $project_id)
            ->get(db_prefix() . 'projects')->row_array();
    }

    /**
     * Get tasks for a project optionally filtered by assigned staff
     */
    public function get_tasks_for_project($project_id, $staff_id = null)
    {
        $has_est = $this->db->field_exists('estimated_hours', db_prefix() . 'tasks');
        $sel = 't.id, t.name, t.startdate, t.duedate, t.status' . ($has_est ? ', t.estimated_hours' : '');

        $this->db->select($sel);
        $this->db->from(db_prefix() . 'tasks t');
        $this->db->where('t.rel_type', 'project');
        $this->db->where('t.rel_id', $project_id);
        $this->db->where('t.status !=', 5); // Not completed

        if ($staff_id) {
            $this->db->join(db_prefix() . 'task_assigned ta', 'ta.taskid = t.id AND ta.staffid = ' . (int)$staff_id);
        }

        $this->db->order_by('t.name', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Fire Perfex notification when a staff member is overloaded
     */
    private function _check_overload_notify($staff_id, $date_from, $date_to, $project_id = null)
    {
        try {
            $capacity = $this->get_capacity([$staff_id], $date_from, $date_to);
            if (empty($capacity[$staff_id])) return;

            $overloaded_days = [];
            foreach ($capacity[$staff_id] as $date => $day) {
                if ($day['status'] === 'overbooked') {
                    $overloaded_days[] = $date;
                }
            }

            if (empty($overloaded_days)) return;

            $staff_row = $this->db->select('firstname, lastname')->where('staffid', $staff_id)->get(db_prefix() . 'staff')->row();
            $name = $staff_row ? $staff_row->firstname . ' ' . $staff_row->lastname : '#' . $staff_id;
            $project_name = '';
            if ($project_id) {
                $p = $this->db->select('name')->where('id', $project_id)->get(db_prefix() . 'projects')->row();
                if ($p) $project_name = ' (' . $p->name . ')';
            }

            $msg = $name . ' ist am ' . implode(', ', array_slice($overloaded_days, 0, 3))
                 . (count($overloaded_days) > 3 ? ' (+' . (count($overloaded_days) - 3) . ')' : '')
                 . ' überlastet' . $project_name;

            // Notify affected staff member
            add_notification([
                'description'     => $msg,
                'touserid'        => $staff_id,
                'fromcompany'     => 1,
                'fromuserid'      => get_staff_user_id(),
                'link'            => 'resourcebooking/planning_board',
                'additional_data' => json_encode(['type' => 'overload']),
            ]);

            // Notify all admins
            $admins = $this->db->where('admin', 1)->get(db_prefix() . 'staff')->result_array();
            foreach ($admins as $admin) {
                if ((int)$admin['staffid'] === (int)$staff_id) continue; // Skip if admin IS the staff
                add_notification([
                    'description'     => $msg,
                    'touserid'        => $admin['staffid'],
                    'fromcompany'     => 1,
                    'fromuserid'      => get_staff_user_id(),
                    'link'            => 'resourcebooking/planning_board',
                    'additional_data' => json_encode(['type' => 'overload']),
                ]);
            }
        } catch (Exception $e) {
            log_message('error', 'RB overload notify error: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // REPORTING
    // ========================================================================

    /**
     * Get allocations grouped by project for reporting
     */
    public function get_allocations_by_project($date_from, $date_to, $staff_id = null)
    {
        // Check if table exists
        if (!$this->db->table_exists(db_prefix() . 'rb_allocations')) {
            return [];
        }
        
        // First get all allocations in the range
        $this->db->select('a.*, p.name as project_name');
        $this->db->from(db_prefix() . 'rb_allocations a');
        $this->db->join(db_prefix() . 'projects p', 'p.id = a.project_id', 'left');
        $this->db->where('a.date_from <=', $date_to);
        $this->db->where('a.date_to >=', $date_from);
        
        if ($staff_id) {
            $this->db->where('a.staff_id', $staff_id);
        }
        
        $allocations = $this->db->get()->result_array();
        
        // Calculate actual working hours per project
        $project_hours = [];
        
        foreach ($allocations as $alloc) {
            $project_id = $alloc['project_id'] ?: 0;
            $project_name = $alloc['project_name'] ?: 'Internal';
            
            if (!isset($project_hours[$project_id])) {
                $project_hours[$project_id] = [
                    'id' => $project_id,
                    'project_name' => $project_name,
                    'total_hours' => 0,
                    'staff_count' => []
                ];
            }
            
            // Calculate working days within the visible range
            $alloc_start = max(strtotime($alloc['date_from']), strtotime($date_from));
            $alloc_end = min(strtotime($alloc['date_to']), strtotime($date_to));
            
            $working_days = 0;
            for ($d = $alloc_start; $d <= $alloc_end; $d += 86400) {
                $dayOfWeek = date('N', $d); // 1=Mon, 7=Sun
                if ($dayOfWeek < 6) { // Mon-Fri
                    $working_days++;
                }
            }
            
            $hours = $working_days * floatval($alloc['hours_per_day']);
            $project_hours[$project_id]['total_hours'] += $hours;
            $project_hours[$project_id]['staff_count'][$alloc['staff_id']] = true;
        }
        
        // Convert to array and add staff count
        $result = [];
        foreach ($project_hours as $data) {
            $result[] = [
                'id' => $data['id'],
                'project_name' => $data['project_name'],
                'total_hours' => round($data['total_hours'], 1),
                'staff_count' => count($data['staff_count'])
            ];
        }
        
        // Sort by total hours descending
        usort($result, function($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });
        
        return $result;
    }

    /**
     * Get time off summary for reporting
     */
    public function get_time_off_summary($date_from, $date_to, $staff_id = null)
    {
        // Check if table exists
        if (!$this->db->table_exists(db_prefix() . 'rb_time_off')) {
            return [];
        }
        
        $this->db->select('t.type, COUNT(*) as count, 
                           SUM(DATEDIFF(LEAST(t.date_to, "' . $this->db->escape_str($date_to) . '"), GREATEST(t.date_from, "' . $this->db->escape_str($date_from) . '")) + 1) as total_days');
        $this->db->from(db_prefix() . 'rb_time_off t');
        $this->db->where('t.date_from <=', $date_to);
        $this->db->where('t.date_to >=', $date_from);
        
        if ($staff_id) {
            $this->db->where('t.staff_id', $staff_id);
        }
        
        $this->db->group_by('t.type');
        
        return $this->db->get()->result_array();
    }
}
