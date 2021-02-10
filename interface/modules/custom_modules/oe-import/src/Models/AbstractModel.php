<?php

namespace Mi2\Import\Models;

use OpenEMR\Events\BoundFilter;

class AbstractModel extends \Mi2\Framework\AbstractModel
{
    public static $table = null;

    public function getData() {
        $data = $this->toArray();
        unset($data['members']);
        return $data;
    }

    public function save() {
        $result = null;
        if (property_exists($this, 'id')) {
            $result = self::update($this->id, $this->getData());
        } else {
            $result = self::create($this->getData());
        }

        return $result;
    }

    public static function find( $id )
    {
        $table = static::$table;
        $statement = "SELECT *
                FROM $table
                WHERE id = ?";
        $result = sqlStatement( $statement, array( $id ) );
        $referral = false;
        if ( $result ) {
            $referral = sqlFetchArray( $result );
        }

        return $referral;
    }

    /**
     * @param BoundFilter|null $filter
     * @return false|\recordset
     *
     * The result of this function can be used to iterate over the recordset
     * using sqlFetchArray()
     */
    public static function all(BoundFilter $filter = null)
    {
        $table = static::$table;
        $statement = "SELECT *
                FROM $table";
        if ($filter) {
            $statement .= " " . $filter->getFilterClause();
            if (count($filter->getBoundValues())) {
                $result = sqlStatement($statement, $filter->getBoundValues());
            } else {
                $result = sqlStatement($statement);
            }
        } else {
            $result = sqlStatement($statement);
        }

        return $result;
    }

    /**
     * @param array $fields
     * @return int (last insert ID)
     */
    public static function create(array $fields)
    {
        $table = static::$table;

        // Build SQL to insert new encounter
        $count = 0;
        $sql = "INSERT INTO $table SET ";
        foreach ($fields as $key => $value) {
            $sql .= "$key = ?";
            if ( $count < count( $fields ) - 1 ) {
                $sql .= ", ";
            }
            $count++;
        }

        return sqlInsert( $sql, $fields );
    }

    public static function update($id, array $fields)
    {
        $table = static::$table;
        $count = 0;
        $insert_fields = [];
        $sql = "UPDATE $table SET ";
        foreach ( $fields as $key => $value ) {
            $insert_fields [] = $value;
            $sql .= "$key = ?";
            if ( $count < count( $fields ) - 1 ) {
                $sql .= ", ";
            }
            $count++;
        }

        $sql .= " WHERE id = ?";
        $insert_fields[] = $id;

        return sqlStatement($sql, $insert_fields);
    }
}
