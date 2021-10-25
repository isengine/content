<?php

namespace is\Masters\Modules\Isengine\Content;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;

$instance = Strings::replace($this -> instance, ':', '-');
$sets = &$this -> settings;

//echo print_r($this, 1);

//$this -> eget('container') -> addClass('new');
//$this -> eget('container') -> open(true);
//$this -> eget('container') -> close(true);
//$this -> eget('container') -> print();

?>

<?php
$this -> data -> iterate(function($item, $key, $position) use ($this) {
	$name = $item -> getEntryKey('name');
	$data = $item -> getData();
	//echo print_r($key, 1) . '<br>';
	//echo print_r($data, 1) . '<br>';
	$sets = &$this -> settings;
	
	echo print_r($data, 1) . '<br>';
	
});
?>

<div class="<?= $instance; ?>">
	
	<p><?= $sets['key']; ?></p>
	
	<?php
		if (System::typeIterable($sets['array'])) {
	?>
	<ul>
	<?php
		foreach ($sets['array'] as $item) {
	?>
		<li><?= $item; ?></li>
	<?php
		}
		unset($item);
	?>
	</ul>
	<?php
		}
	?>
	
	<?php $this -> block('block'); ?>
	
</div>