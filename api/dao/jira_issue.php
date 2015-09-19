<?php
class DAO_JiraIssue extends Cerb_ORMHelper {
	const ID = 'id';
	const PROJECT_ID = 'project_id';
	const JIRA_ID = 'jira_id';
	const JIRA_KEY = 'jira_key';
	const JIRA_TYPE_ID = 'jira_type_id';
	const JIRA_VERSIONS = 'jira_versions';
	const JIRA_STATUS_ID = 'jira_status_id';
	const SUMMARY = 'summary';
	const CREATED = 'created';
	const UPDATED = 'updated';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO jira_issue () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
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
				CerberusContexts::checkpointChanges('cerberusweb.contexts.jira.issue', $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'jira_issue', $fields);
			
			// Send events
			if($check_deltas) {
				// Local events
				self::_processUpdateEvents($batch_ids, $fields);
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.jira_issue.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged('cerberusweb.contexts.jira.issue', $batch_ids);
			}
		}
	}
	
	static function _processUpdateEvents($ids, $change_fields) {
		// We only care about these fields, so abort if they aren't referenced

		$observed_fields = array(
			DAO_JiraIssue::JIRA_STATUS_ID,
		);
		
		$used_fields = array_intersect($observed_fields, array_keys($change_fields));
		
		if(empty($used_fields))
			return;
		
		// Load records only if they're needed
		
		if(false == ($before_models = CerberusContexts::getCheckpoints('cerberusweb.contexts.jira.issue', $ids)))
			return;
		
		foreach($before_models as $id => $before_model) {
			$before_model = (object) $before_model; /* @var $before_model Model_JiraIssue */
			
			/*
			 * Status change
			 */
			// [TODO] Fold into 'Record changed'
			
			@$status_id = $change_fields[DAO_JiraIssue::JIRA_STATUS_ID];
			
			if($status_id == $before_model->jira_status_id)
				unset($change_fields[DAO_JiraIssue::JIRA_STATUS_ID]);
			
			if(isset($change_fields[DAO_JiraIssue::JIRA_STATUS_ID])) {
				Event_JiraIssueStatusChanged::trigger($id);
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
		$sql = "SELECT id, project_id, jira_id, jira_key, jira_versions, jira_type_id, jira_status_id, summary, created, updated ".
			"FROM jira_issue ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 * @param integer $id
	 * @return Model_JiraIssue	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	static function random() {
		return self::_getRandom('jira_issue');
	}
	
	static function randomComment($issue_id=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// With a specific issue ID?
		if($issue_id && false != ($comment_id = $db->GetOneSlave(sprintf("SELECT jira_comment_id FROM jira_issue_comment WHERE jira_issue_id = %d ORDER BY RAND() LIMIT 1", $issue_id))))
			return $comment_id;
		
		return $db->GetOneSlave("SELECT jira_comment_id FROM jira_issue_comment ORDER BY RAND() LIMIT 1");
	}
	
	static function getByJiraId($remote_id) {
		$results = self::getWhere(sprintf("%s = %d", self::JIRA_ID, $remote_id));
		
		if(empty($results))
			return NULL;
		
		return current($results);
	}
	
	static function getDescription($issue_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		return $db->GetOneSlave(sprintf("SELECT description FROM jira_issue_description WHERE jira_issue_id = %d", $issue_id));
	}
	
	static function setDescription($issue_id, $description) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$db->ExecuteMaster(sprintf("REPLACE INTO jira_issue_description (jira_issue_id, description) VALUES (%d, %s)",
			$issue_id,
			$db->qstr($description)
		));
		
		return TRUE;
	}
	
	static function setVersions($issue_id, $fix_version_ids) {
		if(!is_array($fix_version_ids)) $fix_version_ids = array($fix_version_ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		$db->ExecuteMaster(sprintf("DELETE FROM jira_issue_to_version WHERE jira_issue_id = %d", $issue_id));
		
		foreach($fix_version_ids as $fix_version_id) {
			$db->ExecuteMaster(sprintf("INSERT INTO jira_issue_to_version (jira_issue_id, jira_version_id) VALUES (%d, %d)",
				$issue_id,
				$fix_version_id
			));
		}
		
		return TRUE;
	}
	
	static function saveComment($comment_id, $issue_id, $created, $author, $body) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$result = $db->ExecuteMaster(sprintf("REPLACE INTO jira_issue_comment (jira_comment_id, jira_issue_id, created, jira_author, body) ".
			"VALUES (%d, %d, %d, %s, %s)",
			$comment_id,
			$issue_id,
			$created,
			$db->qstr($author),
			$db->qstr($body)
		));
		
		return $db->Affected_Rows();
	}
	
	static function getComments($issue_id) {
		$db = DevblocksPlatform::getDatabaseService();

		$results = $db->GetArraySlave(sprintf("SELECT jira_comment_id, jira_issue_id, created, jira_author, body ".
			"FROM jira_issue_comment ".
			"WHERE jira_issue_id = %d ".
			"ORDER BY created DESC",
			$issue_id
		));
		
		return $results;
	}
	
	static function getComment($comment_id) {
		$db = DevblocksPlatform::getDatabaseService();

		$results = $db->GetRowSlave(sprintf("SELECT jira_comment_id, jira_issue_id, created, jira_author, body ".
			"FROM jira_issue_comment ".
			"WHERE jira_comment_id = %d ".
			"ORDER BY created DESC",
			$comment_id
		));
		
		return $results;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_JiraIssue[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_JiraIssue();
			$object->id = $row['id'];
			$object->project_id = $row['project_id'];
			$object->jira_id = $row['jira_id'];
			$object->jira_key = $row['jira_key'];
			$object->jira_versions = $row['jira_versions'];
			$object->jira_type_id = $row['jira_type_id'];
			$object->jira_status_id = $row['jira_status_id'];
			$object->summary = $row['summary'];
			$object->created = $row['created'];
			$object->updated = $row['updated'];
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
		
		$db->ExecuteMaster(sprintf("DELETE FROM jira_issue WHERE id IN (%s)", $ids_list));
		
		// Cascade delete to linked tables
		$db->ExecuteMaster("DELETE FROM jira_issue_comment WHERE jira_issue_id NOT IN (SELECT jira_id FROM jira_issue)");
		$db->ExecuteMaster("DELETE FROM jira_issue_description WHERE jira_issue_id NOT IN (SELECT jira_id FROM jira_issue)");
		$db->ExecuteMaster("DELETE FROM jira_issue_to_version WHERE jira_issue_id NOT IN (SELECT jira_id FROM jira_issue)");
		
		// [TODO] Maint
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => 'cerberusweb.contexts.jira.issue',
					'context_ids' => $ids
				)
			)
		);
		
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
			"jira_issue.jira_versions as %s, ".
			"jira_issue.jira_type_id as %s, ".
			"jira_issue.jira_status_id as %s, ".
			"jira_issue.summary as %s, ".
			"jira_issue.created as %s, ".
			"jira_issue.updated as %s ",
				SearchFields_JiraIssue::ID,
				SearchFields_JiraIssue::PROJECT_ID,
				SearchFields_JiraIssue::JIRA_ID,
				SearchFields_JiraIssue::JIRA_KEY,
				SearchFields_JiraIssue::JIRA_VERSIONS,
				SearchFields_JiraIssue::JIRA_TYPE_ID,
				SearchFields_JiraIssue::JIRA_STATUS_ID,
				SearchFields_JiraIssue::SUMMARY,
				SearchFields_JiraIssue::CREATED,
				SearchFields_JiraIssue::UPDATED
			);
			
		$join_sql = "FROM jira_issue ".
			(isset($tables['context_link']) ? "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.jira.issue' AND context_link.to_context_id = jira_issue.id) " : " ").
			'';
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'jira_issue.id',
			$select_sql,
			$join_sql
		);
		
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
			
		$from_context = 'cerberusweb.contexts.jira.issue';
		$from_index = 'jira_issue.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			// [TODO] Attribute for project id
			case SearchFields_JiraIssue::FULLTEXT_CONTENT:
				$search = Extension_DevblocksSearchSchema::get(Search_JiraIssue::ID);
				$query = $search->getQueryFromParam($param);
				
				if(false === ($ids = $search->query($query, array()))) {
					$args['where_sql'] .= 'AND 0 ';
				
				} elseif(is_array($ids)) {
					$args['where_sql'] .= sprintf('AND %s IN (%s) ',
						$from_index,
						implode(', ', (!empty($ids) ? $ids : array(-1)))
					);
					
				} elseif(is_string($ids)) {
					$args['join_sql'] .= sprintf("INNER JOIN %s ON (%s.id=jira_issue.id) ",
						$ids,
						$ids
					);
				}
				break;
			
			case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_JiraIssue::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
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
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY jira_issue.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_JiraIssue::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT jira_issue.id) " : "SELECT COUNT(jira_issue.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_JiraIssue implements IDevblocksSearchFields {
	const ID = 'j_id';
	const PROJECT_ID = 'j_project_id';
	const JIRA_ID = 'j_jira_id';
	const JIRA_KEY = 'j_jira_key';
	const JIRA_VERSIONS = 'j_jira_versions';
	const JIRA_TYPE_ID = 'j_jira_type_id';
	const JIRA_STATUS_ID = 'j_jira_status_id';
	const SUMMARY = 'j_summary';
	const CREATED = 'j_created';
	const UPDATED = 'j_updated';
	
	const FULLTEXT_CONTENT = 'ft_j_content';
	
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
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
			self::JIRA_VERSIONS => new DevblocksSearchField(self::JIRA_VERSIONS, 'jira_issue', 'jira_versions', $translate->_('dao.jira_issue.jira_versions'), Model_CustomField::TYPE_SINGLE_LINE),
			self::JIRA_TYPE_ID => new DevblocksSearchField(self::JIRA_TYPE_ID, 'jira_issue', 'jira_type_id', $translate->_('dao.jira_issue.jira_type_id'), null),
			self::JIRA_STATUS_ID => new DevblocksSearchField(self::JIRA_STATUS_ID, 'jira_issue', 'jira_status_id', $translate->_('dao.jira_issue.jira_status_id'), null),
			self::SUMMARY => new DevblocksSearchField(self::SUMMARY, 'jira_issue', 'summary', $translate->_('dao.jira_issue.summary'), Model_CustomField::TYPE_SINGLE_LINE),
			self::CREATED => new DevblocksSearchField(self::CREATED, 'jira_issue', 'created', $translate->_('common.created'), Model_CustomField::TYPE_DATE),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'jira_issue', 'updated', $translate->_('common.updated'), Model_CustomField::TYPE_DATE),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
				
			self::FULLTEXT_CONTENT => new DevblocksSearchField(self::FULLTEXT_CONTENT, 'ft', 'content', $translate->_('common.content'), 'FT'),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_CONTENT]->ft_schema = Search_JiraIssue::ID;
		
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

