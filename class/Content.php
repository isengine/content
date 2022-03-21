<?php

namespace is\Masters\Modules\Isengine;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;
use is\Helpers\Parser;
use is\Helpers\Paths;
use is\Helpers\Prepare;
use is\Helpers\Local;
use is\Helpers\Sessions;

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
	public $type; // тип вывода контента - список, один материал или нет материалов
	
	public $list;
	
	public $filter;
	public $navigate;
	
	public $datetime; // объект Datatime
	public $tvar; // объект обработчика текстовых переменных
	public $translit; // объект транслитирования строк
	
	/*
	* Мы добавили в настройки:
	* имена полные, с родителями, в виде массива, для фильтрации
	* include - включаем только их
	* exclude - исключаем их
	* при указании обоих, сначала работает include, потом exclude
	* так, например, мы можем указать несколько обязательных материалов
	* и исключить из них текущий
	*/
	
	/*
	* в сам класс нужно внести доработки, связанные с выводом модуля на странице
	* дело в том, что если модуль использует родителей, может возникнуть путаница
	* например, на странице /news/ вызывается материал news1 с родителем news
	* вроде бы все просто, нужно не учитывать страницу:
	* /news/news1/
	* а если материал вызывается на странице /articles/?
	* ок, можно не учитывать первого родителя:
	* /articles/news1/
	* но тогда не будет работать роутинг!
	* ок, а если материал вызывается на странице /content/news/?
	* а если материал использует двух и более родителей?
	* все это нуждается в большой доработке, но уже не в рамках данной версии
	* в рамках данной версии, мы использует вариант 1, т.е. не учитываем страницу
	*/
	
	public function launch() {
		
		$sets = &$this -> settings;
		
		// кэширование
		
		$uri = Uri::getInstance();
		$url = $uri -> path['string'];
		
		$config = Config::getInstance();
		$caching = System::exists($sets['cache']) && $sets['cache'] !== 'default' ? $sets['cache'] : $config -> get('cache:content');
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
			
			// очевидно, excepts - это ислючения для фильтрации
			// чтобы rest запросы типа items, page не попадали в общий фильтр
			
			if ($sets['routing']) {
				$this -> filter -> rest();
			}
			
			// есть ли здесь фильтрация между датами создания и удаления
			// если нет, то ее нужно добавлять по необходимости, например, в настройки
			// или выключать, чтобы можно было выводить весь контент, например, в админке
			// но это нужно делать до сортировки и навигации, очевидно чтобы исключенные материалы
			// не влияли на уменьшение числа материалов на странице и т.д.
			
			// а где же вывод материалов согласно запрошенной странице?
			// очевидно, должен быть через iterate
			
			$this -> check();
			
			$this -> read();
			$this -> sort($sets['sort']);
			$this -> list = $this -> data -> getNames();
			
			$name = $this -> current ? ($this -> parents ? $this -> parents . ':' : null) . $this -> current : null;
			
			$this -> navigate = new Navigate;
			$this -> navigate -> init($sets);
			$this -> navigate -> list($this -> list);
			$this -> navigate -> current($name);
			$this -> navigate -> launch();
			
			if ($sets['routing'] && $name) {
				
				$map = $this -> data -> map -> count;
				
				if (is_array($this -> list) && Objects::match($this -> list, $name)) {
					$this -> data -> leaveByName($name);
				} elseif (is_array($map) && Objects::matchByIndex($map, $name)) {
					$this -> parents .= ':' . $this -> current;
					$this -> current = null;
					$this -> data -> addFilter('parents', '+' . Strings::replace($name, ':', ':+'));
					$this -> data -> filtration();
					$this -> data -> leaveByList($this -> list, 'name');
				} else {
					$this -> data -> reset();
				}
				
			} else {
				
				$this -> data -> removeByCut(
					($this -> navigate -> page - 1) * $sets['limit'] + $sets['skip']
				);
				
				$this -> sort($this -> navigate -> sort ? $this -> navigate -> sort : $sets['sort-after']);
				
				$this -> data -> removeByCut(
					0,
					$sets['limit']
				);
				
			}
			
			$this -> data -> countMap();
			
			$this -> type();
			
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
		$this -> parents = $this -> settings['parents'] ? $this -> settings['parents'] : ($this -> settings['routing'] ? Strings::join($router -> content['parents'], ':') : null);
		$this -> current = $this -> settings['name'] ? $this -> settings['name'] : ($this -> settings['routing'] ? $router -> content['name'] : null);
	}
	
	public function read() {
		
		if ($this -> settings['db']) {
			$db = new Datasheet;
			$db -> init( $this -> settings['db'] );
			$db -> query('read');
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
		
		if ($this -> settings['include']) {
			//System::debug($this -> settings['include']);
			$db -> data -> leaveByList($this -> settings['include'], 'name');
		}
		
		if ($this -> settings['exclude']) {
			$names = Objects::remove(
				$db -> data -> getNames(),
				$this -> settings['exclude']
			);
			//System::debug($this -> settings['exclude']);
			//System::debug($names);
			$db -> data -> leaveByList($names, 'name');
		}
		
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
	
	public function type() {
		// определяет тип контента
		if (System::set($this -> current)) {
			$this -> type = $this -> data -> getFirst() ? 'alone' : 'none';
		} else {
			$this -> type = $this -> navigate -> pages && $this -> navigate -> page > $this -> navigate -> pages ? 'none' : 'list';
		}
	}
	
	public function iterate($files = null) {
		
		if (!$this -> type) {
			return;
		}
		
		if (!$files) {
			$files = ($this -> template && $this -> template !== 'default' ? $this -> template : Strings::after($this -> instance, ':', null, true)) . ':' . $this -> type;
		}
		
		if ($this -> type === 'list') {
			$this -> data -> iterate(function($item, $key, $pos) use ($files){
				if (System::typeIterable($files)) {
					foreach ($files as $file) {
						$this -> block($file, $item);
					}
					unset($file);
				} else {
					$this -> block($files, $item);
				}
			});
		} else {
			$item = $this -> data -> getFirst();
			if (System::typeIterable($files)) {
				foreach ($files as $file) {
					$this -> block($file, $item);
				}
				unset($file);
			} else {
				$this -> block($files, $item);
			}
			unset($item);
		}
		
	}
	
}

?>