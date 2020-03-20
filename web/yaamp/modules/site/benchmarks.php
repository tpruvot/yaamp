<br>

<div class="main-left-box">
<div class="main-left-title">cryptopool.builders Benchmarks</div>
<div class="main-left-inner">

<p style="width: 700px;">cryptopool.builders now allow users to share their ccminer (1.7.6+) device hashrate, more supported miners will come later.</p>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>
-o stratum+tcp://<?=YAAMP_STRATUM_URL ?>:&lt;PORT&gt; -a &lt;algo&gt; -u &lt;wallet_adress&gt; -p stats
</pre>

<p style="width: 700px;">
With this option enabled, the stratum will ask for device stats each 50 shares (for 4 times max).<br/>
<br/>
You can combine this miner option with other ones, like the <a href="/site/diff">pool difficulty</a> with a comma.<br/>
<br/>
You can also use the generic username '<b>benchmark</b>' if you don't have a valid address,<br/>
but in this case you will mine without reward (like a donator).
</p>

<p style="width: 700px;">
Please note only the first device stats will be submitted on multi gpus systems.<br/>
If you want to monitor a different card with ccminer, use the <b>--device</b> parameter, like <b>-d 1</b>
</p>

<p style="margin-bottom: 0; font-weight: bold;">You can download compatible versions of ccminer here :</p>
<ul>
<li><a href="https://github.com/tpruvot/ccminer/releases" target="_blank">https://github.com/tpruvot/ccminer/releases</a></li>
<li><a href="https://github.com/KlausT/ccminer/releases" target="_blank">https://github.com/KlausT/ccminer/releases</a></li>
</ul>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
