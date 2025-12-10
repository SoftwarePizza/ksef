<div class="panel">
	<h3><i class="icon icon-credit-card"></i> {l s='KSeF Integration' mod='ksef'}</h3>
	<p>
		<strong>{l s='Here is my new generic module!' mod='ksef'}</strong><br />
		{l s='Thanks to PrestaShop, now I have a great module.' mod='ksef'}<br />
		{l s='I can configure it using the following configuration form.' mod='ksef'}
	</p>
	<br />
	<p>
		{l s='This module will boost your sales!' mod='ksef'}
	</p>
</div>

<div class="panel">
	<h3><i class="icon icon-tags"></i> {l s='Documentation' mod='ksef'}</h3>
	<p>
		&raquo; {l s='You can get a PDF documentation to configure this module' mod='ksef'} :
		<ul>
			<li><a href="#" target="_blank">{l s='English' mod='ksef'}</a></li>
			<li><a href="#" target="_blank">{l s='French' mod='ksef'}</a></li>
		</ul>
	</p>
</div>

<div class="panel">
    <h3><i class="icon icon-cogs"></i> {l s='Test Connection' mod='ksef'}</h3>
    <div class="alert alert-info">
        {l s='Before testing, make sure you have saved your NIP and Token.' mod='ksef'}
    </div>
    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post" class="form-horizontal">
        <button type="submit" name="testKsefConnection" class="btn btn-primary">{l s='Test Connection' mod='ksef'}</button>
    </form>
    {if isset($test_result)}
        <div class="alert {if $test_success}alert-success{else}alert-danger{/if}" style="margin-top: 10px;">
            {$test_result|escape:'htmlall':'UTF-8'}
        </div>
    {/if}
</div>
