<?php
namespace Mi2\DataTable_1_9;

use Mi2\Framework\AbstractModel;

class Column extends AbstractModel
{
    protected $title = ''; 
    protected $data = '';
    protected $sort = '';
    protected $index = '';
    protected $searchable = true;
    protected $orderable = true;
    protected $class = '';
    protected $width = '';
    protected $defaultContent = '';
    
    protected $behavior = null;
    
    public function getBehavior()
    {
        return $this->behavior;
    }
    
    public function isOrderable()
    {
        return $this->orderable;
    }
    
    public function isSearchable()
    {
        return $this->searchable;
    }
   
    public function getTitle()
    {
        return $this->title;
    }
    
    public function getData()
    {
        return $this->data;
    }

    public function getSort()
    {
        return $this->sort;
    }
    
    public function getWidth()
    {
        return $this->width;
    }

    public function toJson()
    {
        return array(
            'sWidth' => $this->getWidth(),
            'bSortable' => $this->isOrderable(),
            'bSearchable' => $this->isSearchable(),
            'sTitle' => $this->getTitle()

        );
    }
}
