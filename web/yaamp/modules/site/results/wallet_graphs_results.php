<?php
$user = getuserparam(getparam('address'));
if (!$user) return;

echo <<<end
<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Hashrate: $user->username</div>
<div class="main-left-inner"><br>
end;


foreach (yaamp_get_algos() as $algo)
{
    $delay = time() - 24 * 60 * 60;
    $user_shares = controller()
        ->memcache
        ->get_database_scalar("wallet_hashuser-$user->id-$algo", "select count(*) from hashuser where userid=$user->id and time>$delay and algo=:algo limit 1", array(
        ':algo' => $algo
    ));

    $minercount = getdbocount('db_workers', "userid=$user->id and algo=:algo limit 1", array(
        ':algo' => $algo
    ));
    if (!$user_shares && !$minercount) continue;

    echo <<<end
<input type=hidden id=$algo class='graph_algo'>
<div id='graph_results_$algo' style='height: 240px;'></div><br>
end;

}

echo "</div></div><br>";
