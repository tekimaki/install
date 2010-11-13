<h1>Upgrade Packages &amp; Plugins</h1>

{form id="package_select" legend="Upgraded Packages &amp; Plugins" id="package_select"}
	<input type="hidden" name="step" value="{$next_step}" />
	{if $success}
	<p class="success">The following packages were successfully upgraded</p>
	<dl>
		{foreach from=$success item=upgrade key=package}
			{foreach from=$upgrade item=data}
				<dt>{$package}</dt>
				<dd>Upgrade {$data.from_version} &rarr; {$data.version}
					{if $data.post_upgrade}
						<br /><strong>Post install notes</strong>:
						<br />{$data.post_upgrade}
					{/if}
				</dd>
			{/foreach}
		{/foreach}
	</dl>
	{/if}
	{if $plugin_success}
	<p class="success">The following plugins were successfully upgraded</p>
	<dl>
		{foreach from=$plugin_success item=upgrade key=package}
			{foreach from=$upgrade item=data}
				<dt>{$package}</dt>
				<dd>Upgrade {$data.from_version} &rarr; {$data.version}
					{if $data.post_upgrade}
						<br /><strong>Post install notes</strong>:
						<br />{$data.post_upgrade}
					{/if}
				</dd>
			{/foreach}
		{/foreach}
	</dl>
	{/if}
	{if !$success && !$plugin_success}
		<p class="success">No Upgrades</p>
	{/if}
	<div class="buttonHolder row submit">
		<input type="submit" name="continue" value="Continue Install Process" />
	</div>
{/form}
