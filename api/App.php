<?php
class WgmJira_API {
	private $_base_url = '';
	private $_user = '';
	private $_password = '';
	private $_errors = [];
	
	public function setBaseUrl($url) {
		$this->_base_url = rtrim($url,'/');
	}
	
	public function setAuth($user, $password) {
		$this->_user = $user;
		$this->_password = $password;
	}
	
	function getMyself() {
		return $this->_get('/rest/api/2/myself');
	}
	
	function getServerInfo() {
		return $this->_get('/rest/api/2/serverInfo');
	}
	
	function getStatuses() {
		return $this->_get('/rest/api/2/status');
	}
	
	function getProjects() {
		return $this->_get('/rest/api/2/project');
	}
	
	function getProject($key) {
		return $this->_get(sprintf("/rest/api/2/project/%s", $key));
	}
	
	function getIssues($jql, $maxResults=100, $fields=null, $startAt=0) {
		$params = array(
			'jql' => $jql,
			'maxResults' => $maxResults,
			'startAt' => $startAt,
		);
		
		if(!empty($fields) && is_string($fields))
			$params['fields'] = $fields;
		
		return $this->_get('/rest/api/2/search', $params);
	}
	
	function getIssueByKey($key) {
		$response = $this->getIssues(
			sprintf("key='%s'", $key),
			1,
			'summary,created,updated,description,status,issuetype,fixVersions,project,comment',
			0
		);
		
		if($response['issues'])
			return current($response['issues']);
		
		return false;
	}
	
	function getIssueCreateMeta() {
		$params = array(
			'expand' => 'projects.issuetypes.fields',
		);
		return $this->_get(sprintf("/rest/api/2/issue/createmeta"), $params);
	}
	
	function postCreateIssueJson($json) {
		return $this->_postJson('/rest/api/2/issue', null, $json);
	}
	
	function postCommentIssueJson($key, $json) {
		return $this->_postJson(sprintf('/rest/api/2/issue/%s/comment', $key), null, $json);
	}
	
	function getLastError() {
		return current($this->_errors);
	}
	
	function execute($verb, $path, $params=[], $json=null) {
		$response = null;
		
		switch($verb) {
			case 'get':
				$response = $this->_get($path, $params);
				break;
				
			case 'post':
			case 'put':
				$response = $this->_postJson($path, $params, $json, $verb);
				break;
				
			case 'delete':
				// [TODO]
				break;
		}
		
		$this->_reimportApiChanges($verb, $path, $response);
		
		return $response;
	}
	
	private function _postJson($path, $params=[], $json=null, $verb='post') {
		if(empty($this->_base_url))
			return false;
		
		$url = $this->_base_url . $path;
		
		if(!empty($params) && is_array($params))
			$url .= '?' . http_build_query($params);
		
		$ch = DevblocksPlatform::curlInit($url);
		
		$headers = [];
		
		$headers[] = 'Content-Type: application/json';
		
		if(!empty($this->_user)) {
			$headers[]= 'Authorization: Basic ' . base64_encode(sprintf("%s:%s", $this->_user, $this->_password));
		}
		
		switch($verb) {
			case 'post':
				curl_setopt($ch, CURLOPT_POST, 1);
				break;
				
			case 'put':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
				curl_setopt($ch, CURLOPT_POST, 1);
				//curl_setopt($ch, CURLOPT_PUT, 1);
				//$headers[] = 'X-HTTP-Method-Override: PUT';
				//$headers[] = 'Content-Length: ' . strlen($json);
				break;
		}
		
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		
		if(!empty($headers))
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		$out = DevblocksPlatform::curlExec($ch);
		
		//$info = curl_getinfo($ch);

		// [TODO] This can fail without HTTPS
		
		if(curl_errno($ch)) {
			$this->_errors = array(curl_error($ch));
			$json = false;
			
		} elseif(!empty($out) && false == ($json = json_decode($out, true))) {
			$this->_errors = array('Error decoding JSON response');
			$json = false;
			
		} else {
			$this->_errors = [];
		}
		
		curl_close($ch);
		return $json;
	}
	
