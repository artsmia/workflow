<?php

function mia_wf_other_author_metabox_content($post){
	$post_id = $post->ID;
	$parent = mia_wf_get_original_post($post_id);
	global $post;
	setup_postdata($post);
	$author = get_the_author();
	wp_reset_postdata();
	echo "<p>This is " . $author . "'s revision of <strong>" . $parent->post_title . ".</strong></p>";
	if($siblings = mia_wf_get_active_sibling_revisions($post_id)){
		echo "<p>View other users' revisions of the same page:</p>";
		foreach($siblings as $sibling){
			echo "<p><a href='" . get_edit_post_link($sibling->revision_id) . "'>" . $sibling->user_name . "</a> - last modified " . $sibling->modified_date . "</p>";
		}
	}
}
