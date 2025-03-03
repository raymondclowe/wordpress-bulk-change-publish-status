<?php
/**
 * Plugin Name: Post Status Updater
 * Description: A lightweight tool to bulk update post statuses based on expected status and target status.
 * Version: 1.1
 * Author: Your Name
 */

 /* changelog
    * 1.1 - Added support for Custom Permalinks
    * 1.0 - Initial version
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

/**
 * Enhanced function to get post ID from URL, supporting Custom Permalinks
 *
 * @param string $url The URL to convert to a post ID
 * @return int The post ID or 0 if not found
 */
function psu_get_post_id_from_url($url) {
    // First try the standard WordPress function
    $post_id = url_to_postid($url);
    
    if (!$post_id) {
        global $wpdb;
        
        // Parse the URL to get the path
        $url_parts = parse_url($url);
        $path = isset($url_parts['path']) ? ltrim($url_parts['path'], '/') : '';
        
        // Remove home URL path if present
        $home_url_parts = parse_url(home_url());
        $home_path = isset($home_url_parts['path']) ? ltrim($home_url_parts['path'], '/') : '';
        if ($home_path && strpos($path, $home_path) === 0) {
            $path = substr($path, strlen($home_path));
        }
        $path = ltrim($path, '/');
        
        // Method 1: Check if this path exists in the custom permalinks meta
        $custom_meta_post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = 'custom_permalink' AND meta_value = %s",
            $path
        ));
        
        if ($custom_meta_post_id) {
            $post_id = $custom_meta_post_id;
        } else {
            // Method 2: Try to get the post ID by following redirects
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            
            $response = curl_exec($ch);
            $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            
            // Check if the final URL contains the post ID parameter
            if (preg_match('/[?&]p=(\d+)/', $final_url, $matches)) {
                $post_id = intval($matches[1]);
            } else {
                // Method 3: As a last resort, try querying by post name
                $slug = basename(rtrim($path, '/'));
                
                $query = new WP_Query([
                    'name' => $slug,
                    'post_type' => 'any',
                    'post_status' => 'any',
                    'posts_per_page' => 1,
                ]);
                
                if ($query->have_posts()) {
                    $post_id = $query->posts[0]->ID;
                }
            }
        }
    }
    
    return $post_id;
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
        $post_id = psu_get_post_id_from_url($url);

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