	private function _get($path, $params=[]) {
		if(empty($this->_base_url))
			return false;
		
		$url = $this->_base_url . $path;

		if(!empty($params) && is_array($params))
			$url .= '?' . http_build_query($params);
		
		$ch = DevblocksPlatform::curlInit($url);
		
		if(!empty($this->_user)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Basic ' . base64_encode(sprintf("%s:%s", $this->_user, $this->_password)),
			));
		}
		
		$out = DevblocksPlatform::curlExec($ch);

		//$info = curl_getinfo($ch);
		
		// [TODO] This can fail without HTTPS
		
		if(curl_errno($ch)) {
			$this->_errors = array(curl_error($ch));
			$json = false;
			
		} elseif(!empty($out) && false == ($json = json_decode($out, true))) {
			$this->_errors = array('Error decoding JSON response');
			$json = false;
			
		} else {
			$this->_errors = [];
		}
		
		curl_close($ch);
		return $json;
	}
	
	// Special handling for API responses (e.g. recache)
	private function _reimportApiChanges($verb, $path, $response) {
		$matches = [];
		
		// Create issue
		if($verb == 'post' && $path == '/rest/api/2/issue') {
			if(isset($response['key'])) {
				if(false !== ($issue = WgmJira_API::getIssueByKey($response['key'])))
					WgmJira_API::importIssue($issue);
			}

		// Change issue
		} elseif(in_array($verb, array('post', 'put')) && preg_match('#^/rest/api/2/issue/(.*?)(/.*)*$#', $path, $matches)) {
			if(false !== ($issue = WgmJira_API::getIssueByKey($matches[1])))
				WgmJira_API::importIssue($issue);
			
		}
	}
	
	static public function importIssue($object, Model_JiraProject $project=null) {
		$is_new = false;
		
		if(is_null($project)) {
			$project = DAO_JiraProject::getByJiraId($object['fields']['project']['id']);
		}
		
		// Fix versions
		
		$fix_versions = [];
		
		if(is_array($object['fields']['fixVersions']))
		foreach($object['fields']['fixVersions'] as $fix_version) {
			$fix_versions[$fix_version['id']] = $fix_version['name'];
		}
		
		$local_issue = DAO_JiraIssue::getByJiraIdAndProject($object['id'], $project->jira_id);
		
		// Fields
		
		$fields = [
			DAO_JiraIssue::JIRA_ID => $object['id'],
			DAO_JiraIssue::JIRA_KEY => $object['key'],
			DAO_JiraIssue::JIRA_STATUS_ID => $object['fields']['status']['id'],
			DAO_JiraIssue::JIRA_VERSIONS => implode(', ', $fix_versions),
			DAO_JiraIssue::JIRA_TYPE_ID => $object['fields']['issuetype']['id'],
			DAO_JiraIssue::JIRA_PROJECT_ID => $project->jira_id,
			DAO_JiraIssue::PROJECT_ID => $project->id,
			DAO_JiraIssue::SUMMARY => $object['fields']['summary'],
			DAO_JiraIssue::DESCRIPTION => $object['fields']['description'],
			DAO_JiraIssue::CREATED => strtotime($object['fields']['created']),
			DAO_JiraIssue::UPDATED => strtotime($object['fields']['updated']),
		];
		
		if(!empty($local_issue)) {
			$local_issue_id = $local_issue->id;
			DAO_JiraIssue::update($local_issue_id, $fields);

		} else {
			$local_issue_id = DAO_JiraIssue::create($fields);
			$is_new = true;
		}

		// Comments
		
		if(isset($object['fields']['comment']['comments']) && is_array($object['fields']['comment']['comments']))
		foreach($object['fields']['comment']['comments'] as $comment) {
			DAO_JiraIssue::saveComment(
				$comment['id'],
				$object['id'],
				$local_issue_id,
				@strtotime($comment['created']),
				$comment['author']['displayName'],
				$comment['body']
			);
		}
		
		// Trigger 'New JIRA issue created' event
		if($is_new) {
			Event_JiraIssueCreated::trigger($local_issue_id);
		}
		
		return $local_issue_id;
	}
};

