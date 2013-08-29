<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2013, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

abstract class AbstractEvent_JiraIssue extends Extension_DevblocksEvent {
	protected $_event_id = null; // override

	/**
	 *
	 * @param integer $issue_id
	 * @return Model_DevblocksEvent
	 */
	function generateSampleEventModel(Model_TriggerEvent $trigger, $issue_id=null) {
		
		if(empty($issue_id)) {
			// Pull the latest record
			list($results) = DAO_JiraIssue::search(
				array(),
				array(
					//new DevblocksSearchCriteria(SearchFields_Task::IS_CLOSED,'=',0),
				),
				10,
				0,
				SearchFields_JiraIssue::ID,
				false,
				false
			);
			
			shuffle($results);
			
			$result = array_shift($results);
			
			$issue_id = $result[SearchFields_JiraIssue::ID];
		}
		
		return new Model_DevblocksEvent(
			$this->_event_id,
			array(
				'issue_id' => $issue_id,
			)
		);
	}
	
	function setEvent(Model_DevblocksEvent $event_model=null) {
		$labels = array();
		$values = array();

		/**
		 * Issue
		 */
		
		@$issue_id = $event_model->params['issue_id'];
		$merge_labels = array();
		$merge_values = array();
		CerberusContexts::getContext('cerberusweb.contexts.jira.issue', $issue_id, $merge_labels, $merge_values, null, true);

			// Merge
			CerberusContexts::merge(
				'issue_',
				'',
				$merge_labels,
				$merge_values,
				$labels,
				$values
			);

		/**
		 * Return
		 */

		$this->setLabels($labels);
		$this->setValues($values);
	}
	
	function renderSimulatorTarget($trigger, $event_model) {
		$context = 'cerberusweb.contexts.jira.issue';
		$context_id = $event_model->params['issue_id'];
		DevblocksEventHelper::renderSimulatorTarget($context, $context_id, $trigger, $event_model);
	}
	
	function getValuesContexts($trigger) {
		$vals = array(
			'issue_id' => array(
				'label' => 'Issue',
				'context' => 'cerberusweb.contexts.jira.issue',
			),
			'issue_project_id' => array(
				'label' => 'Issue',
				'context' => 'cerberusweb.contexts.jira.project',
			),
			'issue_project_watchers' => array(
				'label' => 'Issue project watchers',
				'context' => CerberusContexts::CONTEXT_WORKER,
			),
			'issue_watchers' => array(
				'label' => 'Issue watchers',
				'context' => CerberusContexts::CONTEXT_WORKER,
			),
		);
		
		$vars = parent::getValuesContexts($trigger);
		
		$vals_to_ctx = array_merge($vals, $vars);
		asort($vals_to_ctx);
		
		return $vals_to_ctx;
	}
	
	function getConditionExtensions() {
		$labels = $this->getLabels();
		
		$labels['issue_link'] = 'Jira issue is linked';
		$labels['issue_project_link'] = 'Jira project is linked';
		
		$labels['issue_project_watcher_count'] = 'Jira project watcher count';
		$labels['issue_watcher_count'] = 'Jira issue watcher count';
		
		$types = array(
			// [TODO] Implement full list
			'issue_created|date' => Model_CustomField::TYPE_DATE,
			'issue_jira_key' => Model_CustomField::TYPE_SINGLE_LINE,
			'issue_record_url' => Model_CustomField::TYPE_URL,
			'issue_jira_status' => Model_CustomField::TYPE_SINGLE_LINE,
			'issue_summary' => Model_CustomField::TYPE_SINGLE_LINE,
			'issue_jira_type' => Model_CustomField::TYPE_SINGLE_LINE,
			'issue_updated|date' => Model_CustomField::TYPE_DATE,
			'issue_jira_versions' => Model_CustomField::TYPE_SINGLE_LINE,
			
			'issue_project_jira_key' => Model_CustomField::TYPE_SINGLE_LINE,
			'issue_project_last_synced_at|date' => Model_CustomField::TYPE_DATE,
			'issue_project_name' => Model_CustomField::TYPE_SINGLE_LINE,
			'issue_project_record_url' => Model_CustomField::TYPE_URL,
			'issue_project_url' => Model_CustomField::TYPE_URL,
			
			'issue_link' => null,
			'issue_project_link' => null,
			
			'issue_watcher_count' => null,
			'issue_project_watcher_count' => null,
		);

		$conditions = $this->_importLabelsTypesAsConditions($labels, $types);
		
		return $conditions;
	}
	
	function renderConditionExtension($token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','condition'.$seq);
		
		switch($token) {
			case 'issue_link':
			case 'issue_project_link':
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::events/condition_link.tpl');
				break;
				
			case 'issue_watcher_count':
			case 'issue_project_watcher_count':
				$tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');
				break;
		}

		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('params');
	}
	
	function runConditionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$pass = true;
		
