<?php

namespace is\Masters\Modules\Isengine\Content;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;

use is\Masters\View;

$sets = &$this->settings;
$instance = Strings::replace($this->instance, ':', '-');

$view = View::getInstance();

?>
<div class="<?= Strings::join($sets['classes'], ' '); ?>">
    <?= $view->get('lang|title'); ?>
</div>