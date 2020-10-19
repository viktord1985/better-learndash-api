<?php

/*
Plugin Name: Better LearnDash API
Description: An API for LearnDash, specially tailored for the Dutch service Autorespond. Also gives option to send email notifications after successfully adding an user through the API.
Version: 0.5.7
Author: Rick Heijster @ RAM ICT Services
Author URI: http://www.ram-ictservices.nl
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: better-learndash-api
*/

if (!defined('BLDA_VERSION_KEY'))
    define('BLDA_VERSION_KEY', 'blda_version');

if (!defined('BLDA_VERSION_NUM'))
    define('BLDA_VERSION_NUM', '0.5.6');

/* !0. TABLE OF CONTENTS */

/*

	1. HOOKS

	2. SHORTCODES

	3. FILTERS
		3.1 blda_admin_menus()
		3.2 blda_plugin_action_links()

	4. EXTERNAL SCRIPTS
        4.1 blda_custom_css()

	5. ACTIONS
		5.1 blda_install()
        5.2 blda_upgrade()
        5.3 blda_update_check()
        5.4 blda_create_tables()
        5.5 blda_log_event()
        5.5 blda_show_log()
        5.7 blda_add_user_to_course()
        5.8 blda_remove_user_from_course()
        5.9 blda_lookup_course_name_by_id()
        5.10 blda_get_list_of_courses()
        5.10a blda_get_courses ()
        5.10b blda_get_lessons ()
        5.10c blda_get_topics ()
        5.11 blda_register_user_date()


	6. HELPERS
		6.1 blda_check_is_ld_active()
        6.2 blda_get_yesno_select()
        6.3 blda_get_current_options()
        6.4 blda_send_email_confirmation()
        6.5 blda_mail_contents()
		6.6 blda_check_api_key()
		6.7 blda_generate_api_key()


	7. CUSTOM POST TYPES

	8. ADMIN PAGES
		8.1 blda_admin_page() - Main Admin Page

	9. SETTINGS
        9.1 blda_register_options()

    10. API

*/

/* !1. HOOKS */
// hint: register our custom menus
add_action('admin_menu', 'blda_admin_menus');

// hint: register plugin options
add_action('admin_init', 'blda_register_options');

// hint: register custom css
add_action( 'admin_enqueue_scripts', 'blda_custom_css' );

// hint: put the API in the loop
add_action('init', 'blda_better_learndash_api');

// hint: run install/upgrade
register_activation_hook( __FILE__, 'blda_install' );
add_action( 'plugins_loaded', 'blda_update_check' );

// hint: fire download when clicked on link in admin page
add_action('wp_ajax_blda_download_log_csv', 'blda_download_log_csv'); // admin users

// hint: Add settings link to Plugin page
add_filter('plugin_action_links', 'blda_plugin_action_links', 10, 2);

/* !2. SHORTCODES */

/* !3. FILTERS */

// 3.1
// hint: registers custom plugin admin menus
function blda_admin_menus() {

    $top_menu_item = 'blda_admin_page';
    add_submenu_page( 'options-general.php', 'Better LearnDash API', 'Better LearnDash API', 'manage_options', $top_menu_item, $top_menu_item );


}

// 3.2
// hint: add Settings link to Plugins page

function blda_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=blda_admin_page">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}


/* !4. EXTERNAL SCRIPTS */

// 4.1
// hint: adds custom css to head
function blda_custom_css($hook) {
    // Load only on ?page=blda_admin_page
    if($hook != 'settings_page_blda_admin_page') {
        return;
    }
    wp_enqueue_style( 'blda_custom_css', plugins_url( 'assets/css/style-admin.css', __FILE__ ) );
}

/* !5. ACTIONS */

// 5.1
// hint: Create table wp_blda_log
function blda_install() {
    add_option(BLDA_VERSION_KEY, BLDA_VERSION_NUM);

    blda_create_tables();
}

// 5.2
// hint: Runs upgrade scripts
function blda_upgrade() {
    blda_create_tables();

    update_option(BLDA_VERSION_KEY, BLDA_VERSION_NUM);
}

// 5.3
// Checks if upgrade is needed
function blda_update_check() {
    //Check for version and upgrade if necessary
    if (get_option('blda_version') != BLDA_VERSION_NUM) blda_upgrade();
}

// 5.4
// hint: creates tables
function blda_create_tables() {
    global $wpdb;

    // setup return value
    $return_value = false;

    try {

        $table_name = $wpdb->prefix . "blda_log";
        $charset_collate = $wpdb->get_charset_collate();

        // sql for our table creation
        $sql = "CREATE TABLE ".$table_name." (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                  datetime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  request text NOT NULL,
                  status varchar(25) NOT NULL,
                  result text NOT NULL,
                  PRIMARY KEY (id)
			) $charset_collate;";

        // make sure we include wordpress functions for dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // dbDelta will create a new table if none exists or update an existing one
        dbDelta($sql);

        // return true
        $return_value = true;

    } catch( Exception $e ) {

        // php error

    }

    // return result
    return $return_value;
}

// 5.5
// hint: adds events to logfile
function blda_log_event( $query_string, $array_result ) {

    global $wpdb;

    // setup our return value
    $return_value = false;

    $req = $query_string;
    $status = $array_result['status'];
    $result = $array_result['message'];


    try {

        $table_name = $wpdb->prefix . "blda_log";

        $wpdb->insert(
            $table_name,
            array(
                'request' => $req,
                'status' => $status,
                'result' => $result,
            ),
            array(
                '%s',
                '%s',
                '%s',
            )
        );

        // return true
        $return_value = true;

    } catch( Exception $e ) {

        // php error

    }

    // return result
    return $return_value;

}

