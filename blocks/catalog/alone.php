<?php

namespace is\Masters\Modules\Isengine\Content;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;

use is\Masters\View;

$sets = &$this->settings;
$instance = Strings::after($this->instance, ':', null, true);

$view = View::getInstance();

?>
<div class="">
    Alone
    <?php System::debug($item, '!q'); ?>
</div>