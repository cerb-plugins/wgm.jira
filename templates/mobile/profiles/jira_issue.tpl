{if $dict->description}
<h3>Description</h3>

<div style="font-size:12px;">
	<div class="cerb-message-contents">{$dict->description|trim|escape:'htmlall'|devblocks_hyperlinks nofilter}</div>
</div>
{/if}