class Search_JiraIssue extends Extension_DevblocksSearchSchema {
	const ID = 'jira.search.schema.jira_issue';
	
	public function getNamespace() {
		return 'jira_issue';
	}
	
	public function getAttributes() {
		return array();
	}
	
	public function query($query, $attributes=array(), $limit=500) {
		if(false == ($engine = $this->getEngine()))
			return false;
		
		$ids = $engine->query($this, $query, $attributes, $limit);
		
		return $ids;
	}
	
	public function reindex() {
		$engine = $this->getEngine();
		$meta = $engine->getIndexMeta($this);
		
		// If the index has a delta, start from the current record
		if($meta['is_indexed_externally']) {
			// Do nothing (let the remote tool update the DB)
			
		// Otherwise, start over
		} else {
			$this->setIndexPointer(self::INDEX_POINTER_RESET);
		}
	}
	
	public function setIndexPointer($pointer) {
		switch($pointer) {
			case self::INDEX_POINTER_RESET:
				$this->setParam('last_indexed_id', 0);
				$this->setParam('last_indexed_time', 0);
				break;
				
			case self::INDEX_POINTER_CURRENT:
				$this->setParam('last_indexed_id', 0);
				$this->setParam('last_indexed_time', time());
				break;
		}
	}
	
	public function index($stop_time=null) {
		$logger = DevblocksPlatform::getConsoleLog();
		
		if(false == ($engine = $this->getEngine()))
			return false;
		
		$ns = self::getNamespace();
		$id = $this->getParam('last_indexed_id', 0);
		$ptr_time = $this->getParam('last_indexed_time', 0);
		$ptr_id = $id;
		$done = false;

		while(!$done && time() < $stop_time) {
			$where = sprintf('(%1$s = %2$d AND %3$s > %4$d) OR (%1$s > %2$d)',
				DAO_JiraIssue::UPDATED,
				$ptr_time,
				DAO_JiraIssue::ID,
				$id
			);
			$issues = DAO_JiraIssue::getWhere($where, array(DAO_JiraIssue::UPDATED, DAO_JiraIssue::ID), array(true, true), 100);

			if(empty($issues)) {
				$done = true;
				continue;
			}
			
			$last_time = $ptr_time;
			
			foreach($issues as $issue) { /* @var $issue Model_JiraIssue */
				$id = $issue->id;
				$ptr_time = $issue->updated;
				
				$ptr_id = ($last_time == $ptr_time) ? $id : 0;
				
				$logger->info(sprintf("[Search] Indexing %s %d...",
					$ns,
					$id
				));
				
				$comments = $issue->getComments();
				
				$doc = array(
					'key' => $issue->jira_key,
					'summary' => $issue->summary,
					'description' => $issue->getDescription(),
					'comments' => array(),
				);

				if(is_array($comments))
				foreach($comments as $comment) {
					$doc['comments'] = array('content' => $comment['body']);
				}
				
				if(false === ($engine->index($this, $id, $doc)))
					return false;
				
				flush();
			}
		}
		
		// If we ran out of records, always reset the ID and use the current time
		if($done) {
			$ptr_id = 0;
			$ptr_time = time();
		}
		
		$this->setParam('last_indexed_id', $ptr_id);
		$this->setParam('last_indexed_time', $ptr_time);
	}
	
