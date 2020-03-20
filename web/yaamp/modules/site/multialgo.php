<br>

<div class="main-left-box">
<div class="main-left-title">MULTIALGO</div>
<div class="main-left-inner">

<p>Here's how you can achieve automatic switching to the best algo.</p>

<p style="width: 700px;">Use the password parameter to define a set of algos you want to mine. Your miner
connection will close (and move to your next configured algo) if the algo is not the best profitable of your set.</p>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>
-p x11,neoscrypt,lyra2
</pre>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>
-p scrypt,scryptn
</pre>

<p>The difficulty parameter can be combined with algos.</p>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>
-p d=64,scrypt,scryptn
</pre>

<p>Or with any other.</p>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>
-p rig1,scrypt,scryptn
</pre>

<p>Note that the password parameter must be all together, that is no spaces.</p>

<p>To complete you setup, you will need to configure your miner to round robin through all algos.</p>

<p>Here is an example of a windows batch file for ccminer.</p>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>

:start

ccminer -r 0 -a x11   -o stratum+tcp://<?=YAAMP_STRATUM_URL ?>:3533 -u joe -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a x13   -o stratum+tcp://<?=YAAMP_STRATUM_URL ?>:3633 -u joe -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a x15   -o stratum+tcp://<?=YAAMP_STRATUM_URL ?>:3733 -u joe -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a lyra2 -o stratum+tcp://<?=YAAMP_STRATUM_URL ?>:4433 -u joe -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a quark -o stratum+tcp://<?=YAAMP_STRATUM_URL ?>:4033 -u joe -p x11,x13,x14,x15,quark,lyra2

sleep 5000
goto start

</pre>

<p>By default, we use our built in factor table to normalize the profitability. The scrypt algo
is the reference with a factor of 1.</p>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>
'scrypt'	=> 1,
'scryptn'	=> 0.5,
'c11'		=> 2.0,
'x11'		=> 5.5,
'x13'		=> 3.9,
'x14'		=> 3.7,
'x15'		=> 3.5,
'nist5'		=> 6.0,
'zr5'		=> 10.0,
'drop'		=> 5.0;
'neoscrypt'	=> 0.3,
'lyra2'		=> 1.3,
'quark'		=> 6,
</pre>

<p style="width: 700px;">But you can also specify your own profitability factors for each algo.</p>

<pre class="main-left-box" style='padding: 3px; font-size: .9em; background-color: #ffffee; font-family: monospace;'>
-p x11=5.1,neoscrypt=0.5,lyra2=2
</pre>


<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>


</script>
