<b>Project:</b><br>
<select name="{$namePrefix}[project_key]">
	{foreach from=$projects item=project}
	<option value="{$project->jira_key}" {if $params.project_key == $project->key}selected="selected"{/if}>{$project->name} ({$project->jira_key})</option>
	{/foreach}
</select>
<br>

<b>Summary:</b><br>
<input type="text" name="{$namePrefix}[summary]" value="{$params.summary}" size="45" style="width:100%;" class="placeholders">
<br>

<b>Type:</b><br>
<input type="text" name="{$namePrefix}[type]" value="{$params.type}" size="45" style="width:100%;" class="placeholders" placeholder="e.g. Bug, New Feature, Task, Improvement">
<br>

<b>Description:</b>
<div>
	<textarea name="{$namePrefix}[description]" rows="10" cols="45" style="width:100%;" class="placeholders">{$params.description}</textarea>
</div>

{if !empty($values_to_contexts)}
<b>Link to:</b>
<div style="margin-left:10px;margin-bottom:0.5em;">
{include file="devblocks:cerberusweb.core::internal/decisions/actions/_shared_var_picker.tpl" param_name="link_to" values_to_contexts=$values_to_contexts}
</div>
{/if}

<b>Save response to a variable named:</b><br>
<input type="text" name="{$namePrefix}[response_placeholder]" value="{$params.response_placeholder|default:"_jira_result"}" size="45" style="width:100%;" placeholder="e.g. _jira_result">
</br>