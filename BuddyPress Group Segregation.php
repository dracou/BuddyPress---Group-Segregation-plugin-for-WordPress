<?php
/**
 * Plugin Name: BuddyPress Group Segregation
 * Plugin URI: https://salle-humide.be
 * Description: Plugin permettant de restreindre les contenus et utilisateurs visibles dans BuddyPress en fonction des groupes (rôles).
 * Version: 0.3
 * Author: dracou
 * Text Domain: bp-group-segregation
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BP_Group_Segregation {

    private static $instance;

    private function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('bp_activity_get', [$this, 'filter_activity'], 10, 2);
        add_action('bp_core_get_users', [$this, 'filter_users'], 10, 2);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        load_plugin_textdomain('bp-group-segregation', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_settings_page() {
        add_options_page(
            __('Group Segregation Settings', 'bp-group-segregation'),
            __('Group Segregation', 'bp-group-segregation'),
            'manage_options',
            'bp-group-segregation',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting('bp_group_segregation', 'bp_group_segregation_content');
        register_setting('bp_group_segregation', 'bp_group_segregation_users');
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1><?php _e('Group Segregation Settings', 'bp-group-segregation'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('bp_group_segregation'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Segregate Content by Groups', 'bp-group-segregation'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="bp_group_segregation_content" value="1" <?php checked(get_option('bp_group_segregation_content'), 1); ?> />
                            <?php _e('Enable', 'bp-group-segregation'); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Segregate Users by Groups', 'bp-group-segregation'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="bp_group_segregation_users" value="1" <?php checked(get_option('bp_group_segregation_users'), 1); ?> />
                            <?php _e('Enable', 'bp-group-segregation'); ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function filter_activity($activities, $params) {
        if (!get_option('bp_group_segregation_content')) {
            return $activities;
        }

        $current_user_id = get_current_user_id();
        $current_user_role = $this->get_user_role($current_user_id);

        if (!$current_user_role || current_user_can('administrator')) {
            return $activities;
        }

        foreach ($activities['activities'] as $key => $activity) {
            $author_role = $this->get_user_role($activity->user_id);
            if ($author_role !== $current_user_role) {
                unset($activities['activities'][$key]);
            }
        }

        $activities['activities'] = array_values($activities['activities']);
        return $activities;
    }

    public function filter_users($users, $params) {
        if (!get_option('bp_group_segregation_users')) {
            return $users;
        }

        $current_user_id = get_current_user_id();
        $current_user_role = $this->get_user_role($current_user_id);

        if (!$current_user_role || current_user_can('administrator')) {
            return $users;
        }

        foreach ($users['users'] as $key => $user) {
            $user_role = $this->get_user_role($user->ID);
            if ($user_role !== $current_user_role) {
                unset($users['users'][$key]);
            }
        }

        $users['users'] = array_values($users['users']);
        return $users;
    }

    private function get_user_role($user_id) {
        $user = get_userdata($user_id);
        return $user ? $user->roles[0] : null;
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=bp-group-segregation">' . __('Settings', 'bp-group-segregation') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

BP_Group_Segregation::get_instance();


