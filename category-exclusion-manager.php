<?php
/**
 * Plugin Name: Category Exclusion Manager
 * Description: Easily manage category exclusions for your site's front page, feeds, and archives.
 * Version: 1.0.0
 * Author: Ostap Brehin
 * Author URI: https://ostapbrehin.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: category-exclusion-manager
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

namespace CategoryExclusionManager;

defined('ABSPATH') || exit;

final class Plugin
{
    /** @var string */
    private $option_name = 'category_exclusion_manager_options';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu_page']);
        add_filter('pre_get_posts', [$this, 'exclude_categories']);
        add_filter("plugin_action_links_" . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function activate()
    {
        if (get_option($this->option_name)) return;

        add_option($this->option_name, [
            'exclude_home'     => [],
            'exclude_feed'     => [],
            'exclude_archives' => []
        ]);
    }

    public function deactivate()
    {
        delete_option($this->option_name);
    }

    public function add_admin_menu_page()
    {
        add_options_page(
            'Category Exclusion Manager',
            'Category Exclusion',
            'manage_options',
            'category-exclusion-manager',
            new OptionsPage($this->option_name)
        );
    }

    public function exclude_categories($query)
    {
        if (! $query->is_admin() && $query->is_main_query()) {
            $options = get_option($this->option_name, []);

            if ($query->is_home() && ! empty($options['exclude_home'])) {
                $query->set('category__not_in', $options['exclude_home']);
            }

            if ($query->is_feed() && ! empty($options['exclude_feed'])) {
                $query->set('category__not_in', $options['exclude_feed']);
            }

            if ($query->is_archive() && ! empty($options['exclude_archives'])) {
                $query->set('category__not_in', $options['exclude_archives']);
            }
        }

        return $query;
    }

    public function add_settings_link($links): array
    {
        $link = '<a href="options-general.php?page=category-exclusion-manager">' . __('Settings', 'category-exclusion-manager') . '</a>';

        return [$link] + $links;
    }
}

final class OptionsPage
{
    private $option_name;

    public function __construct($option_name)
    {
        $this->option_name = $option_name;
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings()
    {
        register_setting(
            'category_exclusion_manager_group',
            $this->option_name,
            [$this, 'sanitize_options']
        );

        add_settings_section(
            'category_exclusion_manager_section',
            'Category Exclusion Settings',
            [$this, 'section_callback'],
            'category-exclusion-manager'
        );

        $this->add_fields();
    }

    private function add_fields(): void
    {
        foreach ([
            'exclude_home'     => 'Exclude from Home Page',
            'exclude_feed'     => 'Exclude from Feeds',
            'exclude_archives' => 'Exclude from Archives'
        ] as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [$this, 'multi_checkbox_callback'],
                'category-exclusion-manager',
                'category_exclusion_manager_section',
                ['label_for' => $key, 'option' => $key]
            );
        }
    }

    public function section_callback($args): void
    {
        echo '<p>' . esc_html__('Select categories to exclude from different areas of your site.', 'category-exclusion-manager') . '</p>';
    }

    public function multi_checkbox_callback($args): void
    {
        $options = get_option($this->option_name, []);
        $categories = get_categories(['hide_empty' => false, 'order' => 'ASC']);
        $selected = $options[$args['option']] ?? [];

        foreach ($categories as $category) {
            printf(
                '<label><input type="checkbox" name="%1$s[%2$s][]" value="%3$d"%4$s> %5$s</label><br>',
                esc_attr($this->option_name),
                esc_attr($args['option']),
                esc_attr($category->term_id),
                checked(in_array($category->term_id, $selected), true, false),
                esc_html($category->name)
            );
        }
    }

    public function sanitize_options($input): array
    {
        $sanitized_input = [];
        $valid_options = ['exclude_home', 'exclude_feed', 'exclude_archives'];

        foreach ($valid_options as $option) {
            $sanitized_input[$option] = isset($input[$option]) ? array_map('intval', $input[$option]) : [];
        }

        return $sanitized_input;
    }

    public function __invoke(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('category_exclusion_manager_group');
                do_settings_sections('category-exclusion-manager');
                submit_button();
                ?>
            </form>
            <div class="notice notice-info inline">
                <p>
                    <strong><?php esc_html_e('Note:', 'category-exclusion-manager'); ?></strong>
                    <?php esc_html_e('If your theme uses Query Loop blocks, enable "Inherit query from template" in block settings to apply these exclusions.', 'category-exclusion-manager'); ?>
                    <a href="https://wordpress.org/documentation/article/query-loop-block/#inherit-query-from-template"
                       target="_blank">
                        <?php esc_html_e('Learn more about Query Loop blocks', 'category-exclusion-manager'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}

new Plugin();
