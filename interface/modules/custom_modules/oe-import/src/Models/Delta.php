<?php

/*
 * This class represents a delta, or change,
 * that was made during batch processing. Each change
 * is logged in this table with a reference to the batch.
 * 0- Type... was it an insert or a modification
 * 1- Field that was changed
 * 2- Original value
 * 3- new value
 */

namespace Mi2\Import\Models;

class Delta extends AbstractModel
{
    public static $table = 'aa_import_batch_delta';

    /**
     * These are the types for Delta records of the batch,
     * If the record was a new insert, it's type is insert,
     * If the record was modified, it's type is modification
     */
    const TYPE_MOD = 'modification';
    const TYPE_INS = 'insert';
}
