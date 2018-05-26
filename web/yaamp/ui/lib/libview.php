<?php

function showButtonHeader()
{
	echo "<div class='buttonwrapper'>";
}

function showButton($name, $link, $htmlOptions = array())
{
	echo CHtml::link($name, $link, $htmlOptions);
}

function showButtonPost($name, $htmlOptions)
{
	echo CHtml::linkButton($name, $htmlOptions);
}

function showTextTeaser($text, $more, $count = 120, $class = 'text')
{
	if(empty($text)) return "";

	$text = strip_tags($text);
	if(strlen($text) < $count)
	{
		echo "<p class='$class'>$text</p>";
		return;
	}

	$text = substr($text, 0, $count)."...";
	echo "<p class='$class'>".$text." [".CHtml::link("more...", $more)."]</p>";
}

function getTextTeaser($text, $count = 120)
{
	if(empty($text)) return "";

	$text = strip_tags($text);
	if(strlen($text) < $count)
		return $text;

	$text = substr($text, 0, $count)."...";
	return $text;
}

function getTextTitle($text)
{
	$b = preg_match('/([^\.\r\n]*)/', $text, $match);
	return $match[1];
}

function showTableSorter($id, $options='')
{
	JavascriptReady("
		$('#{$id}').tablesorter({$options});
		$('.tablesorter-header').not('.sorter-false').css('cursor', 'pointer');"
	);
	echo "<table id='$id' class='dataGrid2'>";
}




