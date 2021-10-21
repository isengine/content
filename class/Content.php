<?php

namespace is\Masters\Modules\Isengine;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;
use is\Helpers\Parser;
use is\Helpers\Paths;
use is\Helpers\Prepare;
use is\Helpers\Local;

use is\Components\Cache;
use is\Components\Collection;
use is\Components\Config;
use is\Components\Datetime;
use is\Components\Router;
use is\Components\Uri;

use is\Masters\Modules\Master;
use is\Masters\View;
use is\Masters\Database;
use is\Masters\Datasheet;

use is\Masters\Modules\Isengine\Content\Filter;
use is\Masters\Modules\Isengine\Content\Navigate;

class Content extends Master {
	
	public $current; // название текущего материала
	public $parents; // путь к родителям
	
	public $list;
	
	public $filter;
	public $navigate;
	
	public $datetime; // объект Datatime
	public $tvar; // объект обработчика текстовых переменных
	public $translit; // объект транслитирования строк
	
	public function launch() {
		
		$sets = &$this -> settings;
		
		// кэширование
		
		$uri = Uri::getInstance();
		$url = $uri -> path['string'];
		
		$config = Config::getInstance();
		$caching = $config -> get('cache:content');
		$path = $config -> get('path:cache') . 'content' . DS . ($sets['db']['parents'] ? Paths::toReal($sets['db']['parents']) . DS : null);
		$original = DR . Paths::toReal($sets['db']['name'] . DS . $sets['db']['collection']);

		$cache = new Cache($path);
		$cache -> caching($caching);
		
		$cache -> init(
			$this -> instance,
			$this -> template,
			$this -> settings,
			$url
		);
		
		$cache -> compare($original);
		
		$data = $cache -> start();
		
		if (!$data) {
			
			// Задаем другие базовые свойства
			
			$this -> datetime = Datetime::getInstance();
			$this -> datetime -> setFormat('{a}');
			
			$view = View::getInstance();
			$this -> tvars = $view -> get('tvars');
			$this -> translit = $view -> get('translit');
			unset($view);
			
			// Создаем контент
			
			$this -> data = new Collection;
			
			$this -> filter = new Filter;
			$this -> filter -> init($sets);
			$this -> filter -> excepts();
			$this -> filter -> rest();
			
			$this -> check();
			
			$this -> read();
			$this -> sort($sets['sort']);
			$this -> list = $this -> data -> getNames();
			
			$this -> navigate = new Navigate;
			$this -> navigate -> init($sets);
			$this -> navigate -> list($this -> list);
			$this -> navigate -> current($this -> current);
			$this -> navigate -> launch();
			
			if ($sets['routing'] && $this -> current) {
				
				$name = ($this -> parents ? $this -> parents . ':' : null) . $this -> current;
				//System::debug($name, '!q');
				
				$names = $this -> data -> getNames();
				$map = $this -> data -> map -> count;
				
				if (is_array($names) && Objects::match($names, $name)) {
					$this -> data -> leaveByName($name);
				} elseif (is_array($map) && Objects::matchByIndex($map, $name)) {
					$this -> parents .= ':' . $this -> current;
					$this -> current = null;
					$this -> data -> addFilter('parents', '+' . Strings::replace($name, ':', ':+'));
					$this -> data -> filtration();
					$this -> data -> leaveByList($this -> data -> getNames(), 'name');
				} else {
					$this -> data -> reset();
				}
				
			} else {
				$this -> limit($sets['skip'], $sets['limit']);
			}
			
			$this -> sort($sets['sort-after']);
			$this -> data -> countMap();
			
			$this -> template();
			
			//if ( !System::includes($this -> template, $this -> custom . 'templates', null, $this) ) {
			//	if ( !System::includes($this -> template, $this -> path . 'templates', null, $this) ) {
			//		if ( !System::includes('default', $this -> custom . 'templates', null, $this) ) {
			//			System::includes('default', $this -> path . 'templates', null, $this);
			//		}
			//	}
			//}
			
		}
		
		$cache -> stop();
		
		return true;
		
		//echo '<pre>';
		//echo $this -> navigate -> get('ses');
		//echo print_r($this -> data, 1);
		//echo print_r($this -> navigate, 1);
		//echo print_r($this, 1);
		//echo '</pre>';
		//exit;
		
	}
	
	public function check() {
		$router = Router::getInstance();
		$this -> parents = $this -> settings['parents'] ? $this -> settings['parents'] : Strings::join($router -> content['parents'], ':');
		$this -> current = $this -> settings['name'] ? $this -> settings['name'] : $router -> content['name'];
	}
	
	public function read() {
		
		if ($this -> settings['db']) {
			$db = new Datasheet;
			$db -> init( $this -> settings['db'] );
			$db -> query('read');
			$db -> rights(true);
			$db -> collection($this -> settings['db']['collection']);
		} else {
			$db = Database::getInstance();
			$db -> collection('content');
		}
		
		if ($this -> parents) {
			$db -> driver -> filter -> addFilter('parents', '+' . Strings::replace($this -> parents, ':', ':+'));
		}
		
		$this -> filter -> filtration($db -> driver -> filter);
		
		$db -> launch();
		
		$this -> data -> addByList( $db -> data -> getData() );
		$this -> data -> countMap();
		
		$db -> clear();
		unset($db);
		
	}
	
	public function sort($data = null) {
		
		if (!$data) {
			return;
		}
		
		$sort = Strings::split($data, ':');
		
		$first = Objects::first($sort, 'value');
		$last = Objects::last($sort, 'value');
		
		$match = ['asc', 'desc', 'random'];
		
		if (Objects::match($match, $first)) {
			$type = $first;
		} elseif (Objects::match($match, $last)) {
			$type = $last;
		} else {
			$type = 'asc';
		}
		
		if (!Objects::match($match, $first)) {
			$by = $first;
			if ($by === 'data') {
				$by_data = $sort[1];
			}
		} else {
			$by = 'id';
		}
		
		if ($type === 'random') {
			$this -> data -> random();
			return;
		}
		
		if ($by === 'id') {
			$this -> data -> sortById();
		} elseif ($by === 'name') {
			$this -> data -> sortByName();
		} elseif ($by === 'data') {
			$this -> data -> sortByData($by_data);
		} else {
			$this -> data -> sortByEntry($by);
		}
		
		if ($type === 'desc') {
			$this -> data -> reverse();
		}
		
	}
	
	public function limit($skip = null, $limit = null) {
		
		//$this -> data -> removeByLen($skip, $limit);
		$this -> data -> removeByCut($skip, $limit);
		//$this -> data -> names = Objects::get($this -> data -> names, $skip ? $skip : 0, $limit ? $limit : null);
		
	}
	
	public function datetime($item, $format = null) {
		// возвращает дату в нужном формате
		// иначе формат даты остается без преобразований
		// если item = null, вернет текущую метку
		return $this -> datetime -> convertDate($item, null, $format);
	}
	
	public function tvars(&$item) {
		// вызывает обработку текстовых переменных для элемента
		$item = $this -> tvars -> launch($item);
	}
	
	public function translit($item, $to = null, $from = null) {
		// вызывает транслитирование строки
		return $this -> translit -> launch($item, $to, $from);
	}
	
	public function iterate() {
		
		//System::debug($this -> getData());
		
		if (System::set($this -> current)) {
			$this -> block($this -> instance . ':alone', $this -> data -> getFirst());
		} else {
			$this -> data -> iterate(function($item, $key, $pos){
				$this -> block($this -> instance . ':list', $item);
			});
		}
		
	}
	
}

?>