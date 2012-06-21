<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// jira_project

if(!isset($tables['jira_project'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS jira_project (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			jira_id INT UNSIGNED NOT NULL DEFAULT 0,
			jira_key VARCHAR(16) DEFAULT '',
			name VARCHAR(255) DEFAULT '',
			url VARCHAR(255) DEFAULT '',
			issuetypes_json TEXT,
			statuses_json TEXT,
			versions_json TEXT,
			last_synced_at INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);

	$tables['jira_project'] = 'jira_project';
}

// ===========================================================================
// jira_issue

if(!isset($tables['jira_issue'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS jira_issue (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id INT UNSIGNED NOT NULL DEFAULT 0,
			jira_id INT UNSIGNED NOT NULL DEFAULT 0,
			jira_key VARCHAR(32) DEFAULT '',
			jira_type_id SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			jira_version_id SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			jira_status_id SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			summary VARCHAR(255) DEFAULT '',
			created INT UNSIGNED NOT NULL DEFAULT 0,
			updated INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			INDEX project_id (project_id),
			INDEX jira_id (jira_id),
			INDEX jira_status_id (jira_status_id),
			INDEX updated (updated)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);

	$tables['jira_issue'] = 'jira_issue';
}

// ===========================================================================
// Enable scheduled task and give defaults

if(null != ($cron = DevblocksPlatform::getExtension('wgmjira.cron', true, true))) {
	$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
	$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '10');
	$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
	$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday 23:45'));
}

return TRUE;