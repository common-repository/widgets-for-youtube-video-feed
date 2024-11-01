<?php
defined('ABSPATH') or die('No script kiddies please!');
foreach ($this->getOptionNames() as $optName) {
delete_option($this->getOptionName($optName));
}
?>