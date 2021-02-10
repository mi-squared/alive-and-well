<?php
namespace Mi2\DataTable;

use Mi2\Framework\AbstractModel;

class ResultBuilder extends AbstractModel
{
    public $results = array();

    protected $draw = 1;
    protected $totalItems = 0;
    protected $filteredTotal = 0;

    /**
     *
     * @var RowAttributeFilterIF $rowAttributeFilter
     */
    protected $rowAttributeFilter = null;

    protected $dataTable;
    protected $queryBuilder;

    public function __construct(DataTable $dataTable, QueryBuilder $queryBuilder)
    {
        $this->dataTable = $dataTable;
        $this->queryBuilder = $queryBuilder;
    }

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

    public function getResults(array $options = null)
    {
        $this->draw = intval($options['draw']);


        if (isset( $options['start']) && $options['length'] != '-1') {
            $limit = new Limit(intval($options['start']), intval($options['length']));
            $this->queryBuilder->setLimit($limit);
        }

        // Process sort order
        foreach ($options['order'] as $order) {
            $sort_column = $order['column'];
            $sort_direction = $order['dir'];
            $col = $this->findColumn($this->dataTable->getColumns(), $sort_column);
            if ($col instanceof Column) {
                if ($col->isOrderable()) {
                    $sortField = $col->getSort() ? $col->getSort() : $col->getField();
                    $sortOrder = new SortOrder($sortField, $sort_direction);
                    $this->queryBuilder->addSortOrder($sortOrder);
                }
            } else {
                throw new \Exception("Column not found at index $sort_column");
            }
        }

        // Global search
        if (!empty($options['search'])) {
            foreach ($this->dataTable->getColumns() as $column) {
                if ($column instanceof Column) {
                    if ($column->isSearchable()) {
                        $searchTerm = $options['search']['value'];
                        if ($searchTerm) {
                            $searchFilter = $this->makeSearchFilter($column, $searchTerm, true);
                            $this->queryBuilder->addSearchFilter($searchFilter);
                        }
                    }
                }
            }
        }

        // Additional Filters
        if (count($this->dataTable->getSearchFilters()) > 0) {
            foreach ($this->dataTable->getSearchFilters() as $searchFilter) {
                $this->queryBuilder->addSearchFilter($searchFilter, false);
            }
        }

        // Filter individual columns
        $columnIndex = 0;
        foreach ( $this->dataTable->getColumns() as $column ) {

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
                        $this->queryBuilder->addSearchFilter( $searchFilter, false );
                    }

                } else {
                    $searchFilter = $this->makeSearchFilter( $column, $searchTerm );
                    $this->queryBuilder->addSearchFilter( $searchFilter, true );
                }
            }

            $columnIndex++;
        }

        if ($this->dataTable->getGroupBy()) {
            $this->queryBuilder->setGroupBy($this->dataTable->getGroupBy());
        }

        $this->draw = $options['draw'];

        $count = 0;
        $results = array();
        $this->queryBuilder->execute();
        while ($row = $this->queryBuilder->fetchNext()) {
            $result = $this->dataTable->processRow( $row );
            $results[]= $result;
            $count++;
        }

        // Get total number of rows in the table.
        $this->totalItems = $this->queryBuilder->getTotalCount();

        // Get total number of rows in the table after filtering.
        $this->filteredTotal = $this->queryBuilder->getFilteredCount();

        $this->results = $results;
        return $this;
    }

    protected function makeSearchFilter( Column $column, $searchTerm, $textSearch = false )
    {
        $searchFilter = null;
        if ( $column instanceof Column &&
            $column->isSearchable()  ) {

            $type = SearchFilter::TYPE_LIKE;
            $behavior = $column->getBehavior();
            if ($behavior !== null) {
                $map = $behavior->getMap();
                if (is_a($behavior, 'ActiveChecklist') &&
                    is_array($map)) {

                    // If we have a checklist, we check the value as text because
                    // The stored value is in a JSON string

                    $searchTerm = '\"' . $searchTerm . '\"';
                    $type = SearchFilter::TYPE_LIKE;

                } else if ($behavior &&
                    is_array($map)) {

                    // By default, the type is "like"
                    // but if we have a map, make the term match exactly using strict

                    if ($searchTerm === 'NULL') {
                        // If the search term contains NULL keyword, then we search for values with sql "IS NULL"
                        $type = SearchFilter::TYPE_IS_NULL;
                    } else {
                        $type = SearchFilter::TYPE_STRICT;
                    }
                }
            }


            if ( $textSearch &&
                $column->getSort() ) {
                $searchFilter = new SearchFilter( $column->getSort(), $searchTerm, SearchFilter::TYPE_LIKE );
            } else {
                $searchFilter = new SearchFilter( $column->getField(), $searchTerm, $type );
            }

        }

        return $searchFilter;
    }

    public function toJson()
    {
        $output = [
            "draw" => $this->draw,
            "recordsTotal" => $this->totalItems,
            "recordsFiltered" => $this->filteredTotal,
            "data" => []
        ];

        $count = 1;
        foreach ($this->results as $row) {
            $rowClass = '';
            $rowId = $count;
            if ($this->rowAttributeFilter instanceof RowAttributeFilterIF) {
                $rowClass = $this->rowAttributeFilter->calculateRowClass($row);
                $rowId = $this->rowAttributeFilter->calculateRowId($row);
            }

            $arow = ['DT_RowId' => $rowId, 'DT_RowClass' => $rowClass];
            $output['data'][]= array_merge($row, $arow);
            $count++;
        }

        $json = json_encode($output);
        return $json;
    }
}
