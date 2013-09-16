<?php

function mia_wf_post_overview_metabox_content($post){
	echo "<p>There are " . mia_wf_count_active_revisions($post->ID) . " revisions for this post:</p>";
	$revisions = mia_wf_get_active_revisions($post->ID);
	foreach($revisions as $revision){
		echo "<p><a href='" . get_edit_post_link($revision->revision_id) . "'>" . $revision->user_name . "</a> - last modified " . $revision->modified_date . "</p>";
	}
	echo "<p><a href='" . admin_url() . "'>View Revision Overview (on the Dashboard)</a></p>";
}