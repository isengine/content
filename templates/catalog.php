<?php

namespace is\Masters\Modules\Isengine\Content;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;

$instance = Strings::after($this->instance, ':', null, true);

?>
<div class="<?= $instance; ?>">
    <?php $this->iterate(); ?>
</div>