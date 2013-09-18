<?php

function mia_wf_author_metabox_content($post){
	
	$parent = mia_wf_get_original_post($post->ID);
	$post_type = get_post_type_object($parent->post_type);
	$post_type_label = strtolower($post_type->labels->singular_name);
	echo "<div class='mia_wf_meta_div'>";
		echo "<h2>This is your personal revision of the $post_type_label <strong>&ldquo;" . $parent->post_title . ".&rdquo;</strong></h2>";
	echo "</div>";
	echo "<div class='mia_wf_meta_div'>";
		echo "<p class='mia_wf_label'>Status</p>";
		switch(mia_wf_get_revision_status($post->ID)){
			case 'in_progress':
				echo "<h4>In Progress.</h4>";
				break;
			case 'pending_merge':
				echo "<h4>Pending Review.</h4>";
				break;
			default:
				echo "<h4>Status Unknown.</h3>";
				echo "<p>Something's wrong with Workflow. Could you let <a href='mailto:tborger@artsmia.org'>Tom Borger</a> know?";
		}
	echo "</div>";
	echo "<div class='mia_wf_meta_div'>";
		echo "<p class='mia_wf_label'>Actions</p>";
		submit_button('Save Changes', 'large primary', 'mia_wf_save_revision', false);
		if(mia_wf_get_revision_status($post->ID) == 'in_progress'){
			echo "<p id='mia_wf_open_request_merge_info' class='button button-large'>Ready to Publish&nbsp;...</p>";
			echo "<div id='mia_wf_request_merge_info'>";
				echo "<p>Enter a short log message describing your changes.</p>";
				echo "<textarea name='mia_wf_request_merge_message' id='mia_wf_request_merge_message' style='width:100%; min-height:75px; display:block; margin-bottom:10px;'></textarea>";
				submit_button('Submit for Review', 'large primary', 'mia_wf_request_merge', false, array('id'=>'mia_wf_request_merge_button'));
			echo "</div>";
		}
	echo "</div>";
	echo "<div class='mia_wf_meta_div destructive'>";	
		echo "<p class='mia_wf_label'>Destructive Actions (use with care)</p>";
		submit_button('Revert to Original', 'large secondary', 'mia_wf_revert_revision', false, array('id'=>'mia_wf_revert_button'));
		submit_button('Discard', 'large secondary', 'mia_wf_discard_revision', false, array('id'=>'mia_wf_discard_button'));
		if($siblings = mia_wf_get_active_sibling_revisions($post->ID)){
			echo "<p>View other users' revisions of this page:</p>";
			foreach($siblings as $sibling){
				echo "<p><a href='" . get_edit_post_link($sibling->revision_id) . "'>" . $sibling->user_name . "</a> - last modified " . $sibling->modified_date . "</p>";
			}
		}
	echo "</div>";
	echo "<input type='hidden' name='mia_wf_revision_id' value='" . $post->ID . "' />";
	echo "<input type='hidden' name='mia_wf_parent_id' value='" . $parent->ID . "' />";
}