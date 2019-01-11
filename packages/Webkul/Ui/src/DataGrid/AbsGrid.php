<?php

namespace Webkul\Ui\DataGrid;

use Illuminate\Http\Request;
/**
 * Product Data Grid class
 *
 * @author    Jitendra Singh <jitendra@webkul.com>
 * @copyright 2018 Webkul Software Pvt Ltd (http://www.webkul.com)
 */
abstract class AbsGrid
{
    protected $index = null;
    protected $columns = [];
    protected $allColumns = [];
    protected $queryBuilder = [];
    protected $collection = [];
    protected $actions = [];
    protected $massActions = [];
    protected $request;
    protected $parse;
    // protected $gridName = null;

    abstract public function prepareMassActions();
    abstract public function prepareActions();
    abstract public function prepareQueryBuilder();
    abstract public function addColumns();
    abstract public function setIndex();

    /**
     * Parse the URL and get it ready to be used.
     */
    private function parse()
    {
        $parsed = [];
        $unparsed = url()->full();

        if (count(explode('?', $unparsed)) > 1) {
            $to_be_parsed = explode('?', $unparsed)[1];

            parse_str($to_be_parsed, $parsed);
            unset($parsed['page']);
        }

        return $parsed;
    }

    public function addColumn($column)
    {
        array_push($this->columns, $column);

        $this->setAllColumnDetails($column);
    }

    public function setAllColumnDetails($column)
    {
        array_push($this->allColumns, $column);
    }

    public function setQueryBuilder($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function addAction($action)
    {
        array_push($this->actions, $action);
    }

    public function addMassAction($massAction)
    {
        array_push($this->massActions, $massAction);
    }

    public function getCollection()
    {
        $p = $this->parse();

        if(count($p)) {
            $filteredOrSortedCollection = $this->sortOrFilterCollection($this->collection = $this->queryBuilder, $p);

            // return $filteredOrSortedCollection->get();

            if (config()->has('datagrid.pagination')) {
                return $filteredOrSortedCollection->paginate(config('datagrid.pagination'));
            } else {
                return $filteredOrSortedCollection->get();
            }
        }

        if (config()->has('datagrid.pagination')) {
            $this->collection = $this->queryBuilder->paginate(config('datagrid.pagination'));
        } else {
            $this->collection = $this->queryBuilder->get();
        }

        if ($this->collection) {
            return $this->collection;
        } else {
            dd('no records found');
        }
    }

    /**
     * To find the alias of the column and by taking the column name.
     *
     * @return string
     */
    public function findColumnType($columnAlias) {
        foreach($this->allColumns as $column) {
            if($column['alias'] == $columnAlias) {
                return [$column['type'], $column['index']];
            }
        }
    }

    public function sortOrFilterCollection($collection, $parseInfo) {

        foreach($parseInfo as $key => $info)  {
            $columnType = $this->findColumnType($key)[0];
            $columnName = $this->findColumnType($key)[1];

            if($key == "sort") {
                $count_keys = count(array_keys($info));

                if ($count_keys > 1) {
                    throw new \Exception('Fatal Error! Multiple Sort keys Found, Please Resolve the URL Manually');
                }

                $columnName = $this->findColumnType(array_keys($info)[0]);

                return $collection->orderBy(
                    $columnName[1],
                    array_values($info)[0]
                );
            } else if($key == "search") {
                $count_keys = count(array_keys($info));

                if($count_keys > 1) {
                    throw new \Exception('Multiple Search keys Found, Please Resolve the URL Manually');
                }

                if($count_keys == 1) {
                    return $collection->where(function() use($collection, $info) {
                        foreach ($this->allColumns as $column) {
                            if($column['searchable'] == true)
                                $collection->orWhere($column['index'], 'like', '%'.$info['all'].'%');
                        }
                    });
                }
            } else {
                if (array_keys($info)[0] == "like" || array_keys($info)[0] == "nlike") {
                    foreach ($info as $condition => $filter_value) {
                        return $collection->where(
                            $columnName,
                            config("datagrid.operators.{$condition}"),
                            '%'.$filter_value.'%'
                        );
                    }
                } else {
                    foreach ($info as $condition => $filter_value) {
                        if($columnType == 'datetime') {
                            return $collection->whereDate(
                                $columnName,
                                config("datagrid.operators.{$condition}"),
                                $filter_value
                            );
                        } else {
                            return $collection->where(
                                $columnName,
                                config("datagrid.operators.{$condition}"),
                                $filter_value
                            );
                        }
                    }
                }
            }
        }
    }

    public function render()
    {
        $this->addColumns();

        $this->setIndex();

        $this->prepareActions();

        $this->prepareMassActions();

        $this->prepareQueryBuilder();

        return view('ui::testgrid.table')->with('results', ['records' => $this->getCollection(), 'columns' => $this->allColumns, 'actions' => $this->actions, 'massactions' => $this->massActions, 'index' => $this->index]);
    }
}