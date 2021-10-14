<?php

namespace is\Masters\Modules\Isengine\Content;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;
use is\Helpers\Parser;
use is\Helpers\Prepare;
use is\Helpers\Local;

use is\Components\Config;
use is\Components\Collection;
use is\Components\Router;
use is\Components\Uri;

use is\Parents\Data;

use is\Masters\Modules\Master;
use is\Masters\View;

class Map extends Data {
	
	public $total;
	
	public function init(&$sets) {
		$this -> setData($sets['map']);
	}
	
	public function total($value) {
		$this -> total = $value;
	}
	
	// сделать метод, когда мы проходимся по этой карте
	// и добавляем в нее значения:
	// count - счетчик вложенных элементов, берем из карты коллекции
	// link - ссылка на группу, формируем автоматически
	
}

?>