<?php
namespace Mi2\DataTable;

interface RowAttributeFilterIF
{
    public function calculateRowClass($row);

    public function calculateRowId($row);
}
