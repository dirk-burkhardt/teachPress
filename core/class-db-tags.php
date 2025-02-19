<?php
/**
 * This file contains the database access class for publication tags
 * @package teachpress
 * @subpackage core
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 */

/**
 * Database access class for tags
 * @package teachpress
 * @subpackage database
 * @since 5.0.0
 */
class tp_tags {
    
   /**
    * Returns an array of all used tags based on the publication tag relation
    * 
    * Note: If you only need a list of used tags, set group_by to true.
    * In this case you should ignore the columns con_id and pub_id from return
    * 
    * Possible values for array $args:
    *       pub_id (STRING)          Publication IDs (separated by comma)
    *       user (STRING)            User IDs (separated by comma)
    *       exclude (STRING)         Tag IDs you want to exclude from result (separated by comma)
    *       order (STRING)           ASC or DESC; default is ASC
    *       limit (STRING)           The SQL limit, example: 0,30
    *       search (STRING)          A normal search string
    *       group by (BOOLEAN)       Boolean flag for the group by clause. Default is: false
    *       count (BOOLEAN)          Set it to true if you only need an number of tags which will be returned by your selection. Default is: false
    *       output type (STRING)     OBJECT, ARRAY_A, ARRAY_N, default is OBJECT
    * 
    * @param array $args
    * @return array|object
    * @since 5.0.0
    */
   public static function get_tags( $args = array() ) {
       $defaults = array(
           'pub_id' => '',
           'user' => '',
           'exclude' => '',
           'order' => 'ASC',
           'limit' => '',
           'search' => '',
           'count' => false,
           'group_by' => false, 
           'output_type' => OBJECT
       ); 
       $args = wp_parse_args( $args, $defaults );
       extract( $args, EXTR_SKIP );

       global $wpdb;
       $limit = esc_sql($limit);
       $order = esc_sql($order);
       $user = tp_db_helpers::generate_where_clause($user, "u.user", "OR", "=");
       $pub_id = tp_db_helpers::generate_where_clause($pub_id, "r.pub_id", "OR", "=");
       $exclude = tp_db_helpers::generate_where_clause($exclude, "r.tag_id", "AND", "!=");
       $output_type = esc_sql($output_type);
       $search = esc_sql( htmlspecialchars( stripslashes($search) ) );

       // Define basics
       $select = "SELECT DISTINCT t.name, r.tag_id, r.pub_id, r.con_id FROM " . TEACHPRESS_RELATION . " r INNER JOIN " . TEACHPRESS_TAGS . " t ON t.tag_id = r.tag_id";
       $join = '';
       $where = '';

       // define global search
       if ( $search != '' ) {
           $search = "t.name like '%$search%'";
       }

       // if the user needs only the number of rows
       if ( $count === true ) {
           $select = "SELECT COUNT(t.`tag_id`) AS `count` FROM " . TEACHPRESS_TAGS . " t";
       }

       // Additional tables
       if ( $user != '' ) {
           $join .= " INNER JOIN " . TEACHPRESS_USER . " u ON u.pub_id = r.pub_id ";
       }

       // WHERE clause
       if ( $pub_id != '') {
           $where = ( $where != '' ) ? $where . " AND ( $pub_id ) " : " ( $pub_id ) ";
       }
       if ( $user != '' ) {
           $where = ( $where != '' ) ? $where . " AND ( $user ) " : " ( $user ) ";
       }
       if ( $search != '') {
           $where = $where != '' ? $where . " AND ( $search ) " : " ( $search ) " ;
       }
       if ( $exclude != '' ) {
           $where = ( $where != '' ) ? $where . " AND ( $exclude ) " : " ( $exclude ) ";
       }
       if ( $where != '' ) {
           $where = " WHERE $where";
       }

       // LIMIT clause
       if ( $limit != '' ) {
           $limit = "LIMIT $limit";
       }

       // GROUP BY clause
       $group_by = ( $group_by === true ) ? " GROUP BY t.name" : '';

       // End
       $sql = $select . $join . $where . $group_by . " ORDER BY t.name $order $limit";
       // echo get_tp_message($sql, 'orange');
       $sql = ( $count == false ) ? $wpdb->get_results($sql, $output_type): $wpdb->get_var($sql);
       return $sql;
   }
   
   /**
    * Adds a new tag
    * @param string $name          the new tag
    * @return int                  the id of the created tag
    * @since 5.0.0
    */
   public static function add_tag($name) {
       global $wpdb;
       
       // prevent possible double escapes
       $name = stripslashes($name);
       
       $wpdb->insert(TEACHPRESS_TAGS, array('name' => $name), array('%s'));
       return $wpdb->insert_id;
   }
    
