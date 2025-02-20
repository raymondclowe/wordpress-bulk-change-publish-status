<?php
/**
 * Plugin Name: Post Status Updater
 * Description: A lightweight tool to bulk update post statuses based on expected status and target status.
 * Version: 1.0
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add submenu item under the "Tools" menu
add_action('admin_menu', 'psu_add_submenu_page');
function psu_add_submenu_page() {
    add_submenu_page(
        'tools.php',              // Parent menu slug (Tools)
        'Post Status Updater',    // Page title
        'Status Updater',         // Menu title
        'manage_options',         // Capability required
        'post-status-updater',    // Menu slug
        'psu_render_admin_page'   // Callback function
    );
}

// Render admin page
function psu_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>Post Status Updater</h1>
        <form method="post" action="">
            <?php wp_nonce_field('psu_update_posts', 'psu_nonce'); ?>

            <p>
                <label for="urls">Enter URLs (one per line):</label><br>
                <textarea id="urls" name="urls" rows="10" cols="50" required></textarea>
            </p>

            <p>
                <label for="expected_status">Expected Status:</label><br>
                <select id="expected_status" name="expected_status" required>
                    <option value="publish">Publish</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="private">Private</option>
                    <option value="trash">Trash</option>
                </select>
            </p>

            <p>
                <label for="new_status">Change Status To:</label><br>
                <select id="new_status" name="new_status" required>
                    <option value="publish">Publish</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="private">Private</option>
                    <option value="trash">Trash</option>
                </select>
            </p>

            <p>
                <input type="submit" name="submit" class="button button-primary" value="Update Posts">
            </p>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
            psu_process_form();
        }
        ?>
    </div>
    <?php
}

// Process form submission
function psu_process_form() {
    // Verify nonce
    if (!isset($_POST['psu_nonce']) || !wp_verify_nonce($_POST['psu_nonce'], 'psu_update_posts')) {
        echo '<div class="error"><p>Security check failed. Please try again.</p></div>';
        return;
    }

    // Validate input fields
    $urls = isset($_POST['urls']) ? trim($_POST['urls']) : '';
    $expected_status = isset($_POST['expected_status']) ? sanitize_text_field($_POST['expected_status']) : '';
    $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

    if (empty($urls) || empty($expected_status) || empty($new_status)) {
        echo '<div class="error"><p>All fields are required.</p></div>';
        return;
    }

    // Parse URLs
    $url_list = array_map('trim', explode("\n", $urls));
    foreach ($url_list as $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo '<div class="error"><p>Invalid URL detected: ' . esc_html($url) . '</p></div>';
            return;
        }
    }

    // Process each URL
    foreach ($url_list as $url) {
        $post_id = url_to_postid($url);

        if (!$post_id) {
            echo '<div class="error"><p>No post found for URL: ' . esc_html($url) . '</p></div>';
            continue;
        }

        $post = get_post($post_id);
        if (!$post) {
            echo '<div class="error"><p>Failed to retrieve post for URL: ' . esc_html($url) . '</p></div>';
            continue;
        }

        // Check if the current status matches the expected status
        if ($post->post_status !== $expected_status) {
            echo '<div class="error"><p>Status mismatch for URL: ' . esc_html($url) . '. Expected: ' . esc_html($expected_status) . ', Actual: ' . esc_html($post->post_status) . '</p></div>';
            continue;
        }

        // Update the post status
        $updated = wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ]);

        if (is_wp_error($updated) || !$updated) {
            echo '<div class="error"><p>Failed to update post for URL: ' . esc_html($url) . '</p></div>';
            return; // Stop processing further
        }

        // Double-check the status change
        $updated_post = get_post($post_id);
        if ($updated_post->post_status !== $new_status) {
            echo '<div class="error"><p>Status change verification failed for URL: ' . esc_html($url) . '</p></div>';
            return; // Stop processing further
        }

        echo '<div class="updated"><p>Successfully updated post for URL: ' . esc_html($url) . '</p></div>';
    }
}