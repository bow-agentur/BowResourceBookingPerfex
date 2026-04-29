<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!is_dir(RESOURCEBOOKING_MODULE_UPLOAD_FOLDER)) {
  mkdir(RESOURCEBOOKING_MODULE_UPLOAD_FOLDER, 0755);
  $fp = fopen(RESOURCEBOOKING_MODULE_UPLOAD_FOLDER . '/index.html', 'w');
  fclose($fp);
}

if (!$CI->db->table_exists(db_prefix() . 'resource_group')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'resource_group` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `group_name` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `creator` INT(11) NOT NULL,
  `date_create` DATE NOT NULL,
  PRIMARY KEY (`id`));');
}
if (!$CI->db->table_exists(db_prefix() . 'resource')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'resource` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `resource_name` VARCHAR(100) NOT NULL,
  `resource_group` INT(11) NOT NULL,
  `approved` INT(11) NOT NULL,
  `manager` INT(11) NULL,
  `color` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `status` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`));');
}
if (!$CI->db->table_exists(db_prefix() . 'booking')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'booking` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `purpose` VARCHAR(255) NOT NULL,
  `orderer` INT(11) NOT NULL,
  `resource_group` INT(11) NOT NULL,
  `resource` INT(11) NOT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` INT(11) NOT NULL DEFAULT "1",
  `description` TEXT NULL,
  PRIMARY KEY (`id`));');
}
if (!$CI->db->table_exists(db_prefix() . 'booking_follower')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'booking_follower` (
  `follower_id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking` INT(11) NOT NULL,
  `follower` INT(11) NOT NULL,
  PRIMARY KEY (`follower_id`));');
}

if (!$CI->db->field_exists('type', 'task_comments')) {
    $CI->db->query('ALTER TABLE `'.db_prefix() . 'task_comments` 
  ADD COLUMN `type` VARCHAR(50) NULL DEFAULT "task" AFTER `dateadded`;');            
}

// ============================================================================
// FLOAT-ÄHNLICHE RESSOURCENPLANUNG - Neue Tabellen (MVP Phase 1)
// ============================================================================

// Allocations: Staff auf Projekte/Tasks zuweisen
if (!$CI->db->table_exists(db_prefix() . 'rb_allocations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'rb_allocations` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) UNSIGNED NOT NULL,
        `project_id` INT(11) UNSIGNED NULL,
        `task_id` INT(11) UNSIGNED NULL,
        `date_from` DATE NOT NULL,
        `date_to` DATE NOT NULL,
        `hours_per_day` DECIMAL(4,2) NOT NULL DEFAULT 8.00,
        `allocation_type` ENUM("hours","percent") NOT NULL DEFAULT "hours",
        `include_weekends` TINYINT(1) NOT NULL DEFAULT 0,
        `note` VARCHAR(500) NULL,
        `color` VARCHAR(20) NULL,
        `created_by` INT(11) UNSIGNED NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_staff_date` (`staff_id`, `date_from`, `date_to`),
        INDEX `idx_project` (`project_id`),
        INDEX `idx_date_range` (`date_from`, `date_to`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// Add include_weekends column if it doesn't exist (for existing installations)
if ($CI->db->table_exists(db_prefix() . 'rb_allocations')) {
    if (!$CI->db->field_exists('include_weekends', db_prefix() . 'rb_allocations')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'rb_allocations` ADD `include_weekends` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allocation_type`');
    }
}

// Work Patterns: Arbeitszeitmodelle pro Staff (Mo-So Stunden)
if (!$CI->db->table_exists(db_prefix() . 'rb_work_patterns')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'rb_work_patterns` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) UNSIGNED NOT NULL,
        `name` VARCHAR(100) NOT NULL DEFAULT "Standard",
        `valid_from` DATE NOT NULL,
        `valid_to` DATE NULL,
        `mon_hours` DECIMAL(4,2) NOT NULL DEFAULT 8.00,
        `tue_hours` DECIMAL(4,2) NOT NULL DEFAULT 8.00,
        `wed_hours` DECIMAL(4,2) NOT NULL DEFAULT 8.00,
        `thu_hours` DECIMAL(4,2) NOT NULL DEFAULT 8.00,
        `fri_hours` DECIMAL(4,2) NOT NULL DEFAULT 8.00,
        `sat_hours` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
        `sun_hours` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        INDEX `idx_staff_valid` (`staff_id`, `valid_from`, `valid_to`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// Time Off: Abwesenheiten (Urlaub, Krank, Feiertag)
if (!$CI->db->table_exists(db_prefix() . 'rb_time_off')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'rb_time_off` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) UNSIGNED NOT NULL,
        `date_from` DATE NOT NULL,
        `date_to` DATE NOT NULL,
        `type` ENUM("vacation","sick","holiday","other") NOT NULL DEFAULT "vacation",
        `hours_per_day` DECIMAL(4,2) NULL,
        `note` VARCHAR(255) NULL,
        `approved` TINYINT(1) NOT NULL DEFAULT 0,
        `approved_by` INT(11) UNSIGNED NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_staff_date` (`staff_id`, `date_from`, `date_to`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// Default Work Pattern erstellen (System-Standard: 40h Woche)
$default_pattern_exists = $CI->db->where('is_default', 1)->where('staff_id', 0)->get(db_prefix() . 'rb_work_patterns')->num_rows();
if (!$default_pattern_exists && $CI->db->table_exists(db_prefix() . 'rb_work_patterns')) {
    $CI->db->insert(db_prefix() . 'rb_work_patterns', [
        'staff_id'   => 0,
        'name'       => 'System Default (40h)',
        'valid_from' => '2020-01-01',
        'valid_to'   => null,
        'mon_hours'  => 8.00,
        'tue_hours'  => 8.00,
        'wed_hours'  => 8.00,
        'thu_hours'  => 8.00,
        'fri_hours'  => 8.00,
        'sat_hours'  => 0.00,
        'sun_hours'  => 0.00,
        'is_default' => 1
    ]);
}

// ============================================================================
// PLANNING BOARD v2.0 — Migrations
// ============================================================================

// 1. tbltasks: estimated_hours (einzige Änderung an Perfex-Kerntabellen)
if ($CI->db->table_exists(db_prefix() . 'tasks')) {
    if (!$CI->db->field_exists('estimated_hours', db_prefix() . 'tasks')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'tasks` ADD COLUMN `estimated_hours` DECIMAL(6,2) NULL DEFAULT NULL AFTER `duedate`');
    }
}

// 2. rb_allocations: UNIQUE KEY für UPSERT-Fähigkeit (Planungs-Overrides)
if ($CI->db->table_exists(db_prefix() . 'rb_allocations')) {
    $key_exists = $CI->db->query(
        "SELECT COUNT(1) as cnt FROM INFORMATION_SCHEMA.STATISTICS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = '" . $CI->db->escape_str(db_prefix() . 'rb_allocations') . "'
         AND INDEX_NAME = 'uq_staff_project_task'"
    )->row();
    if (!$key_exists || (int)$key_exists->cnt === 0) {
        // Allow NULL in project_id and task_id for partial keys — use generated column workaround
        // Instead add separate index that covers the most common access patterns
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'rb_allocations` 
            ADD INDEX `idx_staff_proj_task` (`staff_id`, `project_id`, `task_id`)');
    }
}
}