	public function delete($ids) {
		if(false == ($engine = $this->getEngine()))
			return false;
		
		return $engine->delete($this, $ids);
	}
};

class Model_JiraIssue {
	public $id;
	public $project_id;
	public $jira_id;
	public $jira_key;
	public $jira_versions;
	public $jira_type_id;
	public $jira_status_id;
	public $summary;
	public $created;
	public $updated;
	
	function getProject() {
		return DAO_JiraProject::getByJiraId($this->project_id);
	}
	
	function getType() {
		if(false == ($project = $this->getProject()))
			return null;
		
		return @$project->issue_types[$this->jira_type_id];
	}
	
	function getStatus() {
		if(false == ($project = $this->getProject()))
			return null;
		
		return @$project->statuses[$this->jira_status_id];
	}
	
	function getDescription() {
		return DAO_JiraIssue::getDescription($this->jira_id);
	}
	
	function getComments() {
		return DAO_JiraIssue::getComments($this->jira_id);
	}
};

class View_JiraIssue extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
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
			SearchFields_JiraIssue::JIRA_VERSIONS,
			SearchFields_JiraIssue::JIRA_TYPE_ID,
			SearchFields_JiraIssue::JIRA_STATUS_ID,
			SearchFields_JiraIssue::UPDATED,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_JiraIssue::JIRA_ID,
			SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK,
			SearchFields_JiraIssue::VIRTUAL_HAS_FIELDSET,
			SearchFields_JiraIssue::VIRTUAL_WATCHERS,
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
				case SearchFields_JiraIssue::JIRA_VERSIONS:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
				case SearchFields_JiraIssue::VIRTUAL_HAS_FIELDSET:
				case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
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

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_JiraIssue::JIRA_VERSIONS:
				$counts = $this->_getSubtotalCountForStringColumn('DAO_JiraIssue', $column);
				break;
				
			case SearchFields_JiraIssue::PROJECT_ID:
				$label_map = array();
				
				$projects = DAO_JiraProject::getAll();
				foreach($projects as $project_id => $project)
					$label_map[$project->jira_id] = $project->name;
				
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
				
			case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_JiraIssue', 'cerberusweb.contexts.jira.issue', $column);
				break;

			case SearchFields_JiraIssue::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_JiraIssue', 'cerberusweb.contexts.jira.issue', $column);
				break;
				
			case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_JiraIssue', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_JiraIssue', $column, 'jira_issue.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_JiraIssue::FULLTEXT_CONTENT),
				),
			'content' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_JiraIssue::FULLTEXT_CONTENT),
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_JiraIssue::CREATED),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_JiraIssue::ID),
				),
			'key' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_JiraIssue::JIRA_KEY, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
					'examples' => array(
						'CHD',
					),
			),
			'project' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_JiraIssue::PROJECT_ID),
					'examples' => array(
						'"Project Name"',
					),
			),
			'status' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_JiraIssue::JIRA_STATUS_ID),
					'examples' => array(
						'"open"',
						'"in progress"',
						'"reopened"',
						'"resolved"',
						'"closed"',
					),
			),
			'summary' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_JiraIssue::SUMMARY, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'type' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_JiraIssue::JIRA_TYPE_ID),
					'examples' => array(
						'bug',
						'epic',
						'feature',
						'improvement',
						'task',
					),
			),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_JiraIssue::UPDATED),
				),
			'version' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_JiraIssue::JIRA_VERSIONS, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
					'examples' => array(
						'1.*',
						'("2.0")',
					),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_JiraIssue::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext('cerberusweb.contexts.jira.issue', $fields, null);
		$fields = self::_appendFieldsFromQuickSearchContext('cerberusweb.contexts.jira.project', $fields, 'project');
		
		// Engine/schema examples: Fulltext
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_JiraIssue::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples)) {
			$fields['_fulltext']['examples'] = $ft_examples;
			$fields['content']['examples'] = $ft_examples;
		}
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamsFromQuickSearchFields($fields) {
		$search_fields = $this->getQuickSearchFields();
		$params = DevblocksSearchCriteria::getParamsFromQueryFields($fields, $search_fields);

		// Handle virtual fields and overrides
		if(is_array($fields))
		foreach($fields as $k => $v) {
			switch($k) {
				case 'project':
					$field_keys = array(
						'project' => SearchFields_JiraIssue::PROJECT_ID,
					);
					
					@$field_key = $field_keys[$k];
					
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$patterns = DevblocksPlatform::parseCsvString($v);
					$projects = DAO_JiraProject::getAll();
					$values = array();
					
					if(is_array($patterns))
					foreach($patterns as $pattern) {
						foreach($projects as $project) {
							if(false !== stripos($project->name, $pattern) || false !== stripos($project->jira_key, $pattern))
								$values[$project->jira_id] = true;
						}
					}
					
					$param = new DevblocksSearchCriteria(
						$field_key,
						$oper,
						array_keys($values)
					);
					$params[$field_key] = $param;					
					break;
					
				case 'status':
					$field_keys = array(
						'status' => SearchFields_JiraIssue::JIRA_STATUS_ID,
					);
					
					@$field_key = $field_keys[$k];
					
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$patterns = DevblocksPlatform::parseCsvString($v);
					$statuses = DAO_JiraProject::getAllStatuses();
					$values = array();
					
					if(is_array($patterns))
					foreach($patterns as $pattern) {
						foreach($statuses as $status_id => $status) {
							if(false !== stripos($status['name'], $pattern))
								$values[$status_id] = true;
						}
					}
					
					$param = new DevblocksSearchCriteria(
						$field_key,
						$oper,
						array_keys($values)
					);
					$params[$field_key] = $param;					
					break;
					
				case 'type':
					$field_keys = array(
						'type' => SearchFields_JiraIssue::JIRA_TYPE_ID,
					);
					
					@$field_key = $field_keys[$k];
					
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$patterns = DevblocksPlatform::parseCsvString($v);
					$types = DAO_JiraProject::getAllTypes();
					$values = array();
					
					if(is_array($patterns))
					foreach($patterns as $pattern) {
						foreach($types as $type_id => $type) {
							if(false !== stripos($type['name'], $pattern))
								$values[$type_id] = true;
						}
					}
					
					$param = new DevblocksSearchCriteria(
						$field_key,
						$oper,
						array_keys($values)
					);
					$params[$field_key] = $param;					
					break;
			}
		}
		
		$this->renderPage = 0;
		$this->addParams($params, true);
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.jira.issue');
		$tpl->assign('custom_fields', $custom_fields);

		// Projects
		
		$projects = DAO_JiraProject::getAll();
		$tpl->assign('projects', $projects);
		
		$tpl->assign('view_template', 'devblocks:wgm.jira::jira_issue/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_JiraIssue::JIRA_KEY:
			case SearchFields_JiraIssue::JIRA_VERSIONS:
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
				foreach($projects as $project) {
					$options[$project->jira_id] = $project->name;
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
				
			case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_JiraIssue::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, 'cerberusweb.contexts.jira.issue');
				break;
				
			case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
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
			case SearchFields_JiraIssue::PROJECT_ID:
				$strings = array();
				$projects = DAO_JiraProject::getAll();
				
				foreach($values as $v) {
					if(false != (@$project = DAO_JiraProject::getByJiraId($v)))
						$strings[] = DevblocksPlatform::strEscapeHtml($project->name);
				}
				
				echo implode(' or ', $strings);
				break;
				
			case SearchFields_JiraIssue::JIRA_STATUS_ID:
				$strings = array();
				$projects = DAO_JiraProject::getAll();
				$project = array_shift($projects);
				
				foreach($values as $v) {
					if(isset($project->statuses[$v]))
						$strings[] = DevblocksPlatform::strEscapeHtml($project->statuses[$v]['name']);
				}
				
				echo implode(' or ', $strings);
				break;
				
			case SearchFields_JiraIssue::JIRA_TYPE_ID:
				$strings = array();
				$projects = DAO_JiraProject::getAll();
				
				foreach($values as $v) {
					foreach($projects as $project) {
						if(isset($project->issue_types[$v]))
							$strings[] = DevblocksPlatform::strEscapeHtml($project->issue_types[$v]['name']);
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
			case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_JiraIssue::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_JiraIssue::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_JiraIssue::JIRA_KEY:
			case SearchFields_JiraIssue::JIRA_VERSIONS:
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
				
			case SearchFields_JiraIssue::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_JiraIssue::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_JiraIssue::VIRTUAL_WATCHERS:
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
					
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
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
			self::_doBulkSetCustomFields('cerberusweb.contexts.jira.issue', $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_JiraIssue extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	const ID = 'cerberusweb.contexts.jira.issue';
	
	function getRandom() {
		return DAO_JiraIssue::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=jira_issue&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$jira_issue = DAO_JiraIssue::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($jira_issue->summary);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $jira_issue->id,
			'name' => sprintf("[%s] %s", $jira_issue->jira_key, $jira_issue->summary),
			'permalink' => $url,
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				switch($key) {
					case 'project__label':
						$label = 'Project';
						break;
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'project__label',
			'jira_key',
			'jira_type',
			'jira_status',
			'jira_versions',
			'created',
			'updated',
		);
	}
	
	function getContext($jira_issue, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'JIRA Issue:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(Context_JiraIssue::ID);

		// Polymorph
		if(is_numeric($jira_issue)) {
			$jira_issue = DAO_JiraIssue::get($jira_issue);
		} elseif($jira_issue instanceof Model_JiraIssue) {
			// It's what we want already.
		} elseif(is_array($jira_issue)) {
			$jira_issue = Cerb_ORMHelper::recastArrayToModel($jira_issue, 'Model_JiraIssue');
		} else {
			$jira_issue = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'jira_key' => $prefix.$translate->_('dao.jira_issue.jira_key'),
			'jira_type' => $prefix.$translate->_('dao.jira_issue.jira_type_id'),
			'jira_status' => $prefix.$translate->_('dao.jira_issue.jira_status_id'),
			'summary' => $prefix.$translate->_('dao.jira_issue.summary'),
			'description' => $prefix.$translate->_('common.description'),
			'created' => $prefix.$translate->_('common.created'),
			'updated' => $prefix.$translate->_('common.updated'),
			'jira_versions' => $prefix.$translate->_('dao.jira_issue.jira_versions'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'jira_key' => Model_CustomField::TYPE_SINGLE_LINE,
			'jira_type' => Model_CustomField::TYPE_SINGLE_LINE,
			'jira_status' => Model_CustomField::TYPE_SINGLE_LINE,
			'summary' => Model_CustomField::TYPE_SINGLE_LINE,
			'description' => Model_CustomField::TYPE_MULTI_LINE,
			'created' => Model_CustomField::TYPE_DATE,
			'updated' => Model_CustomField::TYPE_DATE,
			'jira_versions' => Model_CustomField::TYPE_SINGLE_LINE,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = Context_JiraIssue::ID;
		$token_values['_types'] = $token_types;
		
		if($jira_issue) {
			$project = $jira_issue->getProject();
			$type = $jira_issue->getType();
			$status = $jira_issue->getStatus();
			
			$token_values['_loaded'] = true;
			$token_values['_label'] = '[' . $jira_issue->jira_key . '] ' . $jira_issue->summary;
			$token_values['id'] = $jira_issue->id;
			$token_values['jira_id'] = $jira_issue->jira_id;
			$token_values['jira_key'] = $jira_issue->jira_key;
			$token_values['jira_type_id'] = $jira_issue->jira_type_id;
			$token_values['jira_type'] = (is_array($type) ? $type['name'] : '');
			$token_values['jira_status_id'] = $jira_issue->jira_status_id;
			$token_values['jira_status'] = (is_array($status) ? $status['name'] : '');
			$token_values['summary'] = $jira_issue->summary;
			$token_values['created'] = $jira_issue->created;
			$token_values['updated'] = $jira_issue->updated;
			$token_values['jira_versions'] = $jira_issue->jira_versions;
			
			$token_values['project_id'] = $jira_issue->project_id;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($jira_issue, $token_values);
			
			// [TODO] Content
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=jira_issue&id=%d-%s",$jira_issue->id, DevblocksPlatform::strToPermalink($jira_issue->summary)), true);
		}
		
		// JIRA Project
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext('cerberusweb.contexts.jira.project', null, $merge_token_labels, $merge_token_values, '', true);

		CerberusContexts::merge(
			'project_',
			$prefix.'Project:',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);

		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = 'cerberusweb.contexts.jira.issue';
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'description':
				if(isset($dictionary['jira_id']))
					$values['description'] = DAO_JiraIssue::getDescription($dictionary['jira_id']);
				break;

			case 'discussion':
				if(isset($dictionary['jira_id']))
					$values['discussion'] = DAO_JiraIssue::getComments($dictionary['jira_id']);
				break;
				
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
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
		$view->addParams(array(
		), true);
		$view->renderSortBy = SearchFields_JiraIssue::UPDATED;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		$view->renderFilters = false;
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

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
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		// Params
		
		$tpl->assign('jira_base_url', DevblocksPlatform::getPluginSetting('wgm.jira','base_url',''));
		
		// Model
		
		if(!empty($context_id) && null != ($jira_issue = DAO_JiraIssue::get($context_id))) {
			$tpl->assign('model', $jira_issue);
		}
		
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.jira.issue', false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.jira.issue', $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}

		// Comments
		$comments = DAO_Comment::getByContext('cerberusweb.contexts.jira.issue', $context_id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		$tpl->display('devblocks:wgm.jira::jira_issue/peek.tpl');
	}
};