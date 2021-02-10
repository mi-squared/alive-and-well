<?php


namespace Mi2\Import;


interface ImportsInterface
{
    public function setup($batch);

    public function validate();

    /**
     *
     * Pass in the upload file from the PHP files array
     *
     * @param $file
     * @return mixed
     */
    public function validateUploadFile($file);

    public function getValidationMessages();

    public function import();
}
