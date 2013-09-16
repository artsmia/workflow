<?php
/*
Plugin Name: Workflow
Description: Creates a workflow for the revision of published pages.
Version: 1.0
Author: Minneapolis Institute of Arts
*/

date_default_timezone_set('America/Chicago');

/*
 *
 * INCLUDES
 * This will be haphazard for a while.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
include(__DIR__ . "/inc/metabox-author.php");
include(__DIR__ . "/inc/metabox-other-author.php");
include(__DIR__ . "/inc/metabox-notifications.php");
include(__DIR__ . "/inc/metabox-editor.php");
include(__DIR__ . "/inc/metabox-overview.php");


/*
 *
 * ENQUEUE STYLE
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('admin_enqueue_scripts', 'mia_wf_enqueue_style');
function mia_wf_enqueue_style(){
	wp_enqueue_style('mia_wf_style', plugins_url('/css/mia_wf_style.css', __FILE__));
	wp_enqueue_script('mia_wf_js', plugins_url('/js/mia_wf_js.js', __FILE__), 'jquery');
}


/*
 *
 * MIA_WF_SETUP
 * Prepare system on plugin activation
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
 
register_activation_hook( __FILE__, 'mia_wf_setup' );
function mia_wf_setup(){
	
	// Create Relationships table
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$wpdb->query("CREATE TABLE IF NOT EXISTS $mia_wf_relationships (`id` int(11) NOT NULL AUTO_INCREMENT, `post_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `revision_id` int(11) NOT NULL, `revision_status` varchar(45) NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8");

	// Create Notifications table
	$mia_wf_notifications = $wpdb->prefix . "mia_wf_notifications";
	$wpdb->query("CREATE TABLE IF NOT EXISTS $mia_wf_notifications (`id` int(11) NOT NULL AUTO_INCREMENT, `date_posted` datetime NOT NULL, `user_id` int(11) NOT NULL, `revision_id` int(11) NOT NULL, `message` longtext NOT NULL, `type` varchar(45) DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8");

	// Add manage_workflow capability
   $editor = get_role( 'editor' );
   $editor->add_cap( 'manage_workflow' );
	$admin = get_role( 'administrator' );
	$admin->add_cap( 'manage_workflow' );
}


/*
 *
 * MIA_WF_STRIKE
 * Return system to normal following plugin deactivation
 * 
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
 
register_deactivation_hook( __FILE__, 'mia_wf_strike' );
function mia_wf_strike(){
	
	// Drop Relationships table
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$wpdb->query("DROP TABLE IF EXISTS $mia_wf_relationships");
	
	// Drop Notifications table
	$mia_wf_notifications = $wpdb->prefix . "mia_wf_notifications";
	$wpdb->query("DROP TABLE IF EXISTS $mia_wf_notifications");
	
	// Remove manage_workflow capability
	$editor = get_role( 'editor' );
	$editor->remove_cap( 'manage_workflow' );
	$admin = get_role( 'administrator' );
	$admin->remove_cap( 'manage_workflow' );
}


/*
 *
 * ENQUEUE_MIA_WF_ROUTE_USER
 * MIA_WF_ROUTE_USER
 * Redirect authors to their personal revision of a page,
 * or create the page if a revision does not yet exist
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
 
// Only enqueue on the admin side; otherwise the site asplodes. :(
add_action('admin_init', 'enqueue_mia_wf_route_user');
function enqueue_mia_wf_route_user(){
	add_action('pre_get_posts', 'mia_wf_route_user');
	function mia_wf_route_user(){
		global $pagenow;
		global $post;
		$screen = get_current_screen();
		if($pagenow == 'post.php' && $screen->id != 'attachment'){
			
			// Check for capability
			if($post->post_status != 'pending' && !current_user_can('manage_workflow')){
				
				// Check for revision
				global $wpdb;
				$post_id = $post->ID;
				$user_id = get_current_user_id();
				$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
				$revision_id = $wpdb->get_var($wpdb->prepare("SELECT revision_id FROM $mia_wf_relationships WHERE post_id = %d AND user_id = %d AND (revision_status = 'in_progress' OR revision_status = 'pending_merge')", $post_id, $user_id));
				if(!$revision_id){
					
					// Create revision
					$revision_args = array(
						'post_author' => $user_id,
						'post_content' => $post->post_content,
						'post_title' => $post->post_title,
						'post_excerpt' => $post->post_excerpt,
						'post_status' => 'pending',
						'post_name' => $post->post_name . "-rev-u" . $user_id,
						'post_parent' => $post->post_parent,
						'post_type' => $post->post_type,
					);
					$revision_id = wp_insert_post($revision_args);
					
					// Copy over postmeta
					$metas = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", $post_id
						), 'OBJECT'
					);
					foreach($metas as $meta){
						$wpdb->query(
							$wpdb->prepare(
								"INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) VALUES (%d, %s, %s)", $revision_id, $meta->meta_key, $meta->meta_value
							)
						);
					}
					
					// Copy over tags
					if(get_post_type($post_id) == 'post'){
						$tags = get_the_terms($post_id, 'post_tag');
						$tag_ids = array();
						foreach($tags as $tag){
							$tag_ids[] = intval($tag->term_id);
						}
						wp_set_object_terms($revision_id, $tag_ids, 'post_tag');
						$authors = get_the_terms($post_id, 'author');
						$author_ids = array();
						foreach($authors as $author){
							$author_ids[] = intval($author->term_id);
						}
						wp_set_object_terms($revision_id, $author_ids, 'author');
					}
					
					// Copy over event types
					if(get_post_type($post_id) == 'event'){
						$types = get_the_terms($post_id, 'ev_type');
						$type_ids = array();
						foreach($types as $type){
							$type_ids[] = intval($type->term_id);
						}
						wp_set_object_terms($revision_id, $type_ids, 'ev_type');
					}
					// Copy group connections
					if((get_post_type($post_id) == 'event') || (get_post_type($post_id) == 'exhibition')){
						$p2p_table = $wpdb->prefix . 'p2p';
						$connections = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT * FROM $p2p_table WHERE p2p_to = %d", $post_id
							), 'OBJECT'
						);
						foreach($connections as $connection){
							$wpdb->query(
								$wpdb->prepare(
									"INSERT INTO $p2p_table (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", intval($connection->p2p_from), intval($revision_id), $connection->p2p_type
								)
							);
						}
					}
					if(get_post_type($post_id) == 'group'){
						$p2p_table = $wpdb->prefix . 'p2p';
						$connections = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT * FROM $p2p_table WHERE p2p_from = %d", $post_id
							), 'OBJECT'
						);
						foreach($connections as $connection){
							$wpdb->query(
								$wpdb->prepare(
									"INSERT INTO $p2p_table (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", intval($revision_id), intval($connection->p2p_to), $connection->p2p_type
								)
							);
						}
					}	
										
					// Add record to mia_wf_relationships table
					$wpdb->query($wpdb->prepare("INSERT INTO $mia_wf_relationships (post_id, user_id, revision_id, revision_status) VALUES (%d, %d, %d, 'in_progress')", $post_id, $user_id, $revision_id));
				}
				
				// Redirect to revision
				$revision_edit_url = get_edit_post_link($revision_id, '');
				wp_redirect($revision_edit_url);
				exit;	
			}
		}
	}
}


/*
 *
 * METABOXES
 * Remove standard publish and page attributes metaboxes
 * and add custom metaboxes to manage revision workflow
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
 
add_action('add_meta_boxes', 'mia_wf_metaboxes');
function mia_wf_metaboxes(){
	global $post;
	if($post->post_status == 'pending'){
		remove_meta_box('submitdiv', $post->post_type, 'side');
		remove_meta_box('pageparentdiv', $post->post_type, 'side');
		if(current_user_can('manage_workflow')){
			add_meta_box('workflow-control-div', 'Revision Options', 'mia_wf_editor_metabox_content', $post->post_type, 'side', 'high');
			add_meta_box('workflow-notifications-div', 'Revision Comments', 'mia_wf_notifications_metabox_content', $post->post_type, 'side', 'high');
		} else {
			if(mia_wf_is_revision_author($post)){
				add_meta_box('workflow-control-div', 'Revision Options', 'mia_wf_author_metabox_content', $post->post_type, 'side', 'high');
				add_meta_box('workflow-notifications-div', 'Revision Comments', 'mia_wf_notifications_metabox_content', $post->post_type, 'side', 'high');
			} else {
				add_meta_box('workflow-control-div', 'Revision Options', 'mia_wf_guest_author_metabox_content', $post->post_type, 'side', 'high');
			}
		}
	} else if (mia_wf_count_active_revisions($post->ID) && current_user_can('manage_workflow')){
		add_meta_box('workflow-control-div', 'Revisions', 'mia_wf_post_overview_metabox_content', $post->post_type, 'side', 'high');
	}
}


/*
 *
 * MIA_WF_CUSTOM_MESSAGES
 * Create custom admin messages
 * Via http://wp-bytes.com/function/2013/02/changing-post-updated-messages/
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_filter('post_updated_messages', 'mia_wf_custom_messages');
function mia_wf_custom_messages($messages) {
	global $post, $post_ID;
	$post_type = get_post_type( $post_ID );
	$obj = get_post_type_object($post_type);
	$singular = $obj->labels->singular_name;
	$messages[$post_type] = array(
		0 => '', // Unused. Messages start at index 1.
		1 => sprintf( __($singular.' updated. <a href="%s">View '.strtolower($singular).'</a>'), esc_url( get_permalink($post_ID) ) ),
		2 => __('Custom field updated.'),
		3 => __('Custom field deleted.'),
		4 => __($singular.' updated.'),
		5 => isset($_GET['revision']) ? sprintf( __($singular.' restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		6 => sprintf( __($singular.' published. <a href="%s">View '.strtolower($singular).'</a>'), esc_url( get_permalink($post_ID) ) ),
		7 => __('Page saved.'),
		8 => sprintf( __($singular.' submitted. <a target="_blank" href="%s">Preview '.strtolower($singular).'</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
		9 => sprintf( __($singular.' scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview '.strtolower($singular).'</a>'), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
		10 => sprintf( __($singular.' draft updated. <a target="_blank" href="%s">Preview '.strtolower($singular).'</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
		11 => __('Revision saved.'),
		12 => __('Revision submitted for review.'),
		13 => __('Message saved to revision.'),
		14 => sprintf( __($singular . ' merged with revision and updated. The revision has been closed. <a href="%s">View ' . strtolower($singular) . '</a>'), esc_url( get_permalink($post_ID) ) ),
		15 => __('Revision reverted to original content.'),
		16 => __('Revision discarded.'),
	);
	return $messages;
}


/*
 *
 * MIA_WF_DISPLAY_MESSAGE
 * Display appropriate admin message after save, merge
 * request, etc.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_filter('redirect_post_location', 'mia_wf_display_message');
function mia_wf_display_message($location){
	if (isset($_POST['mia_wf_save_revision'])){
		$new_location = add_query_arg('message', '11', $location);
		return $new_location;
	} else if (isset($_POST['mia_wf_request_merge'])){
		$new_location = add_query_arg('message', '12', $location);
		return $new_location;
	} else if (isset($_POST['mia_wf_submit_message'])){
		$new_location = add_query_arg('message', '13', $location);
		return $new_location;
	} else if (isset($_POST['mia_wf_merge_posts']) && isset($_POST['mia_wf_parent_id'])){
		$new_location = add_query_arg('message', '14', $location);
		$new_location = add_query_arg('post', $_POST['mia_wf_parent_id'], $new_location);
		return $new_location;
	} else if (isset($_POST['mia_wf_revert_revision']) && isset($_POST['mia_wf_parent_id'])){
		$new_location = add_query_arg('message', '15', $location);
		$new_location = add_query_arg('post', $_POST['mia_wf_parent_id'], $new_location);
		return $new_location;
	} else if (isset($_POST['mia_wf_discard_revision'])){
		$new_location = add_query_arg(array(), get_admin_url());
		return $new_location;
	} else {
		return $location;
	}
}


/*
 *
 * MIA_WF_HANDLE_MERGE_REQUEST
 * Update tables for merge request
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action('save_post', 'mia_wf_handle_merge_request');
function mia_wf_handle_merge_request($post_id){
	if(isset($_POST['mia_wf_request_merge']) && isset($_POST['mia_wf_request_merge_message'])){
		mia_wf_set_revision_status($post_id, 'pending_merge');
		mia_wf_add_notification($post_id, 'merge_request', $_POST['mia_wf_request_merge_message']);
	}
}


/*
 *
 * MIA_WF_HANDLE_DISCARD
 * Update tables for discard
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action('save_post', 'mia_wf_handle_discard');
function mia_wf_handle_discard($post_id){
	if(isset($_POST['mia_wf_discard_revision'])){
		mia_wf_set_revision_status($post_id, 'closed');
		mia_wf_add_notification($post_id, 'discard', 'Revision discarded by author.');
	}
}


/*
 *
 * MIA_WF_HANDLE_REVERT
 * Update tables for revert
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action('save_post', 'mia_wf_handle_revert');
function mia_wf_handle_revert($post_id){
	if(isset($_POST['mia_wf_revert_revision'])){
		mia_wf_set_revision_status($post_id, 'closed');
		mia_wf_add_notification($post_id, 'revert', 'Revision reverted to original by author.');
	}
}




/*
 *
 * MIA_WF_MERGE_POSTS
 * Merge a revision into a post.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action('save_post', 'mia_wf_merge_posts');
function mia_wf_merge_posts($revision_id){
	if(isset($_POST['mia_wf_merge_posts']) && isset($_POST['mia_wf_parent_id']) && isset($_POST['mia_wf_revision_id']) && mia_wf_get_revision_status($_POST['mia_wf_revision_id']) == 'pending_merge' && !wp_is_post_revision($revision_id)){
		$parent_id = $_POST['mia_wf_parent_id'];
		global $wpdb;
		global $post;
		$post = get_post($revision_id);
		setup_postdata($post);
		
		// Temporarily remove action to prevent infinite loop
		remove_action('save_post', 'mia_wf_merge_posts');

		// Update content, title, excerpt of post
		$revision_args = array(
			'ID' => $parent_id,
			'post_content' => $post->post_content,
			'post_title' => $post->post_title,
			'post_excerpt' => $post->post_excerpt,
		);
		$parent_id = wp_update_post($revision_args);		

		// Clear out parent meta
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->postmeta WHERE post_id = %d", $parent_id
			)
		);
		
		// Copy over revision meta
		$metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", $revision_id
			), 'OBJECT'
		);
		foreach($metas as $meta){
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) VALUES (%d, %s, %s)", $parent_id, $meta->meta_key, $meta->meta_value
				)
			);
		}
		
		// Copy over tags and authors
		if(get_post_type($revision_id) == 'post'){
			$tags = get_the_terms($revision_id, 'post_tag');
			$tag_ids = array();
			foreach($tags as $tag){
				$tag_ids[] = intval($tag->term_id);
			}
			wp_set_object_terms($parent_id, $tag_ids, 'post_tag');
			$authors = get_the_terms($revision_id, 'author');
			$author_ids = array();
			foreach($authors as $author){
				$author_ids[] = intval($author->term_id);
			}
			wp_set_object_terms($parent_id, $author_ids, 'author');
		}
		
		// Copy over event types
		if(get_post_type($revision_id) == 'event'){
			$types = get_the_terms($revision_id, 'ev_type');
			$type_ids = array();
			foreach($types as $type){
				$type_ids[] = intval($type->term_id);
			}
			wp_set_object_terms($parent_id, $type_ids, 'ev_type');
		}
		
		// Copy over groups info
		if((get_post_type($revision_id) == 'event') || (get_post_type($revision_id) == 'exhibition')){
			$p2p_table = $wpdb->prefix . 'p2p';
			// Get revision connections
			$connections = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $p2p_table WHERE p2p_to = %d", $revision_id
				), 'OBJECT'
			);
			// Delete parent post connections
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $p2p_table WHERE p2p_to = %d", $parent_id
				)
			);
			// Copy revision connections to parent
			foreach($connections as $connection){
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO $p2p_table (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", intval($connection->p2p_from), intval($parent_id), $connection->p2p_type
					)
				);
			}
		}
		if(get_post_type($post_id) == 'group'){
			$p2p_table = $wpdb->prefix . 'p2p';
			// Get revision connections
			$connections = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $p2p_table WHERE p2p_from = %d", $revision_id
				), 'OBJECT'
			);
			// Delete parent group connections
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $p2p_table WHERE p2p_from = %d", $parent_id
				)
			);
			// Copy revision connections to parent
			foreach($connections as $connection){
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO $p2p_table (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", intval($parent_id), intval($connection->p2p_to), $connection->p2p_type
					)
				);
			}
		}	
		
		// Change status in Relationships table
		$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
		$mia_wf_notifications = $wpdb->prefix . "mia_wf_notifications";
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $mia_wf_relationships SET revision_status = 'closed' WHERE revision_id = %d", $revision_id
			)
		);
		
		// Change status of actual post to ... inherit?
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->posts SET post_status = 'inherit' WHERE ID = %d", $revision_id
			)
		);
		
		// Reinstate save action
		add_action('save_post', 'mia_wf_merge_posts');
	}
}


/*
 *
 * MIA_WF_BLOCK_PUBLISH
 * Let Wordpress work all its publishing magic, but then
 * switch the post status back to pending in the end.
 * 
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
 
add_action('pending_to_publish', 'mia_wf_block_publish');
function mia_wf_block_publish($post){
	$post->post_status = 'pending';
	wp_update_post($post);
}


/*
 *
 * MIA_WF_GET_REVISION_STATUS (UTILITY)
 * Returns current revision status slug
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
 
function mia_wf_get_revision_status($revision_id){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$status = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT revision_status FROM $mia_wf_relationships WHERE revision_id = %d", $revision_id
		)
	);
	return $status;
}

/*
 *
 * MIA_WF_SET_REVISION_STATUS (UTILITY)
 * Update status in Relationships table
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function mia_wf_set_revision_status($revision_id, $status){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE $mia_wf_relationships SET revision_status = %s WHERE revision_id = %d", $status, $revision_id
		)
	);
}


/*
 *
 * MIA_WF_ADD_NOTIFICATION (UTILITY)
 * Add an entry to the Notifications table.
 * 
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function mia_wf_add_notification($revision_id, $type, $message){
	$user_id = get_current_user_id();
	global $wpdb;
	$mia_wf_notifications = $wpdb->prefix . "mia_wf_notifications";
	$date = date('Y-m-d H:i:s');
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO $mia_wf_notifications (user_id, revision_id, date_posted, type, message) VALUES (%d, %d, %s, %s, %s)", $user_id, $revision_id, $date, $type, $message
		)
	);
}


/*
 *
 * MIA_WF_SAVE_MESSAGE
 * Saves a message to the revision.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action('save_post', 'mia_wf_save_message');
function mia_wf_save_message($post_id){
	if(isset($_POST['mia_wf_submit_message']) && isset($_POST['mia_wf_message_content'])){
		mia_wf_add_notification($post_id, 'general', $_POST['mia_wf_message_content']);
	}
}


/*
 *
 * MIA_WF_GET_ORIGINAL_POST (UTILITY)
 * Given the post ID of a revision, retrieves the post object
 * of the post it's based on.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function mia_wf_get_original_post($revision_id){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$orig_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM $mia_wf_relationships WHERE revision_id = %d", $revision_id
		)
	);
	$orig_post = get_post($orig_id);
	return $orig_post;
}

/*
 *
 * MIA_WF_GET_ACTIVE_SIBLING_REVISIONS
 * Given the ID of a revision, retrieves a numerically
 * indexed array of other ACTIVE revisions (each an object 
 * with properties revision_id, user_id, revision_status, 
 * user_name, created_date, and modified_date).
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function mia_wf_get_active_sibling_revisions($revision_id){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$siblings = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT revision_id, user_id, revision_status FROM $mia_wf_relationships WHERE revision_id != %d AND (revision_status = 'in_progress' OR revision_status = 'pending_merge') AND post_id IN (SELECT * FROM (SELECT post_id FROM $mia_wf_relationships WHERE revision_id = %d) Alias)", $revision_id, $revision_id					
		), 'OBJECT'
	);
	foreach($siblings as $key=>$sibling){
		global $post;
		$save_post = $post;
		$post = get_post($sibling->revision_id);
		setup_postdata($post);
		$siblings[$key]->user_name = get_the_author();
		$siblings[$key]->created_date = get_the_date('n/j/Y');
		$siblings[$key]->modified_date = get_the_modified_date('n/j/Y');
		$post = $save_post;
		setup_postdata($post);
	}
	return $siblings;
}


/*
 *
 * MIA_WF_COUNT_ACTIVE_REVISIONS
 * Given the post ID of a post, returns the number of
 * revisions existing for it in the relationships table.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function mia_wf_count_active_revisions($post_id){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$revisions = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $mia_wf_relationships WHERE post_id = %d AND (revision_status = 'in_progress' OR revision_status = 'pending_merge')", $post_id
		)
	);
	return $revisions;
}


/*
 *
 * MIA_WF_GET_ACTIVE_REVISIONS
 * Given the ID of a (parent) post, retrieves a numerically
 * indexed array of its active revisions (each an object with
 * properties revision_id, user_id, revision_status, user_name,
 * created_date, and modified_date).
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function mia_wf_get_active_revisions($post_id){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$revisions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT revision_id, user_id, revision_status FROM $mia_wf_relationships WHERE post_id = %d AND (revision_status = 'in_progress' OR revision_status = 'pending_merge')", $post_id
		), 'OBJECT'
	);
	foreach($revisions as $key=>$revision){
		global $post;
		$save_post = $post;
		$post = get_post($revision->revision_id);
		setup_postdata($post);
		$revisions[$key]->user_name = get_the_author();
		$revisions[$key]->created_date = get_the_date('n/j/Y');
		$revisions[$key]->modified_date = get_the_modified_date('n/j/Y');
		$post = $save_post;
		setup_postdata($post);
	}
	return $revisions;
}


/*
 *
 * DASHBOARD WIDGETS
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action( 'wp_dashboard_setup', 'mia_wf_dashboard_widget' );
function mia_wf_dashboard_widget() {
	if(current_user_can('manage_workflow')){
		wp_add_dashboard_widget(
			'mia_wf_editor_dashboard_widget',
			'Revisions Ready for Merge',
			'mia_wf_editor_dashboard_widget_content'
		);	
	} else {
		wp_add_dashboard_widget(
			'mia_wf_author_dashboard_widget',
			'My Revisions',
			'mia_wf_author_dashboard_widget_content'
		);
	}
}
function mia_wf_editor_dashboard_widget_content() {
	$ready_revs = mia_wf_get_ready_revisions();
	?>
   <table style="width:100%;">
   	<tr>
      	<th>Title</th>
         <th>Author</th>
         <th>Changes</th>
         <th>View Revision</th>
      </tr>
   <?php
	foreach($ready_revs as $revision){
		echo "<tr>";
		echo "<td>" . $revision->title . "</td>";
		echo "<td>" . $revision->user_name . "</td>";
		echo "<td>" . $revision->message . "</td>";
		echo "<td><a href='" . get_edit_post_link($revision->revision_id) . "'>View</a></td>";
		echo "</tr>";
	}
	echo "</table>";
} 
function mia_wf_author_dashboard_widget_content() {
	$my_revs = mia_wf_get_revs_by_author(get_current_user_id());
	?>
   <table style="width:100%;">
   	<tr>
      	<th>Status</th>
      	<th>Title</th>
         <th>Last Modified</th>
         <th>Edit Revision</th>
      </tr>
   <?php
	foreach($my_revs as $revision){
		switch($revision->revision_status){
			case 'in_progress':
				$status = 'In Progress';
				break;
			case 'pending_merge':
				$status = 'Merge Requested';
				break;
			default:
				$status = 'Unknown';
		}
		echo "<tr>";
		echo "<td>" . $status . "</td>";
		echo "<td>" . $revision->title . "</td>";
		echo "<td>" . $revision->modified_date . "</td>";
		echo "<td><a href='" . get_edit_post_link($revision->revision_id) . "'>Edit</a></td>";
		echo "</tr>";
	}
	echo "</table>";
}


/*
 *
 * MIA_WF_GET_READY_REVISIONS
 * Fetch all ready revisions for the editor dashboard widget.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function mia_wf_get_ready_revisions(){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$mia_wf_notifications = $wpdb->prefix . "mia_wf_notifications";
	$ready_revs = $wpdb->get_results(
			"SELECT * FROM ( SELECT b.id, a.post_id, a.user_id, a.revision_id, b.message, b.type FROM $mia_wf_relationships AS a JOIN $mia_wf_notifications AS b ON a.revision_id = b.revision_id WHERE a.revision_status = 'pending_merge' AND b.type = 'merge_request' ORDER BY b.id DESC) AS c GROUP BY type", 'OBJECT'
	);
	foreach($ready_revs as $key=>$revision){
		global $post;
		$post = get_post($revision->revision_id);
		$save_post = $post;
		setup_postdata($post);
		$ready_revs[$key]->user_name = get_the_author();
		$ready_revs[$key]->created_date = get_the_date('n/j/Y');
		$ready_revs[$key]->modified_date = get_the_modified_date('n/j/Y');
		$ready_revs[$key]->title = get_the_title();
		$post = $save_post;
		setup_postdata($post);
	}
	return $ready_revs;
}


/*
 *
 * MIA_WF_GET_REVS_BY_AUTHOR
 * Fetch all revisions for a particular author
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function mia_wf_get_revs_by_author($user_id){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$my_revs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, revision_id, revision_status FROM $mia_wf_relationships WHERE user_id = %d AND (revision_status = 'pending_merge' OR revision_status = 'in_progress')", $user_id
		), 'OBJECT'
	);
	foreach($my_revs as $key=>$revision){
		global $post;
		$post = get_post($revision->revision_id);
		$save_post = $post;
		setup_postdata($post);
		$my_revs[$key]->created_date = get_the_date('n/j/Y');
		$my_revs[$key]->modified_date = get_the_modified_date('n/j/Y');
		$my_revs[$key]->title = get_the_title();
		$post = $save_post;
		setup_postdata($post);
	}
	return $my_revs;
}

/*
 *
 * MIA_WF_IS_REVISION_AUTHOR
 * Returns true if the current user is the revision author,
 * otherwise returns false.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function mia_wf_is_revision_author($post){
	$user_id = get_current_user_id();
	$author_id = $post->post_author;
	return $user_id == $author_id;
}

// DON'T SHOW PENDING IN LIST

add_filter( 'posts_where' , 'mia_wf_posts_where' );
function mia_wf_posts_where( $where ) {
	global $pagenow;
	if(is_admin() && $pagenow == 'edit.php') {
		$where .= " AND post_status != 'pending' ";
	}
	return $where;
}