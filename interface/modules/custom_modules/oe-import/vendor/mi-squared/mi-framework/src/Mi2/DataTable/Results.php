<?php
namespace Mi2\DataTable;

use Mi2\Framework\AbstractModel;

class Results extends AbstractModel
{
    public $results = array();

    protected $sEcho = 1;
    protected $totalItems = 0;
    protected $filteredTotal = 0;

    /**
     *
     * @var RowAttributeFilterIF $rowClassFilter
     */
    protected $rowClassFilter = null;

    /**
     * Find a column by it's index
     * @param int $index
     */
    public function findColumn( $columns, $index )
    {
        foreach ( $columns as $key => $col ) {
            if ( $key == $index ) {
                return $col;
            }
        }

        return null;
    }

    public function getResults(QueryBuilder $sql, array $options = null)
    {
        $this->sEcho = intval( $options['sEcho'] );
        $aColumns = explode( ',', $options['sColumns'] );

        if ( isset( $options['iDisplayStart'] ) && $options['iDisplayLength'] != '-1' ) {
            $limit = new Limit( intval( $options['iDisplayStart'] ), intval( $options['iDisplayLength'] ) );
            $sql->setLimit( $limit );
        }

        // Process sort order
        for ( $i = 0; $i < intval( $options['iSortingCols'] ); ++$i ) {
            $iSortCol = intval( $options["iSortCol_$i"] );
            if ( $options["bSortable_$iSortCol"] == "true" ) {
                $sSortDir = mysqli_real_escape_string( $options["sSortDir_$i"] ); // ASC or DESC
                $order = ( $sSortDir == 'desc' ) ? SortOrder::SORT_DESC : SortOrder::SORT_ASC;
                $column = $this->findColumn( $sql->getColumns(), $iSortCol );
                if ( $column instanceof Column ) {
                    if ( $column->isOrderable() ) {
                        $sortField = $column->getSort() ? $column->getSort() : $column->getData();
                        $sortOrder = new SortOrder( $sortField, $order );
                        $sql->addSortOrder( $sortOrder );
                    }
                } else {
                    error_log( "Column not found at index $iSortCol" );
                }
            }
        }

        // KCC hack to always sort by last updated datetime after other sort parameters
        $sortOrder = new SortOrder( 'last_updated_datetime', SortOrder::SORT_DESC );
        $sql->addSortOrder( $sortOrder );

        if ( !empty( $options['sSearch'] ) ) {

            foreach ( $sql->getColumns() as $column ) {
                if ( $column instanceof Column ) {
                    if ( $column->isSearchable() ) {
                        $searchTerm = $options['sSearch'];
                        $searchFilter = $this->makeSearchFilter( $column, $searchTerm, true );
                        $sql->addSearchFilter( $searchFilter );
                    }
                }
            }
        }

        // Filter individual columns
        $columnIndex = 0;
        foreach ( $sql->getColumns() as $column ) {

            if ( $column instanceof Column &&
                $column->isSearchable() ) {

                $searchTerm = '';
                if ( $options["sSearch_$columnIndex"] ) {
                    $searchTerm = $options["sSearch_$columnIndex"];
                } else {
                    // No search term
                    $columnIndex++;
                    continue;
                }

                // There is a regex
                if ( strpos( $searchTerm, "[" ) !== false &&
                    strpos( $searchTerm, "]" ) !== false ) {
                    $term = str_replace( "[", "", $searchTerm );
                    $term = str_replace( "[", "", $term );

                    // Handle all the ORs
                    $parts = explode( "|", $term );
                    foreach ( $parts as $filter_part ) {
                        $searchFilter = $this->makeSearchFilter( $column, $filter_part );
                        $sql->addSearchFilter( $searchFilter, false );
                    }

                } else {
                    $searchFilter = $this->makeSearchFilter( $column, $searchTerm );
                    $sql->addSearchFilter( $searchFilter, true );
                }
            }

            $columnIndex++;
        }

        $this->sEcho = $options['sEcho'];

        $count = 0;
        $results = array();
        $sql->execute();
        while ( $row = $sql->fetchNext() ) {
            $result = $sql->processRow( $row );
            $results[]= $result;
            $count++;
        }
        // Get total number of rows in the table.
        $this->totalItems = $sql->getTotalCount();

        // Get total number of rows in the table after filtering.
        $this->filteredTotal = $sql->getFilteredCount();

        $this->results = $results;
        return $this->results;
    }

    protected function makeSearchFilter( Column $column, $searchTerm, $textSearch = false )
    {
        $searchFilter = null;
        if ( $column instanceof Column &&
            $column->isSearchable()  ) {

            $type = SearchFilter::TYPE_LIKE;
            $behavior = $column->getBehavior();
            $map = $behavior->getMap();
            if (  is_a( $behavior, 'ActiveChecklist' ) &&
                is_array( $map ) ) {

                // If we have a checklist, we check the value as text because
                // The stored value is in a JSON string

                $searchTerm = '\"'.$searchTerm.'\"';
                $type = SearchFilter::TYPE_LIKE;

            } else if ( $behavior &&
                is_array( $map ) ) {

                // By default, the type is "like"
                // but if we have a map, make the term match exactly using strict

                if ( $searchTerm === 'NULL' ) {
                    // If the search term contains NULL keyword, then we search for values with sql "IS NULL"
                    $type = SearchFilter::TYPE_IS_NULL;
                } else {
                    $type = SearchFilter::TYPE_STRICT;
                }
            }


            if ( $textSearch &&
                $column->getSort() ) {
                $searchFilter = new SearchFilter( $column->getSort(), $searchTerm, SearchFilter::TYPE_LIKE );
            } else {
                $searchFilter = new SearchFilter( $column->getData(), $searchTerm, $type );
            }

        }

        return $searchFilter;
    }

    public function toJson()
    {
        $output = [
            "draw" => 1,
            "recordsTotal" => 2,
            "recordsFiltered" => 2,
            "data" => []
        ];

        $count = 0;
        foreach ($this->results as $row) {
            $values = array_values($row);
            $rowClass = '';
            if ($this->rowClassFilter instanceof RowAttributeFilterIF) {
                $rowClass = $this->rowClassFilter->calculateRowClass($row);
            }
            $arow = ['DT_RowId' => 'row-'.$count, 'DT_RowClass' => $rowClass];
            foreach ($values as $val) {
                $arow[]= stripslashes($val);
            }
            $output['data'][]= $arow;
            $count++;
        }

        $json = json_encode($output);
        return $json;
    }
}
