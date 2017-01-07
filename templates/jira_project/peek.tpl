<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmJiraProjectPeek">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="jira_project">
<input type="hidden" name="action" value="savePeek">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate}</legend>
	
	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		<tbody>
			<tr>
				<td width="1%" nowrap="nowrap">{'common.name'|devblocks_translate}:</td>
				<td width="99%">
					{$model->name}
				</td>
			</tr>
			
			<tr>
				<td width="1%" nowrap="nowrap" valign="top">{'dao.jira_project.is_sync'|devblocks_translate|capitalize}: </td>
				<td width="99%">
					<label><input type="radio" name="is_sync" value="0" {if empty($model->is_sync)}checked="checked"{/if}> {'common.no'|devblocks_translate|capitalize}</label>
					<label><input type="radio" name="is_sync" value="1" {if $model->is_sync}checked="checked"{/if}> {'common.yes'|devblocks_translate|capitalize}</label>
				</td>
			</tr>
		</tbody>
		
	</table>
	
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context='cerberusweb.contexts.jira.project' context_id=$model->id}

<fieldset class="peek">
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<textarea name="comment" rows="2" cols="45" style="width:98%;" placeholder="{'comment.notify.at_mention'|devblocks_translate}"></textarea>
</fieldset>

{if !empty($model->id)}
<fieldset style="display:none;" class="delete">
	<legend>{'common.delete'|devblocks_translate|capitalize}</legend>
	
	<div>
		Are you sure you want to delete this jira project?
	</div>
	
	<button type="button" class="delete" onclick="var $frm=$(this).closest('form');$frm.find('input:hidden[name=do_delete]').val('1');$frm.find('button.submit').click();"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> Confirm</button>
	<button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();"><span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span> {'common.cancel'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

<div class="buttons">
	<button type="button" class="submit" onclick="genericAjaxPopupPostCloseReloadView(null,'frmJiraProjectPeek','{$view_id}', false, 'jira_project_save');"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	{*{if !empty($model->id)}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}*}
</div>

{if !empty($model->id)}
<div style="float:right;">
	<a href="{devblocks_url}c=profiles&type=jira_project&id={$model->id}-{$model->name|devblocks_permalink}{/devblocks_url}">view full record</a>
</div>
<br clear="all">
{/if}
</form>

<script type="text/javascript">
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		var $textarea = $popup.find('textarea[name=comment]');
		
		$popup.dialog('option','title',"{'Jira Project'|escape:'javascript' nofilter}");

		$popup.find('input:text:first').focus();

		// @mentions
		
		var atwho_workers = {CerberusApplication::getAtMentionsWorkerDictionaryJson() nofilter};

		$textarea.atwho({
			at: '@',
			{literal}displayTpl: '<li>${name} <small style="margin-left:10px;">${title}</small> <small style="margin-left:10px;">@${at_mention}</small></li>',{/literal}
			{literal}insertTpl: '@${at_mention}',{/literal}
			data: atwho_workers,
			searchKey: '_index',
			limit: 10
		});
		
	});
</script>
