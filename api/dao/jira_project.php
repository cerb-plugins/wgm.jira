<?php
class DAO_JiraProject extends Cerb_ORMHelper {
	const _CACHE_ALL = 'cache_jira_project_all';
	
	const ID = 'id';
	const JIRA_ID = 'jira_id';
	const JIRA_KEY = 'jira_key';
	const NAME = 'name';
	const URL = 'url';
	const ISSUETYPES_JSON = 'issuetypes_json';
	const STATUSES_JSON = 'statuses_json';
	const VERSIONS_JSON = 'versions_json';
	const LAST_CHECKED_AT = 'last_checked_at';
	const LAST_SYNCED_AT = 'last_synced_at';
	const LAST_SYNCED_CHECKPOINT = 'last_synced_checkpoint';
	const IS_SYNC = 'is_sync';

	static function create($fields, $check_deltas=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO jira_project () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields, $check_deltas);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(Context_JiraProject::ID, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'jira_project', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.jira_project.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(Context_JiraProject::ID, $batch_ids);
			}
		}
		
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('jira_project', $fields, $where);
	}
	
	/**
	 * @param bool $nocache
	 * @return Model_JiraProject[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		if($nocache || null === ($projects = $cache->load(self::_CACHE_ALL))) {
			$projects = self::getWhere(
				null,
				DAO_JiraProject::NAME,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			
			if(!is_array($projects))
				return false;
			
			$cache->save($projects, self::_CACHE_ALL);
		}
		
		return $projects;
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_JiraProject[]
	 */
	static function getWhere($where=null, $sortBy=DAO_JiraProject::NAME, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, jira_id, jira_key, name, url, issuetypes_json, statuses_json, versions_json, last_checked_at, last_synced_at, last_synced_checkpoint, is_sync ".
			"FROM jira_project ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;

		if($options & Cerb_ORMHelper::OPT_GET_MASTER_ONLY) {
			$rs = $db->ExecuteMaster($sql, _DevblocksDatabaseManager::OPT_NO_READ_AFTER_WRITE);
		} else {
			$rs = $db->ExecuteSlave($sql);
		}
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_JiraProject
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$projects = DAO_JiraProject::getAll();
		
		if(isset($projects[$id]))
			return $projects[$id];
		
		return null;
	}
	
	static function random() {
		return self::_getRandom('jira_project');
	}
	
	/**
	 *
	 * @param integer $remote_id
	 * @return Model_JiraProject|null
	 */
	static function getByJiraId($remote_id, $nocache=false) {
		// If we're ignoring the cache, check the database directly
		if($nocache) {
			$results = DAO_JiraProject::getWhere(
				sprintf("%s = %d",
					DAO_JiraProject::JIRA_ID,
					$remote_id
				)
			);
			
			if(is_array($results) && !empty($results))
				return array_shift($results);
			
			return null;
		}
		
		
		$projects = DAO_JiraProject::getAll();
		
		foreach($projects as $project_id => $project) { /* @var $project Model_JiraProject */
			if($project->jira_id == $remote_id)
				return $project;
		}
		
		return null;
	}
	
	/**
	 *
	 * @param string $jira_key
	 * @return Model_JiraProject|null
	 */
	static function getByJiraKey($jira_key) {
		$projects = DAO_JiraProject::getAll();
		
		foreach($projects as $project_id => $project) { /* @var $project Model_JiraProject */
			if($project->jira_key == $jira_key)
				return $project;
		}
		
		return null;
	}
	
	static function getAllTypes() {
		$results = array();
		
		$projects = DAO_JiraProject::getAll();
		
		foreach($projects as $project) {
			foreach($project->issue_types as $type_id => $type) {
				$results[$type_id] = $type;
			}
		}
		
		return $results;
	}
	
	static function getAllStatuses() {
		$results = array();
		
		$projects = DAO_JiraProject::getAll();
		
		foreach($projects as $project) {
			foreach($project->statuses as $status_id => $status) {
				$results[$status_id] = $status;
			}
		}
		
		return $results;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_JiraProject[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_JiraProject();
			$object->id = intval($row['id']);
			$object->jira_id = $row['jira_id'];
			$object->jira_key = $row['jira_key'];
			$object->name = $row['name'];
			$object->url = $row['url'];
			$object->last_checked_at = intval($row['last_checked_at']);
			$object->last_synced_at = intval($row['last_synced_at']);
			$object->last_synced_checkpoint = intval($row['last_synced_checkpoint']);
			$object->is_sync = intval($row['is_sync']) ? true : false;
			
			if(false !== (@$obj = json_decode($row['issuetypes_json'], true))) {
				$object->issue_types = $obj;
			}
			
			if(false !== (@$obj = json_decode($row['statuses_json'], true))) {
				$object->statuses = $obj;
			}
			
			if(false !== (@$obj = json_decode($row['versions_json'], true))) {
				$object->versions = $obj;
			}

			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM jira_project WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => Context_JiraProject::ID,
					'context_ids' => $ids
				)
			)
		);
		
		self::clearCache();
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_JiraProject::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_JiraProject', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"jira_project.id as %s, ".
			"jira_project.jira_id as %s, ".
			"jira_project.jira_key as %s, ".
			"jira_project.name as %s, ".
			"jira_project.url as %s, ".
			"jira_project.last_checked_at as %s, ".
			"jira_project.last_synced_at as %s, ".
			"jira_project.is_sync as %s ",
				SearchFields_JiraProject::ID,
				SearchFields_JiraProject::JIRA_ID,
				SearchFields_JiraProject::JIRA_KEY,
				SearchFields_JiraProject::NAME,
				SearchFields_JiraProject::URL,
				SearchFields_JiraProject::LAST_CHECKED_AT,
				SearchFields_JiraProject::LAST_SYNCED_AT,
				SearchFields_JiraProject::IS_SYNC
			);
			
		$join_sql = "FROM jira_project ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_JiraProject');
	
		// Virtuals
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
		);
		
		array_walk_recursive(
			$params,
			array('DAO_JiraProject', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'jira_project',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		$from_context = Context_JiraProject::ID;
		$from_index = 'jira_project.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_JiraProject::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
			
		if($limit > 0) {
			if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
				return false;
		} else {
			if(false == ($rs = $db->ExecuteSlave($sql)))
				return false;
			$total = mysqli_num_rows($rs);
		}
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_JiraProject::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(jira_project.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

	static public function clearCache() {
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::_CACHE_ALL);
	}
	
};

class SearchFields_JiraProject extends DevblocksSearchFields {
	const ID = 'j_id';
	const JIRA_ID = 'j_jira_id';
	const JIRA_KEY = 'j_jira_key';
	const NAME = 'j_name';
	const URL = 'j_url';
	const ISSUETYPES_JSON = 'j_issuetypes_json';
	const STATUSES_JSON = 'j_statuses_json';
	const VERSIONS_JSON = 'j_versions_json';
	const LAST_CHECKED_AT = 'j_last_checked_at';
	const LAST_SYNCED_AT = 'j_last_synced_at';
	const IS_SYNC = 'j_is_sync';

	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'jira_project.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			Context_JiraProject::ID => new DevblocksSearchFieldContextKeys('jira_project.id', self::ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, Context_JiraProject::ID, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_WATCHERS:
				return self::_getWhereSQLFromWatchersField($param, Context_JiraProject::ID, self::getPrimaryKey());
				break;
			
			default:
				if('cf_' == substr($param->field, 0, 3)) {
					return self::_getWhereSQLFromCustomFields($param);
				} else {
					return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
				}
				break;
		}
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'jira_project', 'id', $translate->_('common.id'), null, true),
			self::JIRA_ID => new DevblocksSearchField(self::JIRA_ID, 'jira_project', 'jira_id', $translate->_('dao.jira_project.jira_id'), null, true),
			self::JIRA_KEY => new DevblocksSearchField(self::JIRA_KEY, 'jira_project', 'jira_key', $translate->_('dao.jira_project.jira_key'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'jira_project', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::URL => new DevblocksSearchField(self::URL, 'jira_project', 'url', $translate->_('common.url'), Model_CustomField::TYPE_URL, true),
			self::ISSUETYPES_JSON => new DevblocksSearchField(self::ISSUETYPES_JSON, 'jira_project', 'issuetypes_json', $translate->_('dao.jira_project.issuetypes_json'), null, false),
			self::STATUSES_JSON => new DevblocksSearchField(self::STATUSES_JSON, 'jira_project', 'statuses_json', $translate->_('dao.jira_project.statuses_json'), null, false),
			self::VERSIONS_JSON => new DevblocksSearchField(self::VERSIONS_JSON, 'jira_project', 'versions_json', $translate->_('dao.jira_project.versions_json'), null, false),
			self::LAST_CHECKED_AT => new DevblocksSearchField(self::LAST_CHECKED_AT, 'jira_project', 'last_checked_at', $translate->_('dao.jira_project.last_checked_at'), Model_CustomField::TYPE_DATE, true),
			self::LAST_SYNCED_AT => new DevblocksSearchField(self::LAST_SYNCED_AT, 'jira_project', 'last_synced_at', $translate->_('dao.jira_project.last_synced_at'), Model_CustomField::TYPE_DATE, true),
			self::IS_SYNC => new DevblocksSearchField(self::IS_SYNC, 'jira_project', 'is_sync', $translate->_('dao.jira_project.is_sync'), Model_CustomField::TYPE_CHECKBOX, true),

			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS', false),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_JiraProject {
	public $id = 0;
	public $jira_id = null;
	public $jira_key = null;
	public $name = null;
	public $url = null;
	public $issue_types = array();
	public $statuses = array();
	public $versions = array();
	public $last_checked_at = 0;
	public $last_synced_at = 0;
	public $is_sync = false;
};

class View_JiraProject extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'jira_projects';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('JIRA Projects');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_JiraProject::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_JiraProject::NAME,
			SearchFields_JiraProject::JIRA_KEY,
			SearchFields_JiraProject::URL,
			SearchFields_JiraProject::LAST_CHECKED_AT,
			SearchFields_JiraProject::LAST_SYNCED_AT,
			SearchFields_JiraProject::IS_SYNC,
		);

		$this->addColumnsHidden(array(
			SearchFields_JiraProject::ID,
			SearchFields_JiraProject::ISSUETYPES_JSON,
			SearchFields_JiraProject::JIRA_ID,
			SearchFields_JiraProject::STATUSES_JSON,
			SearchFields_JiraProject::VERSIONS_JSON,
			SearchFields_JiraProject::VIRTUAL_CONTEXT_LINK,
			SearchFields_JiraProject::VIRTUAL_HAS_FIELDSET,
			SearchFields_JiraProject::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_JiraProject::ID,
			SearchFields_JiraProject::ISSUETYPES_JSON,
			SearchFields_JiraProject::JIRA_ID,
			SearchFields_JiraProject::STATUSES_JSON,
			SearchFields_JiraProject::VERSIONS_JSON,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_JiraProject::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_JiraProject');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_JiraProject', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_JiraProject', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_JiraProject::URL:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_JiraProject::VIRTUAL_CONTEXT_LINK:
				case SearchFields_JiraProject::VIRTUAL_HAS_FIELDSET:
				case SearchFields_JiraProject::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();
		$context = Context_JiraProject::ID;

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_JiraProject::URL:
				$counts = $this->_getSubtotalCountForStringColumn($context, $column);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn($context, $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_JiraProject::getFields();
		
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_JiraProject::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_JiraProject::ID),
					'examples' => [
						['type' => 'chooser', 'context' => 'cerberusweb.contexts.jira.project', 'q' => ''],
					]
				),
			'isSync' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_BOOL,
					'options' => array('param_key' => SearchFields_JiraProject::IS_SYNC),
				),
			'key' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_JiraProject::JIRA_KEY),
				),
			'lastCheckedAt' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_JiraProject::LAST_CHECKED_AT),
				),
			'lastSyncAt' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_JiraProject::LAST_SYNCED_AT),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_JiraProject::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'url' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_JiraProject::URL),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_JiraProject::VIRTUAL_WATCHERS),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links');
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(Context_JiraProject::ID, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			default:
				if($field == 'links' || substr($field, 0, 6) == 'links.')
					return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(Context_JiraProject::ID);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:wgm.jira::jira_project/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_JiraProject::JIRA_KEY:
			case SearchFields_JiraProject::NAME:
			case SearchFields_JiraProject::URL:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_JiraProject::ID:
			case SearchFields_JiraProject::JIRA_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_JiraProject::IS_SYNC:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_JiraProject::LAST_CHECKED_AT:
			case SearchFields_JiraProject::LAST_SYNCED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_JiraProject::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_JiraProject::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, Context_JiraProject::ID);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_JiraProject::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_JiraProject::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_JiraProject::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_JiraProject::JIRA_KEY:
			case SearchFields_JiraProject::NAME:
			case SearchFields_JiraProject::URL:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_JiraProject::ID:
			case SearchFields_JiraProject::JIRA_ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_JiraProject::LAST_CHECKED_AT:
			case SearchFields_JiraProject::LAST_SYNCED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_JiraProject::IS_SYNC:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_JiraProject::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
};

