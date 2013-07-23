<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmJiraIssuePeek">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="jira_issue">
<input type="hidden" name="action" value="savePeek">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">

{$jira_project = $model->getProject()}

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate}</legend>
	
	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'common.name'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{$model->summary}
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'dao.jira_issue.jira_key'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				<a href="{$jira_base_url}/browse/{$model->jira_key}" target="_blank">{$model->jira_key}</a>
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'dao.jira_issue.project_id'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{if $jira_project}
					{$jira_project->name}
				{/if}
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'dao.jira_issue.jira_versions'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{$model->jira_versions|default:'(none)'}
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'dao.jira_issue.jira_type_id'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{$type = $model->getType()}
				{$type.name}
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'dao.jira_issue.jira_status_id'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{$status = $model->getStatus()}
				{$status.name}
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'common.created'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{$model->created|devblocks_date} ({$model->created|devblocks_prettytime})</abbr>
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>{'common.updated'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{$model->updated|devblocks_date} ({$model->updated|devblocks_prettytime})</abbr>
			</td>
		</tr>
		
		{* Watchers *}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right">{$translate->_('common.watchers')|capitalize|capitalize}: </td>
			<td width="100%">
				{if empty($model->id)}
					<button type="button" class="chooser_watcher"><span class="cerb-sprite sprite-view"></span></button>
					<ul class="chooser-container bubbles" style="display:block;"></ul>
				{else}
					{$object_watchers = DAO_ContextLink::getContextLinks('cerberusweb.contexts.jira.issue', array($model->id), CerberusContexts::CONTEXT_WORKER)}
					{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context='cerberusweb.contexts.jira.issue' context_id=$model->id full=true}
				{/if}
			</td>
		</tr>
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context='cerberusweb.contexts.jira.issue' context_id=$model->id}

{* Description *}
<fieldset class="peek">
	<legend>{'common.description'|devblocks_translate|capitalize}</legend>
	{$model->getDescription()|escape:'html'|devblocks_hyperlinks|nl2br nofilter}
</fieldset>

{* Comment *}
{if !empty($last_comment)}
	{include file="devblocks:cerberusweb.core::internal/comments/comment.tpl" readonly=true comment=$last_comment}
{/if}

<fieldset class="peek">
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<textarea name="comment" rows="5" cols="45" style="width:98%;"></textarea>
	<div class="notify" style="display:none;">
		<b>{'common.notify_watchers_and'|devblocks_translate}:</b>
		<button type="button" class="chooser_notify_worker"><span class="cerb-sprite sprite-view"></span></button>
		<ul class="chooser-container bubbles" style="display:block;"></ul>
	</div>
</fieldset>

{if !empty($model->id)}
<fieldset style="display:none;" class="delete">
	<legend>{'common.delete'|devblocks_translate|capitalize}</legend>
	
	<div>
		Are you sure you want to delete this jira issue?
	</div>
	
	<button type="button" class="delete" onclick="var $frm=$(this).closest('form');$frm.find('input:hidden[name=do_delete]').val('1');$frm.find('button.submit').click();"><span class="cerb-sprite2 sprite-tick-circle"></span> Confirm</button>
	<button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();"><span class="cerb-sprite2 sprite-minus-circle"></span> {'common.cancel'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

<div class="buttons">
	<button type="button" class="submit" onclick="genericAjaxPopupPostCloseReloadView(null,'frmJiraIssuePeek','{$view_id}', false, 'jira_issue_save');"><span class="cerb-sprite2 sprite-tick-circle"></span> {$translate->_('common.save_changes')|capitalize}</button>
	{*{if !empty($model->id)}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="cerb-sprite2 sprite-cross-circle"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}*}
</div>

{if !empty($model->id)}
<div style="float:right;">
	<a href="{devblocks_url}c=profiles&type=jira_issue&id={$model->id}-{$model->summary|devblocks_permalink}{/devblocks_url}">view full record</a>
</div>
<br clear="all">
{/if}
</form>

<script type="text/javascript">
	$popup = genericAjaxPopupFetch('peek');
	$popup.one('popup_open', function(event,ui) {
		$(this).dialog('option','title',"{'Jira Issue'}");
		
		$(this).find('button.chooser_watcher').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','add_watcher_ids', { autocomplete:true });
		});
		
		$(this).find('textarea[name=comment]').keyup(function() {
			if($(this).val().length > 0) {
				$(this).next('DIV.notify').show();
			} else {
				$(this).next('DIV.notify').hide();
			}
		});
		
		$('#frmJiraIssuePeek button.chooser_notify_worker').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','notify_worker_ids', { autocomplete:true });
		});
		
		$(this).find('input:text:first').focus();
	} );
</script>
