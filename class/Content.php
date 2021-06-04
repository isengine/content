<?php

namespace is\Masters\Modules\Isengine;

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

use is\Masters\Modules\Isengine\Filter;

class Content extends Master {
	
	public $current; // название текущего материала
	public $parents; // путь к родителям
	
	public $cache; // путь к кэшу
	
	public $list;
	public $count;
	public $position;
	
	public $filter;
	
	public function launch() {
		
		$sets = &$this -> settings;
		
		$this -> data = new Collection;
		
		$this -> filter = new Filter;
		$this -> filter -> launch();
		$this -> filter -> excepts($sets['rest']);
		$this -> filter -> setData($sets['filter']);
		$this -> filter -> filterRest();
		
		$this -> check();
		
		$result = null;
		
		if ($sets['cache']) {
			$this -> setCache();
			$result = $this -> readCache();
		}
		
		if (!$result) {
			$this -> read();
			$this -> sort($sets['sort']);
			$this -> list = $this -> data -> getNames();
			$this -> count = $this -> data -> count();
			$this -> limit($sets['skip'], $sets['limit']);
		}
		
		if ($sets['cache']) {
			$this -> writeCache();
		}
		
		$this -> position();
		
		$this -> sort($sets['sort-after']);
		
		//echo '<pre>';
		//echo print_r($this -> data, 1);
		//echo '</pre>';
		
	}
	
	public function check() {
		
		$router = Router::getInstance();
		
		$this -> parents = $this -> settings['parents'] ? $this -> settings['parents'] : Strings::join($router -> content['parents'], ':');
		
		$this -> current = $this -> settings['name'] ? $this -> settings['name'] : $router -> content['item'];
		
	}
	
	public function setCache() {
		
		if (!$this -> parents) {
			return;
		}
		
		$config = Config::getInstance();
		$parent = Strings::before($this -> parents, ':');
		$hash = Prepare::hash(Parser::toJson([
			'name'     => $this -> current,
			'parents'  => $this -> parents,
			'database' => $this -> settings['db'],
			'filter'   => $this -> filter -> getData(),
			'sort'     => $this -> settings['sort'],
			'skip'     => $this -> settings['skip'],
			'limit'    => $this -> settings['limit']
		]));
		
		$this -> cache = $config -> get('path:cache') . 'content' . DS . $parent . DS . $hash . '.ini';
		
	}
	
	public function readCache() {
		
		if (!$this -> parents) {
			return true; // это важно!
		}
		
		if (!Local::matchFile($this -> cache)) {
			return; // и это важно!
		}
		
		$content = Parser::fromJson( Local::readFile($this -> cache) );
		if ($content) {
			$this -> list = $content['list'];
			$this -> count = $content['count'];
			$this -> data -> addByList( $content['data'] );
		}
		unset($content);
		
		return true; // и это!
		
	}
	
	public function writeCache() {
		
		if (!$this -> parents || !$this -> cache) {
			return;
		}
		
		$content = Parser::toJson([
			'list' => $this -> list,
			'count' => $this -> count,
			'data' => $this -> data -> getData()
		]);
		
		if ($content) {
			Local::createFolder( Strings::before($this -> cache, DS, true, true) );
			Local::writeFile($this -> cache, $content);
		}
		
		unset($content);
		
	}
	
	public function read() {
		
		if ($this -> settings['db']) {
			$db = new Datasheet;
			$db -> init( $this -> settings['db'] );
			$db -> query('read');
			$db -> rights(true);
			$db -> driver -> parents( $this -> parents );
		} else {
			$db = Database::getInstance();
		}
		
		$db -> collection('content');
		
		if ($this -> parents) {
			$db -> driver -> filter -> addFilter('parents', '+' . Strings::replace($this -> parents, ':', ':+'));
		}
		if ($this -> current) {
			$db -> driver -> filter -> addFilter('name', '+' . $this -> current);
		}
		
		$this -> filter -> filtration($db -> driver -> filter);
		echo print_r($db -> driver -> filter, 1);
		
		$db -> launch();
		
		$this -> data -> addByList( $db -> data -> getData() );
		
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
			$by = 'name';
		}
		
		if ($type === 'random') {
			$this -> data -> randomize();
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
	
	public function position() {
		
		$list = $this -> data -> getNames();
		
		if (!$list) {
			return;
		}
		
		$this -> position = [
			Objects::first($list, 'key'),
			Objects::last($list, 'key')
		];
		
	}
	
}

?>