<p><b>Nightbot</b>, paste this in your chat:</p>
<div class="copy-command">
    <code>!commands add !ai -cd=5 $(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))</code>
    <button type="button" class="btn btn-sm">Copy</button>
</div>
<p><b>Fossabot</b>, paste this in your chat:</p>
<div class="copy-command">
    <code>!addcmd -u 5 ai $(customapi https://xgpt.gerhard.dev/api/command?q=$(querystring))</code>
    <button type="button" class="btn btn-sm">Copy</button>
</div>
@once
<script>
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.copy-command button');
        if (!button) return;
        navigator.clipboard.writeText(button.previousElementSibling.textContent.trim()).then(function () {
            button.textContent = 'Copied!';
            setTimeout(function () { button.textContent = 'Copy'; }, 1500);
        });
    });
</script>
@endonce
