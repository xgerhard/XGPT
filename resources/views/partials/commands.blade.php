<p><b>Nightbot</b>, paste this in your chat:</p>
<div class="copy-command mb-4">
    <code>!commands add !ai -cd=5 $(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))</code>
    <button type="button" class="btn btn-sm btn-secondary">Copy</button>
</div>
<p><b>Fossabot</b>, paste this in your chat:</p>
<div class="copy-command">
    <code>!addcmd -u 5 ai $(customapi https://xgpt.gerhard.dev/api/command?q=$(querystring))</code>
    <button type="button" class="btn btn-sm btn-secondary">Copy</button>
</div>
@once
<style>
    .copy-command { display: flex; gap: .75rem; align-items: center; }
    .copy-command code { flex: 1; overflow-wrap: anywhere; padding: .5rem .75rem; }
</style>
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
