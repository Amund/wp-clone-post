<?php

class WpClonePost
{

    /**
     * Injects the SVG store in the footer of the page.
     *
     * If the page is an administration page, it is injected in the admin_footer action.
     * Otherwise, it is injected in the wp_footer action.
     *
     * If the page is the login page, it is also injected in the login_footer action.
     *
     * @return void
     */
    public static function init(): void
    {
        if (is_admin()) {
            add_filter('post_row_actions', [static::class, 'admin_link'], 25, 2);
            add_filter('page_row_actions', [static::class, 'admin_link'], 25, 2);
            add_action('admin_action_wp_clone_post_clone', [static::class, 'clone']);
        }
    }

    public static function admin_link($actions, $post)
    {
        if (!current_user_can('edit_posts')) wp_die('No way.');
        $url = wp_nonce_url(
            add_query_arg(
                [
                    'action' => 'wp_clone_post_clone',
                    'post' => $post->ID,
                ],
                'admin.php'
            ),
            basename(__FILE__),
        );
        $actions['clone'] = '<a href="' . esc_url($url) . '">Cloner</a>';
        return $actions;
    }

    public static function clone()
    {
        // check if post ID has been provided and action
        if (empty($_GET['post'])) {
            wp_die('Bad request');
        }
        check_admin_referer(basename(__FILE__));

        $post_id = (int) $_GET['post'];
        $post = get_post($post_id);
        if (!$post) {
            wp_die('Post not found');
        }

        $current_user = wp_get_current_user();
        $new_post_author = $current_user->ID;

        // clone post
        $args = array(
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => $new_post_author,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_name'      => $post->post_name,
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_title'     => $post->post_title,
            'post_type'      => $post->post_type,
            'to_ping'        => $post->to_ping,
            'menu_order'     => $post->menu_order,
            'post_status'    => 'draft',
        );
        // wp_insert_post() attend des données slashées (il applique wp_unslash()) :
        // sans wp_slash(), les antislashs du contenu sont perdus, notamment les échappements
        // (\u0022, \u0026, \u003c…) du JSON des attributs de blocs (ACF, Gutenberg)
        $new_post_id = wp_insert_post(wp_slash($args), true);
        if (is_wp_error($new_post_id)) {
            wp_die($new_post_id);
        }

        // clone taxonomies
        $taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
        if ($taxonomies) {
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
                wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
            }
        }

        // clone post metas
        // $post_meta = get_post_meta($post_id);
        // if ($post_meta) {
        //     foreach ($post_meta as $meta_key => $meta_values) {
        //         if (in_array($meta_key, array('_edit_lock', '_wp_old_slug'))) {
        //             continue;
        //         }
        //         foreach ($meta_values as $meta_value) {
        //             add_post_meta($new_post_id, $meta_key, $meta_value);
        //         }
        //     }
        // }

        // redirect to list
        wp_safe_redirect(
            add_query_arg(
                array(
                    'post_type' => ('post' !== $post->post_type ? $post->post_type : false),
                ),
                admin_url('edit.php')
            )
        );
        exit;
    }
}
