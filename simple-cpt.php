<?php
/**
 * Plugin Name: Simple CPT
 * Plugin URI: https://github.com/kumarayush957680/simple-cpt
 * Description: A lightweight plugin to create, edit, and delete custom post types with shared or custom categories/tags.
 * Version: 1.3
 * Author: Ayush Kumar
 * Author URI: mailto:kumarayush957680@gmail.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-cpt
 * Domain Path: /languages
 */

add_action('admin_menu', 'simple_cpt_admin_menu');
add_action('admin_init', 'simple_cpt_handle_actions');

function simple_cpt_admin_menu() {
    add_menu_page(
        'Simple CPT',
        'CPT Builder',
        'manage_options',
        'simple-cpt',
        'simple_cpt_admin_page',
        'dashicons-hammer',
        25
    );
}

function simple_cpt_admin_page() {
    $edit_mode = false;
    $edit_slug = '';
    $edit_values = ['singular' => '', 'plural' => '', 'tax' => 'none'];

    if (isset($_GET['edit_cpt'])) {
        $slug = sanitize_key($_GET['edit_cpt']);
        $all = get_option('simple_cpt_list', []);
        if (isset($all[$slug])) {
            $edit_mode = true;
            $edit_slug = $slug;
            $edit_values = $all[$slug];
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo $edit_mode ? 'Edit Custom Post Type' : 'Create Custom Post Type'; ?></h1>
        <form method="post">
            <?php
            if ($edit_mode) echo '<input type="hidden" name="original_slug" value="' . esc_attr($edit_slug) . '" />';
            wp_nonce_field('simple_cpt_save', 'simple_cpt_nonce');
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="post_type">Post Type Slug</label></th>
                    <td><input type="text" name="post_type" required value="<?php echo esc_attr($edit_mode ? $edit_slug : ''); ?>" <?php if ($edit_mode) echo 'readonly'; ?> /></td>
                </tr>
                <tr>
                    <th><label for="singular_label">Singular Label</label></th>
                    <td><input type="text" name="singular_label" required value="<?php echo esc_attr($edit_values['singular']); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="plural_label">Plural Label</label></th>
                    <td><input type="text" name="plural_label" required value="<?php echo esc_attr($edit_values['plural']); ?>" /></td>
                </tr>
                <tr>
                    <th><label>Taxonomy Type</label></th>
                    <td>
                        <label><input type="radio" name="tax_type" value="none" <?php checked($edit_values['tax'], 'none'); ?>> None</label><br>
                        <label><input type="radio" name="tax_type" value="shared" <?php checked($edit_values['tax'], 'shared'); ?>> Shared (WP Category/Tag)</label><br>
                        <label><input type="radio" name="tax_type" value="custom" <?php checked($edit_values['tax'], 'custom'); ?>> Custom (unique per CPT)</label>
                    </td>
                </tr>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?php echo $edit_mode ? 'Update Post Type' : 'Create Post Type'; ?>">
                <?php if ($edit_mode): ?>
                    <a href="<?php echo admin_url('admin.php?page=simple-cpt'); ?>" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>

        <hr>
        <h2>Registered Custom Post Types</h2>
        <ul>
            <?php
            $cpts = get_option('simple_cpt_list', []);
            if (!empty($cpts)) {
                foreach ($cpts as $slug => $labels) {
                    $edit_url = wp_nonce_url(admin_url("admin.php?page=simple-cpt&edit_cpt={$slug}"), 'simple_cpt_edit_' . $slug);
                    $delete_url = wp_nonce_url(admin_url("admin.php?page=simple-cpt&delete_cpt={$slug}"), 'simple_cpt_delete_' . $slug);
                    echo "<li><strong>{$slug}</strong> — {$labels['plural']} ";
                    echo " (<em>{$labels['tax']} taxonomy</em>) ";
                    echo "<a href='{$edit_url}' style='margin-left:10px;'>Edit</a>";
                    echo "<a href='{$delete_url}' style='color:red; margin-left:10px;'>Delete</a></li>";
                }
            } else {
                echo "<li>No custom post types registered yet.</li>";
            }
            ?>
        </ul>
    </div>
    <?php
}

function simple_cpt_handle_actions() {
    if (isset($_POST['simple_cpt_nonce']) && wp_verify_nonce($_POST['simple_cpt_nonce'], 'simple_cpt_save')) {
        if (!current_user_can('manage_options')) return;

        $slug = sanitize_title($_POST['post_type']);
        $singular = sanitize_text_field($_POST['singular_label']);
        $plural = sanitize_text_field($_POST['plural_label']);
        $tax = in_array($_POST['tax_type'], ['none', 'shared', 'custom']) ? $_POST['tax_type'] : 'none';

        $cpts = get_option('simple_cpt_list', []);
        $cpts[$slug] = ['singular' => $singular, 'plural' => $plural, 'tax' => $tax];
        update_option('simple_cpt_list', $cpts);

        wp_redirect(admin_url('admin.php?page=simple-cpt'));
        exit;
    }

    if (isset($_GET['delete_cpt']) && isset($_GET['_wpnonce'])) {
        $slug = sanitize_key($_GET['delete_cpt']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'simple_cpt_delete_' . $slug)) {
            $cpts = get_option('simple_cpt_list', []);
            if (isset($cpts[$slug])) {
                unset($cpts[$slug]);
                update_option('simple_cpt_list', $cpts);
            }
            wp_redirect(admin_url('admin.php?page=simple-cpt'));
            exit;
        }
    }
}

add_action('init', 'simple_cpt_register_all');
function simple_cpt_register_all() {
    $cpts = get_option('simple_cpt_list', []);
    foreach ($cpts as $slug => $settings) {
        // Register CPT
        register_post_type($slug, [
            'label' => $settings['plural'],
            'labels' => [
                'name' => $settings['plural'],
                'singular_name' => $settings['singular'],
                'add_new_item' => 'Add New ' . $settings['singular'],
                'edit_item' => 'Edit ' . $settings['singular'],
                'view_item' => 'View ' . $settings['singular'],
                'all_items' => 'All ' . $settings['plural'],
                'search_items' => 'Search ' . $settings['plural'],
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-admin-post',
            'supports' => ['title', 'editor', 'thumbnail'],
            'taxonomies' => ($settings['tax'] === 'shared') ? ['category', 'post_tag'] : [],
            'show_in_rest' => true,
        ]);

        // Register custom taxonomies if selected
        if ($settings['tax'] === 'custom') {
            $cat_tax = $slug . '_category';
            $tag_tax = $slug . '_tag';

            register_taxonomy($cat_tax, $slug, [
                'label' => ucfirst($settings['singular']) . ' Categories',
                'hierarchical' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => $cat_tax],
            ]);

            register_taxonomy($tag_tax, $slug, [
                'label' => ucfirst($settings['singular']) . ' Tags',
                'hierarchical' => false,
                'show_ui' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => $tag_tax],
            ]);
        }
    }
}
