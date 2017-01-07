<h2>Connect to JIRA API</h2>

<form action="javascript:;" method="post" id="frmSetup" onsubmit="return false;">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="connected_account">
<input type="hidden" name="action" value="saveAuthFormJson">
<input type="hidden" name="ext_id" value="{ServiceProvider_Jira::ID}">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset style="margin-top:5px;">
	<legend>JIRA API Credentials</legend>
	
	<b>Base URL to JIRA Installation:</b><br>
	<input type="text" name="params[base_url]" value="{$params.base_url}" size="64" placeholder="e.g. https://example.atlassian.net" spellcheck="false"><br>
	<br>
	<b>JIRA User:</b><br>
	<input type="text" name="params[jira_user]" value="{$params.jira_user}" size="64" placeholder="e.g. Cerb" spellcheck="false"><br>
	<br>
	<b>Password:</b><br>
	<input type="password" name="params[jira_password]" value="{$params.jira_password}" size="32" spellcheck="false"><br>
	<br>

	<div>
		<div class="status" style="display:inline-block;"></div>
	</div>

	<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>	
</fieldset>

</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#frmSetup');
	
	$frm.find('BUTTON.submit')
		.click(function(e) {
			genericAjaxPost($frm,'',null,function(json) {
				if(false == json || false == json.status) {
					var error = 'An unexpected error occurred.';
					
					if(json.error)
						error = json.error;
						
					Devblocks.showError('#frmSetup div.status', error);
					
				} else {
					window.opener.genericAjaxGet('view{$view_id}', 'c=internal&a=viewRefresh&id={$view_id}');
					window.close();
				}
			});
		})
	;
});
</script>