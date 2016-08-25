<?php
if(class_exists('Extension_PluginSetup')):
class WgmJira_Setup extends Extension_PluginSetup {
	const POINT = 'wgmjira.setup';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();

		$params = array(
			'base_url' => DevblocksPlatform::getPluginSetting('wgm.jira','base_url',''),
			'jira_user' => DevblocksPlatform::getPluginSetting('wgm.jira','jira_user',''),
			'jira_password' => DevblocksPlatform::getPluginSetting('wgm.jira','jira_password',''),
		);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.jira::setup/index.tpl');
	}
	
	function save(&$errors) {
		try {
			@$base_url = DevblocksPlatform::importGPC($_REQUEST['base_url'],'string','');
			@$jira_user = DevblocksPlatform::importGPC($_REQUEST['jira_user'],'string','');
			@$jira_password = DevblocksPlatform::importGPC($_REQUEST['jira_password'],'string','');
			
			if(empty($base_url))
				throw new Exception("The base URL is required.");
			
			$base_url = rtrim($base_url,'/');
			
			// Test connection
			$jira = WgmJira_API::getInstance();
			$jira->setBaseUrl($base_url);
			$jira->setAuth($jira_user, $jira_password);
			
			// Show the actual error
			if(false === $jira->getServerInfo())
				throw new Exception($jira->getLastError());
			
			DevblocksPlatform::setPluginSetting('wgm.jira','base_url',$base_url);
			DevblocksPlatform::setPluginSetting('wgm.jira','jira_user',$jira_user);
			DevblocksPlatform::setPluginSetting('wgm.jira','jira_password',$jira_password);

			return true;
			
		} catch (Exception $e) {
			$errors[] = $e->getMessage();
			return false;
		}
	}
};
endif;

class WgmJira_API {
	private static $_instance = null;
	private $_base_url = '';
	private $_user = '';
	private $_password = '';
	private $_errors = array();
	
	/**
	 * @return WgmJira_API
	 */
	public static function getInstance() {
		if(null == self::$_instance) {
			self::$_instance = new WgmJira_API();
		}
		
		return self::$_instance;
	}
	
	private function __construct() {
		$base_url = DevblocksPlatform::getPluginSetting('wgm.jira','base_url','');
		$user = DevblocksPlatform::getPluginSetting('wgm.jira','jira_user','');
		$password = DevblocksPlatform::getPluginSetting('wgm.jira','jira_password','');
		
		$this->setBaseUrl($base_url);
		$this->setAuth($user, $password);
	}

	public function setBaseUrl($url) {
		$this->_base_url = rtrim($url,'/');
	}
	
