<?php

JavascriptFile('/yaamp/ui/js/auto_refresh.min.js');

echo "<div id='index_results'>";
echo "<br><br><br><br><br><br><br><br><br><br>";
echo "</div>";

echo "<br><br><br><br><br><br><br><br><br><br>";
echo "<br><br><br><br><br><br><br><br><br><br>";
echo "<br><br><br><br><br><br><br><br><br><br>";
echo "<br><br><br><br><br><br><br><br><br><br>";

echo <<<END

<script>

function page_refresh()
{
	index_refresh();
}

function index_ready(data)
{
	$('#index_results').html(data);
}

function index_refresh()
{
	var url = "/nicehash/index_results";
	$.get(url, '', index_ready);
}

</script>

END;

