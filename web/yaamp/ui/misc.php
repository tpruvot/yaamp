<?php

function showFlashMessage()
{
	if(user()->hasFlash('message'))
	{
		echo '<div class="ui-widget">';
		echo '<div class="ui-state-highlight ui-corner-all" style="color: green; padding: 8px; margin-bottom: 8px;">';
		echo user()->getFlash('message');
		echo '</div></div>';
	}

	if(user()->hasFlash('error'))
	{
		echo '<div class="ui-widget">';
		echo '<div class="ui-state-error ui-corner-all" style="padding: 8px; margin-bottom: 8px;">';
		echo user()->getFlash('error');
		echo '</div></div>';
	}
}

function showPageContent($content)
{
	echo "<div class='content-out'>";

	if(controller()->id=='renting')
		echo "<div class='content-inner' style='background: url(/images/beta_corner_banner2.png) top right no-repeat; '>";
	else
		echo "<div class='content-inner'>";

	showFlashMessage();
	echo $content;

//	echo "<br><br><br><br><br><br><br><br><br><br><br><br><br><br>";
//	echo "<br><br><br><br><br><br><br><br><br><br><br><br><br><br>";

	echo "</div>";
	echo "</div>";
}




