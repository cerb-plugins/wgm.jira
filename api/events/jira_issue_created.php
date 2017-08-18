<?php
class Event_JiraIssueCreated extends AbstractEvent_JiraIssue {
	const ID = 'wgmjira.event.issue.created';
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->_event_id = self::ID;
	}
	
	static function trigger($context_id, $variables=array()) {
		$events = DevblocksPlatform::services()->event();
		return $events->trigger(
			new Model_DevblocksEvent(
				self::ID,
				array(
					'context_id' => $context_id,
					'_variables' => $variables,
				)
			)
		);
	}
};