<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Resource Booking - Capacity Helper Functions
 * 
 * Utility functions for capacity calculation, display formatting,
 * and planning board helpers.
 * 
 * @since 2.0.0
 */

if (!function_exists('rb_format_hours')) {
    /**
     * Format hours for display
     * 
     * @param float $hours
     * @param bool $short Use short format (4.5h vs 4.5 hours)
     * @return string
     */
    function rb_format_hours($hours, $short = true)
    {
        $hours = round($hours, 1);
        
        if ($short) {
            return $hours . 'h';
        }
        
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours');
    }
}

if (!function_exists('rb_format_capacity_percent')) {
    /**
     * Format capacity utilization as percentage
     * 
     * @param float $allocated Allocated hours
     * @param float $available Available capacity hours
     * @return string Formatted percentage
     */
    function rb_format_capacity_percent($allocated, $available)
    {
        if ($available <= 0) {
            return $allocated > 0 ? '∞%' : '0%';
        }
        
        $percent = round(($allocated / $available) * 100);
        return $percent . '%';
    }
}

if (!function_exists('rb_capacity_status_class')) {
    /**
     * Get CSS class for capacity status
     * 
     * @param float $allocated
     * @param float $available
     * @return string CSS class name
     */
    function rb_capacity_status_class($allocated, $available)
    {
        if ($available <= 0) {
            return $allocated > 0 ? 'rb-overbooked' : 'rb-empty';
        }
        
        $percent = ($allocated / $available) * 100;
        
        if ($percent >= 110) {
            return 'rb-overbooked';
        } elseif ($percent >= 100) {
            return 'rb-full';
        } elseif ($percent >= 80) {
            return 'rb-high';
        } elseif ($percent >= 50) {
            return 'rb-medium';
        } elseif ($percent > 0) {
            return 'rb-low';
        }
        
        return 'rb-empty';
    }
}

if (!function_exists('rb_get_week_dates')) {
    /**
     * Get array of dates for a given week
     * 
     * @param string $date Any date within the week (Y-m-d)
     * @param bool $include_weekend Include Saturday/Sunday
     * @return array Array of date strings
     */
    function rb_get_week_dates($date, $include_weekend = false)
    {
        $timestamp = strtotime($date);
        $day_of_week = date('N', $timestamp); // 1=Monday, 7=Sunday
        $monday = date('Y-m-d', strtotime('-' . ($day_of_week - 1) . ' days', $timestamp));
        
        $dates = [];
        $end = $include_weekend ? 7 : 5;
        
        for ($i = 0; $i < $end; $i++) {
            $dates[] = date('Y-m-d', strtotime("+{$i} days", strtotime($monday)));
        }
        
        return $dates;
    }
}

if (!function_exists('rb_get_date_range')) {
    /**
     * Get array of dates between start and end
     * 
     * @param string $start_date Y-m-d
     * @param string $end_date Y-m-d
     * @param bool $exclude_weekends Skip Sat/Sun
     * @return array Array of date strings
     */
    function rb_get_date_range($start_date, $end_date, $exclude_weekends = false)
    {
        $dates = [];
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        
        while ($current <= $end) {
            if ($exclude_weekends) {
                $day_of_week = date('N', $current);
                if ($day_of_week < 6) { // 1-5 = Mon-Fri
                    $dates[] = date('Y-m-d', $current);
                }
            } else {
                $dates[] = date('Y-m-d', $current);
            }
            $current = strtotime('+1 day', $current);
        }
        
        return $dates;
    }
}

if (!function_exists('rb_count_working_days')) {
    /**
     * Count working days between two dates
     * 
     * @param string $start_date Y-m-d
     * @param string $end_date Y-m-d
     * @return int Number of working days (Mon-Fri)
     */
    function rb_count_working_days($start_date, $end_date)
    {
        return count(rb_get_date_range($start_date, $end_date, true));
    }
}

