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
        
        // Auto-migration: Add include_weekends column if missing
        $this->ensure_include_weekends_column();
    }
    
    /**
     * Ensure include_weekends column exists (auto-migration)
     */
    private function ensure_include_weekends_column()
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        
        if ($this->db->table_exists($this->table_allocations)) {
            if (!$this->db->field_exists('include_weekends', $this->table_allocations)) {
                $this->db->query('ALTER TABLE `' . $this->table_allocations . '` ADD `include_weekends` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allocation_type`');
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
        $time_off_data = $this->get_time_off_indexed($staff_ids, $date_from, $date_to);

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
     */
    private function get_allocations_indexed($staff_ids, $date_from, $date_to)
    {
        $allocations = $this->get_allocations([
            'staff_ids' => $staff_ids,
            'date_from' => $date_from,
            'date_to'   => $date_to
        ]);

        $indexed = [];

        foreach ($allocations as $alloc) {
            $staff_id = $alloc['staff_id'];
            $start    = new DateTime($alloc['date_from']);
            $end      = (new DateTime($alloc['date_to']))->modify('+1 day');
            $period   = new DatePeriod($start, new DateInterval('P1D'), $end);
            
            // Check if include_weekends field exists (backward compatibility)
            $include_weekends = isset($alloc['include_weekends']) ? !empty($alloc['include_weekends']) : false;

            foreach ($period as $date) {
                $day_str = $date->format('Y-m-d');
                if ($day_str >= $date_from && $day_str <= $date_to) {
                    // Skip weekends unless include_weekends is enabled
                    $dow = $date->format('w'); // 0 = Sunday, 6 = Saturday
                    if (!$include_weekends && ($dow == 0 || $dow == 6)) {
                        continue;
                    }
                    
                    if (!isset($indexed[$staff_id][$day_str])) {
                        $indexed[$staff_id][$day_str] = 0;
                    }
                    $indexed[$staff_id][$day_str] += (float) $alloc['hours_per_day'];
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
    // BOARD DATA (für Timeline-Ansicht)
    // ========================================================================

    /**
     * Get all data needed for the planning board
     */
    public function get_board_data($date_from, $date_to, $filters = [])
    {
        // Staff mit Kapazität
        $staff = $this->get_staff_with_capacity($date_from, $date_to, $filters);
        $staff_ids = array_column($staff, 'staffid');

        // Allocations
        $allocations = [];
        if (!empty($staff_ids)) {
            $allocations = $this->get_allocations([
                'staff_ids' => $staff_ids,
                'date_from' => $date_from,
                'date_to'   => $date_to
            ]);
        }

        // Time Off
        $time_off = [];
        if (!empty($staff_ids)) {
            $time_off = $this->get_time_off([
                'staff_ids' => $staff_ids,
                'date_from' => $date_from,
                'date_to'   => $date_to
            ]);
        }

        // Kapazität pro Tag
        $capacity = [];
        if (!empty($staff_ids)) {
            $capacity = $this->get_capacity($staff_ids, $date_from, $date_to);
        }

        // Projekte (für Dropdown/Legende)
        $projects = $this->get_active_projects();

        return [
            'staff'       => $staff,
            'allocations' => $allocations,
            'time_off'    => $time_off,
            'capacity'    => $capacity,
            'projects'    => $projects,
            'date_from'   => $date_from,
            'date_to'     => $date_to
        ];
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
