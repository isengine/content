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
	
	public $list; // список всех значений для фильтрации
	
	/*
	* Список записывается так:
	* "filtration" : {
	*   "list" : {
	*     "price:range" : "...",
	*     "color" : "...",
	*     "matherial:search" : "...",
	*     ...
	*   },
	* ...
	* }
	* 
	* в ключах списка можно указать имя:тип
	* имя повторяет ключ данных в материале
	* по-умолчанию тип составляет массив из всех значений
	* range записывает только два значения - мин и макс
	* search вообще не сохраняет значений, т.к. будет идти поиск
	* значение содержит вызываемый блок поля фильтрации
	*/
	
	public function init(&$sets) {
		$this -> excepts = [];
		$this -> sets = &$sets;
		$this -> setData($sets['filter']);
		
		$this -> list = [];
	}
	
	public function excepts() {
		$this -> excepts = Objects::add($this -> excepts, System::typeIterable($this -> sets['rest']) ? Objects::values($this -> sets['rest']) : []);
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
	
	public function list(&$collection) {
		
		$list = $this -> sets['filtration']['list'];
		
		if (!System::typeIterable($list)) {
			return;
		}
		
		Objects::each($collection -> getData(), function($item) use ($list) {
			
			$data = $item -> getData();
			
			Objects::each($list, function($item, $key) use ($data) {
				
				$key = Strings::split($key, ':');
				$type = $key[1];
				$key = $key[0];
				
				$value = $data[$key];
				
				if (!System::set($value)) {
					return;
				}
				
				if ($type === 'search') {
					$this -> list[$key] = null;
				} elseif ($type === 'range') {
					$value = System::typeTo($value, 'numeric');
					if (!System::typeIterable($this -> list[$key])) {
						$this -> list[$key] = [$value, $value];
					} elseif ($value < $this -> list[$key][0]) {
						$this -> list[$key][0] = $value;
					} elseif ($value > $this -> list[$key][1]) {
						$this -> list[$key][1] = $value;
					}
				} elseif (
					System::typeOf($value, 'iterable')
				) {
					$this -> list[$key] = Objects::add($this -> list[$key], $value);
					$this -> list[$key] = Objects::unique($this -> list[$key]);
				} elseif (
					!System::typeIterable($this -> list[$key]) ||
					!Objects::match($this -> list[$key], $value)
				) {
					$this -> list[$key][] = $value;
				}
				
			});
			
		});
		
		//System::debug($this -> list); // это массив для значений фильтров с ключами
		
	}
	
}

?>