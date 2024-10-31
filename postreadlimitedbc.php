<?php
/**
 * Plugin Name: Post Read Limited by Specified Category
 * Description: Limit posts that users read by specify a category and a number!
 * Version: 1.2.0
 * Author: Johnlei
 * Author URI: http://demo.dada123.cn
 */
! defined( 'ABSPATH' ) and exit;

/**
 *add user meta post_read_count
 */
function prlsc_registration_save( $user_id ) {
    update_user_meta($user_id, 'post_read_count', 0);
}
add_action( 'user_register', 'prlsc_registration_save', 10, 1 );

/**
 * Adds post read count column to the user display dashboard.
 *
 * @param $columns The array of columns that are displayed on the user dashboard
 * @return The updated array of columns now including post read count.
 */
function prlsc_add_user_read_post_count_column( $columns ) {
    $columns['post_read_count'] = _e( 'Readed', 'prlsc' );
    return $columns;
}
add_filter( 'manage_users_columns', 'prlsc_add_user_read_post_count_column' );

/**
 * Populates the count column with the specified user's count.
 *
 * @param $value An empty string
 * @param $column_name The name of the column to populate
 * @param $user_id The ID of the user for which we're working with
 * @return The count associated with the user
 */
function prlsc_show_user_read_post_count_data( $value, $column_name, $user_id ) {
    if( 'post_read_count' == $column_name ) {
        return get_user_meta( $user_id, 'post_read_count', true );
    }
}
add_action( 'manage_users_custom_column', 'prlsc_show_user_read_post_count_data', 10, 3 );

/*
 *Admin panel add settings options
 */
function prlsc_add_admin_options() {
    add_options_page('Post read limit Options', 'PostReadLimit', 'manage_options', 'functions','prlsc_admin_options');
}
add_action('admin_menu', 'prlsc_add_admin_options');
/*
 *Admin panel settings options html form
 */
function prlsc_admin_options() {
?>
    <div class="wrap">
        <h2><?php _e('Post Read Limited by Category Options','prc');?></h2>
        <form method="post" action="options.php">
            <?php wp_nonce_field('update-options') ?>
            <p> <?php _e('Category Limit','prc');?>
                <?php $categories= get_categories(); 
                echo "<select name='prc_cat'>";
                foreach ($categories as $cat) { 
                    $selected = ($cat->cat_name == get_option('prc_cat')) ? 'selected="selected"' : '';
                    echo "<option value='$cat->cat_name' $selected>$cat->name</option>";
                } 
                echo "</select>"; ?>
            </p>
            <p><?php _e('Number Limit&nbsp;','prc');?>
                <input type="text" name="prc_limited" size="5" value="<?php echo get_option('prc_limited'); ?>" />
            </p>
            <p><?php
                   _e('Apply On Roles','prc');
                   $roles  = get_option('prc_role');
               ?>
                <input type="checkbox" name="prc_role[]" value="guest" <?php checked( in_array('guest', $roles) ); ?> />Guest&nbsp;&nbsp;&nbsp;
                <input type="checkbox" name="prc_role[]" value="subscriber" <?php checked( in_array('subscriber', $roles) ); ?> />Subscriber&nbsp;&nbsp;&nbsp;
            </p>
            <p><input type="submit" name="Submit" value="Save" /></p>
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="prc_cat, prc_limited, prc_role" />
        </form>
    </div>
<?php
}
/*
 *Record user reading post number
 */
function prlsc_post_readcount($user_id) {
    //Set the name of the user meta key.
    $count_key = 'post_read_count';
    //Returns values of the user meta value with the specified key from the specified user.
    $count = get_user_meta($user_id, $count_key, true);
    //If the the user meta value is empty. 
    if($count == ''){
        $count = 0; // set the counter to zero.
        //Delete all user meta key with the specified key from the specified user. 
        delete_user_meta($user_id, $count_key);
        //Add a user meta key and value to the specified user.
        add_user_meta($user_id, $count_key, '1');
    //If the the user meta key is NOT empty.
    }else{
        $count++; //increment the counter by 1.
        //Update the value of an existing meta key and value for the specified user.
        update_user_meta($user_id, $count_key, $count);
    }
}
/*
 *Limt post reading number main routing
 */
function prlsc_auth_post_content($content) {
    $limited_cat = get_option('prc_cat');
    $limited_count = get_option('prc_limited');
    $limited_role = get_option('prc_role');
    $readed = get_user_meta(get_current_user_id(), 'post_read_count', true);
    $categories_list = get_the_category_list( ', ' );
    $role = restrictly_get_current_user_role();
    if(!$role) $role = 'guest';
    $catch = FALSE;
    if (strpos($categories_list, $limited_cat) !== false) {
        $catch = TRUE;
    }
    $msg = _c('You do not have permission to read this post or have exceeded the reading limit.');
    if($role=='guest' && $catch && in_array($role, $limited_role)){ 
        if (isset($_SESSION['prlsc_readcount'])) {
            if ($_SESSION['prlsc_readcount'] < $limited_count){
                $_SESSION['prlsc_readcount']++;
            }else{
                return $msg;
            }
        }else{
            $_SESSION['prlsc_readcount'] = 1 ;
        }
    }elseif ($catch) {
        if (in_array($role, $limited_role)) {
            if ($readed >= $limited_count) {
                return $msg;
            }else{
                prlsc_post_readcount(get_current_user_id());
            }
        }
    }
    return $content;
}
add_action('the_content', 'prlsc_auth_post_content');

/*
 *Get current user role
 */
function restrictly_get_current_user_role() {
    if( is_user_logged_in() ) {
        $user = wp_get_current_user();
        $role = ( array ) $user->roles;
        return $role[0];
    } else {
        return FALSE;
    }
}
