<?php

$last = dboscalar("select max(last) from connections");
$list = getdbolist('db_connections', "1 order by id desc");

//echo "<table class='dataGrid'>";
showTableSorter('maintable');
echo "<thead>";
echo "<tr>";
echo "<th>ID</th>";
echo "<th>User</th>";
echo "<th>Host</th>";
echo "<th>Database</th>";
echo "<th>Idle</th>";
echo "<th>Created</th>";
echo "<th>Last</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

foreach($list as $conn)
{
	echo "<tr class='ssrow'>";

	$d1 = sectoa($conn->idle);
	$d2 = datetoa2($conn->created);
	$d3 = datetoa2($conn->last);
	$b = Booltoa($conn->last == $last);

	echo "<td>$conn->id</td>";
	echo "<td>$conn->user</td>";
	echo "<td>$conn->host</td>";
	echo "<td>$conn->db</td>";
	echo "<td>$d1</td>";
	echo "<td>$d2</td>";
	echo "<td>$d3</td>";
	echo "<td>$b</td>";

	echo "</tr>";
}

echo "</tbody></table><br>";

echo count($list)." connections to the database<br>";
