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
use is\Masters\Modules\Isengine\Content\Structure;

class Content extends Master
{
    public $name; // полное имя текущего материала, включая его родителей
    public $current; // название текущего материала
    public $parents; // путь к родителям
    public $type; // тип вывода контента - список, один материал или нет материалов

    public $list; // список материалов
    public $map; // карта разделов

    public $filter;
    public $navigate;
    public $structure;

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

    public function launch()
    {
        $sets = $this->settings;

        // структура данных

        $this->structure = new Structure();
        $this->structure->init($sets);

        // кэширование

        $uri = Uri::getInstance();
        $url = $uri->path['string'];

        $config = Config::getInstance();
        $caching = $sets['cache'] !== 'default' ? $sets['cache'] : $config->get('cache:content');
        $path =
            $config->get('path:cache') . 'content' . DS
            . (
                $sets['db']['parents']
                ? Paths::toReal($sets['db']['parents']) . DS
                : null
            );
        $original = DR . Paths::toReal($sets['db']['name'] . DS . $sets['db']['collection']);

        $cache = new Cache($path);
        $cache->caching($caching);
        $cache->init(
            $this->instance,
            $this->template,
            $this->settings,
            $url
        );

        // сравнение под вопросом, т.к. это только для файлов и непонятно, что он проверяет
        $cache->compare($original);

        $data = $cache->start();

        if (!$data) {
            // Задаем другие базовые свойства

            $this->datetime = Datetime::getInstance();
            $this->datetime->setFormat('{a}');

            $view = View::getInstance();
            $this->tvars = $view->get('tvars');
            $this->translit = $view->get('translit');
            unset($view);

            // Создаем контент

            $this->data = new Collection();

            $this->filter = new Filter();
            $this->filter->init($sets);
            $this->filter->excepts();

            // очевидно, excepts - это ислючения для фильтрации
            // чтобы rest запросы типа items, page не попадали в общий фильтр

            if ($sets['routing']) {
                $this->filter->rest();
            }

            // есть ли здесь фильтрация между датами создания и удаления
            // если нет, то ее нужно добавлять по необходимости, например, в настройки
            // или выключать, чтобы можно было выводить весь контент, например, в админке
            // но это нужно делать до сортировки и навигации, очевидно чтобы исключенные материалы
            // не влияли на уменьшение числа материалов на странице и т.д.

            // а где же вывод материалов согласно запрошенной странице?
            // очевидно, должен быть через iterate

            $this->check();

            $this->read();
            $this->sort($sets['sort']);
            $this->list = $this->data->getNames();

            // было
            //$name = $this->current ? ($this->parents ? $this->parents . ':' : null) . $this->current : null;
            // стало
            $this->name();

            // здесь требуется дополнительная проверка
            // потому что со вложенными родителями возникает проблема
            // когда последнего родителя система воспринимает как материал
            // это естественно, потому что мы не можем сделать точного определения
            // либо была бы дополнительная нагрузка для каждой страницы -
            // проверять по всему контенту
            // поэтому делаем проще
            // смотрим карту и ищем в ней родителя
            // и если он есть, то переназначаем current, parents и name

            if (
                System::set($this->name) &&
                System::typeIterable($this->map) &&
                Objects::matchByIndex(
                    $this->map,
                    $this->name
                )
            ) {
                $this->parents .= ':' . $this->current;
                $this->current = null;
                $this->name();

                $this->data->addFilter('parents', '+' . Strings::replace($this->parents, ':', ':+'));
                $this->data->filtration();
                $this->list = $this->data->getNames();
                $this->data->leaveByList($this->list, 'name');
            }

            //System::debug($this);
            //System::debug($this->map, '!q');
            //System::debug($this->parents, '!q');
            //System::debug($this->current, '!q');
            //System::debug($this->name, '!q');

            $this->navigate = new Navigate();
            $this->navigate->init($sets);
            $this->navigate->list($this->list);
            $this->navigate->current($this->name);
            $this->navigate->launch();

            if ($sets['routing'] && $this->name) {
                if (is_array($this->list) && Objects::match($this->list, $this->name)) {
                    $this->data->leaveByName($this->name);
                } elseif (is_array($this->map) && Objects::matchByIndex($this->map, $this->name)) {
                    $this->parents .= ':' . $this->current;
                    $this->current = null;
                    $this->data->addFilter('parents', '+' . Strings::replace($this->name, ':', ':+'));
                    $this->data->filtration();
                    $this->data->leaveByList($this->list, 'name');
                } else {
                    $this->data->reset();
                }
            } else {
                $this->data->removeByCut(
                    ($this->navigate->page - 1) * $sets['limit'] + $sets['skip']
                );

                $this->sort($this->navigate->sort ? $this->navigate->sort : $sets['sort-after']);

                $this->data->removeByCut(
                    0,
                    $sets['limit']
                );
            }

            $this->data->countMap();

            $this->type();

            $this->template();

            //if ( !System::includes($this->template, $this->custom . 'templates', null, $this) ) {
            //    if ( !System::includes($this->template, $this->path . 'templates', null, $this) ) {
            //        if ( !System::includes('default', $this->custom . 'templates', null, $this) ) {
            //            System::includes('default', $this->path . 'templates', null, $this);
            //        }
            //    }
            //}
        }

        $cache->stop();

        return true;

        //echo '<pre>';
        //echo $this->navigate->get('ses');
        //echo print_r($this->data, 1);
        //echo print_r($this->navigate, 1);
        //echo print_r($this, 1);
        //echo '</pre>';
        //exit;
    }

