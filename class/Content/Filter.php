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
use is\Masters\Database;
use is\Masters\Datasheet;

class Filter extends Data {
	
	public $excepts;
	public $sets;
	
	public function init(&$sets) {
		$this -> excepts = [];
		$this -> sets = &$sets;
		$this -> setData($sets['filter']);
	}
	
	public function excepts() {
		$this -> excepts = Objects::add($this -> excepts, Objects::values($this -> sets['rest']));
	}
	
	public function rest() {
		
		$uri = Uri::getInstance();
		
		Objects::each($uri -> getData(), function($item, $key) {
			if (!Objects::match($this -> excepts, $key)) {
				$this -> addDataKey('data:' . $key, $item);
			}
		});
		unset($key, $item);
		
	}
	
	public function filtration(&$filter) {
		
		Objects::each($this -> getData(), function($item, $key) use ($filter) {
			$filter -> addFilter($key, $item);
		});
		
	}
	
}

?>