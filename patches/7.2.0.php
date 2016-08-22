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

if(!isset($columns['last_synced_checkpoint'])) {
	$db->ExecuteMaster("ALTER TABLE jira_project ADD COLUMN last_synced_checkpoint INT UNSIGNED NOT NULL DEFAULT 0");
}

return TRUE;