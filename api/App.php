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
		
		$ch = curl_init($url);
		
		$headers = array();
		
		$headers[] = 'Content-Type: application/json';
		
		if(!empty($this->_user)) {
			$headers[]= 'Authorization: Basic ' . base64_encode(sprintf("%s:%s", $this->_user, $this->_password));
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		
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
		
		$out = curl_exec($ch);
		
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
		
		$ch = curl_init($url);
		
		if(!empty($this->_user)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Basic ' . base64_encode(sprintf("%s:%s", $this->_user, $this->_password)),
			));
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out = curl_exec($ch);

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
		$response = $jira->getIssueCreateMeta();
		$projects = $response->projects;
		
		if(is_array($projects))
		foreach($projects as $project_meta) {
			// Pull the full record for each project and merge with createmeta
			if(false == ($project = $jira->getProject($project_meta->key)))
				continue;
			
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
				
			$local_project = DAO_JiraProject::getByJiraId($project->id);

			$fields = array(
				DAO_JiraProject::JIRA_ID => $project->id,
				DAO_JiraProject::JIRA_KEY => $project->key,
				DAO_JiraProject::NAME => $project->name,
				DAO_JiraProject::URL => $project->url,
				DAO_JiraProject::ISSUETYPES_JSON => json_encode($issue_types),
				DAO_JiraProject::STATUSES_JSON => json_encode($statuses),
				DAO_JiraProject::VERSIONS_JSON => json_encode($versions),
			);
			
			if(!empty($local_project)) {
				DAO_JiraProject::update($local_project->id, $fields);
		
			} else {
				$local_id = DAO_JiraProject::create($fields);
				$local_project = DAO_JiraProject::get($local_id);
			}
			
			if($local_project->is_sync) {
				$logger->info(sprintf("Syncing project [%s] %s", $local_project->jira_key, $local_project->name));
			
				$startAt = 0;
				$maxResults = 200;
				$last_updated_date = $local_project->last_synced_at;
				$last_unique_updated_date = $last_updated_date;
				
				/*
				 * This should track if we've pulled more than one page, and if so we
				* should bail out as soon as we have a subsequent row which has a different
				* updated date.
				*/
				$is_overflow = false;
			
				// Resume from last sync date
				do {
					if(false == ($response = $jira->getIssues(
						sprintf("project='%s' AND updated > %d000 ORDER BY updated ASC", $local_project->jira_key, date('U', $local_project->last_synced_at)),
						$maxResults,
						'summary,created,updated,description,status,issuetype,fixVersions,project,comment',
						$startAt
					)
					)) {
						$is_overflow = false;
						continue;
					}
			
					if(!isset($response->issues) || !is_array($response->issues) || empty($response->issues)) {
						$is_overflow = false;
						continue;
					}
			
					$num_issues = count($response->issues);
					$num_processed = 0;
			
					foreach($response->issues as $object) {
						$current_updated_date = strtotime($object->fields->updated);
						$num_processed++;
			
						if($current_updated_date != $last_updated_date)
							$last_unique_updated_date = $last_updated_date;
	
						// We're overflowing
						if(!$is_overflow && $num_processed >= floor($maxResults * 0.90)) {
							$is_overflow = true;
						}
			
						// We're done overflowing
						if($is_overflow && $current_updated_date == $last_updated_date) {
							$is_overflow = false;
							$num_issues = 0;
							break;
						}
						
						$local_issue_id = WgmJira_API::importIssue($object);
						
						$last_updated_date = $current_updated_date;
					}
			
					// If we finished everything, move the date cursor to past the last row
					if($num_issues < $maxResults && $num_processed == $num_issues)
						$last_unique_updated_date = $last_updated_date;
			
					// If we need to get another page, move the row cursor
					$startAt += $maxResults;
			
				} while($is_overflow);
			
				// Set the last updated date on the project
				if(!empty($last_unique_updated_date)) {
					DAO_JiraProject::update($local_project->id, array(
						DAO_JiraProject::LAST_SYNCED_AT => $last_unique_updated_date,
					));
				}
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