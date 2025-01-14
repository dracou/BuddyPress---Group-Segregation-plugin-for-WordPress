<?php
/**
 * Plugin Name: BuddyPress Group Segregation
 * Plugin URI: https://salle-humide.be
 * Description: Plugin permettant de restreindre les contenus et utilisateurs visibles dans BuddyPress en fonction des groupes (rôles).
 * Version: 0.58 bêta
 * Author: Franck Masson
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
        add_filter('bp_directory_members_count', [$this, 'filter_member_count']);
        add_filter('bp_get_total_member_count', [$this, 'filter_member_count']);
        add_filter('bp_get_members_pagination_count', [$this, 'filter_member_pagination_count']);
        add_filter('bp_after_has_members_parse_args', [$this, 'filter_members_query_args']);
        add_filter('bp_members_directory_member_count', [$this, 'update_member_count_display']);
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

    public function filter_member_count($count) {
        if (!get_option('bp_group_segregation_users')) {
            return $count;
        }

        $current_user_id = get_current_user_id();
        $current_user_role = $this->get_user_role($current_user_id);

        if (!$current_user_role || current_user_can('administrator')) {
            return $count;
        }

        $users_query = new WP_User_Query([
            'role' => $current_user_role,
            'count_total' => true,
            'fields' => 'ID',
        ]);

        return $users_query->get_total();
    }

    public function filter_member_pagination_count($pagination_count) {
        if (!get_option('bp_group_segregation_users')) {
            return $pagination_count;
        }

        $current_user_id = get_current_user_id();
        $current_user_role = $this->get_user_role($current_user_id);

        if (!$current_user_role || current_user_can('administrator')) {
            return $pagination_count;
        }

        $users_query = new WP_User_Query([
            'role' => $current_user_role,
            'fields' => 'ID',
        ]);

        $total_count = $users_query->get_total();
        $current_count = count($users_query->get_results());

        return sprintf(
            __('Viewing %1$s of %2$s members', 'bp-group-segregation'),
            $current_count,
            $total_count
        );
    }

    public function filter_members_query_args($args) {
        if (!get_option('bp_group_segregation_users')) {
            return $args;
        }

        $current_user_id = get_current_user_id();
        $current_user_role = $this->get_user_role($current_user_id);

        if (!$current_user_role || current_user_can('administrator')) {
            return $args;
        }

        $args['role'] = $current_user_role;
        return $args;
    }

    public function update_member_count_display($content) {
        if (!get_option('bp_group_segregation_users')) {
            return $content;
        }

        $current_user_id = get_current_user_id();
        $current_user_role = $this->get_user_role($current_user_id);

        if (!$current_user_role || current_user_can('administrator')) {
            return $content;
        }

        $users_query = new WP_User_Query([
            'role' => $current_user_role,
            'fields' => 'ID',
        ]);

        $total_count = $users_query->get_total();
        return sprintf(__('Viewing %d members', 'bp-group-segregation'), $total_count);
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