class WgmJira_Cron extends CerberusCronPageExtension {
	const ID = 'wgmjira.cron';

	public function run() {
		$logger = DevblocksPlatform::services()->log("JIRA");
		$logger->info("Started");
		
		$this->_synchronize();

		$logger->info("Finished");
	}

	function _synchronize() {
		@$max_projects = DevblocksPlatform::importGPC($_REQUEST['max_projects'],'integer', 20);
		@$max_issues = DevblocksPlatform::importGPC($_REQUEST['max_issues'],'integer', 20);
		
		$jira_projects = DAO_JiraProject::getWhere(
			sprintf("%s > 0",
				DAO_JiraProject::CONNECTED_ACCOUNT_ID
			),
			DAO_JiraProject::LAST_CHECKED_AT,
			true,
			$max_projects
		);
		
		$logger = DevblocksPlatform::services()->log("JIRA");
		
		foreach($jira_projects as $jira_project) {
			DAO_JiraProject::update($jira_project->id, [ DAO_JiraProject::LAST_CHECKED_AT => time() ]);
			
			if(false == ($connected_account = $jira_project->getConnectedAccount()))
				continue;
			
			$credentials = $connected_account->decryptParams();
			
			$jira = new WgmJira_API();
			$jira->setBaseUrl($credentials['base_url']);
			$jira->setAuth($credentials['jira_user'], $credentials['jira_password']);
			
			if(false == ($json = $jira->getMyself()) || !isset($json['displayName'])) {
				$logger->error('Failed to connect to JIRA API using account: '. $connected_account->name);
				continue;
			}
			
			// Pull the full record for each project and merge with createmeta
			if(false == ($project = $jira->getProject($jira_project->jira_key)) || array_key_exists('errors', $project)) {
				$logger->info(sprintf("Couldn't find project with key '%s'", $jira_project->jira_key));
				continue;
			}
			
			$logger->info(sprintf("Updating local project record for %s [%s]", $project['name'], $project['key']));
			
			// Sync statuses
			if(false == ($results = $jira->getStatuses()))
				continue;
			
			$statuses = [];
			
			if(is_array($results))
			foreach($results as $object) {
				unset($object['description']);
				unset($object['iconUrl']);
				unset($object['self']);
				$statuses[$object['id']] = $object;
			}
			
			unset($results);
			
			$fields = [
				DAO_JiraProject::JIRA_ID => $project['id'],
				DAO_JiraProject::NAME => $project['name'],
			];
			
			if(empty($jira_project->url))
				$fields[DAO_JiraProject::URL] = isset($project['url']) ? $project['url'] : '';
			
			// Only store the JSON info if we're syncing this project
			$issue_types = [];
			$versions = [];
			
			if(isset($project['issueTypes']) && is_array($project['issueTypes'])) {
				foreach($project['issueTypes'] as $object) {
					unset($object['self']);
					$issue_types[$object['id']] = $object;
				}
			}
			
			if(isset($project['versions']) && is_array($project['versions'])) {
				foreach($project['versions'] as $object) {
					unset($object['self']);
					$versions[$object['id']] = $object;
				}
			}
			
			$fields[DAO_JiraProject::ISSUETYPES_JSON] = json_encode($issue_types);
			$fields[DAO_JiraProject::STATUSES_JSON] = json_encode($statuses);
			$fields[DAO_JiraProject::VERSIONS_JSON] = json_encode($versions);
			
			DAO_JiraProject::update($jira_project->id, $fields, false);
			
			$logger->info(sprintf("Syncing issues for project %s [%s]", $jira_project->name, $jira_project->jira_key));
			
			$startAt = 0;
			$maxResults = $max_issues;
			
			$last_synced_at = $jira_project->last_synced_at;
			$last_synced_checkpoint = $jira_project->last_synced_checkpoint;
			
			$jql = sprintf("project='%s' AND ((updated = %d000 AND created > %d000) OR (updated > %d000)) ORDER BY updated ASC, created ASC",
				$jira_project->jira_key,
				$last_synced_at,
				$last_synced_checkpoint,
				$last_synced_at
			);
			
			$logger->info(sprintf("JQL: %s", $jql));
			
			if(false != ($response = $jira->getIssues(
				$jql,
				$maxResults,
				'summary,created,updated,description,status,issuetype,fixVersions,project,comment',
				$startAt
			))) {
				if(isset($response['issues']))
				foreach($response['issues'] as $object) {
					WgmJira_API::importIssue($object, $jira_project);
					
					$last_synced_at = strtotime($object['fields']['updated']);
					$last_synced_checkpoint = strtotime($object['fields']['created']);
				}
			}
			
			// Set the last updated date on the project
			DAO_JiraProject::update($jira_project->id, [
				DAO_JiraProject::LAST_SYNCED_AT => $last_synced_at,
				DAO_JiraProject::LAST_SYNCED_CHECKPOINT => $last_synced_checkpoint,
			]);
		}
	}
	
