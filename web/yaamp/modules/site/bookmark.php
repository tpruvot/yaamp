<?php

// Edit bookmark address

echo "<a href='/site/coin?id={$coin->id}'>{$coin->name}</a> ";
echo "Bookmark: $bookmark->label<br>";

$this->widget('UniForm');

echo CUFHtml::beginForm();
echo CUFHtml::errorSummary($bookmark);
echo CUFHtml::openTag('fieldset', array('class'=>'inlineLabels'));

echo CUFHtml::openActiveCtrlHolder($bookmark, 'label');
echo CUFHtml::activeLabelEx($bookmark, 'label');
echo CUFHtml::activeTextField($bookmark, 'label', array('maxlength'=>32));
echo "<p class='formHint2'>.</p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($bookmark, 'address');
echo CUFHtml::activeLabelEx($bookmark, 'address');
echo CUFHtml::activeTextField($bookmark, 'address', array('maxlength'=>128));
echo "<p class='formHint2'>.</p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::closeTag('fieldset');
showSubmitButton('Save');
echo CUFHtml::endForm();
