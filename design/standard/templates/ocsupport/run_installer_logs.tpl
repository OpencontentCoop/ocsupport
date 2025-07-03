<h2>{$current_installer.name|wash()}</h2>
<code>{$current_installer.identifier|wash()} v{$current_installer.available|wash()}</code>
<div style="position:relative">
  <div id="loader" style="display:none;position: absolute;right: 0;top: 0;"><i
            class="spinner fa a fa-circle-o-notch fa-spin"></i></div>
  <pre id="installer-logs" style="margin-top:20px"></pre>

    {literal}
      <script>
        $(document).ready(function () {
          function getLogs() {
            var loader = $('#loader');
            loader.show();
            $.getJSON('/ocsupport/run_installer/logs?data=1', function (response) {
              loader.hide();
              if (response.data !== null) {
                $('#installer-logs').html(response.data)
              }
            })
          }

          getLogs();
          setInterval(function () {
            getLogs();
          }, 2000)
        })
      </script>
    {/literal}
</div>