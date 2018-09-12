{if $error}
    <div class="message-error">
        <p>{$error|wash()}</p>
    </div>
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