	public function configure($instance) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->cache_lifetime = "0";

		// Load settings
		/*
		$clients_updated_from = $this->getParam('clients.updated_from', 0);
		if(empty($clients_updated_from))
			$clients_updated_from = gmmktime(0,0,0,1,1,2000);

		$tpl->assign('clients_updated_from', $clients_updated_from);

		$tpl->display('devblocks:wgm.freshbooks::config/cron.tpl');
		*/
	}

	public function saveConfigurationAction() {
		/*
		@$clients_updated_from = DevblocksPlatform::importGPC($_POST['clients_updated_from'], 'string', '');

		// Save settings
		$clients_timestamp = intval(@strtotime($clients_updated_from));
		if(!empty($clients_timestamp))
			$this->setParam('clients.updated_from', $clients_timestamp);
		*/
	}
};

class WgmJira_EventActionApiCall extends Extension_DevblocksEventAction {
	function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null) {
		$active_worker = CerberusApplication::getActiveWorker();
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('params', $params);
		
		if(!is_null($seq))
			$tpl->assign('namePrefix', 'action'.$seq);
		
		if(isset($params['connected_account_id'])) {
			if(false != ($connected_account = DAO_ConnectedAccount::get($params['connected_account_id']))) {
				if(Context_ConnectedAccount::isReadableByActor($connected_account, $active_worker))
					$tpl->assign('connected_account', $connected_account);
			}
		}
		
		$tpl->display('devblocks:wgm.jira::events/action_jira_api_call.tpl');
	}
	
	function simulate($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
		
		$out = null;
		
		@$api_verb = $params['api_verb'];
		@$api_path = $tpl_builder->build($params['api_path'], $dict);
		@$json = $tpl_builder->build($params['json'], $dict);
		@$response_placeholder = $params['response_placeholder'];
		@$run_in_simulator = $params['run_in_simulator'];
		@$connected_account_id = $params['connected_account_id'];
		
		if(empty($api_verb))
			return "[ERROR] API verb is required.";
		
		if(empty($api_path))
			return "[ERROR] API path is required.";
		
		if(empty($response_placeholder))
			return "[ERROR] No result placeholder given.";
		
		if(empty($connected_account_id))
			return "[ERROR] No connected account is configured.";
		
		if(false == ($connected_account = DAO_ConnectedAccount::get($connected_account_id)))
			return "[ERROR] No connected account is configured.";
			
		if(!Context_ConnectedAccount::isReadableByActor($connected_account, $trigger->getBot()))
			return "[ERROR] This bot is now allowed to use this connected account.";
		
		// Output
		$out = sprintf(">>> Sending request to JIRA API:\n%s %s\n%s\n",
			mb_convert_case($api_verb, MB_CASE_UPPER),
			$api_path,
			(in_array($api_verb, array('post','put')) ? ("\n" . $json . "\n") : "")
		);
		
		// Run in simulator?
		
		if($run_in_simulator) {
			$this->run($token, $trigger, $params, $dict);
			
			$out .= sprintf(">>> API response is:\n\n%s\n\n",
				DevblocksPlatform::strFormatJson($dict->$response_placeholder)
			);
			
			// Placeholder
			$out .= sprintf(">>> Saving response to placeholder:\n%s\n",
				$response_placeholder
			);
		}
		
		return $out;
	}
	
	function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
		
		@$api_verb = $params['api_verb'];
		@$api_path = $tpl_builder->build($params['api_path'], $dict);
		@$json = $tpl_builder->build($params['json'], $dict);
		@$response_placeholder = $params['response_placeholder'];
		@$connected_account_id = $params['connected_account_id'];
		
		if(empty($api_verb) || empty($api_path))
			return false;
		
		if(empty($response_placeholder))
			return false;
		
		if(empty($connected_account_id))
			return false;
		
		if(false == ($connected_account = DAO_ConnectedAccount::get($connected_account_id)))
			return false;
			
		if(!Context_ConnectedAccount::isReadableByActor($connected_account, $trigger->getBot()))
			return false;
		
		$credentials = $connected_account->decryptParams();
		
		$jira = new WgmJira_API();
		$jira->setBaseUrl($credentials['base_url']);
		$jira->setAuth($credentials['jira_user'], $credentials['jira_password']);
		
		$response = $jira->execute($api_verb, $api_path, array(), $json);
		
		if(is_array($response)) {
			$dict->$response_placeholder = $response;
		}
	}
};

