<h1>Upgrade Packages</h1>

{form id="package_select" legend="Upgraded Packages" id="package_select"}
	<p class="success">The following packages were successfully upgraded</p>
	<input type="hidden" name="step" value="{$next_step}" />
	<dl>
		{foreach from=$success item=upgrade key=package}
			{foreach from=$upgrade item=data key=version}
				<dt>{$package}</dt>
				<dd>Upgrade &rarr; {$version}
					{if $data.post_upgrade}
						<br /><strong>Post install notes</strong>:
						<br />{$data.post_upgrade}
					{/if}
				</dd>
			{/foreach}
		{/foreach}
	</dl>

	<div class="row submit">
		<input type="submit" name="continue" value="Continue Install Process" />
	</div>
{/form}
