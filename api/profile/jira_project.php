<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2017, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class PageSection_ProfilesJiraProject extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		
		$context = Context_JiraProject::ID;
		$active_worker = CerberusApplication::getActiveWorker();
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // jira_project
		$id = array_shift($stack); // 123

		@$id = intval($id);
		
		if(null == ($jira_project = DAO_JiraProject::get($id))) {
			return;
		}
		$tpl->assign('jira_project', $jira_project);
		
		// Dictionary
		$labels = array();
		$values = array();
		CerberusContexts::getContext($context, $jira_project, $labels, $values, '', true, false);
		$dict = DevblocksDictionaryDelegate::instance($values);
		$tpl->assign('dict', $dict);
	
		// Tab persistence
		
		$point = 'profiles.jira_project.tab';
		$tpl->assign('point', $point);
		
		if(null == (@$tab_selected = $stack[0])) {
			$tab_selected = $visit->get($point, '');
		}
		$tpl->assign('tab_selected', $tab_selected);
	
		// Properties
			
		$properties = array();
			
		$properties['is_sync'] = array(
			'label' => mb_ucfirst($translate->_('dao.jira_project.is_sync')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $jira_project->is_sync,
		);
		
		$properties['last_synced_at'] = array(
			'label' => mb_ucfirst($translate->_('dao.jira_project.last_synced_at')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $jira_project->last_synced_at,
		);
		
		$properties['url'] = array(
			'label' => mb_ucfirst($translate->_('common.url')),
			'type' => Model_CustomField::TYPE_URL,
			'value' => $jira_project->url,
		);
	
		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(Context_JiraProject::ID, $jira_project->id)) or array();
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields(Context_JiraProject::ID, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets

		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets(Context_JiraProject::ID, $jira_project->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			Context_JiraProject::ID => array(
				$jira_project->id => 
					DAO_ContextLink::getContextLinkCounts(
						Context_JiraProject::ID,
						$jira_project->id,
						array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		
		$tpl->assign('properties', $properties);
			
		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, Context_JiraProject::ID);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Interactions
		$interactions = Event_GetInteractionsForWorker::getInteractionsByPointAndWorker('record:' . $context, $dict, $active_worker);
		$interactions_menu = Event_GetInteractionsForWorker::getInteractionMenu($interactions);
		$tpl->assign('interactions_menu', $interactions_menu);
		
		// Template
		$tpl->display('devblocks:wgm.jira::jira_project/profile.tpl');
	}
	
	function savePeekAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		
		@$is_sync = DevblocksPlatform::importGPC($_REQUEST['is_sync'], 'integer', 0);
		
		//@$name = DevblocksPlatform::importGPC($_REQUEST['name'], 'string', '');
		//@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!empty($id) && !empty($do_delete)) { // Delete
			//DAO_JiraProject::delete($id);
			
		} else {
			if(empty($id)) { // New
				/*
				$fields = array(
					DAO_JiraProject::UPDATED_AT => time(),
					DAO_JiraProject::NAME => $name,
				);
				$id = DAO_JiraProject::create($fields);
				
				if(!empty($view_id) && !empty($id))
					C4_AbstractView::setMarqueeContextCreated($view_id, Context_JiraProject::ID, $id);
				*/
				
			} else { // Edit
				$fields = array(
					DAO_JiraProject::IS_SYNC => $is_sync,
				);
				DAO_JiraProject::update($id, $fields);
			}

			// If we're adding a comment
			if(!empty($comment)) {
				$also_notify_worker_ids = array_keys(CerberusApplication::getWorkersByAtMentionsText($comment));
				
				$fields = array(
					DAO_Comment::CREATED => time(),
					DAO_Comment::CONTEXT => Context_JiraProject::ID,
					DAO_Comment::CONTEXT_ID => $id,
					DAO_Comment::COMMENT => $comment,
					DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
					DAO_Comment::OWNER_CONTEXT_ID => $active_worker->id,
				);
				$comment_id = DAO_Comment::create($fields, $also_notify_worker_ids);
			}
			
			// Custom field saves
			@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', []);
			if(!DAO_CustomFieldValue::handleFormPost(Context_JiraProject::ID, $id, $field_ids, $error))
				throw new Exception_DevblocksAjaxValidationError($error);
		}
	}
	
	function viewExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::services()->url();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);

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
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=jira_project', true),
					'toolbar_extension_id' => 'cerberusweb.contexts.jira.project.explore.toolbar',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=jira_project&id=%d-%s", $row[SearchFields_JiraProject::ID], DevblocksPlatform::strToPermalink($row[SearchFields_JiraProject::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_JiraProject::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
	
	function showIssuesTabAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'],'string','');
		@$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'],'integer',0);
		@$point = DevblocksPlatform::importGPC($_REQUEST['point'],'string','contact.history');
		
		$tpl = DevblocksPlatform::services()->template();
		$translate = DevblocksPlatform::getTranslationService();
		
		if(empty($context_id))
			return;
		
		if(false == ($project = DAO_JiraProject::get($context_id)))
			return;

		// Issue worklist with project filter
		
		$view_id = sprintf("jira_project_profile_issues_%d", $project->id);
		
		if(null == ($view = C4_AbstractViewLoader::getView($view_id))) {
			$context_ext = Extension_DevblocksContext::get(Context_JiraIssue::ID);
			$view = $context_ext->getSearchView($view_id);
		}

		if(empty($view))
			return;
		
		$view->name = mb_convert_case($translate->_('wgm.jira.common.issues'), MB_CASE_TITLE);
		$view->is_ephemeral = true;
		
		$view->addParamsRequired(
			array(
				SearchFields_JiraIssue::PROJECT_ID => new DevblocksSearchCriteria(SearchFields_JiraIssue::PROJECT_ID, '=', $project->jira_id),
			),
			true
		);
		
		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/search_and_view.tpl');
	}
};