// 5.6
// hint: Show log
function blda_show_log() {
    global $wpdb;

    $page = isset($_REQUEST['blda_log_page']) && intval($_REQUEST['blda_log_page']) > 0 ? intval($_REQUEST['blda_log_page']) : 0;

    $first_record = $page * 50;

    $table_name = $wpdb->prefix . "blda_log";

    $log_count = $wpdb->get_var( "SELECT COUNT(*)
	             FROM ".$table_name);

    $query = $wpdb->prepare("SELECT datetime, request, status, result
	             FROM ".$table_name."
	             ORDER BY datetime DESC
	             LIMIT ".$first_record.", 50");

    $last_page = false;
    $min_count = ($page) * 50;
    $max_count = ($page + 1) * 50;

    if (($page + 1) * 50 > $log_count) {
        $max_item = $log_count;
        $last_page = true;
    }

    // get the records in the log table
    $logs = $wpdb->get_results($query);

    // IF we have rows in the log
    if( $wpdb->num_rows > 0 ) {

        echo "<p><strong>Log items ".$min_count." tot ".$max_count."</strong></p>";

        // loop over all our subscribers
        foreach ($logs as $log) {

            echo "<strong>Date</strong>: " . $log->datetime . " <strong>Status</strong>: " . $log->status . " <strong>Result</strong>: " . $log->result . "<br/><strong>Request</strong>: " . $log->request . "<br/><hr>";

        }

        if ($page > 0) echo "<a href='/wp-admin/options-general.php?page=blda_admin_page&tab=log&blda_log_page=".($page - 1)."'>Vorige 50</a>";
        if ($page > 0 || !$last_page) echo " | ";
        if (!$last_page) echo "<a href='/wp-admin/options-general.php?page=blda_admin_page&tab=log&blda_log_page=".($page + 1)."'>Volgende 50</a>";

    } else {
        echo "<p>Geen vermeldingen in het log gevonden</p>";
    }

}

// 5.7
// hint: add user to course

function blda_add_user_to_course( $user_id, $course_id ) {

    if (blda_check_is_ld_active()) {
        if ( strpos( $course_id, ',' ) !== false ) { //Course_id is list of courses
            $course_ids = explode(",", $course_id);

            foreach ($course_ids as $id) {
                $result = ld_update_course_access($user_id, $id, false);
            }
        } else {
            $result = ld_update_course_access($user_id, $course_id, false);
        }

        if ($result) {
            return "User " . $user_id . " added to course(s) " . blda_lookup_course_name_by_id($course_id) . " (".$course_id.")";
        } else {
            return false;
        }
    } else {
        return false;
    }
}

// 5.8
// hint: remove user from course

function blda_remove_user_from_course( $user_id, $course_id ) {

    if (blda_check_is_ld_active()) {
        if ( strpos( $course_id, ',' ) !== false ) { //Course_id is list of courses
            $course_ids = explode(",", $course_id);

            foreach ($course_ids as $id) {
                $result = ld_update_course_access( $user_id, $id, true );
            }
        } else {
            $result = ld_update_course_access( $user_id, $course_id, true );
        }

        if ($result) {
            return "User " . $user_id . " removed from course(s) " . $course_id;
        } else {
            return false;
        }
    } else {
        return false;
    }

}

// 5.9
// hint: Lookup Course name by ID

function blda_lookup_course_name_by_id ( $course_id ) {

    if (blda_check_is_ld_active()) {
        if ( strpos( $course_id, ',' ) !== false ) { //Course_id is list of courses
            $course_ids = explode(",", $course_id);

            $titles = "";

            foreach($course_ids as $id) {
                $course = get_post( $id );
                if ( ( !( $course instanceof WP_Post ) ) || ( $course->post_type != 'sfwd-courses' ) || ( empty( $course->post_title ) ) ) {
                    $titles = $titles;
                } else {
                    if ($titles == "") {
                        $titles = $course->post_title;
                    } else {
                        $titles = $titles . ", " . $course->post_title;
                    }
                }
            }

            return $titles;

        } else { //Just one course in course_id

            $course = get_post( $course_id );
            if ( ( !( $course instanceof WP_Post ) ) || ( $course->post_type != 'sfwd-courses' ) || ( empty( $course->post_title ) ) ) {
                return false;
            } else {
                return $course->post_title;
            }
        }
    } else {
        return false;
    }

}

// 5.10
// Hint: gets list of courses
function blda_get_list_of_courses ($array = true, $include_title = true) {

    if (blda_check_is_ld_active()) {
        $course_query_args = array(
            'post_type' => 'sfwd-courses',
            'fields' => 'ids',
            'nopaging' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $course_query = new WP_Query($course_query_args);

        if($course_query->have_posts() ) {

            $courses = array();

            while($course_query->have_posts() ) {
                $course_query->the_post();
                $course["ID"] =  get_the_ID();
                $course["title"] =  get_the_title();

                $courses[] = $course;
            }
        } else {
            $courses = false;
        }

        wp_reset_postdata();

        return $courses;
    } else {
        return false;
    }


}

function blda_get_sessions($lang)
{

    $session_query_args = array(
        'post_type' => 'product',
        'lang' => $lang,
        'post_status' => 'publish',
        'nopaging' => true,
        'orderby' => 'ID',
        'order' => 'ASC',
        'product_cat' => 'sessions,sessii'
//        'tax_query' => array(
//            'relation' => 'OR',
//            array(
//                'taxonomy' => 'product_cat',
//                'field' => 'slug',
//                'terms' => 'sessions'
//            ),
//            array(
//                'taxonomy' => 'product_cat',
//                'field' => 'slug',
//                'terms' => 'sessii'
//            )
//        ),
    );

    $sessions_query = new WP_Query($session_query_args);

    if ($sessions_query->have_posts()) {

        $sessions = array();

        while ($sessions_query->have_posts()) {
            $sessions_query->the_post();
            $session["ID"] = get_the_ID();
            $session["title"] = get_the_title();
            $session["thumbnail_img_url"] = get_the_post_thumbnail_url(get_the_ID(),'thumbnail');
            $session["full_img_url"] = get_the_post_thumbnail_url(get_the_ID(),'full');
            $session["content"] = get_the_content();
            if (get_the_ID() == 3624 || get_the_ID() == 3989) {
                $session["apple_product_id"]= "3624_3989"; //+
            } else if (get_the_ID() == 5340 || get_the_ID() == 5341) {
                $session["apple_product_id"]= "5340_5341"; //+
            } else if (get_the_ID() == 5176 || get_the_ID() == 5177) {
                $session["apple_product_id"]= "5176_5177"; //+
            } else if (get_the_ID() == 5342 || get_the_ID() == 5343) {
                $session["apple_product_id"]= "5342_5343"; //+
            } else if (get_the_ID() == 3478 || get_the_ID() == 3991) {
                $session["apple_product_id"]= "3478_3991"; //+
            } else if (get_the_ID() == 5174 || get_the_ID() == 5175) {
                $session["apple_product_id"]= "5174_5175"; //+
            } else {
                $session["apple_product_id"]= get_the_ID();
            }
            $sessions[] = $session;
        }
    } else {
        $sessions = false;
    }

    wp_reset_postdata();

    return $sessions;
}

// 5.10a
// Hint: get course
function blda_get_courses($username, $lang)
{

    if (blda_check_is_ld_active()) {
        $course_query_args = array(
            'post_type' => 'sfwd-courses',
            'lang' => $lang,
            'fields' => 'ids',
            'nopaging' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $user = get_user_by('login', $username);
        $enrolled_courses = $user ? learndash_user_get_enrolled_courses($user->ID) : [];

        $course_query = new WP_Query($course_query_args);

        if ($course_query->have_posts()) {

            $courses = array();

            while ($course_query->have_posts()) {
                $course_query->the_post();
                $course["ID"] = get_the_ID();
                $course["title"] = get_the_title();
                $course["thumbnail_img_url"] = get_the_post_thumbnail_url(get_the_ID(),'thumbnail');
                $course["full_img_url"] = get_the_post_thumbnail_url(get_the_ID(),'full');
                $course["content"] = get_the_content();
                $course["content_stripped"] = wp_strip_all_tags(get_the_content());
                $course["course_price_type"] = learndash_get_setting(get_the_ID(), 'course_price_type' );


                if (get_the_ID() == 2935 || get_the_ID() == 4097) {
                    $course["apple_product_id"]= "2935_4097"; //+
                } else if (get_the_ID() == 5223 || get_the_ID() == 5264) {
                    $course["apple_product_id"]= "5223_5264"; //?
                } else {
                    $course["apple_product_id"]= get_the_ID();
                }

                $course["access"] = in_array(intval(get_the_ID()), $enrolled_courses);

                //$ld_course_steps_object = LDLMS_Factory_Post::course_steps(intval(get_the_ID()));
                //$course_steps = $ld_course_steps_object->get_steps('t');
                //$course["steps"] = $course_steps;

                //$course_progress = get_user_meta( $user->ID, '_sfwd-course_progress', true );
                //$course["course_progress"] = $course_progress;
                $course["status"] = learndash_course_status(get_the_ID(), $user->ID);

                $ld_lessons_object = blda_get_lessons( $user->ID, get_the_ID(), $lang);
                $course["lessons"] = $ld_lessons_object;

                $courses[] = $course;
            }
        } else {
            $courses = false;
        }

        wp_reset_postdata();

        return $courses;
    } else {
        return false;
    }
}

// 5.10b
// Hint: get lessons by course_id
// Example: learndash_get_lesson_list( 538, array( 'num' => 0 ));
// Example: learndash_get_course_lessons_list(538);
function blda_get_lessons($user_id, $course_id, $lang)
{

    if (blda_check_is_ld_active()) {

        $course_lessons_args = learndash_get_course_lessons_order($course_id);

        $lessons_query_args = array(
            'post_type' => 'sfwd-lessons',
            'lang' => $lang,
            'meta_key' => 'course_id',
            'meta_value' => $course_id,
            'fields' => 'ids',
            'nopaging' => true,
            'orderby' => $course_lessons_args["orderby"],
            'order' => $course_lessons_args["order"],
        );

        $lessons_query = new WP_Query($lessons_query_args);

        if ($lessons_query->have_posts()) {

            $lessons = array();

            while ($lessons_query->have_posts()) {
                $lessons_query->the_post();
                $lesson["ID"] = get_the_ID();
                $lesson["title"] = get_the_title();
                $lesson["thumbnail_img_url"] = get_the_post_thumbnail_url(get_the_ID(),'thumbnail');
                $lesson["full_img_url"] = get_the_post_thumbnail_url(get_the_ID(),'full');
                $lesson["content"] = get_the_content();
                $lesson["content_stripped"] = wp_strip_all_tags(get_the_content());

                $lesson["lesson_video_enabled"] = learndash_get_setting(get_the_ID(), 'lesson_video_enabled' );
                $lesson["lesson_video_url"] = learndash_get_setting(get_the_ID(), 'lesson_video_url' );
                $lesson["lesson_video_shown"] = learndash_get_setting(get_the_ID(), 'lesson_video_shown' );

                //$ld_topics_object = blda_get_topics(get_the_ID());
                //$lesson["topics"] = $ld_topics_object;

                $lesson["is_complete"] = learndash_is_lesson_complete($user_id, get_the_ID(), $course_id);

                $lessons[] = $lesson;
            }
        } else {
            $lessons = false;
        }

        wp_reset_postdata();

        return $lessons;
    } else {
        return false;
    }
}

// 5.10c
// Hint: get topica by lesson_id
function blda_get_topics($lesson_id)
{

    if (blda_check_is_ld_active()) {
        $topics_query_args = array(
            'post_type' => 'sfwd-topic',
            'meta_key' => 'lesson_id',
            'meta_value' => $lesson_id,
            'fields' => 'ids',
            'nopaging' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $topics_query = new WP_Query($topics_query_args);

        if ($topics_query->have_posts()) {

            $topics = array();

            while ($topics_query->have_posts()) {
                $topics_query->the_post();
                $topic["ID"] = get_the_ID();
                $topic["title"] = get_the_title();
                //$topic["content"] = get_the_content();
                $topic["content_stripped"] = wp_strip_all_tags(get_the_content());

                $topics[] = $topic;
            }
        } else {
            $topics = false;
        }

        wp_reset_postdata();

        return $topics;
    } else {
        return false;
    }
}

// 5.11
// hint: updates user data with first name, last name and display name
function blda_register_user_data ($userid, $fname, $lname) {
    $first = 0;
    $last = 0;
    $user_array = array( 'ID' => $userid);

    $user_info = get_userdata($userid);

    if (!strlen($user_info->first_name && !strlen($user_info->last_name))) {
        // Only update if there is no current first name and/or last name registered
        if (strlen($fname)) {
            $first_name = esc_attr($fname);
            $user_array['first_name'] = $first_name;
            $first = 1;
        }

        if (strlen($lname)) {
            $last_name = esc_attr($lname);
            $user_array['last_name'] = $last_name;
            $last = 1;
        }

        if ($first && !$last) {
            $user_array['display_name'] = $first_name;
            $result = "Name and Display name set to ".$first_name;
        } elseif ($first && $last) {
            $user_array['display_name'] = $first_name . " " . $last_name;
            $result = "Name and Display name set to ".$first_name. " " . $last_name;;
        } elseif (!$first && $last) {
            $user_array['display_name'] = $last_name;
            $result = "Name and Display name set to ".$last_name;
        }

        if ($first || $last) {
            $user_id = wp_update_user($user_array);
            if (is_wp_error($user_id)) {
                $error_string = $user_id->get_error_message();
                return "Error: ".$error_string;
            } else {
                return $result;
            }
        } else {
            return "No first or last name received. Name not set.";
        }
    } else {
        return "No first or last name received. Name not set.";
    }
}

/* !6. HELPERS */

// 6.1
// hint: Checks if LearnDash is active
function blda_check_is_ld_active() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    $ld_is_active = false;

    if ( is_plugin_active( 'sfwd-lms/sfwd_lms.php' ) ) {
        $ld_is_active = true;
    }

    return $ld_is_active;
}

// 6.2
// hint: returns html for a page selector
function blda_get_yesno_select( $input_name="blda_yesno", $input_id="", $selected_value="" ) {

    // setup our select html
    $select = '<select name="'. $input_name .'" ';

    // IF $input_id was passed in
    if( strlen($input_id) ):
        // add an input id to our select html
        $select .= 'id="'. $input_id .'" ';

    endif;

    // setup our first select option
    $select .= '><option value="">- Select One -</option>';

    //Add Yes
    // check if this option is the currently selected option
    $selected = '';
    if( $selected_value == "1" ):
        $selected = ' selected="selected" ';
    endif;

    // build our option html
    $option = '<option value="1" '. $selected .'>Ja</option>';

    // append our option to the select html
    $select .= $option;

    //Add No
    // check if this option is the currently selected option
    $selected = '';
    if( $selected_value == "0" ):
        $selected = ' selected="selected" ';
    endif;

    // build our option html
    $option = '<option value="0" '. $selected .'>Nee</option>';

    // append our option to the select html
    $select .= $option;
    // close our select html tag
    $select .= '</select>';

    // return our new select
    return $select;

}

// 6.3
// hint: get's the current options and returns values in associative array
function blda_get_current_options() {

    // setup our return variable
    $current_options = array();

    try {

        $blda_option_send_confirmation_email = (get_option('blda_option_send_confirmation_email', null) !== null) ? get_option('blda_option_send_confirmation_email') : 0;
        $blda_option_check_if_user_exists = (get_option('blda_option_check_if_user_exists', null) !== null) ? get_option('blda_option_check_if_user_exists') : 1;
        $blda_option_update_user_data = (get_option('blda_option_update_user_data', null) !== null) ? get_option('blda_option_update_user_data') : 1;
        $blda_option_destination_email = (get_option('blda_option_destination_email')) ? get_option('blda_option_destination_email') : get_option('admin_email');
        $blda_options_email_include_password = (get_option('blda_options_email_include_password')) ? get_option('blda_options_email_include_password') : 0;
        $blda_options_api_key = (get_option('blda_options_api_key')) ? get_option('blda_options_api_key') : "";

        if ($blda_options_api_key == "") {
            $blda_options_api_key = blda_generate_api_key();
            update_option( 'blda_options_api_key', $blda_options_api_key);
        }

        // build our current options associative array
        $current_options = array(
            'blda_option_send_confirmation_email' => $blda_option_send_confirmation_email,
            'blda_option_check_if_user_exists' => $blda_option_check_if_user_exists,
            'blda_option_destination_email' => $blda_option_destination_email,
            'blda_option_update_user_data' => $blda_option_update_user_data,
            'blda_options_email_include_password' => $blda_options_email_include_password,
            'blda_options_api_key' => $blda_options_api_key,
        );

    } catch( Exception $e ) {

        // php error

    }

    // return current options
    return $current_options;

}

// 6.4
// hint: Send email confirmations
function blda_send_email_confirmation($action, $user, $user_pass, $level) {
    // setup return variable
    $email_sent = false;

    $options = blda_get_current_options();

    $email_destination = explode(";", $options['blda_option_destination_email']);
    $email_include_password = $options['blda_options_email_include_password'];

    // get email data
    $email_contents = blda_mail_contents($action, $user, $user_pass, $level, $email_include_password);


    // IF email template data was found
    if( !empty( $email_contents ) ):

        // set wp_mail headers
        $wp_mail_headers = array('Content-Type: text/html; charset=UTF-8');

        // use wp_mail to send email
        $email_sent = wp_mail( $email_destination , $email_contents['subject'], $email_contents['contents'], $wp_mail_headers );

    endif;

    return $email_sent;
}

// 6.5
// hint: create email contents
function blda_mail_contents($action, $user, $user_pass, $level, $email_include_password) {

    $email_contents = array();

    if ($action == "new_user") {
        $email_contents['subject'] = "Nieuwe gebruiker toegevoegd aan LearnDash via Autorespond";
        $email_contents['contents'] = '
		<p>Hallo,</p>
		<p>Er is zojuist een nieuwe gebruiker toegevoegd aan LearnDash via Autorespond:</p>
		<p>Gebruiker: '.$user.'<br/>';
        if ($email_include_password) $email_contents['contents'] = $email_contents['contents'].'Wachtwoord: '.$user_pass.'<br/>';


        $email_contents['contents'] = $email_contents['contents'].'
		Level: '.$level.'</p>
		<p>Met vriendelijke groet,<br/>
		   Better LearnDash API</p>
		';

    } elseif ($action == "add_level") {
        $email_contents['subject'] = "Nieuw level toegevoegd aan gebruiker in LearnDash Member via Autorespond";
        $email_contents['contents'] = '
		<p>Hallo,</p>
		<p>Er is zojuist een nieuwe cusus toegevoegd aan gebruiker '.$user.' in LearnDash via Autorespond:</p>
		<p>Gebruiker: '.$user.'<br/>
		   Toegevoegd level: '.$level.'</p>
		<p>Met vriendelijke groet,<br/>
		   Better LearnDash API</p>
		';
    }

    return $email_contents;

}

// 6.6
// Hint: Check if given string matches the API-key

function blda_check_api_key ($api_key = "nokey") {
    $blda_api_key = (get_option('blda_options_api_key')) ? get_option('blda_options_api_key') : "";

    if ($api_key == $blda_api_key ) {
        return true;
    } else {
        return false;
    }

}

// 6.7
// Hint: Generates unique API key
function blda_generate_api_key () {
    $key = md5(microtime().rand());

    return $key;
}

/* !7. CUSTOM POST TYPES */

/* !8. ADMIN PAGES */

// 8.1 Main Admin Menu
// hint: create Admin menu

function blda_admin_page() {

    $options = blda_get_current_options();

    if (!blda_check_is_ld_active()) {
        $error_ld_not_active = '
            <div class="error">
                <p>
                    <strong>De plugin LearnDash is niet gevonden.</strong>
                </p>
                <p>
                    Deze plugin is een uitbreiding van de plugin LearnDash.
                </p>
                <p>
                    Zonder LearnDash heeft deze plugin geen functie.
                </p>
            </div>';
    }

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'front_page_options';

    $active_class_front_page_options = $active_tab == "front_page_options" ? "nav-tab-active" : "";
    $active_class_list_courses = $active_tab == "list_courses" ? "nav-tab-active" : "";
    $active_class_log = $active_tab == "log" ? "nav-tab-active" : "";

    echo '
		<div class="wrap">
			<h2>Better LearnDash API</h2>
            <h2 class="nav-tab-wrapper">
                <a href="?page=blda_admin_page&tab=front_page_options" class="nav-tab ' . $active_class_front_page_options . '">Instellingen</a>
                <a href="?page=blda_admin_page&tab=list_courses" class="nav-tab ' . $active_class_list_courses . '">Beschikbare cursussen</a>
                <a href="?page=blda_admin_page&tab=log" class="nav-tab ' . $active_class_log . '">Log</a>     
            </h2> <!-- nav-tab-wrapper -->
            <div class="blda-wrapper">
                <div id="content" class="wrapper-cell">';
    if ($active_tab == "front_page_options") {
        echo '
                    ' . $error_ld_not_active . '
                    <h2>Better LearnDash API Opties</h2>
                    <p>Deze plugin geeft je mogelijkheden om Autorespond en LearnDash te koppelen</p>
                    <p>Met name:</p>
                    <ul>
                        <li>* De mogelijkheid om via Autorespond een gebruiker aan te maken en een cursus toe te kennen</li>
                        <li>* De mogelijkheid om via Autorespond een cursus aan een bestaande gebruiker toe te kennen</li>
                        <li>* De mogelijkheid om van je website een bevestiging te krijgen van de aanmelding van de nieuwe gebruiker of het toekennen van de nieuwe cursus</li>
                        <li>* De mogelijkheid om de transacties tussen Autorespond en LearnDash in een log te bekijken</li>
                    </ul>

                    <form action="options.php" method="post">';
        // outputs a unique nounce for our plugin options
        settings_fields('blda_plugin_options');
        // generates a unique hidden field with our form handling url
        @do_settings_fields('blda_plugin_options', 'default');

        echo '<table class="form-table">

                            <tbody>

                                <tr>
                                    <th scope="row"><label for="blda_options_api_key">API-sleutel</label></th>
                                    <td>
                                        <input type="text" id="blda_options_api_key" name="blda_options_api_key" value="' . $options['blda_options_api_key'] . '" size="100"/]<br/>
                                        <p class="description" id="blda_options_api_key-description">De API-sleutel is een unieke waarde die opgegeven moet worden bij ieder verzoek aan Better LearnDash API. Je kunt de API-sleutel zien als het wachtwoord van Better LearnDash API</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="blda_option_update_user_data">Registreer Naam</label></th>
                                    <td>
                                        ' . blda_get_yesno_select("blda_option_update_user_data", "blda_option_update_user_data", $options['blda_option_update_user_data']) . '
                                        <p class="description" id="blda_option_update_user_data-description">Als deze optie is ingeschakeld, worden de voornaam, achternaam en schermnaam toegevoegd aan de gebruiker, als deze niet al zijn ingevuld.</p>
                                    </td>
                                </tr>
                                <tr>
                                <tr>
                                    <th scope="row"><label for="blda_option_send_confirmation_email">Bevestigingsmail</label></th>
                                    <td>
                                        ' . blda_get_yesno_select("blda_option_send_confirmation_email", "blda_option_send_confirmation_email", $options['blda_option_send_confirmation_email']) . '
                                        <p class="description" id="blda_option_send_confirmation_email-description">Als deze optie is ingeschakeld, krijg je een e-mail als er via Better LearnDash API een nieuwe gebruiker is toegevoegd, of als er een level is toegevoegd aan een bestaande gebruiker.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="blda_options_email_include_password">Vermeld wachtwoord in e-mail</label></th>
                                    <td>
                                        ' . blda_get_yesno_select("blda_options_email_include_password", "blda_options_email_include_password", $options['blda_options_email_include_password']) . '
                                        <p class="description" id="blda_options_email_include_password-description">Als deze optie is ingeschakeld, wordt in de bevestigingsmail ook het ingestelde wachtwoord vermeld bij een nieuwe gebruiker.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="blda_option_destination_email">E-mail adres</label></th>
                                    <td>
                                        <input type="text" id="blda_option_destination_email" name="blda_option_destination_email" value="' . $options['blda_option_destination_email'] . '" size="100"/]<br/>
                                        <p class="description" id="blda_option_destination_email-description">Op welk e-mailadres wil je de bevestigingsmails ontvangen? Als je wilt dat de bevestigingen naar meerdere e-mailadressen gestuurd worden, zet ze dan achter elkaar, geschieden door een puntkomma (;)</p>
                                    </td>
                                </tr>

                            </tbody>

                        </table>';

        @submit_button();

        echo '</form>';

    } elseif ($active_tab == "list_courses") {
        echo '
                    ' . $error_ld_not_active . '
                    <h2>Lijst met beschikbare cursussen en bijbehorende ID\'s</h2>';

        $list_of_courses = blda_get_list_of_courses();

        if ($list_of_courses) {
            $alternate = "";

            $table = '
                        <table class="widefat">
                        <thead>
                            <tr>
                                <th scope="col" width="100">Course ID</th>
                                <th scope="col">Course Name</th>                                
                            </tr>
                        </thead>
                        <tbody>
                        ';

            foreach ($list_of_courses as $course) {
                if ($alternate == "") {
                    $alternate = "alternate";
                } else {
                    $alternate = "";
                }

                $row = '
                                <tr class="' . $alternate . '" id="course_row_' . $course['ID'] . '">
                                    <td>                                        
                                        ' . $course['ID'] . '
                                    </td>
                                    <td>
                                        <strong>' . $course['title'] . '</strong>
                                    </td>
                            ';

                $table = $table.$row;
            }

            $table = $table."</tbody></table>";

            //echo "<pre>"; print_r($list_of_courses); echo "</pre>";

            echo $table;
        } else {
            echo "<p>Geen LearnDash Courses gevonden</p>";
        }

    } else {
        echo '
                    ' . $error_ld_not_active . '
                    <h2>Better LearnDash API Log</h2>
                    <p>';
        blda_show_log();
        echo "</p>";
    }

    echo '
                </div>
                <div id="sidebar" class="wrapper-cell">
                    <div class="sidebar_box info_box">
                        <h3>Plugin Info</h3>
                        <div class="inside">
                            <a href="https://www.autorespond.nl" target="_blank"><img  width="272px" src="'. plugins_url( 'img/logo-ar.jpg', __FILE__ ) .'" /></a>
                            <ul>
                                <li>Naam: Better LearnDash API</li>
                                <li>Auteur: Rick Heijster @ RAM ICT Services</li>
                            </ul>
                            <p>Deze plugin wordt je gratis aangeboden door Autorespond in samenwerking met RAM ICT Services.</p>
                            <a href="https://www.ram-ictservices.nl" target="_blank"><img  width="272px" src="'. plugins_url( 'img/Logo-Ram.png', __FILE__ ) .'" /></a>
                        </div>
                    </div>
                </div>
            </div>
		</div>

	';

}


/* !9. SETTINGS */

// 9.1
// hint: registers all our plugin options
function blda_register_options() {
    // plugin options
    register_setting('blda_plugin_options', 'blda_option_check_if_user_exists');
    register_setting('blda_plugin_options', 'blda_option_send_confirmation_email');
    register_setting('blda_plugin_options', 'blda_option_destination_email');
    register_setting('blda_plugin_options', 'blda_option_update_user_data');
    register_setting('blda_plugin_options', 'blda_options_email_include_password');
    register_setting('blda_plugin_options', 'blda_options_api_key');

}

/* !10. API */

function blda_better_learndash_api () {

    // Check if there's a simplewlmapi request
    if(isset($_REQUEST['better_ld_api'])) {

        if ( !blda_check_is_ld_active() ) {
            // If LearnDash is not active, disengage.
            $result['message'] = "Request received, but LearnDash is not active. Better LearnDash API ignored request.";
            $result['status'] = "Error";
            blda_log_event($_SERVER['QUERY_STRING'], $result);
            exit;
        }

        $api_key = isset($_REQUEST['better_ld_api']) ? sanitize_key($_REQUEST['better_ld_api']) : false;
        $better_ld_api_method = isset($_REQUEST['better_ld_api_method']) && $_REQUEST['better_ld_api_method'] != "" ? sanitize_text_field($_REQUEST['better_ld_api_method']) : false;
        $useremail = isset($_REQUEST['useremail']) && $_REQUEST['useremail'] != "" && is_email($_REQUEST['useremail']) ? sanitize_email($_REQUEST['useremail']) : false;
        $username = isset($_REQUEST['username'])  && $_REQUEST['username'] != "" ? sanitize_text_field($_REQUEST['username']) : false;
        $userpass = isset($_REQUEST['userpass'])  && $_REQUEST['userpass'] != "" ? sanitize_text_field($_REQUEST['userpass']) : false;
        $course_id = isset($_REQUEST['course_id'])  && $_REQUEST['course_id'] != "" ? sanitize_text_field($_REQUEST['course_id']) : false;
        $user_first_name = isset($_REQUEST['fname'])  && $_REQUEST['fname'] != "" ? sanitize_text_field($_REQUEST['fname']) : false;
        $user_last_name = isset($_REQUEST['lname'])  && $_REQUEST['lname'] != "" ? sanitize_text_field($_REQUEST['lname']) : false;
        $post_id = isset($_REQUEST['post_id'])  && $_REQUEST['post_id'] != "" ? sanitize_text_field($_REQUEST['post_id']) : false;
        $lang = isset($_REQUEST['lang'])  && $_REQUEST['lang'] != "" ? sanitize_text_field($_REQUEST['lang']) : false;

        $data = isset($_REQUEST['data'])  && $_REQUEST['data'] != "" ? sanitize_text_field($_REQUEST['data']) : false;
        $time = isset($_REQUEST['time'])  && $_REQUEST['time'] != "" ? sanitize_text_field($_REQUEST['time']) : false;
        $session_id = isset($_REQUEST['session_id'])  && $_REQUEST['session_id'] != "" ? sanitize_text_field($_REQUEST['session_id']) : false;
        $session_name = isset($_REQUEST['session_name'])  && $_REQUEST['session_name'] != "" ? sanitize_text_field($_REQUEST['session_name']) : false;
        $transaction_id = isset($_REQUEST['transaction_id'])  && $_REQUEST['transaction_id'] != "" ? sanitize_text_field($_REQUEST['transaction_id']) : false;

        $avatar_data = isset($_REQUEST['avatar_data'])  && $_REQUEST['avatar_data'] != "" ? sanitize_text_field($_REQUEST['avatar_data']) : false;

        // Check if LearnDash is installed
        if (blda_check_is_ld_active()) {

            header('Content-type: application/json');

            // Check if the passed api key matches wlm's api key
            if(blda_check_api_key($api_key)) {

                $result = array();

                $blda_options = blda_get_current_options();

                // Check if the method passed is valid
                if(in_array($better_ld_api_method, array('set_avatar', 'add_new_member', 'remove_member_from_course', 'get_courses', 'get_sessions', 'get_courses_v2', 'mark_completed', 'add_to_course', 'one_to_one_session'))) {

                    switch ($better_ld_api_method) {
                        case 'one_to_one_session':

                            if (!$data || !$time || !$user_first_name || !$user_last_name || !$useremail || !$session_id || !$session_name || !$transaction_id) {
                                echo json_encode(array('success' => 0, 'message' => 'one_to_one_session method needs the the following data: data, time, fname, lname, useremail, session_id, session_name, transaction_id'));
                                $result['message'] = "Request add member to course, but no data or no time or no fname or no lname or no useremail or no session_id or no session_name or no transaction_id received.";
                                $result['status'] = "Error";
                            } else {

                                $contact_email = "viktor.derk1985@gmail.com," . get_option('admin_email');

                                $subject = "New 1-2-1 request";

                                $message_formatted = '<html><head><title>' . $subject . '</title></head><body>'
                                    . $user_first_name . ' ' . $user_last_name . ' created a request at ' . $data . '-' . $time . ' session name: ' . $session_name . '(#' . $session_id . ') and transaction ID: ' . $transaction_id . '</body></html>';

                                $headers = 'MIME-Version: 1.0' . "\r\n";
                                $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                                $headers .= 'To: ' . $contact_email . "\r\n";
                                $headers .= 'From: ' . (!empty($user_first_name) ? $user_first_name . ' <' . $useremail . '>' : $useremail) . "\r\n";

                                mail($contact_email, $subject, $message_formatted, $headers);

                                $action_result = "Success";
                                echo json_encode(array('success' => 1, 'message' => $action_result));
                                $result['message'] = $action_result;
                                $result['status'] = "Success";
                            }
                            break;
                        case 'set_avatar' :

                            if (!$username || !$avatar_data) {
                                echo json_encode(array('success' => 0, 'message' => 'set_avatar method needs the the following data: username, avatar_data'));
                                $result['message'] = "Request add member, but no username or no avatar_data received.";
                                $result['status'] = "Error";
                            } else {

                                $name = '/user_avatar_'. $username . '.png';
                                $upload = wp_upload_dir();
                                $file = $upload['basedir'] . $name;
                                file_put_contents($file, base64_decode($avatar_data));

                                echo json_encode(array('success' => 1, 'message' => "Success", "user_avatar_url" => $upload['baseurl'] . $name));
                                $result['message'] = "Success";
                                $result['status'] = "Success";
                            }

                            break;
                        case 'get_courses_v2':
                            // Give list of courses with their ID's

                            $course = blda_get_courses($username, $lang ? $lang : 'en');

                            if ($course) {
                                echo json_encode(array('success' => 1, 'message' => "Course content", 'course' => $course));
                                $result['message'] = "Course content sent";
                                $result['status'] = "Success";
                            } else {
                                echo json_encode(array('success' => 0, 'message' => "No course found"));
                                $result['message'] =  "No course found";
                                $result['status'] = "Error";
                            }
                            break;
                        case 'get_sessions':

                            $sessions = blda_get_sessions($lang ? $lang : 'en');

                            if ($sessions) {
                                echo json_encode(array('success' => 1, 'message' => "Sessions content", 'course' => $sessions));
                                $result['message'] = "Sessions content sent";
                                $result['status'] = "Success";
                            } else {
                                echo json_encode(array('success' => 0, 'message' => "No sessions found"));
                                $result['message'] =  "No sessions found";
                                $result['status'] = "Error";
                            }
                            break;
                        case 'get_courses':
                            // Give list of courses with their ID's

                            $list_of_courses = blda_get_list_of_courses();

                            if ($list_of_courses) {
                                echo json_encode(array('success' => 1, 'message' => "List of courses follows", 'courses' => $list_of_courses));
                                $result['message'] = "List of courses sent";
                                $result['status'] = "Success";
                            } else {
                                echo json_encode(array('success' => 1, 'message' => "No courses found"));
                                $result['message'] =  "No courses found";
                                $result['status'] = "Success";
                            }
                            break;
                        case 'mark_completed':
                            $user_id = '';
                            if ($useremail) {
                                $exists = email_exists($useremail);
                                if ($exists) {
                                    $user = get_user_by('email', $useremail);
                                    $user_id = $user->ID;
                                }
                            } elseif ($username) {
                                $exists = username_exists($username);
                                if ($exists) {
                                    $user = get_user_by('login', $username);
                                    $user_id = $user->ID;
                                }
                            } else {
                                $exists = false;
                            }

                            if ($exists && $post_id) {
                                $received_user_id = $exists;

                                $ids = explode("|", $post_id);
                                if (isset($ids) && count($ids) != 0) {
                                    for ($i = 0; $i < count($ids); $i++) {
                                        $action_result = learndash_process_mark_complete($user_id, $ids[$i]);
                                    }
                                } else {
                                    $action_result = learndash_process_mark_complete($user_id, $post_id);
                                }

                                if ($action_result) {
                                    echo json_encode(array('success' => 1, 'message' => strval($action_result)));
                                    $result['message'] = strval($action_result);
                                    $result['status'] = "Success";
                                } else {
                                    echo json_encode(array('success' => 0, 'message' => "Error encountered while mark complete " . $received_user_id . " from post " . $post_id));
                                    $result['message'] = "Error encountered while mark complete " . $received_user_id . " from post " . $post_id;
                                    $result['status'] = "Error";
                                }
                            } elseif (!$post_id || (!$useremail && !$username)) {
                                echo json_encode(array('success' => 0, 'message' => 'mark_completed method needs the Post ID and the email address or username of the user'));
                                $result['message'] = "Request to mark complete received, but no Post ID or username or user email received.";
                                $result['status'] = "Error";
                            } elseif (!$exists) {
                                echo  json_encode( array( 'success' => 0, 'message' => 'User not found' ));
                                $result['message'] = "Request to mark complete received, but user not found.";
                                $result['status'] = "Error";
                            }
                            break;
                        case 'remove_member_from_course':

                            if ($useremail) {
                                $exists = email_exists($useremail);
                            } elseif ($username) {
                                $exists = email_exists($username);
                            } else {
                                $exists = false;
                            }

                            if ($exists && $course_id) {
                                $received_user_id = $exists;

                                $action_result = blda_remove_user_from_course($received_user_id, $course_id);

                                if ($action_result) {
                                    echo json_encode(array('success' => 1, 'message' => $action_result));
                                    $result['message'] = $action_result;
                                    $result['status'] = "Success";
                                } else {
                                    echo json_encode(array('success' => 0, 'message' => "Error encountered while removing user " . $received_user_id . " from course(s) " . blda_lookup_course_name_by_id($course_id)));
                                    $result['message'] = "Error encountered while removing user " . $received_user_id . " from course(s) " . blda_lookup_course_name_by_id($course_id);
                                    $result['status'] = "Error";
                                }
                            } elseif (!$course_id || (!$useremail && !$username)) {
                                echo json_encode(array('success' => 0, 'message' => 'remove_member_from_course method needs the ID of the course and the email address or username of the user'));
                                $result['message'] = "Request to member of level received, but no Course ID or username or user email received.";
                                $result['status'] = "Error";
                            } elseif (!$exists) {
                                echo  json_encode( array( 'success' => 0, 'message' => 'User not found' ));
                                $result['message'] = "Request to remove member from course received, but user not found.";
                                $result['status'] = "Error";
                            }

                            break;
                        case 'add_new_member':

                            if (!$course_id || !$username || !$useremail || !$userpass) {
                                echo json_encode(array('success' => 0, 'message' => 'add_new_member method needs the the following data: username, useremail, userpass, course id'));
                                $result['message'] = "Request add member, but no Course ID or user login or email or password received.";
                                $result['status'] = "Error";
                            } else {
                                $exists = email_exists($useremail);
                                $course_name = blda_lookup_course_name_by_id($course_id);

                                if ($exists) {
                                    //User already exists. Add level to user
                                    $received_user_id = $exists;

                                    $action_result = blda_add_user_to_course($received_user_id, $course_id);

                                    if ($action_result) {
                                        echo json_encode(array('success' => 1, 'message' => $action_result, 'new_member' => 0));
                                        $result['message'] = $action_result;
                                        $result['status'] = "Success";
                                    } else {
                                        echo json_encode(array('success' => 0, 'message' => "Error encountered while adding user " . $received_user_id . " to course(s) " . blda_lookup_course_name_by_id($course_id)));
                                        $result['message'] = "Error encountered while adding user " . $received_user_id . " to course(s) " . blda_lookup_course_name_by_id($course_id);
                                        $result['status'] = "Error";
                                    }

                                    if ($blda_options['blda_option_send_confirmation_email']) {
                                        $action = "add_level";
                                        blda_send_email_confirmation($action, $useremail, $userpass, $course_name);
                                    }
                                } else {
                                    //User does not already exists. Add user
                                    $member_id = wp_create_user($username, $userpass, $useremail);

                                    $add_user_result = "Added user to WordPress";

                                    if ($blda_options['blda_option_update_user_data']) {
                                        if (strlen($user_first_name) || strlen($user_last_name)) {
                                            $result_user_data = blda_register_user_data($member_id, $user_first_name, $user_last_name);
                                        }
                                    }

                                    $action_result = blda_add_user_to_course($member_id, $course_id);

                                    if ($action_result) {
                                        $total_result = $add_user_result . ". " . $result_user_data . ". " . $action_result;

                                        echo json_encode(array('success' => 1, 'message' => $total_result, 'new_member' => 1));
                                        $result['message'] = $total_result;
                                        $result['status'] = "Success";
                                    } else {
                                        $total_result = $add_user_result . ". " . $result_user_data . ". Error encountered while adding user " . $member_id . " to course(s) " . blda_lookup_course_name_by_id($course_id);

                                        echo json_encode(array('success' => 0, 'message' => $total_result));
                                        $result['message'] = $total_result;
                                        $result['status'] = "Error";
                                    }

                                    if ($blda_options['blda_option_send_confirmation_email']) {
                                        $action = "new_user";

                                        blda_send_email_confirmation($action, $username, $userpass, $course_name);
                                    }
                                }
                            }

                            blda_log_event(serialize($_REQUEST), $result);

                            break;
                        case 'add_to_course':

                            if (!$course_id || !$username) {
                                echo json_encode(array('success' => 0, 'message' => 'add_to_course method needs the the following data: username, course id'));
                                $result['message'] = "Request add member to course, but no Course ID or user login received.";
                                $result['status'] = "Error";
                            } else {
                                $exists = username_exists($username);

                                if ($exists) {
                                    //User already exists. Add level to user
                                    $received_user_id = $exists;

                                    $action_result = blda_add_user_to_course($received_user_id, $course_id);

                                    if ($course_id === '3624') {
                                        $course_id = '3989';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '3989') {
                                        $course_id = '3624';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($course_id === '5340') {
                                        $course_id = '5341';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '5341') {
                                        $course_id = '5340';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($course_id === '5176') {
                                        $course_id = '5177';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '5177') {
                                        $course_id = '5176';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($course_id === '5342') {
                                        $course_id = '5343';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '5343') {
                                        $course_id = '5342';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($course_id === '3478') {
                                        $course_id = '3991';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '3991') {
                                        $course_id = '3478';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($course_id === '5174') {
                                        $course_id = '5175';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '5175') {
                                        $course_id = '5174';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($course_id === '2935') {
                                        $course_id = '4097';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '4097') {
                                        $course_id = '2935';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($course_id === '5223') {
                                        $course_id = '5264';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    } else if ($course_id === '5264') {
                                        $course_id = '5223';
                                        $action_result = blda_add_user_to_course($received_user_id, $course_id);
                                    }

                                    if ($action_result) {
                                        echo json_encode(array('success' => 1, 'message' => $action_result, 'new_member' => 0));
                                        $result['message'] = $action_result;
                                        $result['status'] = "Success";
                                    } else {
                                        echo json_encode(array('success' => 0, 'message' => "Error encountered while adding user " . $received_user_id . " to course(s) " . blda_lookup_course_name_by_id($course_id)));
                                        $result['message'] = "Error encountered while adding user " . $received_user_id . " to course(s) " . blda_lookup_course_name_by_id($course_id);
                                        $result['status'] = "Error";
                                    }
                                } else {
                                    echo json_encode(array('success' => 0, 'message' => 'add_to_course -> User not found'));
                                    $result['message'] = "Request add member to course -> user not found.";
                                    $result['status'] = "Error";
                                }
                            }

                            blda_log_event(serialize($_REQUEST), $result);

                            break;
                    }
                } else {
                    echo  json_encode( array( 'success' => 0, 'message' => 'Wrong method, supported methods are set_avatar, add_new_member, remove_member_from_course, get_courses, get_sessions, get_courses_v2, mark_completed, add_to_course, one_to_one_session' ));
                    $result['message'] = "Wrong method, supported methods are set_avatar, add_new_member, remove_member_from_course, get_courses, get_courses, add_to_course, one_to_one_session";
                    $result['status'] = "Error";
                }
            } else {
                echo  json_encode( array( 'success' => 0, 'message' => 'Wrong API Key' ));
                $result['message'] = "Wrong API Key";
                $result['status'] = "Error";
            }

            blda_log_event(serialize($_REQUEST), $result);

            exit;
        } else {
            $result['message'] = "Request received, but LearnDash is not active. Better LearnDash API ignored request.";
            $result['status'] = "Error";
            blda_log_event(serialize($_REQUEST), $result);
        }
    }

}
