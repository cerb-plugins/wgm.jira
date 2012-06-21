<?php
if(class_exists('Extension_PluginSetup')):
class WgmJira_Setup extends Extension_PluginSetup {
	const POINT = 'wgmjira.setup';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();

		$params = array(
			//'api_token' => DevblocksPlatform::getPluginSetting('wgm.jira','api_token',''),
			'base_url' => DevblocksPlatform::getPluginSetting('wgm.jira','base_url',''),
		);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.jira::setup/index.tpl');
	}
	
	function save(&$errors) {
		try {
			//@$api_token = DevblocksPlatform::importGPC($_REQUEST['api_token'],'string','');
			@$base_url = DevblocksPlatform::importGPC($_REQUEST['base_url'],'string','');
			
			if(empty($base_url))
				throw new Exception("The base URL is required.");
			
			$base_url = rtrim($base_url,'/');
			
			// Test connection
			$jira = WgmJira_API::getInstance();
			$jira->setBaseUrl($base_url);
			
			// Show the actual error
			if(false === $jira->getServerInfo())
				throw new Exception($jira->getLastError());
			
			DevblocksPlatform::setPluginSetting('wgm.jira','base_url',$base_url);

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
		$this->setBaseUrl($base_url);
	}

	public function setBaseUrl($url) {
		$this->_base_url = rtrim($url,'/');
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
	
	function getIssues($jql, $maxResults=100, $fields=null) {
		$params = array(
			'jql' => $jql,
			'maxResults' => $maxResults,
		);
		
		if(!empty($fields) && is_string($fields))
			$params['fields'] = $fields;
		
		return $this->_get('/rest/api/2/search', $params);
	}
	
	function getLastError() {
		return current($this->_errors);
	}
	
	private function _get($path, $params=array()) {
		if(empty($this->_base_url))
			return false;
		
		$url = $this->_base_url . $path;

		if(!empty($params) && is_array($params))
			$url .= '?' . http_build_query($params);
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$out = curl_exec($ch);

		$info = curl_getinfo($ch);
		
		// [TODO] This can fail without HTTPS
		
		if(curl_errno($ch)) {
			$this->_errors = array(curl_error($ch));
			$json = false;
		} elseif(false == ($json = json_decode($out))) {
			$this->_errors = array('The Base URL does not point to a valid JIRA installation.');
			$json = false;
		} else {
			$this->_errors = array();
		}
		
		curl_close($ch);
		return $json;
	} 
};

if(class_exists('Extension_PageSection')):
class WgmJira_IssueProfileSection extends Extension_PageSection {
	const ID = 'cerberusweb.profiles.jira.issue';
	
	function render() {
	}
	
	function syncAction() {
		$jira = WgmJira_API::getInstance();

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
		if(false == ($projects = $jira->getProjects()))
			return;
		
		if(is_array($projects))
		foreach($projects as $project_meta) {
			if(false == ($project = $jira->getProject($project_meta->key)))
				continue;
			
			$issue_types = array();
			$versions = array();
			
			if(isset($project->issueTypes) && is_array($project->issueTypes))
			foreach($project->issueTypes as $object) {
				unset($object->self);
				$issue_types[$object->id] = $object;
			}
			
			if(isset($project->versions) && is_array($project->versions))
			foreach($project->versions as $object) {
				unset($object->self);
				$versions[$object->id] = $object;
			}
			
			$fields = array(
				DAO_JiraProject::JIRA_ID => $project->id,
				DAO_JiraProject::JIRA_KEY => $project->key,
				DAO_JiraProject::NAME => $project->name,
				DAO_JiraProject::URL => $project->url,
				DAO_JiraProject::ISSUETYPES_JSON => json_encode($issue_types),
				DAO_JiraProject::STATUSES_JSON => json_encode($statuses),
				DAO_JiraProject::VERSIONS_JSON => json_encode($versions),
			);
			
			$local_project = DAO_JiraProject::getByJiraId($project->id);
			
			if(!empty($local_project)) {
				DAO_JiraProject::update($local_project->id, $fields);
				
			} else {
				$local_id = DAO_JiraProject::create($fields);
				$local_project = DAO_JiraProject::get($local_id); 
			}

			// Resume from last sync date
			
			if(false == ($response = $jira->getIssues(
					sprintf("project='%s' AND updated > %d000 ORDER BY updated ASC", $local_project->jira_key, date('U', $local_project->last_synced_at)),
					100,
					'summary,created,updated,description,status,issuetype,fixVersions'
					)
				))
				continue;
			
			if(!isset($response->issues) || !is_array($response->issues))
				continue;
			
			// $response->startAt
			// $response->maxResults
			// $response->total
			
			//var_dump($response->issues);

			$last_updated_date = 0;
			
			if(isset($response->issues) && is_array($response->issues))
			foreach($response->issues as $object) {
				$last_updated_date = strtotime($object->fields->updated);
			
				$fields = array(
					DAO_JiraIssue::JIRA_ID => $object->id,
					DAO_JiraIssue::JIRA_KEY => $object->key,
					DAO_JiraIssue::JIRA_STATUS_ID => $object->fields->status->id,
					DAO_JiraIssue::JIRA_TYPE_ID => $object->fields->issuetype->id,
					//DAO_JiraIssue::JIRA_VERSION_ID => $object->fields->version->id,
					DAO_JiraIssue::PROJECT_ID => $local_project->id,
					DAO_JiraIssue::SUMMARY => $object->fields->summary,
					DAO_JiraIssue::CREATED => strtotime($object->fields->created),
					DAO_JiraIssue::UPDATED => $last_updated_date,
				);
				
				$local_issue = DAO_JiraIssue::getByJiraId($object->id);
				
				if(!empty($local_issue)) {
					$local_issue_id = $local_issue->id;
					DAO_JiraIssue::update($local_issue_id, $fields);
					
				} else {
					$local_issue_id = DAO_JiraIssue::create($fields);
				}
				
				// [TODO] Store description content
			}
			
			// Set the last updated date on the project
			
			if(!empty($last_updated_date)) {
				DAO_JiraProject::update($local_project->id, array(
					DAO_JiraProject::LAST_SYNCED_AT => $last_updated_date,
				));
			}
		}
	}
};
endif;