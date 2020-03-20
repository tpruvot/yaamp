<?php
$series = array();
$n = 0;
$maxdiff = 0.0;

$json = controller()
    ->memcache
    ->get("yiimp-explorer-diff-" . $coin->symbol);

if (empty($json))
{

    // version is used in multi algo coins
    $multiAlgos = versionToAlgo($coin, 0) !== false;

    $series['diff'] = array();
    $tm = 0;

    $remote = new WalletRPC($coin);
    for ($i = $coin->block_height;$i > max(0, $coin->block_height - 500);$i--)
    {
        $hash = $remote->getblockhash($i);
        if (!$hash) continue;

        $block = $remote->getblock($hash);
        if (!$block) continue;

        // only graph PoW blocks
        if (!arraySafeval($block, 'nonce', 0) && !arraySafeval($block, 'auxpow', 0)) continue;

        $n++;

        $tm = $block['time'];
        $dt = date('Y-m-d H:i:s', $tm);
        $diff = floatval($block['difficulty']);
        $vers = $block['version'];
        $algo = versionToAlgo($coin, $vers);

        if (!$multiAlgos)
        {
            $maxdiff = max($diff, $maxdiff);
            $series['diff'][$n] = array(
                $dt,
                $diff,
                $block['height']
            );
        }
        else if ($coin->algo == $algo)
        {
            $maxdiff = max($diff, $maxdiff);
            $series[$algo][$n] = array(
                $dt,
                $diff,
                $block['height']
            );
        }
    }

    // User blocks
    if ($tm)
    {
        $sql = "SELECT id, height, IFNULL(difficulty_user, difficulty) AS diff, time FROM blocks" . " WHERE coin_id=:coin AND algo = :algo AND time >= :tm ORDER BY id";
        $rows = dbolist($sql, array(
            ':coin' => $coin->id,
            ':algo' => $coin->algo,
            ':tm' => intval($tm)
        ));

        $n = 0;
        foreach ($rows as $row)
        {
            $diff = floatval($row['diff']);
            if ($maxdiff && $diff > ($maxdiff * 1.5)) $diff = $maxdiff * 1.5;
            $tm = $row['time'];
            $dt = date('Y-m-d H:i:s', $tm);
            $series['user'][$n] = array(
                $dt,
                $diff,
                $row['diff'],
                $row['height']
            );
            $n++;
        }
    }

    $json = '';
    foreach ($series as $algo => $data)
    {
        if (empty($data)) continue;
        $values = array_values($data);
        $json .= json_encode($values) . ',';
    }
    $json = rtrim($json, ',');

    // memcache the data
    controller()
        ->memcache
        ->set("yiimp-explorer-diff-" . $coin->symbol, $json, 120);
}

echo "[$json]";