class ServiceProvider_Jira extends Extension_ServiceProvider implements IServiceProvider_HttpRequestSigner {
	const ID = 'wgm.jira.service.provider';
	
	function renderConfigForm(Model_ConnectedAccount $account) {
		$tpl = DevblocksPlatform::services()->template();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$params = $account->decryptParams($active_worker);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.jira::provider/edit_params.tpl');
	}
	
	function saveConfigForm(Model_ConnectedAccount $account, array &$params) {
		@$edit_params = DevblocksPlatform::importGPC($_POST['params'], 'array', array());
	
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!isset($edit_params['base_url']) || empty($edit_params['base_url']))
			return "The 'Base URL' is required.";
		
		if(!isset($edit_params['jira_user']) || empty($edit_params['jira_user']))
			return "The 'JIRA User' is required.";
		
		if(!isset($edit_params['jira_password']) || empty($edit_params['jira_password']))
			return "The 'JIRA Password' is required.";
		
		// Test the credentials
		
		$jira = new WgmJira_API();
		$jira->setBaseUrl($edit_params['base_url']);
		$jira->setAuth($edit_params['jira_user'], $edit_params['jira_password']);
		
		if(false == ($json = $jira->getMyself()) || !isset($json['displayName']))
			return "Failed to authenticate to the JIRA API.";
		
		foreach($edit_params as $k => $v)
			$params[$k] = $v;
		
		return true;
	}
	
	function authenticateHttpRequest(Model_ConnectedAccount $account, &$ch, &$verb, &$url, &$body, &$headers) {
		$credentials = $account->decryptParams();
		
		if(!isset($credentials['jira_user']) && !isset($credentials['jira_password']))
			return false;
		
		$headers[] = 'Authorization: Basic ' . base64_encode(sprintf("%s:%s", $credentials['jira_user'], $credentials['jira_password']));
		return true;
	}
}