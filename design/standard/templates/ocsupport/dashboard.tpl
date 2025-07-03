{if $error}
    <div class="message-error">
        <p class="m-0">{$error|wash()}</p>
    </div>
{/if}

{if count($installers)|gt(0)}
    <h1>Installer</h1>
    <table width="100%" cellspacing="0" cellpadding="0" border="0" class="table table-striped list mb-5">
        <thead>
        <tr>
            <th>Name</th>
            <th>Current version</th>
            <th>Available version</th>
            <th width="1"></th>
        </tr>
        </thead>
        <tbody>
        {foreach $installers as $installer sequence array(bglight,bgdark) as $style}
        <tr class="{$style}">
            <td style="vertical-align:middle">
                {$installer.name|wash()}
                <p class="m-0"><code style="font-size:.7em">{$installer.data_dir|explode('html/')[1]|wash()}</code></p>
                {if $installer.description}
                    <p class="m-0"><em>{$installer.description|wash()}</em></p>
                {/if}
            </td>
            <td style="vertical-align:middle">{$installer.current|wash()}</td>
            <td style="vertical-align:middle">{$installer.available|wash()}</td>
            <td style="vertical-align:middle;text-align:center">
                <form method="post" action="{'ocsupport/run_installer'|ezurl(no)}">
                    <input type="hidden" name="Identifier" value="{$installer.identifier}" />
                {if $running_installer|eq($installer.identifier)}
                    <i class="spinner fa a fa-circle-o-notch fa-spin"></i>
                    <span class="d-none">Installing</span>
                    <a href="{'ocsupport/run_installer/logs'|ezurl(no)}"><small>See logs</small></a>
                {else}
                    {if $installer.can_install}
                        <button type="submit" name="RunInstaller" value="install" class="defaultbutton btn btn-xs btn-primary{if $can_run_installer|not} btn-disabled" disabled="disabled{/if}">Install</button>
                    {elseif $installer.can_update}
                        <button type="submit" name="RunInstaller" value="update" class="defaultbutton btn btn-xs btn-primary{if $can_run_installer|not} btn-disabled" disabled="disabled{/if}">Update</button>
                    {/if}
                {/if}
                </form>
            </td>
        </tr>
        {/foreach}
        </tbody>
    </table>
{/if}

{if $packages}

    <h1>Composer packages</h1>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" class="table table-striped list">
        <thead>
            <tr>
                <th>Name</th>
                <th>Version</th>
                <th>Source</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
        {foreach $packages as $package sequence array(bglight,bgdark) as $style}
            <tr class="{$style}">
                <td>{$package.name|wash()}</td>
                <td>{$package.version|wash()}</td>
                <td>
                    {if $package.source}
                        <a target="_blank" href="{$package.source.url|wash()}">
                            {if $package.source.reference}
                                {$package.source.reference|extract_left(5)|wash()}
                            {else}
                                {$package.source.type|wash()}
                            {/if}
                        </a>
                    {else}
                        <a target="_blank" href="{$package.dist.url|wash()}">
                            {if $package.dist.reference}
                                {$package.dist.reference|extract_left(5)|wash()}
                            {else}
                                {$package.dist.url|explode('/')|extract_right(1)[0]}
                            {/if}
                        </a>
                    {/if}
                </td>
                <td>{$package.description|wash()}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>

{/if}

{if $repos}

    <h1>Git repos</h1>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" class="table table-striped list">
        <thead>
        <tr>
            <th>Name</th>
            <th>Branch</th>
            <th>Tag</th>
            <th>Commit</th>
            <th>Remote</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        {foreach $repos as $repo sequence array(bglight,bgdark) as $style}
            <tr class="{$style}">
                <td>{$repo.name|wash()}</td>
                <td>{$repo.branch|wash()}</td>
                <td>{$repo.tag|wash()}</td>
                <td>{$repo.hash|wash()}</td>
                <td>{$repo.remote|implode('<br />')}</td>
                <td>{$repo.working_dir|implode('<br />')}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>

{/if}
