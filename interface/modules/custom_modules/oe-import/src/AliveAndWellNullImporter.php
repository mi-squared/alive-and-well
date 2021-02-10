<?php


namespace Mi2\Import;


class AliveAndWellNullImporter implements ImportsInterface
{
    protected $validationMessages = [];

    public function setup($batch)
    {
        // TODO: Implement setup() method.
    }

    public function validateUploadFile($file)
    {
        $this->validationMessages[] = "Could not initialize and importer, check that file is one of the supported types (csv, jpg, jpeg, png)";
        return false;
    }

    public function validate()
    {
        $this->validationMessages[] = "Could not initialize and importer, check that file is one of the supported types (csv, jpg, jpeg, png)";
        return false;
    }

    public function getValidationMessages()
    {
        return $this->validationMessages;
    }

    public function import()
    {
        // TODO: Implement import() method.
    }
}
