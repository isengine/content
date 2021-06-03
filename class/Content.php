<?php

namespace is\Masters\Modules\Isengine;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;

use is\Components\Collection;
use is\Components\Router;
use is\Components\Uri;

use is\Parents\Data;

use is\Masters\Modules\Master;
use is\Masters\View;
use is\Masters\Database;

class Content extends Master {
	
	public $current;
	
	public function launch() {
		
		$sets = &$this -> settings;
		
		$this -> data = new Collection;
		
		$this -> read($sets['custom']);
		$this -> sort($sets['sort']);
		$this -> limit($sets['skip'], $sets['limit']);
		$this -> sort($sets['sort-after']);
		
		//echo '<pre>';
		//echo print_r($this -> data, 1);
		//echo '</pre>';
		
		//$state = $this -> someMethod();

	}
	
	public function read($sets = null) {
		
		$db = Database::getInstance();
		$db -> collection('content');
		
		if (System::set($sets)) {
			
			if (System::set($sets['name'])) {
				$this -> current = $sets['name'];
			} else {
				$uri = Uri::getInstance();
				$path = $uri -> path['array'];
				if (System::typeIterable($path)) {
					$this -> current = Objects::last($path, 'value');
				}
				unset($path, $uri);
			}
			
			$parents = $sets['parents'];
			if ($parents) {
				$db -> driver -> filter -> addFilter('parents', '+' . Strings::replace($parents, ':', ':+'));
			}
			
			$db -> launch();
			
		} else {
			
			$router = Router::getInstance();
			
			$name = $router -> content['name'];
			$last = Objects::last($router -> content['array'], 'value');
			$parents = Objects::unlast($router -> content['array']);
			$parents = System::typeIterable($parents) ? Strings::join($parents, ':+') : null;
			
			$db -> driver -> filter -> addFilter('parents', '+' . $name . ($parents ? ':+' . $parents : null) . ($last ? ':+' . $last : null));
			$db -> launch();
			
			if (!$db -> data -> getFirstData()) {
				
				$db -> clear();
				
				$db -> driver -> filter -> addFilter('parents', '+' . $name . ($parents ? ':+' . $parents : null));
				$db -> collection('content');
				$db -> launch();
				
				$this -> current = $last;
				
			}
			
		}
		
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
	
}

?>