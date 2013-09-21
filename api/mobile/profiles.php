<?php
if(class_exists('Extension_MobileProfileBlock')):
class MobileProfile_JiraIssue extends Extension_MobileProfileBlock {
	const ID = 'jira.mobile.profile.block.issue';
	
	function render(DevblocksDictionaryDelegate $dict) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('dict', $dict);
		$tpl->display('devblocks:wgm.jira::mobile/profiles/jira_issue.tpl');
	}
};
endif;