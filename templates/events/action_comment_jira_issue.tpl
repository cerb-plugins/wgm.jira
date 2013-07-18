<b>Issue Key:</b><br>
<input type="text" name="{$namePrefix}[key]" value="{$params.key}" size="45" style="width:100%;" class="placeholders">
<br>

<b>Comment:</b>
<div>
	<textarea name="{$namePrefix}[comment]" rows="10" cols="45" style="width:100%;" class="placeholders">{$params.comment}</textarea>
</div>

<b>Save response to a variable named:</b><br>
<input type="text" name="{$namePrefix}[response_placeholder]" value="{$params.response_placeholder|default:"_jira_result"}" size="45" style="width:100%;" placeholder="e.g. _jira_result">
</br>