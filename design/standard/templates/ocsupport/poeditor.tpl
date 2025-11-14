<!doctype html>
<html lang="it" style="height:100%">
<head>
  <title>{$title}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {ezscript_load(array(
    'ezjsc::jquery',
    'ezjsc::jqueryUI',
    'ezjsc::jqueryio',
    'moment-with-locales.min.js',
    'handlebars.min.js',
    'alpaca.min.js',
    'jq.dt.min.js',
    'dt.b4.min.js',
    'dataTables.responsive.min.js',
    'jquery.enhsplitter.js'
    ))}
    {ezcss_load(array(
    'bootstrap.min.css',
    'glyphicon.css',
    'dt.b4.min.css',
    'responsive.dataTables.min.css',
    'jquery.enhsplitter.css'
    ))}
</head>
<body class="bg-primary">
<div class="container-fluid m-0 bg-white p-5 position-relative">
  <div class="row">
    <div class="col-12 mb-5">
      <h1>Assistente PoEditor {if is_set($current_project.id)} - <a href="https://poeditor.com/projects/view?id={$current_project.id}" target="_blank">{$current_project.name|wash()}</a> {/if}</h1>
    </div>
    {if $error}
      {if $error|eq('missing-token')}
        <div class="col-12">
          <form action="{'/ocsupport/poeditor'|ezurl(no)}" method="post">
            <label for="store_poeditor_token">Inserisci il token api PoEditor (lo trovi qui: <a href="https://poeditor.com/account/api" target="_blank">https://poeditor.com/account/api</a> )</label>
            <input type="text" id="store_poeditor_token" class="form-control mb-2" required name="store_poeditor_token" />
            <input type="submit" class="btn btn-success btn-lg" value="Invia"/>
            <input type="hidden" name="ezxform_token" value="{$ezxform_token}"/>
          </form>
        </div>
      {else}
          <h2>Error: {$error|wash}</h2>
      {/if}
    {else}
      <div class="col-3">
        <div class="list-group">
          {foreach $projects as $project}
            <a href="{concat('/ocsupport/poeditor/', $project.id)|ezurl(no)}" class="list-group-item list-group-item-action{if $current_project.id|eq($project.id)} active{/if}" aria-current="true">
              <h6 class="mb-1">{$project.name|wash()}</h6>
              <small><strong>Project ID:</strong> {$project.id}</small>
            </a>
          {/foreach}
        </div>
      </div>
      {if is_set($current_project.id)}
        <div class="col-9">
          <form action="{concat('/ocsupport/poeditor/', $current_project.id)|ezurl(no)}" method="get">
          <div class="row">
            <div class="col-4">
              <div class="form-group">
                <label for="poe_locale">Seleziona lingua</label>
                <select name="locale" id="poe_locale" class="form-control">
                    {foreach $languages as $locale => $language}
                      <option value="{$locale}"{if $current_locale|eq($locale)} selected{/if}>{$locale}</option>
                    {/foreach}
                </select>
              </div>
            </div>
            {if count($tags)}
            <div class="col-4">
              <div class="form-group">
                <label for="poe_tag">Seleziona tag</label>
                <select name="tag" id="poe_tag" class="form-control">
                    {foreach $tags as $tag}
                      <option value="{$tag}"{if $current_tag|eq($tag)} selected{/if}>{$tag}</option>
                    {/foreach}
                </select>
              </div>
            </div>
            {/if}
            {if is_array($installers)}
              <div class="col-4">
                <div class="form-group">
                  <label for="poe_installer">Seleziona installer (todo)</label>
                    <input type="text" class="form-control" readonly value="{$default_installer}" name="installer" id="poe_installer">
{*                  <select name="tag" id="poe_tag" class="form-control">*}
{*                      {foreach $tags as $tag}*}
{*                        <option value="{$tag}"{if $current_tag|eq($tag)} selected{/if}>{$tag}</option>*}
{*                      {/foreach}*}
{*                  </select>*}
                </div>
              </div>
            {/if}
            <div class="col-4 pt-4">
              <input type="submit" class="btn btn-primary btn-xs mt-2" value="Seleziona"/>
            </div>
          </div>
          </form>

          {if is_array($translation_diff)}
              {if count($translation_diff)}
                <table class="table table-striped">
                    <thead>
                      <tr>
                        {foreach $translation_diff[0] as $key => $value}
                          <th>{$key|wash}</th>
                        {/foreach}
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                    {foreach $translation_diff as $item}
                        <tr>
                          {foreach $item as $value}
                            {if $value|eq('n.d.')}
                              <td><em>missing</em></td>
                              {else}
                              <td>{$value|wash}</td>
                            {/if}
                          {/foreach}
                          <td>
                            {if $current_project_terms|contains(concat($item.context, '#', $item.term))|not()}
                            <form action="{concat('/ocsupport/poeditor/', $current_project.id, '?locale=', $current_locale, '&tag=', $current_tag)|ezurl(no)}" method="post">
                              <input type="hidden" name="context" value="{$item.context|wash}">
                              <input type="hidden" name="term" value="{$item.term|wash}">
                              <button class="btn btn-sm btn-primary text-nowrap" type="submit" name="send_poeditor_term">Send term to poeditor</button>
                            </form>
                            {/if}
                          </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
              {else}
                  <p class="lead">Traduzioni sincronizzate</p>
              {/if}
          {/if}
        </div>
      {/if}
    {/if}
  </div>
</div>
</body>

