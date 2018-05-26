<?php

echo "<a href='/site/coin?id=$coin->id'>$coin->name</a> ";
echo "$market->name<br>";

$this->widget('UniForm');

echo CUFHtml::beginForm();
echo CUFHtml::errorSummary($market);
echo CUFHtml::openTag('fieldset', array('class'=>'inlineLabels'));

echo CUFHtml::openActiveCtrlHolder($market, 'deposit_address');
echo CUFHtml::activeLabelEx($market, 'deposit_address');
echo CUFHtml::activeTextField($market, 'deposit_address', array('maxlength'=>200));
echo '<p class="formHint2">Use Address::PaymentID on XMR forks</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($market, 'base_coin');
echo CUFHtml::activeLabelEx($market, 'base_coin');
echo CUFHtml::activeTextField($market, 'base_coin', array('maxlength'=>16,'style'=>'width: 40px;'));
echo '<p class="formHint2">Default (empty) is BTC</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::closeTag('fieldset');
showSubmitButton('Save');
echo CUFHtml::endForm();



