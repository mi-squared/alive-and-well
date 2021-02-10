<?php
namespace Mi2\DataTable;

use Mi2\Framework\AbstractModel;

class Column extends AbstractModel
{
    /**
     * @var string
     *
     * The column title
     */
    protected $title = '';

    /**
     * @var string
     *
     * The database field from the select list that represents this column
     */
    protected $field = '';

    protected $data = '';
    protected $sort = '';
    protected $index = '';
    protected $searchable = true;
    protected $orderable = true;
    protected $class = '';
    protected $width = '';
    protected $defaultContent = '';
    protected $visible = true;

    /**
     * @return mixed
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * @param mixed $visible
     */
    public function setVisible($visible): void
    {
        $this->visible = $visible;
    }

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

    public function getField()
    {
        return $this->field;
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
        return [
            'width' => $this->getWidth(),
            'sortable' => $this->isOrderable(),
            'searchable' => $this->isSearchable(),
            'data' => $this->getField(),
            'name' => $this->getTitle(),
            'visible' => $this->getVisible()
        ];
    }
}
