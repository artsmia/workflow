jQuery(document).ready(function(){
	jQuery('#mia_wf_open_request_merge_info').on('click', function(e){
		e.preventDefault();
		e.stopPropagation();
		jQuery('#mia_wf_request_merge_info').toggle();
	});
	jQuery('#mia_wf_discard_button').on('click', function(e){
		if(!confirm('You are about to permanently delete this revision. Although the original page will be unaffected, your changes will be lost. Are you sure you want to discard?')){
			return false;
		}
	});
	jQuery('#mia_wf_revert_button').on('click', function(e){
		if(!confirm('You are about to permanently erase your work on this revision and start over with the current state of the original post. Are you sure you want to revert?')){
			return false;
		}
	});
	jQuery('#mia_wf_request_merge_button').on('click', function(e){
		if(jQuery('textarea#mia_wf_request_merge_message').val() == ''){
			alert('Please enter a short message describing your edits. This helps track changes and allows editors to expedite your request.');
			return false;
		}
	});
});
