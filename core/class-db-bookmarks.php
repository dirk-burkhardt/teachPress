<?php
/**
 * This file contains the database access class for publication bookmarks
 * @package teachpress
 * @subpackage core
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 */

/**
 * Contains functions for getting, adding and deleting of bookmarks
 * @package teachpress
 * @subpackage database
 * @since 5.0.0
 */
class tp_bookmarks {
    
    /**
     * Returns an arrayor object of bookmarks of an user
     * 
     * Possible values for the array $args:
     *      user (INT)               The user ID
     *      output_type (STRING)     OBJECT, ARRAY_N or ARRAY_A, default is OBJECT
     *
     * @since 5.0.0
     * @param array $args
     * @return mixed
     */
    public static function get_bookmarks( $args = array() ) {
        $defaults = array(
            'user' => '',
            'output_type' => OBJECT
        ); 
        $args = wp_parse_args( $args, $defaults );
        extract( $args, EXTR_SKIP );

        global $wpdb;
        $user = intval($user);

        $sql = "SELECT `bookmark_id`, `pub_id` FROM " . TEACHPRESS_USER . " WHERE `user` = '$user'";
        return $wpdb->get_results($sql, $output_type);
    }
    
    /** 
     * Adds a new bookmark for a user
     * @param int $pub_id   The publication ID
     * @param int $user     The user ID
     * @return int          The id of the created element
     * @since 5.0.0
    */
   public static function add_bookmark($pub_id, $user) {
        global $wpdb;
        $wpdb->insert(TEACHPRESS_USER, array('pub_id' => $pub_id, 'user' => $user), array('%d', '%d'));
        return $wpdb->insert_id;
    }
    
    /** 
     * Delete a bookmark 
     * @param int $del_id   IDs of the publications
     * @param int $user     user ID
     * @since 5.0.0
    */
    public static function delete_bookmark($del_id) {
        global $wpdb;
        $wpdb->query( "DELETE FROM " . TEACHPRESS_USER . " WHERE `bookmark_id` = '" . intval($del_id) . "'" );
    }
    
    /**
     * Checks if an user has bookmarked a publication. Returns true the bookmark exists.
     * @param int $pub_id       The publication ID
     * @param int $user_id      The user ID
     * @return boolean
     * @since 5.0.0
     */
    public static function bookmark_exists($pub_id, $user_id) {
        global $wpdb;
        $test = $wpdb->query("SELECT `pub_id` FROM " . TEACHPRESS_USER . " WHERE `pub_id`='" . intval($pub_id) . "' AND `user` = '" . intval($user_id) . "'");
        if ($test != 0) {
            return true;
        }
        return false;
    }
    
}