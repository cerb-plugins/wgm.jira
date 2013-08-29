{* [TODO] Secure API paths from plugin config *}

<b>API Path:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	{$verbs = [get,post,put]}
	<select name="{$namePrefix}[api_verb]" class="jira-api-verb">
		{foreach from=$verbs item=verb}
		<option value="{$verb}" {if $params.api_verb == $verb}selected="selected"{/if}>{$verb|upper}</option>
		{/foreach}
	</select>
	<br>
	
	<input type="text" name="{$namePrefix}[api_path]" value="{$params.api_path|default:"/rest/api/2/serverInfo"}" class="placeholders" spellcheck="false" size="45" style="width:100%;" placeholder="e.g. /rest/api/2/serverInfo">
</div>

<div class="jira-api-json" style="{if !in_array($params.api_verb,[post,put])}display:none;{/if}">
	<b>Request JSON:</b>
	<div style="margin-left:10px;margin-bottom:10px;">
		<textarea rows="3" cols="60" name="{$namePrefix}[json]" style="width:100%;white-space:pre;word-wrap:normal;" class="placeholders" spellcheck="false">{$params.json}</textarea>
	</div>
</div>

<b>Save response to a variable named:</b><br>
<div style="margin-left:15px;margin-bottom:5px;">
	<input type="text" name="{$namePrefix}[response_placeholder]" value="{$params.response_placeholder|default:"_jira_response"}" size="45" style="width:100%;" placeholder="e.g. _jira_response">
</div>

<script type="text/javascript">
var $action = $('fieldset#{$namePrefix}');
$action.find('textarea').elastic();

$action.find('select.jira-api-verb').change(function() {
	var $container = $(this).closest('fieldset');
	var $div_json = $container.find('div.jira-api-json');
	var val = $(this).val();
	
	if(val == 'post' || val == 'put')
		$div_json.show().find('textarea').elastic();
	else
		$div_json.fadeOut();
});
</script>
