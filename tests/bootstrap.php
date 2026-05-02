<?php

/**
 * PHPUnit Bootstrap — stubs for Perfex CRM / CodeIgniter globals.
 */

define('BASEPATH', true);
define('RESOURCEBOOKING_MODULE', 'bowresourceplanning');

if (!function_exists('_l')) {
    function _l(string $key, ...$args): string { return $key; }
}

if (!function_exists('db_prefix')) {
    function db_prefix(): string { return 'tbl'; }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool { return false; }
}

if (!function_exists('staff_can')) {
    function staff_can(string $cap, string $module): bool { return false; }
}

if (!function_exists('get_instance')) {
    function get_instance() { return new stdClass(); }
}

require_once __DIR__ . '/../helpers/rb_capacity_helper.php';
