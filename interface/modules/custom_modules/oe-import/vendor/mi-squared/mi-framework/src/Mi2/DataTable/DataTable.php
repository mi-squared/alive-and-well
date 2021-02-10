<?php
namespace Mi2\DataTable;

use Mi2\Framework\AbstractModel;
use Mi2\Framework\Request;

/**
 *
 * @author kchapple
 *
 *    Model of a sortable, searchable list.
 */
class DataTable extends AbstractModel
{
    protected $table = '';

    protected $tableId = '';
    protected $countSQL = '';
    protected $resultsUrl = '';
    protected $baseUrl = '';
    protected $columnHeadersJSON = ''; // JSON string representation of column headers array
    protected $iDisplayLength = 10;

    protected $sFirst = '';
    protected $sLast = '';
    protected $sNext = '';
    protected $sPrevious = '';

    protected $sEcho = 1;

    protected $rowAttributeFilter = null;

    protected $setPatientUrl = '';

    protected $statement;

    // Additional search filters that can be applied after instanciation
    protected $searchFilters = [];

    protected $groupBy;

    /**
     * @return array
     */
    public function getSearchFilters(): array
    {
        return $this->searchFilters;
    }

    public function __construct($statement , $tableId = null, $resultsUrl = null, RowAttributeFilterIF $rowAttributeFilter = null, $setPatientUrl = '')
    {
        $this->statement = $statement;
        $this->setPatientUrl = $setPatientUrl;
        $this->rowAttributeFilter = $rowAttributeFilter;
        $this->resultsUrl = $resultsUrl;
        $this->tableId = $tableId;
        $this->baseUrl = ( $GLOBALS['webroot'] ) ? $GLOBALS['webroot'] : '';

        $this->sFirst = xla('First');
        $this->sLast = xla('Last');
        $this->sNext = xla('Next');
        $this->sPrevious = xla('Previous');
    }

    /**
     * @param $statement
     */
    public static function make($statement, $id, $url, RowAttributeFilterIF $rowAttributeFilterIF = null)
    {
        // Data table config
        $dataTable = new DataTable($statement, $id, $url, $rowAttributeFilterIF);

        return $dataTable;
    }

    public function groupBy($groupBy)
    {
        $this->groupBy = $groupBy;
        return $this;
    }

    public function getGroupBy()
    {
        return $this->groupBy;
    }

    public function getResults(Request $request)
    {
        // Takes the statement and allows us to append search filters and sorting
        $queryBuilder = new QueryBuilder($this->statement);

        // Turns the query and data table configuration into Json Results
        $resultBuilder = new ResultBuilder($this, $queryBuilder);

        // If there are options, apply them to the results, such as row filters or column behaviors
        $resultBuilder->setOptions(['rowAttributeFilter' => $this->rowAttributeFilter]);

        // Get the results object using our query, the options, and the request parameters
        $results = $resultBuilder->getResults($request->getParams());

        return $results;
    }

    public function getResultsArray( Request $request )
    {
        $results = new Results( array( 'rowClassFilter' => $this->rowClassFilter ) );
        $results->getResults( $this->sql, $request->getParams() );
        return $results;
    }

    public function getDisplayLength()
    {
        return $this->iDisplayLength;
    }

    public function getTableId()
    {
        return $this->tableId;
    }

    public function getResultsUrl()
    {
        return $this->resultsUrl;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function setResultsUrl( $resultsUrl )
    {
        $this->resultsUrl = $resultsUrl;
    }

    public function getColumnJson()
    {
        $columns = [];
        foreach ( $this->getColumns() as $col ) {
            if ( $col instanceof Column ) {
                $columns[]= $col->toJson();
            }
        }

        return stripslashes(json_encode($columns));
    }

    public function toJson()
    {
        $json = [
            "processing" => true,
            "serverSide" => true,
            "ajax" => [
                "url" => $this->getResultsUrl(),
                "type" => "POST"
            ],
            "columns" => []
        ];
        foreach ( $this->getColumns() as $col ) {
            if ( $col instanceof Column ) {
                $json['columns'][]= $col->toJson();
            }
        }

        return stripslashes(json_encode($json));
    }

    /**
     * Render the required javascript
     */
    public function renderJavascript()
    {
        $string = "";
        foreach ( $this->getColumns() as $column ) {
           if ( $column->getBehavior() instanceof \ActiveElement ) {
               $activeElem = $column->getBehavior();
               $string .= $activeElem->getJavascript();
           }
        }
    }

    public function processRow( $row )
    {
        $result = array();
        foreach ( $this->getColumns() as $col ) {
            if ( $col instanceof Column ) {

                $behavior = $col->getBehavior();
                if ( $behavior instanceof ColumnBehaviorIF ) {
                    $result[$this->getTableColumn($col)]= $behavior->getOutput( $row );
                } else {
                    $temp = $row[$this->getTableColumn($col)];
                    $result[$this->getTableColumn($col)]= $temp;
                }
            }
        }
        return $result;
    }

    public function addSearchFilter(SearchFilter $filter)
    {
        $this->searchFilters[]= $filter;
        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function setColumns( array $columns )
    {
        $this->columns = $columns;
    }

    /**
     * @param $column
     * @return $this
     */
    public function addColumn($column)
    {
        if (is_array($column)) {
           $column = new Column($column);
        }

        $this->columns[] = $column;

        return $this;
    }

    public function getTableColumn( Column $column )
    {
        $parts = explode(".", $column->getField());
        $return = '';
        if ( count( $parts ) > 1 ) {
            $return = $parts[1];
        } else if ( $parts[0] !== null ) {
            $return = $parts[0];
        }

        return $return;
    }
}