class Context_JiraProject extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	const ID = 'cerberusweb.contexts.jira.project';
	
	static function isReadableByActor($models, $actor) {
		// Everyone can view
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Everyone can modify
		return CerberusContexts::allowEverything($models);
	}
	
	function getRandom() {
		return DAO_JiraProject::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=jira_project&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$jira_project = DAO_JiraProject::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($jira_project->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $jira_project->id,
			'name' => $jira_project->name,
			'permalink' => $url,
			'updated' => 0, // [TODO]
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	function getDefaultProperties() {
		return array(
			'jira_key',
			'is_sync',
			'last_checked_at',
			'last_synced_at',
			'url',
		);
	}
	
	function getContext($jira_project, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Jira Project:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(Context_JiraProject::ID);

		// Polymorph
		if(is_numeric($jira_project)) {
			$jira_project_id = $jira_project;
			
			// [TODO] Can we standardize how we request this?
			
			// Try JIRA ID first
			if(false == ($jira_project = DAO_JiraProject::getByJiraId($jira_project_id)))
				// Then Cerb ID
				$jira_project = DAO_JiraProject::get($jira_project_id);
			
		} elseif($jira_project instanceof Model_JiraProject) {
			// It's what we want already.
		} elseif(is_array($jira_project)) {
			$jira_project = Cerb_ORMHelper::recastArrayToModel($jira_project, 'Model_JiraProject');
		} else {
			$jira_project = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'jira_key' => $prefix.$translate->_('dao.jira_project.jira_key'),
			'is_sync' => $prefix.$translate->_('dao.jira_project.is_sync'),
			'last_checked_at' => $prefix.$translate->_('dao.jira_project.last_checked_at'),
			'last_synced_at' => $prefix.$translate->_('dao.jira_project.last_synced_at'),
			'name' => $prefix.$translate->_('common.name'),
			'record_url' => $prefix.$translate->_('common.url.record'),
			'url' => $prefix.$translate->_('common.url'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'jira_key' => Model_CustomField::TYPE_SINGLE_LINE,
			'is_sync' => Model_CustomField::TYPE_CHECKBOX,
			'last_checked_at' => Model_CustomField::TYPE_DATE,
			'last_synced_at' => Model_CustomField::TYPE_DATE,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'record_url' => Model_CustomField::TYPE_URL,
			'url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = Context_JiraProject::ID;
		$token_values['_types'] = $token_types;
		
		if($jira_project) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $jira_project->name;
			$token_values['id'] = $jira_project->id;
			$token_values['jira_key'] = $jira_project->jira_key;
			$token_values['is_sync'] = $jira_project->is_sync;
			$token_values['last_checked_at'] = $jira_project->last_checked_at;
			$token_values['last_synced_at'] = $jira_project->last_synced_at;
			$token_values['name'] = $jira_project->name;
			$token_values['url'] = $jira_project->url;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($jira_project, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=jira_project&id=%d-%s",$jira_project->id, DevblocksPlatform::strToPermalink($jira_project->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = Context_JiraProject::ID;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $links);
				break;
			
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}

	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Jira Project';
		$view->renderSortBy = SearchFields_JiraProject::LAST_CHECKED_AT;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Jira Project';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_JiraProject::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(!empty($context_id) && null != ($jira_project = DAO_JiraProject::get($context_id))) {
			$tpl->assign('model', $jira_project);
		}
		
		$custom_fields = DAO_CustomField::getByContext(Context_JiraProject::ID, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(Context_JiraProject::ID, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}

		// Comments
		$comments = DAO_Comment::getByContext(Context_JiraProject::ID, $context_id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		$tpl->display('devblocks:wgm.jira::jira_project/peek.tpl');
	}
};