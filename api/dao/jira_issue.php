<?php
class DAO_JiraIssue extends Cerb_ORMHelper {
	const ID = 'id';
	const PROJECT_ID = 'project_id';
	const JIRA_ID = 'jira_id';
	const JIRA_KEY = 'jira_key';
	const JIRA_TYPE_ID = 'jira_type_id';
	const JIRA_STATUS_ID = 'jira_status_id';
	const SUMMARY = 'summary';
	const CREATED = 'created';
	const UPDATED = 'updated';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO jira_issue () VALUES ()";
		$db->Execute($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Get state before changes
			$object_changes = parent::_getUpdateDeltas($batch_ids, $fields, get_class());

			// Make changes
			parent::_update($batch_ids, 'jira_issue', $fields);
			
			// Send events
			if(!empty($object_changes)) {
				// Local events
				//self::_processUpdateEvents($object_changes);
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.jira_issue.update',
						array(
							'objects' => $object_changes,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged('cerberusweb.contexts.jira.issue', $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('jira_issue', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_JiraIssue[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, project_id, jira_id, jira_key, jira_type_id, jira_status_id, summary, created, updated ".
			"FROM jira_issue ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 * @param integer $id
	 * @return Model_JiraIssue	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	static function getByJiraId($remote_id) {
		$results = self::getWhere(sprintf("%s = %d", self::JIRA_ID, $remote_id));
		
		if(empty($results))
			return NULL;
		
		return current($results);
	}
	
	/**
	 * @param resource $rs
	 * @return Model_JiraIssue[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_JiraIssue();
			$object->id = $row['id'];
			$object->project_id = $row['project_id'];
			$object->jira_id = $row['jira_id'];
			$object->jira_key = $row['jira_key'];
			$object->jira_type_id = $row['jira_type_id'];
			$object->jira_status_id = $row['jira_status_id'];
			$object->summary = $row['summary'];
			$object->created = $row['created'];
			$object->updated = $row['updated'];
			$objects[$object->id] = $object;
		}
		
		mysql_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->Execute(sprintf("DELETE FROM jira_issue WHERE id IN (%s)", $ids_list));
		
		// Fire event
		/*
	    $eventMgr = DevblocksPlatform::getEventService();
	    $eventMgr->trigger(
	        new Model_DevblocksEvent(
	            'context.delete',
                array(
                	'context' => 'cerberusweb.contexts.',
                	'context_ids' => $ids
                )
            )
	    );
	    */
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_JiraIssue::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

        list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"jira_issue.id as %s, ".
			"jira_issue.project_id as %s, ".
			"jira_issue.jira_id as %s, ".
			"jira_issue.jira_key as %s, ".
			"jira_issue.jira_type_id as %s, ".
			"jira_issue.jira_status_id as %s, ".
			"jira_issue.summary as %s, ".
			"jira_issue.created as %s, ".
			"jira_issue.updated as %s ",
				SearchFields_JiraIssue::ID,
				SearchFields_JiraIssue::PROJECT_ID,
				SearchFields_JiraIssue::JIRA_ID,
				SearchFields_JiraIssue::JIRA_KEY,
				SearchFields_JiraIssue::JIRA_TYPE_ID,
				SearchFields_JiraIssue::JIRA_STATUS_ID,
				SearchFields_JiraIssue::SUMMARY,
				SearchFields_JiraIssue::CREATED,
				SearchFields_JiraIssue::UPDATED
			);
			
		$join_sql = "FROM jira_issue ";
		
		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'jira_issue.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
	
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_JiraIssue', '_translateVirtualParameters'),
			$args
		);
	
		return array(
			'primary_table' => 'jira_issue',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		//$from_context = CerberusContexts::CONTEXT_EXAMPLE;
		//$from_index = 'example.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			/*
			case SearchFields_EXAMPLE::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
				break;
			*/
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
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY jira_issue.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
    		$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
		    $rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
            $total = mysql_num_rows($rs);
		}
		
		$results = array();
		$total = -1;
		
		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($row[SearchFields_JiraIssue::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
				($has_multiple_values ? "SELECT COUNT(DISTINCT jira_issue.id) " : "SELECT COUNT(jira_issue.id) ").
				$join_sql.
				$where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		mysql_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_JiraIssue implements IDevblocksSearchFields {
	const ID = 'j_id';
	const PROJECT_ID = 'j_project_id';
	const JIRA_ID = 'j_jira_id';
	const JIRA_KEY = 'j_jira_key';
	const JIRA_TYPE_ID = 'j_jira_type_id';
	const JIRA_STATUS_ID = 'j_jira_status_id';
	const SUMMARY = 'j_summary';
	const CREATED = 'j_created';
	const UPDATED = 'j_updated';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'jira_issue', 'id', $translate->_('common.id'), null),
			self::PROJECT_ID => new DevblocksSearchField(self::PROJECT_ID, 'jira_issue', 'project_id', $translate->_('dao.jira_issue.project_id'), null),
			self::JIRA_ID => new DevblocksSearchField(self::JIRA_ID, 'jira_issue', 'jira_id', $translate->_('dao.jira_issue.jira_id'), null),
			self::JIRA_KEY => new DevblocksSearchField(self::JIRA_KEY, 'jira_issue', 'jira_key', $translate->_('dao.jira_issue.jira_key'), Model_CustomField::TYPE_SINGLE_LINE),
			self::JIRA_TYPE_ID => new DevblocksSearchField(self::JIRA_TYPE_ID, 'jira_issue', 'jira_type_id', $translate->_('dao.jira_issue.jira_type_id'), null),
			self::JIRA_STATUS_ID => new DevblocksSearchField(self::JIRA_STATUS_ID, 'jira_issue', 'jira_status_id', $translate->_('dao.jira_issue.jira_status_id'), null),
			self::SUMMARY => new DevblocksSearchField(self::SUMMARY, 'jira_issue', 'summary', $translate->_('dao.jira_issue.summary'), Model_CustomField::TYPE_SINGLE_LINE),
			self::CREATED => new DevblocksSearchField(self::CREATED, 'jira_issue', 'created', $translate->_('common.created'), Model_CustomField::TYPE_DATE),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'jira_issue', 'updated', $translate->_('common.updated'), Model_CustomField::TYPE_DATE),
		);
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			'cerberusweb.contexts.jira.issue',
		));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_JiraIssue {
	public $id;
	public $project_id;
	public $jira_id;
	public $jira_key;
	public $jira_type_id;
	public $jira_status_id;
	public $summary;
	public $created;
	public $updated;
};

class View_JiraIssue extends C4_AbstractView implements IAbstractView_Subtotals {
	const DEFAULT_ID = 'jira_issues';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('JIRA Issues');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_JiraIssue::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_JiraIssue::JIRA_KEY,
			SearchFields_JiraIssue::PROJECT_ID,
			SearchFields_JiraIssue::JIRA_TYPE_ID,
			SearchFields_JiraIssue::JIRA_STATUS_ID,
			SearchFields_JiraIssue::UPDATED,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_JiraIssue::JIRA_ID,
		));
		
		$this->addParamsHidden(array(
			SearchFields_JiraIssue::JIRA_ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_JiraIssue::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_JiraIssue', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_JiraIssue', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_JiraIssue::PROJECT_ID:
				case SearchFields_JiraIssue::JIRA_STATUS_ID:
				case SearchFields_JiraIssue::JIRA_TYPE_ID:
					$pass = true;
					break;
					
				// Virtuals
// 				case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
// 				case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
// 					$pass = true;
// 					break;
					
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

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_JiraIssue::PROJECT_ID:
				$label_map = array();
				
				$projects = DAO_JiraProject::getAll();
				foreach($projects as $project_id => $project)
					$label_map[$project_id] = $project->name;
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_JiraIssue', $column, $label_map, 'in', 'options[]');
				break;
				
			case SearchFields_JiraIssue::JIRA_STATUS_ID:
				$label_map = array();
				
				$projects = DAO_JiraProject::getAll();
				$project = current($projects);
				
				if(isset($project->statuses))
				foreach($project->statuses as $status_id => $status) {
					$label_map[$status_id] = $status['name'];
				}
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_JiraIssue', $column, $label_map, 'in', 'options[]');
				break;
				
			case SearchFields_JiraIssue::JIRA_TYPE_ID:
				$label_map = array();

				$types = DAO_JiraProject::getAllTypes();
				foreach($types as $type_id => $type) {
					$label_map[$type_id] = $type['name'];
				}
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_JiraIssue', $column, $label_map, 'in', 'options[]');
				break;
				
// 			case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
// 				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_JiraIssue', 'cerberusweb.contexts.jira.issue', $column);
// 				break;
				
// 			case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
// 				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_JiraIssue', $column);
// 				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_JiraIssue', $column, 'jira_issue.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		//$custom_fields = DAO_CustomField::getByContext(CerberusContexts::XXX);
		//$tpl->assign('custom_fields', $custom_fields);

		// Projects
		
		$projects = DAO_JiraProject::getAll();
		$tpl->assign('projects', $projects);
		
		// Template
		
		$tpl->assign('view_template', 'devblocks:wgm.jira::issue/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_JiraIssue::JIRA_KEY:
			case SearchFields_JiraIssue::SUMMARY:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_JiraIssue::ID:
			case SearchFields_JiraIssue::JIRA_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_JiraIssue::CREATED:
			case SearchFields_JiraIssue::UPDATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_JiraIssue::PROJECT_ID:
				$options = array();
				
				$projects = DAO_JiraProject::getAll();
				if(is_array($projects))
				foreach($projects as $project_id => $project) {
					$options[$project_id] = $project->name;
				}
				
				asort($options);
				$tpl->assign('options', $options);
				
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			case SearchFields_JiraIssue::JIRA_STATUS_ID:
				$options = array();
				
				$projects = DAO_JiraProject::getAll();
				$project = array_shift($projects);
				
				if(isset($project->statuses) && is_array($project->statuses))
				foreach($project->statuses as $k => $v) {
					$options[$k] = $v['name'];
				}
				
				asort($options);
				$tpl->assign('options', $options);
				
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			case SearchFields_JiraIssue::JIRA_TYPE_ID:
				$options = array();
				
				$projects = DAO_JiraProject::getAll();
				if(is_array($projects))
				foreach($projects as $project) {
					if(is_array($project->issue_types))
					foreach($project->issue_types as $k => $v) {
						$options[$k] = sprintf("%s: %s", $project->name, $v['name']);
					}
				}
				
				asort($options);
				$tpl->assign('options', $options);
				
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
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
			case SearchFields_JiraIssue::PROJECT_ID:
				$strings = array();
				$projects = DAO_JiraProject::getAll();
				
				foreach($values as $v) {
					if(isset($projects[$v]))
						$strings[] = $projects[$v]->name;
				}
				
				echo implode(' or ', $strings);
				break;
				
			case SearchFields_JiraIssue::JIRA_STATUS_ID:
				$strings = array();
				$projects = DAO_JiraProject::getAll();
				$project = array_shift($projects);
				
				foreach($values as $v) {
					if(isset($project->statuses[$v]))
						$strings[] = $project->statuses[$v]['name'];
				}
				
				echo implode(' or ', $strings);
				break;
				
			case SearchFields_JiraIssue::JIRA_TYPE_ID:
				$strings = array();
				$projects = DAO_JiraProject::getAll();
				
				foreach($values as $v) {
					foreach($projects as $project) {
						if(isset($project->issue_types[$v]))
							$strings[] = $project->issue_types[$v]['name'];
					}
				}
				
				echo implode(' or ', $strings);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
		}
	}

	function getFields() {
		return SearchFields_JiraIssue::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_JiraIssue::JIRA_KEY:
			case SearchFields_JiraIssue::SUMMARY:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_JiraIssue::ID:
			case SearchFields_JiraIssue::JIRA_ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_JiraIssue::CREATED:
			case SearchFields_JiraIssue::UPDATED:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_JiraIssue::PROJECT_ID:
			case SearchFields_JiraIssue::JIRA_STATUS_ID:
			case SearchFields_JiraIssue::JIRA_TYPE_ID:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$options = DevblocksPlatform::sanitizeArray($options, 'integer', array('nonzero','unique'));
				$criteria = new DevblocksSearchCriteria($field,$oper,$options);
				break;
				
			/*
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
			*/
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
		
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
	
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_JiraIssue::EXAMPLE] = 'some value';
					break;
				/*
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
				*/
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_JiraIssue::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_JiraIssue::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields)) {
				DAO_JiraIssue::update($batch_ids, $change_fields);
			}