    public function check()
    {
        $router = Router::getInstance();
        if (isset($router->content['name'])) {
            $name = $router->content['name'];
        }
        if (isset($router->content['parents'])) {
            $parents = $router->content['parents'];
        }

        $this->parents =
            $this->settings['parents']
            ? $this->settings['parents']
            : ($this->settings['routing'] ? Strings::join($parents, ':') : null);
        $this->current =
            $this->settings['name']
            ? $this->settings['name']
            : ($this->settings['routing'] ? $name : null);
    }

    public function map($map)
    {
        $this->map = $map;
    }

    public function name()
    {
        $this->name = $this->current ? ($this->parents ? $this->parents . ':' : null) . $this->current : null;
    }

    public function read()
    {
        if (
            //System::typeOf($this->settings['db'], 'iterable') &&
            $this->settings['db']['driver'] &&
            $this->settings['db']['collection']
        ) {
            $db = new Datasheet();
            $db->init($this->settings['db']);
            $db->query('read');
            $db->collection($this->settings['db']['collection']);
        } else {
            $db = Database::getInstance();
            $db->collection('content');
        }

        if ($this->parents) {
            $db->driver->filter->addFilter('parents', '+' . Strings::replace($this->parents, ':', ':+'));
        }

        // 1. $db->launch(); вверх, т.е. сюда
        $db->launch();

        // 2. получить карту и записать ее в свойство этого класса
        $db->data->countMap();
        $this->map($db->data->map->count);

        // 3. сделать, очевидно здесь, инклюд/эксклюд, т.к. они
        // влияют на общие настройки контента, но уже после карты,
        // чтобы определение групп от одиночных материалов точно не сбилось
        if ($this->settings['include']) {
            //System::debug($this->settings['include']);
            $db->data->leaveByList($this->settings['include'], 'name');
        }

        if ($this->settings['exclude']) {
            $names = Objects::remove(
                $db->data->getNames(),
                $this->settings['exclude']
            );
            //System::debug($this->settings['exclude']);
            //System::debug($names);
            $db->data->leaveByList($names, 'name');
        }

        // 4. вызвать метод фильтра, который будет собирать данные
        // для заполнения окна фильтрации, но это только если есть
        // параметры фильтрации - не путать с параметрами фильтра,
        // которые задаются для предварительной фильтрации материалов
        // для вывода вообще в рамках данных настроек модуля
        $this->filter->list($db->data);

        // 5. теперь только мы вызываем фильтрацию, но она работает
        // как постфильтр, в том числе leaveByList и пр.
        // было
        //$this->filter->filtration($db->driver->filter);
        // стало
        $this->filter->filtration($db->data);
        $db->data->filtration();
        $db->data->leaveByList($db->data->getNames(), 'name');

        // здесь уже как было

        $this->data->addByList($db->data->getData());
        $this->data->countMap();

        $db->clear();
        unset($db);
    }

    public function sort($data = null)
    {
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
            $this->data->random();
            return;
        }

        if ($by === 'id') {
            $this->data->sortById();
        } elseif ($by === 'name') {
            $this->data->sortByName();
        } elseif ($by === 'data') {
            $this->data->sortByData($by_data);
        } else {
            $this->data->sortByEntry($by);
        }

        if ($type === 'desc') {
            $this->data->reverse();
        }
    }

    public function limit($skip = null, $limit = null)
    {
        //$this->data->removeByLen($skip, $limit);
        $this->data->removeByCut($skip, $limit);
        //$this->data->names = Objects::get($this->data->names, $skip ? $skip : 0, $limit ? $limit : null);
    }

    public function datetime($item, $format = null)
    {
        // возвращает дату в нужном формате
        // иначе формат даты остается без преобразований
        // если item = null, вернет текущую метку
        return $this->datetime->convertDate($item, null, $format);
    }

    public function tvars(&$item)
    {
        // вызывает обработку текстовых переменных для элемента
        $item = $this->tvars->launch($item);
    }

    public function translit($item, $to = null, $from = null)
    {
        // вызывает транслитирование строки
        return $this->translit->launch($item, $to, $from);
    }

    public function type()
    {
        // определяет тип контента
        if (System::set($this->current)) {
            $this->type = $this->data->getFirst() ? 'alone' : 'none';
        } else {
            $this->type = $this->navigate->pages && $this->navigate->page > $this->navigate->pages ? 'none' : 'list';
        }
    }

    public function iterate($files = null)
    {
        if (!$this->type) {
            return;
        }

        if (!$files) {
            $files =
                (
                    $this->template && $this->template !== 'default'
                    ? $this->template
                    : Strings::after($this->instance, ':', null, true)
                ) . ':' . $this->type;
        }

        if ($this->type === 'list') {
            $this->data->iterate(function ($item, $key, $pos) use ($files) {
                $item->setData(
                    Objects::create(
                        $this->structure->getData(),
                        $item->getData()
                    )
                );
                if (System::typeIterable($files)) {
                    foreach ($files as $file) {
                        $this->block($file, $item);
                    }
                    unset($file);
                } else {
                    $this->block($files, $item);
                }
            });
        } else {
            $item = $this->data->getFirst();
            $item->setData(
                Objects::create(
                    $this->structure->getData(),
                    $item->getData()
                )
            );
            if (System::typeIterable($files)) {
                foreach ($files as $file) {
                    $this->block($file, $item);
                }
                unset($file);
            } else {
                $this->block($files, $item);
            }
            unset($item);
        }
    }
}