   /** 
    * Edit a tag. Returns false if errors, or the number of rows affected if successful.
    * @param int $tag_id        The tag ID
    * @param string $name       the tag name
    * @return int|false
    * @since 5.0.0
   */
   public static function edit_tag($tag_id, $name) {
       global $wpdb;
       
       // prevent possible double escapes
       $name = stripslashes($name);
       
       return $wpdb->update( TEACHPRESS_TAGS, array( 'name' => $name ), array( 'tag_id' => $tag_id ), array( '%s' ), array( '%d' ) );
   }
   
   /**
    * Adds a relation between a tag and a publication
    * @param int $pub_id    The ID of the publication
    * @param int $tag_id    The ID of the tag
    * @return int
    * @since 5.0.0
    */
   public static function add_tag_relation($pub_id, $tag_id) {
       global $wpdb;
       $wpdb->insert(TEACHPRESS_RELATION, array('pub_id' => $pub_id, 'tag_id' => $tag_id), array('%d', '%d'));
       return $wpdb->insert_id;
   }
   
   /**
    * Changes tag relations for more than one publication
    * @param array $publications       Array of publication IDs
    * @param string $new_tags          New tags separated by comma
    * @param array $delete             Array of tag IDs whose relations with publications (given in the first parameter) should be deleted
    * @since 5.0.0
    */
   public static function change_tag_relations ($publications, $new_tags, $delete) {
       global $wpdb;
       $array = explode(",",$new_tags);
       $max = count( $publications );
       $max_delete = count ( $delete );

       for( $i = 0; $i < $max; $i++ ) {
           $publication = intval($publications[$i]);
           // Delete tags
           for ( $j = 0; $j < $max_delete; $j++ ) {
               $delete[$j] = intval($delete[$j]);
               $wpdb->query( "DELETE FROM " . TEACHPRESS_RELATION . " WHERE `pub_id` = '$publication' AND `tag_id` = '$delete[$j]'" );
           }

           // Add tags
           foreach( $array as $element ) {
                $element = esc_sql( htmlspecialchars( trim( stripslashes($element ) ) ) );
                if ($element === '') {
                   continue;
                }
                $check = $wpdb->get_var("SELECT `tag_id` FROM " . TEACHPRESS_TAGS . " WHERE `name` = '$element'");
                // if tag not exist
                if ( $check === NULL ){
                    $check = tp_tags::add_tag($element);
                }
                // add releation between publication and tag
                $test = $wpdb->query("SELECT `pub_id` FROM " . TEACHPRESS_RELATION . " WHERE `pub_id` = '$publication' AND `tag_id` = '$check'");
                if ($test === 0) {
                    tp_tags::add_tag_relation($publications[$i], $check);
                }
         	
           }  
       } 
   }
   
   /** 
    * Deletes tags
    * @param array $checkbox       An array with tag IDs
    * @since 5.0.0
   */
   public static function delete_tags($checkbox) {
       global $wpdb;
       for( $i = 0; $i < count( $checkbox ); $i++ ) {
           $checkbox[$i] = intval($checkbox[$i]);
           $wpdb->query( "DELETE FROM " . TEACHPRESS_RELATION . " WHERE `tag_id` = $checkbox[$i]" );
           $wpdb->query( "DELETE FROM " . TEACHPRESS_TAGS . " WHERE `tag_id` = $checkbox[$i]" );
       }
   }
   
   /**
    * Deletes relations between tags and publications
    * @param array $delbox
    * @since 5.0.0
    */
   public static function delete_tag_relation($delbox) {
       global $wpdb;
       for ( $i = 0; $i < count($delbox); $i++ ) {
           $delbox[$i] = intval($delbox[$i]);
           $wpdb->query( "DELETE FROM " . TEACHPRESS_RELATION .  " WHERE `con_id` = $delbox[$i]" );
       }
   }
    
    /**
     * Returns an array|object with the name, tag_id and occurence of all_tags
     * @param string $search            normal search string
     * @param string $limit             SQL limit like 0,50
     * @param string $output_type       OBJECT, ARRAY_N or ARRAY_A, default is ARRAY_A
     * @return array|object
     * @since 5.0.0
     */
    public static function count_tags ( $search = '', $limit = '', $output_type = ARRAY_A ) {
        global $wpdb;
        $search = esc_sql( htmlspecialchars( stripslashes($search) ) );
        $limit = esc_sql($limit);
        
        // define global search
        if ( $search != '' ) {
            $search = "WHERE t.`name` like '%$search%'";
        }
        
        // LIMIT clause
        if ( $limit != '' ) {
            $limit = "LIMIT $limit";
        }
        
        return $wpdb->get_results("SELECT DISTINCT t.name, t.tag_id, count(r.tag_id) AS count FROM " . TEACHPRESS_TAGS . " t LEFT JOIN " . TEACHPRESS_RELATION . " r ON t.tag_id = r.tag_id $search GROUP BY t.name ORDER BY t.name ASC $limit", $output_type);
    }
    
