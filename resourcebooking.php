<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: BOW Booking
Module URI: https://bow-agentur.de
Description: Management of Resources in Agencies
Version: 1.0.0
Requires at least: 2.3.*
Author: BOW E-Commerce Agentur GmbH
Author URI: https://bow-agentur.de
*/

define('RESOURCEBOOKING_MODULE', 'resourcebooking');

define('RESOURCEBOOKING_MODULE_UPLOAD_FOLDER', module_dir_path(RESOURCEBOOKING_MODULE, 'uploads'));
hooks()->add_action('admin_init', 'resourcebooking_permissions');
hooks()->add_action('admin_init', 'resourcebooking_module_init_menu_items');

// Task hooks: persist estimated_hours when tasks are created/updated
hooks()->add_filter('before_add_task', 'resourcebooking_before_add_task');
hooks()->add_filter('before_update_task', 'resourcebooking_before_update_task');

/**
* Register activation module hook
*/
register_activation_hook(RESOURCEBOOKING_MODULE, 'resourcebooking_module_activation_hook');
/**
* Load the module helper
*/
$CI = & get_instance();
$CI->load->helper(RESOURCEBOOKING_MODULE . '/rb_capacity');

function resourcebooking_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
* Register language files, must be registered if the module is using languages
*/
register_language_files(RESOURCEBOOKING_MODULE, [RESOURCEBOOKING_MODULE]);

/**
 * Init goals module menu items in setup in admin_init hook
 * @return null
 */
function resourcebooking_module_init_menu_items()
{
    
    $CI = &get_instance();
    if (has_permission('resourcebooking', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('resource-booking', [
            'name'     => _l('resourcebooking'),
            'icon'     => 'fa fa-calendar',
            'position' => 50,
        ]);

        // NEU: Planning Board (Float-ähnlich) - Hauptfeature
        $CI->app_menu->add_sidebar_children_item('resource-booking', [
            'slug'     => 'planning-board',
            'name'     => _l('planning_board'),
            'icon'     => 'fa fa-th',
            'href'     => admin_url('resourcebooking/planning_board'),
            'position' => 1,
        ]);

        // NEU: Reports
        $CI->app_menu->add_sidebar_children_item('resource-booking', [
            'slug'     => 'rb-reports',
            'name'     => _l('rb_reports'),
            'icon'     => 'fa fa-bar-chart',
            'href'     => admin_url('resourcebooking/reports'),
            'position' => 2,
        ]);
    }
    
}

function resourcebooking_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
            'view'   => _l('permission_view') . ' (' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('resourcebooking', $capabilities, _l('resourcebooking'));
}

/**
 * Hook: persist estimated_hours when a new task is created
 *
 * @param array $data Task data passed by reference
 */
function resourcebooking_before_add_task($data)
{
    $CI = &get_instance();
    $estimated = $CI->input->post('estimated_hours');
    if ($estimated !== null && $estimated !== '') {
        $data['estimated_hours'] = max(0, (float)$estimated);
    }
    return $data;
}

/**
 * Hook: persist estimated_hours when an existing task is updated
 *
 * @param array $data Task data passed by reference
 */
function resourcebooking_before_update_task($data)
{
    $CI = &get_instance();
    $estimated = $CI->input->post('estimated_hours');
    if ($estimated !== null && $estimated !== '') {
        $data['estimated_hours'] = max(0, (float)$estimated);
    }
    return $data;
}
