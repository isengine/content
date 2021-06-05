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

class Navigate extends Data {
	
	public $sets;
	
	public $skip; // число пропущенных материалов от начала
	
	public $all; // общее число материалов
	public $count; // число выводимых материалов
	
	public $from; // номер первого выводимого материала
	
	public $list; // список всех материалов
	public $display; // список выводимых материалов с сохранением порядкового номера
	
	public $current; // имя текущего материала
	public $first; // имя первого материала
	public $last; // имя последнего материала
	public $prev; // имя предыдущего материала
	public $next; // имя следующего материала
	
	public $page; // номер текущей страницы
	public $pages; // общее число страниц
	
	public $name_page; // служебное поле rest для номера страницы
	public $name_items; // служебное поле rest для числа материалов на одной странице
	
	public function init(&$sets) {
		
		$this -> sets = &$sets;
		
		$this -> name_page = $sets['rest']['page'];
		$this -> name_items = $sets['rest']['items'];
		
		$this -> skip = $this -> sets['skip'];
		$this -> count = $this -> sets['limit'];
		
	}
	
	public function list($list) {
		$this -> list = $list;
	}
	
	public function current($current) {
		$this -> current = $current;
	}
	
	public function launch() {
		
		$this -> all = Objects::len($this -> list);
		
		$first = Objects::first($this -> list);
		$this -> first = $first['value'];
		
		$last = Objects::last($this -> list);
		$this -> last = $last['value'];
		
		if ($this -> current) {
			$id = Objects::find($this -> list, $this -> current);
			$first = $first['key'];
			if ($id !== $first) {
				$this -> prev = $this -> list[$id - 1];
			}
			$last = $last['key'];
			if ($id !== $last) {
				$this -> next = $this -> list[$id + 1];
			}
		}
		
		
		//$this -> count = $sets['limit'];
		
		$uri = Uri::getInstance();
		
		$page = $uri -> getData($this -> name_page);
		$items = $uri -> getData($this -> name_items);
		
		if ($page) {
			$this -> page = $page;
		}
		if ($this -> page < 1) {
			$this -> page = 1;
		}
		
		if ($items) {
			$this -> count = $items;
		} elseif ($items === 0 || $items === '0') {
			$this -> count = null;
			$this -> skip = 0;
		}
		
		$this -> from = $this -> skip + ($this -> page - 1) * $this -> count;
		
		$this -> display = Objects::get($this -> list, $this -> from, $this -> count);
		
		$this -> pages = ceil(($this -> all - $this -> skip) / $this -> count);
		
		$this -> sets['skip'] = $this -> skip;
		$this -> sets['limit'] = $this -> count;
		
		//echo '<pre>';
		//echo '[' . print_r($this, 1) . ']';
		//exit;
		
	}
	
}

?>