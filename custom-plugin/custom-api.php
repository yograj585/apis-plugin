<?php
/**
 * Plugin Name: Custom Api
 * Plugin URI: https://example.com/custom-user-fields
 * Description: This plugin displays custom user fields on the user profile page and allows users to edit them.
 * Version: 1.0.0
 * Author: Yograj
 * Author URI: https://example.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-user-fields
 */

//All posts in wordpress
 add_action('rest_api_init', function () {
    register_rest_route('w1/v1', '/posts', [
        'methods' => 'GET',
        'callback' => 'get_all_posts',
    ]);
});

function get_all_posts() {
    $args = array(
        'post_type' => 'GET',
        'posts_per_page' => -1,
    );

 
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $posts = [];

        while ($query->have_posts()) {
            $query->the_post();
            $posts[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'content' => get_the_content(),
                'date' => get_the_date(),
                'author' => get_the_author(),
                'url' => get_permalink(),
            ];
        }
        
        wp_reset_postdata();
        
        return new WP_REST_Response($posts, 200);
    }

    return new WP_REST_Response('No posts found', 404);
}

//User complete data with their subscriptions in wordpress 
add_action('rest_api_init', function () {
    register_rest_route('mp/v1', '/members', [
        'methods' => 'GET',
        'callback' => 'get_memberpress_members_and_subscriptions',
    ]);
});

function get_memberpress_members_and_subscriptions(WP_REST_Request $request) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'mepr_members';
    $subscriptions_table = $wpdb->prefix . 'mepr_subscriptions';
    $query = "
        SELECT m.id AS member_id, m.user_id, m.created_at AS member_created_at, m.total_spent,
               s.id AS subscription_id, s.product_id, s.price, s.total, s.status AS subscription_status,
               s.created_at AS subscription_created_at, s.trial, s.period, s.period_type
        FROM $members_table m
        LEFT JOIN $subscriptions_table s ON m.user_id = s.user_id
    ";
    $results = $wpdb->get_results($query);

    if (empty($results)) {
        return new WP_REST_Response('No members or subscriptions found', 404);
    }
    $members_data = [];
    foreach ($results as $row) {
        $user_info = get_userdata($row->user_id);
        $members_data[] = [
            'member_id' => $row->member_id,
            'user_id' => $row->user_id,
            'username' => $user_info ? $user_info->user_login : 'Unknown User',
            'email' => $user_info ? $user_info->user_email : 'Unknown Email',
            'total_spent' => $row->total_spent,
            'subscription' => [
                'subscription_id' => $row->subscription_id,
                'product_id' => $row->product_id,
                'price' => $row->price,
                'total' => $row->total,
                'status' => $row->subscription_status,
                'trial' => $row->trial,
                'period' => $row->period,
                'period_type' => $row->period_type,
                'subscription_created_at' => $row->subscription_created_at,
            ],
            'member_created_at' => $row->member_created_at,
        ];
    }

    return new WP_REST_Response($members_data, 200);
}

//User memberships plans
add_action('rest_api_init', function() {
    register_rest_route('mp/v1', '/get_memberpress_products', array(
        'methods' => 'GET',
        'callback' => 'get_memberpress_products',
    ));
});
function get_memberpress_products(WP_REST_Request $request) {
    $args = array(
        'post_type' => 'memberpressproduct',
        'posts_per_page' => -1, 
        'post_status' => 'publish',
    );

    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        return new WP_REST_Response('No MemberPress products found', 404);
    }
    $products = [];
    while ($query->have_posts()) {
        $query->the_post();
        $products[] = [
            'id' => get_the_ID(),
            'title' => get_the_title(),
            'slug' => get_post_field('post_name', get_the_ID()),
            'permalink' => get_permalink(),
            'price' => get_post_meta(get_the_ID(), '_mepr_product_price', true), 
            'description' => get_the_content(),
            'product_image' => get_the_post_thumbnail_url(get_the_ID(), 'full'), 
        ];
    }

    wp_reset_postdata();

    return new WP_REST_Response($products, 200);
}