			// Custom Fields
			//self::_doBulkSetCustomFields(ChCustomFieldSource_JiraIssue::ID, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_JiraIssue extends Extension_DevblocksContext {
	const ID = 'cerberusweb.contexts.jira.issue';
	
	function getRandom() {
		//return DAO_JiraIssue::random();
	}
	
	function getMeta($context_id) {
		$issue = DAO_JiraIssue::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		//$friendly = DevblocksPlatform::strToPermalink($example->name);
		
		return array(
			'id' => $issue->id,
			'name' => $issue->summary,
			'permalink' => $url_writer->writeNoProxy(sprintf("c=profiles&=type=jira_issue&id=%d",$context_id), true),
		);
	}
	
	function getContext($issue, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'JIRA Issue:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(Context_JiraIssue::ID);

		// Polymorph
		if(is_numeric($issue)) {
			$issue = DAO_JiraIssue::get($issue);
		} elseif($issue instanceof Model_JiraIssue) {
			// It's what we want already.
		} else {
			$issue = null;
		}
		
		// Token labels
		$token_labels = array(
			'id' => $prefix.$translate->_('common.id'),
			'jira_key' => $prefix.$translate->_('dao.jira_issue.jira_key'),
			'summary' => $prefix.$translate->_('dao.jira_issue.summary'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = Context_JiraIssue::ID;
		
		if($issue) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = '[' . $issue->jira_key . '] ' . $issue->summary;
			$token_values['id'] = $issue->id;
			$token_values['jira_key'] = $issue->jira_key;
			$token_values['summary'] = $issue->summary;
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&a=jira_issue&id=%d-%s", $issue->id, DevblocksPlatform::strToPermalink($issue->summary)), true);
		}

		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = Context_JiraIssue::ID;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($context, $context_id);
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
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->view_columns = array(
			SearchFields_JiraIssue::JIRA_KEY,
			SearchFields_JiraIssue::PROJECT_ID,
			SearchFields_JiraIssue::JIRA_TYPE_ID,
			SearchFields_JiraIssue::JIRA_STATUS_ID,
			SearchFields_JiraIssue::UPDATED,
		);
		$view->addParams(array(
		), true);
		$view->renderSortBy = SearchFields_JiraIssue::UPDATED;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		$view->renderFilters = false;
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array()) {
		$view_id = str_replace('.','_',$this->id);
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_JiraIssue::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_JiraIssue::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
};