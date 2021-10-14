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

use is\Masters\Modules\Isengine\Content\Filter;
use is\Masters\Modules\Isengine\Content\Navigate;

class Content extends Master {
	
	public $current; // название текущего материала
	public $parents; // путь к родителям
	
	public $list;
	
	public $filter;
	public $navigate;
	
	public function launch() {
		
		$sets = &$this -> settings;
		
		$this -> data = new Collection;
		
		$this -> filter = new Filter;
		$this -> filter -> init($sets);
		$this -> filter -> excepts();
		$this -> filter -> rest();
		
		$this -> check();
		
		$this -> read();
		$this -> sort($sets['sort']);
		$this -> list = $this -> data -> getNames();
		
		//$this -> data -> countMap(true);
		
		$this -> navigate = new Navigate;
		$this -> navigate -> init($sets);
		$this -> navigate -> list($this -> list);
		$this -> navigate -> current($this -> current);
		$this -> navigate -> launch();
		
		if ($this -> current) {
			$this -> data -> leaveByName($this -> current);
		} else {
			$this -> limit($sets['skip'], $sets['limit']);
		}
		
		$this -> sort($sets['sort-after']);
		
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
		
		$this -> current = $this -> settings['name'] ? $this -> settings['name'] : $router -> content['item'];
		
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
	
}

?>