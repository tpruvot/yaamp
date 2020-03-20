<br>

<div class="main-left-box">
<div class="main-left-title">YiiMP API</div>
<div class="main-left-inner">

<p>Simple REST API.</p>

<p><b>Wallet Status</b></p>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL ?>/api/wallet?address=<b>WALLET_ADDRESS</b></p>

result:
<pre class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
{
	"unsold": 0.00050362,
	"balance": 0.00000000,
	"unpaid": 0.00050362,
	"paid24h": 0.00000000,
	"total": 0.00050362
}
</pre>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
        http://<?=YAAMP_API_URL ?>/api/walletEx?address=<b>WALLET_ADDRESS</b></p>

result:
<pre class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
{
	"unsold": 0.00050362,
	"balance": 0.00000000,
	"unpaid": 0.00050362,
	"paid24h": 0.00000000,
	"total": 0.00050362,
	"miners":[{
		"version": "ccminer\/1.8.2",
		"password": "d=96",
		"ID": "",
		"algo": "decred",
		"difficulty": 96,
		"subscribe": 1,
		"accepted": 82463372.083,
		"rejected": 0
	}]
<?php if (YAAMP_API_PAYOUTS): ?>
	,"payouts":[{
		"time": 1529860641,
		"amount": "0.001",
		"tx": "transaction_id_of_the_payout"
	}]
<?php
endif; ?>
}
</pre>
<?php
if (YAAMP_API_PAYOUTS) echo "Payouts of the last " . (YAAMP_API_PAYOUTS_PERIOD / 3600) . " hours are displayed, please use a block explorer to see all payouts.";
?>
<p><b>Pool Status</b></p>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL
?>/api/status</p>

result:
<pre class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
{
	"x11": {
		"name": "x11",
		"port": 3533,
		"coins": 10,
		"fees": 1,
		"hashrate": 269473938,
		"workers": 5,
		"estimate_current": "0.00053653",
		"estimate_last24h": "0.00036408",
		"actual_last24h": "0.00035620",
		"hashrate_last24h": 269473000,
		"rental_current": "3.61922463"
	},

	...
}
</pre>


request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL
?>/api/currencies</p>

result:
<pre class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
{
	"BTX": {
		"algo": "bitcore",
		"port": 3556,
		"name": "BitCore",
		"height": 18944,
		"workers": 181,
		"shares": 392,
		"hashrate": 7267227499,
		"24h_blocks": 329,
		"24h_btc": 0.54471295,
		"lastblock": 18945,
		"timesincelast": 67
	},

	...
}
</pre>

<?php if (YAAMP_RENTAL): ?>

<p><b>Rental Status</b></p>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL
?>/api/rental?key=API_KEY</p>

result:
<pre class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
{
	"balance": 0.00000000,
	"unconfirmed": 0.00000000,
	"jobs":
	[
		{
			"jobid": "19",
			"algo": "x11",
			"price": "1",
			"hashrate": "1000000",
			"server": "stratum.server.com",
			"port": "3333",
			"username": "1A5pAdfWLUFXoqcUb6N9Fre2EApr5QLNdG",
			"password": "xx",
			"started": "1",
			"active": "1",
			"accepted": "586406.2014805333",
			"rejected": "",
			"diff": "0.04"
		},

		...

	]
}
</pre>

<p><b>Rental Price</b></p>

<p>Set the rental price of a job.</p>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL
?>/api/rental_price?key=API_KEY&jobid=xx&price=xx</p>

</pre>

<p><b>Rental Hashrate</b></p>

<p>Set the rental max hashrate of a job.</p>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL
?>/api/rental_hashrate?key=API_KEY&jobid=xx&hashrate=xx</p>

</pre>

<p><b>Start Rental Job</b></p>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL
?>/api/rental_start?key=API_KEY&jobid=xx</p>

</pre>

<p><b>Stop Rental Job</b></p>

request:
<p class="main-left-box" style='padding: 3px; font-size: .8em; background-color: #ffffee; font-family: monospace;'>
	http://<?=YAAMP_API_URL
?>/api/rental_stop?key=API_KEY&jobid=xx</p>

</pre>

<?php
endif; /* RENTAL */ ?>

<br><br>

</div></div>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>


</script>