	public function setAuth($user, $password) {
		$this->_user = $user;
		$this->_password = $password;
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
		
		if($response->issues)
			return current($response->issues);
		
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
	
	function execute($verb, $path, $params=array(), $json=null) {
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
	
	private function _postJson($path, $params=array(), $json=null, $verb='post') {
		if(empty($this->_base_url))
			return false;
		
		$url = $this->_base_url . $path;
		
		if(!empty($params) && is_array($params))
			$url .= '?' . http_build_query($params);
		
		$ch = DevblocksPlatform::curlInit($url);
		
		$headers = array();
		
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
		
		$info = curl_getinfo($ch);

		// [TODO] This can fail without HTTPS
		
		if(curl_errno($ch)) {
			$this->_errors = array(curl_error($ch));
			$json = false;
			
		} elseif(!empty($out) && false == ($json = json_decode($out, true))) {
			$this->_errors = array('Error decoding JSON response');
			$json = false;
			
		} else {
			$this->_errors = array();
		}
		
		curl_close($ch);
		return $json;
	}
	
	private function _get($path, $params=array()) {
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

		$info = curl_getinfo($ch);
		
		// [TODO] This can fail without HTTPS
		
		if(curl_errno($ch)) {
			$this->_errors = array(curl_error($ch));
			$json = false;
			
		} elseif(!empty($out) && false == ($json = json_decode($out))) {
			$this->_errors = array('Error decoding JSON response');
			$json = false;
			
		} else {
			$this->_errors = array();
		}
		
		curl_close($ch);
		return $json;
	}
	
	// Special handling for API responses (e.g. recache)
	private function _reimportApiChanges($verb, $path, $response) {

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
	
	static public function importIssue($object) {
		$is_new = false;
		
		// Fix versions
		
		$fix_versions = array();
		
		if(is_array($object->fields->fixVersions))
		foreach($object->fields->fixVersions as $fix_version) {
			$fix_versions[$fix_version->id] = $fix_version->name;
		}
		
		// Fields
		
		$fields = array(
			DAO_JiraIssue::JIRA_ID => $object->id,
			DAO_JiraIssue::JIRA_KEY => $object->key,
			DAO_JiraIssue::JIRA_STATUS_ID => $object->fields->status->id,
			DAO_JiraIssue::JIRA_VERSIONS => implode(', ', $fix_versions),
			DAO_JiraIssue::JIRA_TYPE_ID => $object->fields->issuetype->id,
			DAO_JiraIssue::PROJECT_ID => $object->fields->project->id,
			DAO_JiraIssue::SUMMARY => $object->fields->summary,
			DAO_JiraIssue::CREATED => strtotime($object->fields->created),
			DAO_JiraIssue::UPDATED => strtotime($object->fields->updated),
		);
		
		$local_issue = DAO_JiraIssue::getByJiraId($object->id);
		
		if(!empty($local_issue)) {
			$local_issue_id = $local_issue->id;
			DAO_JiraIssue::update($local_issue_id, $fields);

		} else {
			$local_issue_id = DAO_JiraIssue::create($fields);
			$is_new = true;
		}

		// Versions
		
		DAO_JiraIssue::setVersions($local_issue_id, array_keys($fix_versions));
		
		// Store description content
		
		DAO_JiraIssue::setDescription($object->id, $object->fields->description);
		
		// Comments
		
		if(isset($object->fields->comment->comments) && is_array($object->fields->comment->comments))
		foreach($object->fields->comment->comments as $comment) {
			$result = DAO_JiraIssue::saveComment(
				$comment->id,
				$object->id,
				@strtotime($comment->created),
				$comment->author->displayName,
				$comment->body
			);
			
			// If we inserted, trigger 'New JIRA issue comment' event
			if($result == 1)
				Event_JiraIssueCommented::trigger($local_issue_id, $comment->id);
		}
		
		// Links
		// [TODO]
		
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
		$logger = DevblocksPlatform::getConsoleLog("JIRA");
		$logger->info("Started");
		
		$this->_synchronize();

		$logger->info("Finished");
	}

	function _synchronize() {
		$jira = WgmJira_API::getInstance();
		$logger = DevblocksPlatform::getConsoleLog("JIRA");

		// Sync statuses
		if(false == ($results = $jira->getStatuses()))
			return;
		
		$statuses = array();
		
		if(is_array($results))
			foreach($results as $object) {
				unset($object->description);
				unset($object->iconUrl);
				unset($object->self);
				$statuses[$object->id] = $object;
			}
		
		// Sync projects
		$logger->info("Requesting createmeta manifest");
		$response = $jira->getIssueCreateMeta();
		
		if(is_array($response->projects))
		foreach($response->projects as $project_meta) {
			// Pull the full record for each project and merge with createmeta
			if(false == ($project = $jira->getProject($project_meta->key)))
				continue;
			
			$logger->info(sprintf("Updating local project record for %s [%s]", $project->name, $project->key));
			
			$local_project = DAO_JiraProject::getByJiraId($project->id, true);
			
			$fields = array(
				DAO_JiraProject::JIRA_ID => $project->id,
				DAO_JiraProject::JIRA_KEY => $project->key,
				DAO_JiraProject::NAME => $project->name,
				DAO_JiraProject::URL => isset($project->url) ? $project->url : '',
				DAO_JiraProject::ISSUETYPES_JSON => json_encode(array()),
				DAO_JiraProject::STATUSES_JSON => json_encode(array()),
				DAO_JiraProject::VERSIONS_JSON => json_encode(array()),
			);
			
			if(!empty($local_project)) {
				// Only store the JSON info if we're syncing this project
				if($local_project->is_sync) {
					$issue_types = array();
					$versions = array();
						
					if(isset($project_meta->issuetypes) && is_array($project_meta->issuetypes))
						foreach($project_meta->issuetypes as $object) {
						unset($object->self);
						$issue_types[$object->id] = $object;
					}
		
					if(isset($project->versions) && is_array($project->versions))
						foreach($project->versions as $object) {
						unset($object->self);
						$versions[$object->id] = $object;
					}
					
					$fields[DAO_JiraProject::ISSUETYPES_JSON] = json_encode($issue_types);
					$fields[DAO_JiraProject::STATUSES_JSON] = json_encode($statuses);
					$fields[DAO_JiraProject::VERSIONS_JSON] = json_encode($versions);
				}
				DAO_JiraProject::update($local_project->id, $fields);
		
			} else {
				$logger->info(sprintf("Creating new local project record for %s [%s]", $project->name, $project->key));
				$local_id = DAO_JiraProject::create($fields);
				$local_project = DAO_JiraProject::get($local_id);
			}
		}
		
		unset($response);
		
		// Pull the 10 least recently checked projects
		
		$local_projects = DAO_JiraProject::getWhere(
			null,
			DAO_JiraProject::LAST_CHECKED_AT,
			true,
			10
		);
		
		if(is_array($local_projects))
		foreach($local_projects as $local_project) {
			if($local_project->is_sync) {
				$logger->info(sprintf("Syncing issues for project %s [%s]", $local_project->name, $local_project->jira_key));
			
				$startAt = 0;
				$maxResults = 25;
				
				$last_synced_at = $local_project->last_synced_at;
				$last_synced_checkpoint = $local_project->last_synced_checkpoint;
				
				$jql = sprintf("project='%s' AND ((updated = %d000 AND created > %d000) OR (updated > %d000)) ORDER BY updated ASC, created ASC",
					$local_project->jira_key,
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
				)
				)) {
					foreach($response->issues as $object) {
						$local_issue_id = WgmJira_API::importIssue($object);
						
						$last_synced_at = strtotime($object->fields->updated);
						$last_synced_checkpoint = strtotime($object->fields->created);
					}
				}
		
				// Set the last updated date on the project
				DAO_JiraProject::update($local_project->id, array(
					DAO_JiraProject::LAST_CHECKED_AT => time(),
					DAO_JiraProject::LAST_SYNCED_AT => $last_synced_at,
					DAO_JiraProject::LAST_SYNCED_CHECKPOINT => $last_synced_checkpoint,
				));
			}
		}
	}
	
	public function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();
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

if(class_exists('Extension_DevblocksEventAction')):
class WgmJira_EventActionApiCall extends Extension_DevblocksEventAction {
	function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		
		if(!is_null($seq))
			$tpl->assign('namePrefix', 'action'.$seq);
		
		$tpl->display('devblocks:wgm.jira::events/action_jira_api_call.tpl');
	}
	
	function simulate($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$jira = WgmJira_API::getInstance();
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		
		$out = null;
		
		@$api_verb = $params['api_verb'];
		@$api_path = $tpl_builder->build($params['api_path'], $dict);
		@$json = $tpl_builder->build($params['json'], $dict);
		@$response_placeholder = $params['response_placeholder'];
		@$run_in_simulator = $params['run_in_simulator'];
		
		if(empty($api_verb))
			return "[ERROR] API verb is required.";
		
		if(empty($api_path))
			return "[ERROR] API path is required.";
		
		if(empty($response_placeholder))
			return "[ERROR] No result placeholder given.";
		
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
				DevblocksPlatform::strFormatJson(json_encode($dict->$response_placeholder))
			);
			
			// Placeholder
			$out .= sprintf(">>> Saving response to placeholder:\n%s\n",
				$response_placeholder
			);
		}
		
		return $out;
	}
	
	function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$jira = WgmJira_API::getInstance();
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		
		@$api_verb = $params['api_verb'];
		@$api_path = $tpl_builder->build($params['api_path'], $dict);
		@$json = $tpl_builder->build($params['json'], $dict);
		@$response_placeholder = $params['response_placeholder'];
		
		if(empty($api_verb) || empty($api_path))
			return false;
		
		if(empty($response_placeholder))
			return false;
		
		$response = $jira->execute($api_verb, $api_path, array(), $json);
		
		if(is_array($response)) {
			$dict->$response_placeholder = $response;
		}
	}
};
endif;