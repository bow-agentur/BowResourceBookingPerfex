<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Resource Booking Module — Uninstall Routine
 *
 * Removes ALL database objects created by this module:
 *   • Module-specific tables (rb_* and legacy booking/resource tables)
 *   • Column additions to Perfex core tables (tbltasks.estimated_hours,
 *     tbltask_comments.type)
 *   • Any default data rows inserted during install
 *
 * Core Perfex tables that existed before installation are left intact.
 * Only the COLUMN and ROW additions made by install.php are reversed.
 */

// ============================================================================
// 1. Module-specific tables (drop in FK-safe order)
// ============================================================================

$module_tables = [
    db_prefix() . 'rb_allocations',
    db_prefix() . 'rb_work_patterns',
    db_prefix() . 'rb_time_off',
    db_prefix() . 'booking_follower',
    db_prefix() . 'booking',
    db_prefix() . 'resource',
    db_prefix() . 'resource_group',
];

foreach ($module_tables as $table) {
    if ($CI->db->table_exists($table)) {
        $CI->db->query('DROP TABLE IF EXISTS `' . $table . '`');
    }
}

// ============================================================================
// 2. Column added to tbltask_comments
//    install.php added: type VARCHAR(50) NULL DEFAULT "task"
//    Only drop it if it was added by us (column exists and was not part of
//    the original schema — we check the default value as a fingerprint).
// ============================================================================

if ($CI->db->field_exists('type', db_prefix() . 'task_comments')) {
    $col = $CI->db->query(
        "SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = '" . $CI->db->escape_str(db_prefix() . 'task_comments') . "'
           AND COLUMN_NAME  = 'type'"
    )->row();

    // Drop only if the default value matches what our install.php set
    if ($col && $col->COLUMN_DEFAULT === 'task') {
        $CI->db->query(
            'ALTER TABLE `' . db_prefix() . 'task_comments` DROP COLUMN `type`'
        );
    }
}

// ============================================================================
// 3. Column added to tbltasks
//    install.php added: estimated_hours DECIMAL(6,2) NULL DEFAULT NULL
// ============================================================================

if ($CI->db->field_exists('estimated_hours', db_prefix() . 'tasks')) {
    $CI->db->query(
        'ALTER TABLE `' . db_prefix() . 'tasks` DROP COLUMN `estimated_hours`'
    );
}

// ============================================================================
// 4. Upload folder (best-effort; only removes if empty)
// ============================================================================

$upload_path = module_dir_path(RESOURCEBOOKING_MODULE, 'uploads');
if (is_dir($upload_path)) {
    // Remove the index.html placeholder created during install
    $index = $upload_path . '/index.html';
    if (file_exists($index)) {
        @unlink($index);
    }
    // Remove the directory only if it is now empty
    @rmdir($upload_path);
}