if (!function_exists('rb_color_brightness')) {
    /**
     * Determine if a color is light or dark
     * 
     * @param string $hex_color Hex color (with or without #)
     * @return bool True if light, false if dark
     */
    function rb_color_brightness($hex_color)
    {
        $hex = ltrim($hex_color, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Using relative luminance formula
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        
        return $brightness > 128;
    }
}

if (!function_exists('rb_text_color_for_bg')) {
    /**
     * Get appropriate text color (black/white) for a background color
     * 
     * @param string $bg_color Background hex color
     * @return string '#000000' or '#ffffff'
     */
    function rb_text_color_for_bg($bg_color)
    {
        return rb_color_brightness($bg_color) ? '#000000' : '#ffffff';
    }
}

if (!function_exists('rb_project_color')) {
    /**
     * Get project color or generate a consistent one
     * 
     * @param int $project_id
     * @return string Hex color
     */
    function rb_project_color($project_id)
    {
        if (!$project_id) {
            return '#808080'; // Gray for no project
        }
        
        $CI = &get_instance();
        
        // Try to get project color from database
        $project = $CI->db->select('color')
            ->from(db_prefix() . 'projects')
            ->where('id', $project_id)
            ->get()
            ->row();
        
        if ($project && !empty($project->color)) {
            return $project->color;
        }
        
        // Generate consistent color from project ID
        $colors = [
            '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
            '#1abc9c', '#e67e22', '#34495e', '#16a085', '#27ae60',
            '#d35400', '#8e44ad', '#2980b9', '#c0392b', '#7f8c8d'
        ];
        
        return $colors[$project_id % count($colors)];
    }
}

if (!function_exists('rb_staff_initials')) {
    /**
     * Get initials from staff name
     * 
     * @param string $firstname
     * @param string $lastname
     * @return string 2-letter initials
     */
    function rb_staff_initials($firstname, $lastname)
    {
        $initials = '';
        
        if (!empty($firstname)) {
            $initials .= strtoupper(substr($firstname, 0, 1));
        }
        
        if (!empty($lastname)) {
            $initials .= strtoupper(substr($lastname, 0, 1));
        }
        
        if (empty($initials)) {
            return '??';
        }
        
        return $initials;
    }
}

if (!function_exists('rb_is_overbooked')) {
    /**
     * Check if a staff member is overbooked for a date range
     * 
     * @param int $staff_id
     * @param string $start_date
     * @param string $end_date
     * @return bool
     */
    function rb_is_overbooked($staff_id, $start_date, $end_date)
    {
        $CI = &get_instance();
        
        if (!class_exists('Rb_planning_model')) {
            $CI->load->model('resourcebooking/Rb_planning_model', 'rb_planning_model');
        }
        
        $capacity = $CI->rb_planning_model->get_capacity($staff_id, $start_date, $end_date);
        
        return $capacity['has_overbooking'];
    }
}

if (!function_exists('rb_get_available_hours')) {
    /**
     * Get available hours for a staff member on a specific date
     * 
     * @param int $staff_id
     * @param string $date Y-m-d
     * @return float Available hours
     */
    function rb_get_available_hours($staff_id, $date)
    {
        $CI = &get_instance();
        
        if (!class_exists('Rb_planning_model')) {
            $CI->load->model('resourcebooking/Rb_planning_model', 'rb_planning_model');
        }
        
        $capacity = $CI->rb_planning_model->get_capacity($staff_id, $date, $date);
        
        if (empty($capacity['daily'])) {
            return 0;
        }
        
        $day = reset($capacity['daily']);
        return $day['remaining'];
    }
}

if (!function_exists('rb_allocation_total_hours')) {
    /**
     * Calculate total hours for an allocation
     * 
     * @param string $start_date
     * @param string $end_date
     * @param float $hours_per_day
     * @return float Total hours
     */
    function rb_allocation_total_hours($start_date, $end_date, $hours_per_day)
    {
        $working_days = rb_count_working_days($start_date, $end_date);
        return $working_days * $hours_per_day;
    }
}

if (!function_exists('rb_time_off_type_label')) {
    /**
     * Get translated label for time off type
     * 
     * @param string $type
     * @return string
     */
    function rb_time_off_type_label($type)
    {
        $types = [
            'vacation' => _l('vacation'),
            'sick'     => _l('sick_leave'),
            'holiday'  => _l('holiday'),
            'personal' => _l('personal'),
            'other'    => _l('other'),
        ];
        
        return isset($types[$type]) ? $types[$type] : ucfirst($type);
    }
}

if (!function_exists('rb_format_date_range')) {
    /**
     * Format a date range for display
     * 
     * @param string $start_date Y-m-d
     * @param string $end_date Y-m-d
     * @param bool $short Use short month format
     * @return string
     */
    function rb_format_date_range($start_date, $end_date, $short = true)
    {
        $format = $short ? 'M j' : 'F j';
        $year_format = $short ? 'M j, Y' : 'F j, Y';
        
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        
        $start_year = date('Y', $start);
        $end_year = date('Y', $end);
        
        if ($start_date === $end_date) {
            return date($year_format, $start);
        }
        
        if ($start_year === $end_year) {
            if (date('m', $start) === date('m', $end)) {
                return date($format, $start) . ' - ' . date('j, Y', $end);
            }
            return date($format, $start) . ' - ' . date($format . ', Y', $end);
        }
        
        return date($year_format, $start) . ' - ' . date($year_format, $end);
    }
}
