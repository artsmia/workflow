<?php

function mia_wf_editor_metabox_content($post){
	$post_id = $post->ID;
	$parent = mia_wf_get_original_post($post_id);
	global $post;
	setup_postdata($post);
	$author = get_the_author();
	wp_reset_postdata();
	echo "<p>This is " . $author . "'s revision of <strong>" . $parent->post_title . ".</strong></p>";
	echo "<p><a href='" . get_edit_post_link($parent->ID) . "'>Edit the original.</a></p>";
	if(mia_wf_get_revision_status($post_id) == 'pending_merge'){
		echo "<p>The author has marked this revision as ready to merge.</p>";
		echo "<input type='hidden' name='mia_wf_revision_id' value='" . $post_id . "' />";
		echo "<input type='hidden' name='mia_wf_parent_id' value='" . $parent->ID . "' />";
		submit_button('Merge Now', 'primary', 'mia_wf_merge_posts', false);
	}
	if($siblings = mia_wf_get_active_sibling_revisions($post_id)){
		echo "<p>View other users' revisions of the same page:</p>";
		foreach($siblings as $sibling){
			echo "<p><a href='" . get_edit_post_link($sibling->revision_id) . "'>" . $sibling->user_name . "</a> - last modified " . $sibling->modified_date . "</p>";
		}
	}
	echo "<p><a href='" . admin_url() . "'>View Revision Overview (on the Dashboard)</a></p>";
}