    /**
     * Returns a special array for creating tag clouds
     * 
     * Possible values for array $args:
     *      user (STRING)            User IDs (separated by comma)
     *      exclude (STRING)         Tag IDs you want to exclude from result (separated by comma)
     *      type (STRING)            Publication types (separated by comma)
     *      number_tags (Int)        The number of tags       
     *      output type (STRING)     OBJECT, ARRAY_A, ARRAY_N, default is OBJECT
     * 
     * 
     * The returned array $result has the following array_keys:
     *      'tags'  => it's an array or object with tags, including following keys: tagPeak, name, tag_id
     *      'info'  => it's an object which includes information about the frequency of tags, including following keys: max, min
     * 
     * @param array $args
     * @return array|object
     * @since 5.0.0
    */
    public static function get_tag_cloud ( $args = array() ) {
       $defaults = array(
           'user' => '',
           'type' => '',
           'number_tags' => '',
           'exclude' => '',
           'output_type' => OBJECT
       ); 
       $args = wp_parse_args( $args, $defaults );
       extract( $args, EXTR_SKIP );

       global $wpdb;

       $where = '';
       $number_tags = intval($number_tags);
       $output_type = esc_sql($output_type);
       $type = tp_db_helpers::generate_where_clause($type, "p.type", "OR", "=");
       $user = tp_db_helpers::generate_where_clause($user, "u.user", "OR", "=");
       $exclude = tp_db_helpers::generate_where_clause($exclude, "r.tag_id", "AND", "!=");
       $join1 = "LEFT JOIN " . TEACHPRESS_TAGS . " t ON r.tag_id = t.tag_id";
       $join2 = "INNER JOIN " . TEACHPRESS_PUB . " p ON p.pub_id = r.pub_id";
       $join3 = "INNER JOIN " . TEACHPRESS_USER . " u ON u.pub_id = p.pub_id";

       if ( $user == '' && $type == '' ) {
           $join1 = '';
           $join2 = '';
           $join3 = '';

       }
       if ( $user == '' && $type != '' ) {
           $join3 = '';
       }

       // WHERE clause
       if ( $type != '') {
           $where = ( $where != '' ) ? $where . " AND ( $type ) " : " ( $type ) ";
       }
       if ( $user != '') {
           $where = ( $where != '' ) ? $where . " AND ( $user ) " : " ( $user ) ";
       }
       if ( $exclude != '' ) {
           $where = ( $where != '' ) ? $where . " AND ( $exclude ) " : " ( $exclude ) ";
       }
       if ( $where != '' ) {
           $where = " WHERE $where";
       }

       $sql = "SELECT anzahlTags FROM ( 
                   SELECT COUNT(*) AS anzahlTags 
                   FROM " . TEACHPRESS_RELATION . " r
                   $join1 $join2 $join3 $where
                   GROUP BY r.tag_id 
                   ORDER BY anzahlTags DESC ) as temp1 
               GROUP BY anzahlTags 
               ORDER BY anzahlTags DESC";
       $cloud_info = $wpdb->get_row("SELECT MAX(anzahlTags) AS max, min(anzahlTags) AS min FROM ( $sql ) AS temp", OBJECT);
       $cloud_info->min = $cloud_info->min == '' ? 0 : $cloud_info->min; // Fix if there are no tags
       $sql = "SELECT tagPeak, name, tag_id FROM ( 
                 SELECT COUNT(r.tag_id) as tagPeak, t.name AS name, t.tag_id as tag_id 
                 FROM " . TEACHPRESS_RELATION . " r 
                 LEFT JOIN " . TEACHPRESS_TAGS . " t ON r.tag_id = t.tag_id 
                 INNER JOIN " . TEACHPRESS_PUB . " p ON p.pub_id = r.pub_id 
                 $join3 $where
                 GROUP BY r.tag_id ORDER BY tagPeak DESC 
                 LIMIT $number_tags ) AS temp 
               WHERE tagPeak>=".$cloud_info->min." 
               ORDER BY name";
       $result["tags"] = $wpdb->get_results($sql, $output_type);
       $result["info"] = $cloud_info;
       return $result;
    }
}