//User registration form
add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/users/register', [
        'methods' => 'POST',
        'callback' => 'wc_rest_user_endpoint_handler',
    ]);
});

function wc_rest_user_endpoint_handler( $request = null ) {

  $response = array();
  $parameters = $request->get_json_params();
  $username = sanitize_user( $parameters['username'] );
  $email = sanitize_email( $parameters['email'] );
  $password = sanitize_text_field( $parameters['password'] );
  $error = new WP_Error();
  if ( empty( $username ) ) {
    $error->add( '400', __( "Username field 'username' is required.", 'wp-rest-user' ), array( 'status' => 400 ) );
    return $error;
  }

  if ( empty( $email ) ) {
    $error->add( '401', __( "Email field 'email' is required.", 'wp-rest-user' ), array( 'status' => 400 ) );
    return $error;
  }

  if ( empty( $password ) ) {
    $error->add( '402', __( "Password field 'password' is required.", 'wp-rest-user' ), array( 'status' => 400 ) );
    return $error;
  }
  if ( username_exists( $username ) ) {
    $error->add( '403', __( "Username already exists.", 'wp-rest-user' ), array( 'status' => 400 ) );
    return $error;
  }
  if ( email_exists( $email ) ) {
    $error->add( '404', __( "Email already exists.", 'wp-rest-user' ), array( 'status' => 400 ) );
    return $error;
  }
  $user_id = wp_create_user( $username, $password, $email );
  if ( is_wp_error( $user_id ) ) {
    return $user_id; 
  }
  $user = get_user_by( 'id', $user_id );
  $user->set_role( 'subscriber' );
  $response['code'] = 200;
  $response['message'] = sprintf( __( "User '%s' registration was successful", 'wp-rest-user' ), $username );
  return new WP_REST_Response( $response, 200 );
}

// User login 
add_action( 'rest_api_init', function() {
    register_rest_route( 'custom/v1', '/login', [
        'methods' => 'POST',
        'callback' => 'custom_login_api',
    ]);
});
function custom_login_api( WP_REST_Request $request ) {
    $login = sanitize_text_field( $request->get_param( 'login' ) );
    $password = $request->get_param( 'password' );
    if ( empty( $login ) || empty( $password ) ) {
        return new WP_REST_Response( 'Username/email and password are required.', 400 );
    }
    if ( is_email( $login ) ) {
        $user = get_user_by( 'email', $login );
    } else {
        $user = get_user_by( 'login', $login );
    }
    if ( $user && wp_check_password( $password, $user->user_pass, $user->ID ) ) {
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID ); 
        $user_data = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
        ];
 
        return new WP_REST_Response( $user_data, 200 );
    }
    return new WP_REST_Response( 'Invalid username/email or password.', 401 );
}
// Register custom REST API endpointSS
add_action( 'rest_api_init', function() {
    register_rest_route( 'mp/v1', '/subscriptions', [
        'methods' => 'GET',
        'callback' => 'get_subscriptions_data',
    ]);
});
function get_subscriptions_data( $data ) {
    global $wpdb;
    $user_id = isset( $data['user_id'] ) ? intval( $data['user_id'] ) : 0;
    $product_id = isset( $data['product_id'] ) ? intval( $data['product_id'] ) : 0;



    $status = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';
    $query = "SELECT * FROM {$wpdb->prefix}mepr_subscriptions WHERE 1=1";
    if ( $user_id ) {
        $query .= $wpdb->prepare( " AND user_id = %d", $user_id );
    }
    if ( $product_id ) {
        $query .= $wpdb->prepare( " AND product_id = %d", $product_id );
    }
    if ( !empty( $status ) ) {
        $query .= $wpdb->prepare( " AND status = %s", $status );
    }
    $results = $wpdb->get_results( $query );
    return rest_ensure_response( $results );
}
