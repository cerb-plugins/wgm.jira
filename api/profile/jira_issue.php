<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2014, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/

class PageSection_ProfilesJiraIssue extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // jira_issue
		$id = array_shift($stack); // 123

		@$id = intval($id);
		
		if(null == ($jira_issue = DAO_JiraIssue::get($id))) {
			return;
		}
		$tpl->assign('jira_issue', $jira_issue);
	
		// Tab persistence
		
		$point = 'profiles.jira_issue.tab';
		$tpl->assign('point', $point);
		
		if(null == (@$tab_selected = $stack[0])) {
			$tab_selected = $visit->get($point, '');
		}
		$tpl->assign('tab_selected', $tab_selected);
	
		// Properties
			
		$properties = array();
		
		$properties['jira_key'] = array(
			'label' => ucfirst($translate->_('dao.jira_issue.jira_key')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $jira_issue->jira_key,
		);
		
		$properties['jira_project_id'] = array(
			'label' => ucfirst($translate->_('dao.jira_issue.project_id')),
			'type' => '',
			'value' => $jira_issue->getProject(),
		);
		
		$properties['jira_versions'] = array(
			'label' => ucfirst($translate->_('dao.jira_issue.jira_versions')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $jira_issue->jira_versions,
		);
		
		$properties['jira_type_id'] = array(
			'label' => ucfirst($translate->_('dao.jira_issue.jira_type_id')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => ($jira_type = $jira_issue->getType()) ? $jira_type['name'] : '',
		);
		
		$properties['jira_status_id'] = array(
			'label' => ucfirst($translate->_('dao.jira_issue.jira_status_id')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => ($jira_status = $jira_issue->getStatus()) ? $jira_status['name'] : '',
		);
		
		$properties['created'] = array(
			'label' => ucfirst($translate->_('common.created')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $jira_issue->created,
		);
		
		$properties['updated'] = array(
			'label' => ucfirst($translate->_('common.updated')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $jira_issue->updated,
		);
			
	
		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.jira.issue', $jira_issue->id)) or array();
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields('cerberusweb.contexts.jira.issue', $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets

		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets('cerberusweb.contexts.jira.issue', $jira_issue->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			'cerberusweb.contexts.jira.issue' => array(
				$jira_issue->id => 
					DAO_ContextLink::getContextLinkCounts(
						'cerberusweb.contexts.jira.issue',
						$jira_issue->id,
						array(CerberusContexts::CONTEXT_WORKER, CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		
		// [TODO] This is using JIRA ids rather than Cerb ones
		if(isset($jira_issue->project_id)) {
			$properties_links['cerberusweb.contexts.jira.project'] = array(
				$jira_issue->project_id => 
					DAO_ContextLink::getContextLinkCounts(
						'cerberusweb.contexts.jira.project',
						$jira_issue->project_id,
						array(CerberusContexts::CONTEXT_WORKER, CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			);
		}
		
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		
		$tpl->assign('properties', $properties);
			
		// Macros
		
		$macros = DAO_TriggerEvent::getReadableByActor(
			$active_worker,
			'event.macro.jira_issue'
		);
		$tpl->assign('macros', $macros);

		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, 'cerberusweb.contexts.jira.issue');
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:wgm.jira::jira_issue/profile.tpl');
	}
	
	function savePeekAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		
		//@$name = DevblocksPlatform::importGPC($_REQUEST['name'], 'string', '');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!empty($id) && !empty($do_delete)) { // Delete
			// [TODO] Privs
			DAO_JiraIssue::delete($id);
			
		} else {

			// If we're adding a comment
			if(!empty($comment)) {
				$also_notify_worker_ids = array_keys(CerberusApplication::getWorkersByAtMentionsText($comment));
				
				$fields = array(
					DAO_Comment::CREATED => time(),
					DAO_Comment::CONTEXT => 'cerberusweb.contexts.jira.issue',
					DAO_Comment::CONTEXT_ID => $id,
					DAO_Comment::COMMENT => $comment,
					DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
					DAO_Comment::OWNER_CONTEXT_ID => $active_worker->id,
				);
				$comment_id = DAO_Comment::create($fields, $also_notify_worker_ids);
			}
			
			// Custom fields
			@$field_ids = DevblocksPlatform::importGPC($_REQUEST['field_ids'], 'array', array());
			DAO_CustomFieldValue::handleFormPost('cerberusweb.contexts.jira.issue', $id, $field_ids);
		}
	}
	
	function viewExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}

		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
//					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=jira_issue', true),
					'toolbar_extension_id' => 'cerberusweb.contexts.jira.issue.explore.toolbar',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=jira_issue&id=%d-%s", $row[SearchFields_JiraIssue::ID], DevblocksPlatform::strToPermalink($row[SearchFields_JiraIssue::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_JiraIssue::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
	
	function showDiscussionTabAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'],'string','');
		@$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'],'integer',0);
		@$point = DevblocksPlatform::importGPC($_REQUEST['point'],'string','contact.history');
		
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		
		if(empty($context_id))
			return;
		
		if(false == ($issue = DAO_JiraIssue::get($context_id)))
			return;

		if(!empty($point))
			$visit->set($point, 'discussion');
		
		$tpl->assign('issue', $issue);
		$tpl->assign('comments', $issue->getComments());
		
		$tpl->display('devblocks:wgm.jira::jira_issue/profile/tab_discussion.tpl');
	}
	
	function getIssueCreateFieldsAction() {
		@$name_prefix = DevblocksPlatform::importGPC($_REQUEST['name_prefix'],'string','');
		@$params = DevblocksPlatform::importGPC($_REQUEST[$name_prefix],'array',array());

		@$project_key = $params['project_key'];
		
		if(empty($project_key) || false == ($project = DAO_JiraProject::getByJiraKey($project_key)))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('namePrefix', $name_prefix);
		$tpl->assign('params', $params);
		
		$tpl->assign('project', $project);
		
		$tpl->display('devblocks:wgm.jira::events/action_create_jira_issue_fields.tpl');
	}
};
