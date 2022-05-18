<?php

namespace is\Masters\Modules\Isengine\Content;

use is\Helpers\System;
use is\Helpers\Objects;
use is\Helpers\Strings;
use is\Helpers\Paths;
use is\Parents\Data;
use is\Components\Cache;
use is\Components\Config;
use is\Components\Router;
use is\Masters\Database;

class Structure extends Data
{
    public $name;
    public $parents;
    public $settings;
    public $cache;

    public function init($sets)
    {
        $this->settings = $sets;

        $this->parents();

        $config = Config::getInstance();
        $caching = $this->settings['cache'] !== 'default' ? $this->settings['cache'] : $config->get('cache:content');
        $path =
            $config->get('path:cache') . 'content' . DS . 'structures' . DS
            . (
                $this->settings['db']['parents']
                ? Paths::toReal($this->settings['db']['parents']) . DS
                : null
            );

        $cache = new Cache($path);
        $cache->caching($caching);
        $cache->init($path);

        $data = $cache->read();

        if (!$data) {
            $db = Database::getInstance();
            $db->collection('content');

            if ($this->parents) {
                $db->driver->filter->addFilter('parents', '+' . Strings::replace($this->parents, ':', ':+'));
            }
            $db->driver->filter->addFilter('name', '+' . $this->name);
            $db->driver->filter->addFilter('type', '+settings');

            $db->launch();

            $data = $db->data->getFirstData();

            $db->clear();
            unset($db);
        }

        $cache->write($data);
        $this->setData($data);
        unset($data);
    }

    public function parents()
    {
        if ($this->settings['parents']) {
            $this->parents = $this->settings['parents'];
        } elseif ($this->settings['routing']) {
            $router = Router::getInstance();
            if (isset($router->content['parents'])) {
                $this->parents = Strings::join($router->content['parents'], ':');
            }
        }

        if ($this->parents) {
            $parents = Strings::split($this->parents, ':');
            $this->name = Objects::last($parents, 'value');
            $parents = Objects::unlast($parents);
            $this->parents = Strings::join($parents, ':');
        }
        //$this->parents =
        //  $this->settings['parents']
        //  ? $this->settings['parents']
        //  : ($this->settings['routing'] ? Strings::join($parents, ':') : null);
    }
}
