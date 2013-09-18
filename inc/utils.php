<?php

// UTILS

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
 * MIA_WF_IS_ORIGINAL
 * Returns true if the current "revision" is new content, and
 * false if the current "revision" is actually a revision of
 * an existing post. Better terminology is needed.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function mia_wf_is_original($revision_id){
	global $wpdb;
	$mia_wf_relationships = $wpdb->prefix . "mia_wf_relationships";
	$parent = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM $mia_wf_relationships WHERE revision_id = %d", $revision_id
		)
	);
	if(!$parent){
		// Either no record or no parent; revision is 
		return true;
	} else {
		// There is a record and it has a parent; revision is not original content
		return false;
	}
}
