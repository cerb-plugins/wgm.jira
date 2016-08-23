<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// jira_project

if(!isset($tables['jira_project'])) {
	$logger->error("The 'jira_project' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('jira_project');

if(!isset($columns['last_synced_checkpoint']))
	$changes[] = 'ADD COLUMN last_synced_checkpoint INT UNSIGNED NOT NULL DEFAULT 0';

if(!isset($indexes['last_synced_at']))
	$changes[] = 'ADD INDEX (last_synced_at)';

if(!isset($indexes['is_sync']))
	$changes[] = 'ADD INDEX (is_sync)';

if(!isset($indexes['jira_id']))
	$changes[] = 'ADD INDEX (jira_id)';

if(!empty($changes)) {
	$sql = sprintf("ALTER TABLE jira_project %s", implode(', ', $changes));
	$db->ExecuteMaster($sql) or die("[MySQL Error] " . $db->ErrorMsgMaster());
}


return TRUE;