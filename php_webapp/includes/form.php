<?php

function default_form_property($name, $value)
{
	if (!isset($_REQUEST[$name])) {
		$_REQUEST[$name] = $value;
	}
}
