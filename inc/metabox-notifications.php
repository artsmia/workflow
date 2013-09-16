<?php

function mia_wf_notifications_metabox_content($post){
	echo "<p>Add a comment to this revision.</p>";
	echo "<textarea name='mia_wf_message_content' id='mia_wf_message_content' style='width:100%; min-height:75px; display:block; margin-bottom:10px;'></textarea>";
	submit_button('Submit', 'primary', 'mia_wf_submit_message', false);
	global $wpdb;
	$mia_wf_notifications = $wpdb->prefix . "mia_wf_notifications";
	$comments = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $mia_wf_notifications WHERE revision_id = %d ORDER BY date_posted DESC", $post->ID
		), 'ARRAY_A'
	);
	if($comments){
		foreach($comments as $comment){
			$user_meta = get_userdata($comment['user_id']);
			$user_name = $user_meta->display_name;
		?>
			<div class='comment<?php echo $comment['type'] == 'merge_request' ? " merge_request" : ""; ?>'>
				<?php echo get_wp_user_avatar($comment['user_id'], 'small', 'left'); ?>
				<div class='comment_content'>
					<p class='date'><?php echo date('F j, Y - g:i A', strtotime($comment['date_posted'])); ?></p>
					<?php if($comment['type'] == 'merge_request'){ ?>
					<p class='name'><?php echo $user_name; ?><span> requested that the revision be published.</span></p>
					<p class='message'><?php echo stripslashes($comment['message']); ?></p>
					<?php } else { ?>
					<p class='name'><?php echo $user_name; ?><span> wrote:</span></p>
					<p class='message'><?php echo stripslashes($comment['message']); ?></p>
					<?php } ?>
				</div>
			</div>
		<?php
		}
	} else {
		echo "<p>No comments.</p>";
	}
}