		switch($token) {
			case 'issue_link':
			case 'issue_project_link':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');
				
				$from_context = null;
				$from_context_id = null;

				switch($token) {
					case 'issue_link':
						$from_context = 'cerberusweb.contexts.jira.issue';
						@$from_context_id = $dict->issue_id;
						break;
					case 'issue_project_link':
						$from_context = 'cerberusweb.contexts.jira.project';
						@$from_context_id = $dict->issue_project_id;
						break;
					default:
						$pass = false;
				}
				
				// Get links by context+id
				
				if(!empty($from_context) && !empty($from_context_id)) {
					@$context_strings = $params['context_objects'];
					$links = DAO_ContextLink::intersect($from_context, $from_context_id, $context_strings);
					
					// OPER: any, !any, all
					switch($oper) {
						case 'in':
							$pass = (is_array($links) && !empty($links));
							break;
						case 'all':
							$pass = (is_array($links) && count($links) == count($context_strings));
							break;
						default:
							$pass = false;
							break;
					}
					
				} else {
					$pass = false;
				}
				
				$pass = ($not) ? !$pass : $pass;
				break;
				
			case 'issue_watcher_count':
			case 'issue_project_watcher_count':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				switch($token) {
					case 'issue_project_watcher_count':
						$value = count($dict->issue_project_watchers);
						break;
					default:
						$value = count($dict->issue_watchers);
						break;
				}
				
				switch($oper) {
					case 'is':
						$pass = intval($value)==intval($params['value']);
						break;
					case 'gt':
						$pass = intval($value) > intval($params['value']);
						break;
					case 'lt':
						$pass = intval($value) < intval($params['value']);
						break;
				}
				
				$pass = ($not) ? !$pass : $pass;
				break;
				
			default:
				$pass = false;
				break;
		}
		
		return $pass;
	}
	
	function getActionExtensions() {
		$actions =
			array(
				'add_watchers' => array('label' =>'Add watchers'),
				'create_comment' => array('label' =>'Create a comment'),
				'create_notification' => array('label' =>'Create a notification'),
				'create_task' => array('label' =>'Create a task'),
				'create_ticket' => array('label' =>'Create a ticket'),
				'send_email' => array('label' => 'Send email'),
				'set_links' => array('label' => 'Set links'),
			)
			+ DevblocksEventHelper::getActionCustomFieldsFromLabels($this->getLabels())
			;
			
		return $actions;
	}
	
	function renderActionExtension($token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','action'.$seq);

		$labels = $this->getLabels($trigger);
		$tpl->assign('token_labels', $labels);
			
		switch($token) {
			case 'add_watchers':
				DevblocksEventHelper::renderActionAddWatchers($trigger);
				break;
			
			case 'create_comment':
				DevblocksEventHelper::renderActionCreateComment($trigger);
				break;
				
			case 'create_notification':
				DevblocksEventHelper::renderActionCreateNotification($trigger);
				break;
				
			case 'create_task':
				DevblocksEventHelper::renderActionCreateTask($trigger);
				break;
				
			case 'create_ticket':
				DevblocksEventHelper::renderActionCreateTicket($trigger);
				break;
				
			case 'send_email':
				DevblocksEventHelper::renderActionSendEmail($trigger);
				break;

			case 'set_links':
				DevblocksEventHelper::renderActionSetLinks($trigger);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token, $matches)) {
					$field_id = $matches[2];
					$custom_field = DAO_CustomField::get($field_id);
					DevblocksEventHelper::renderActionSetCustomField($custom_field, $trigger);
				}
				break;
		}
		
		$tpl->clearAssign('params');
		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('token_labels');
	}
	
	function simulateActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$issue_id = $dict->issuel_id;

		if(empty($issue_id))
			return;
		
		switch($token) {
			case 'add_watchers':
				return DevblocksEventHelper::simulateActionAddWatchers($params, $dict, 'issue_id');
				break;
			case 'create_comment':
				return DevblocksEventHelper::simulateActionCreateComment($params, $dict, 'issue_id');
				break;
			case 'create_notification':
				return DevblocksEventHelper::simulateActionCreateNotification($params, $dict, 'issue_id');
				break;
			case 'create_task':
				return DevblocksEventHelper::simulateActionCreateTask($params, $dict, 'issue_id');
				break;
			case 'create_ticket':
				return DevblocksEventHelper::simulateActionCreateTicket($params, $dict);
				break;
			case 'send_email':
				return DevblocksEventHelper::simulateActionSendEmail($params, $dict);
				break;
			case 'set_links':
				return DevblocksEventHelper::simulateActionSetLinks($trigger, $params, $dict);
				break;
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token))
					return DevblocksEventHelper::simulateActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
	function runActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$issue_id = $dict->issue_id;

		if(empty($issue_id))
			return;
		
		switch($token) {
			case 'add_watchers':
				DevblocksEventHelper::runActionAddWatchers($params, $dict, 'issue_id');
				break;
			
			case 'create_comment':
				DevblocksEventHelper::runActionCreateComment($params, $dict, 'issue_id');
				break;
				
			case 'create_notification':
				DevblocksEventHelper::runActionCreateNotification($params, $dict, 'issue_id');
				break;
				
			case 'create_task':
				DevblocksEventHelper::runActionCreateTask($params, $dict, 'issue_id');
				break;

			case 'create_ticket':
				DevblocksEventHelper::runActionCreateTicket($params, $dict);
				break;
				
			case 'send_email':
				DevblocksEventHelper::runActionSendEmail($params, $dict);
				break;
				
			case 'set_links':
				DevblocksEventHelper::runActionSetLinks($trigger, $params, $dict);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token))
					return DevblocksEventHelper::runActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
};