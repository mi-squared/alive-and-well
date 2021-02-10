<?php
namespace Mi2\DataTable_1_9;

class Limit 
{
    public $start = 0;
    public $length = 0;

    public function __construct( $start, $length )
    {
        $this->start = $start;
        $this->length = $length;
    }
}
