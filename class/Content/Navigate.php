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

class Navigate extends Data
{
    public $sets;

    public $skip; // число пропущенных материалов от начала

    public $all; // общее число материалов
    public $count; // число выводимых материалов или не задано, если выводятся все

    public $from; // номер первого выводимого материала

    public $list; // список всех материалов
    public $display; // список выводимых материалов с сохранением порядкового номера

    public $id; // номер текущего материала в списке всех, не задано для списка
    public $current; // имя текущего материала, не задано для списка
    public $first; // имя первого материала в списке всех
    public $last; // имя последнего материала в списке всех
    public $prev; // имя предыдущего материала, не задано для списка
    public $next; // имя следующего материала, не задано для списка

    public $page; // номер текущей страницы
    public $pages; // общее число страниц или не задано, если выводятся все

    public $sort; // сортировка

    public $name_page; // служебное поле rest для номера страницы
    public $name_items; // служебное поле rest для числа материалов на одной странице
    public $name_sort; // служебное поле rest для окончательной сортировки материалов, вместо sort-after

    public $rest; // служебное поле, обозначающее задан ли rest или нужно использовать query
    public $keys; // служебное поле, обозначающее используются ли ключи для rest

    // данные из uri, которые имеются в данный момент, их очевидно нужно сохранить

    public function init(&$sets)
    {
        $this->sets = &$sets;

        if ($sets['routing']) {
            $this->name_page = $sets['rest']['page'];
            $this->name_items = $sets['rest']['items'];
            $this->name_sort = $sets['rest']['sort'];
        }

        $this->skip = $this->sets['skip'];
        $this->count = $this->sets['limit'];
    }

    public function list($list)
    {
        $this->list = $list;
    }

    public function current($current)
    {
        $this->current = $current;
    }

    public function launch()
    {
        if (
            !$this->name_page ||
            !$this->name_items ||
            !$this->name_sort
        ) {
            return;
        }

        $this->all = Objects::len($this->list);

        $first = Objects::first($this->list);
        $this->first = $first['value'];

        $last = Objects::last($this->list);
        $this->last = $last['value'];

        if ($this->current) {
            $id = Objects::find($this->list, $this->current);
            $first = $first['key'];
            if ($id !== $first) {
                $this->prev = $this->list[$id - 1];
            }
            $last = $last['key'];
            if ($id !== $last) {
                $this->next = $this->list[$id + 1];
            }
            $this->id = $id;
        }

        $uri = Uri::getInstance();

        // разберемся с сортировкой

        $this->sort = $uri->getData($this->name_sort);

        // разберемся с количеством записей на странице

        // получаем число записей из урла

        $items = $uri->getData($this->name_items);

        // сверяем число записей
        // здесь очень важно проверить, чтобы число записей было именно числом
        // и если задано число 0, то выводятся все записи без пропусков и лимитов

        if (System::type($items, 'numeric')) {
            if ($items) {
                $this->count = $items;
            } else {
                $this->count = null;
                $this->skip = 0;
            }
        }

        // разберемся со страницами

        // получаем общее число номеров страниц

        $this->pages = $this->count ? ceil(($this->all - $this->skip) / $this->count) : null;

        // получаем номер страницы из урла

        $page = $uri->getData($this->name_page);

        // сверяем номер страницы
        // здесь важно делать проверку на вывод списка или одиночного материала
        // путем проверки this->current
        // в одиночном материале мы математически высчитываем номер страницы в списке

        if ($this->current) {
            $page = floor(($this->id - $this->skip) / $this->count) + 1;
        }

        if (
            !System::type($page, 'numeric') ||
            !$page ||
            $page < 1
        ) {
            $page = 1;
        }

        // записываем номер страницы

        $this->page = $page;

        // устанавливаем все прочие значения для навигации

        $this->from = $this->skip + ($this->page - 1) * $this->count;

        $this->display = Objects::get($this->list, $this->from, $this->count);

        $this->sets['skip'] = $this->skip;
        $this->sets['limit'] = $this->count;

        // задаем линки для формирования навигации

        $this->rest = $uri->get('rest');
        $this->keys = $uri->get('keys');

        // важно - сохраняем старые линки

        $this->setData($uri->getData());

        //echo '<pre>';
        //echo '[' . print_r($this, 1) . ']';
        //exit;
    }

    public function renderData()
    {
        //System::debug($this->getData());

        if ($this->rest) {
            if ($this->keys) {
                $result = Strings::combine($this->getData(), '/', '/');
            } else {
                $result = Strings::join($this->getData(), '/');
            }
            return $result ? $result . '/' : null;
        } else {
            return '?' . http_build_query($this->getData());
        }
    }
}
