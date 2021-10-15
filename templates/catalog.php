<?php

namespace is\Masters\Modules\Isengine\Content;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;

$instance = $object -> get('instance');
$sets = &$object -> settings;

//$object -> eget('container') -> addClass('new');
//$object -> eget('container') -> open(true);
//$object -> eget('container') -> close(true);
//$object -> eget('container') -> print();

//System::debug($object -> map -> getData());
//System::debug($object -> getData());

?>

<?php
$object -> data -> iterate(function($item, $key, $position) use ($object) {
	
	$name = $item -> getEntryKey('name');
	$data = $item -> getData();
	
	System::debug($name, $key, $position, $data);
	
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
	
	<?php $object -> blocks('block'); ?>
	
</div>
