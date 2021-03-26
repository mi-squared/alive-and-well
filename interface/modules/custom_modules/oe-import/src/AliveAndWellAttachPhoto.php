<?php

namespace Mi2\Import;

use Mi2\Import\Models\Response;

class AliveAndWellAttachPhoto implements ImportsInterface
{
    protected $batch_id;
    protected $filename; // path to file on disk
    protected $user_filename; // Name of file that was uploaded
    protected $validation_messages = [];

    public function __construct()
    {

    }

    public function setup($batch)
    {
        $this->batch_id = $batch['id'];
        $this->filename = $batch['filename'];
        $this->user_filename = $batch['user_filename'];
    }

    public function import()
    {
        // image files should be in format last_first_YYYY-MM-DD_extid.jpg
        // strip path and extension so we have just the filename
        $path_parts = pathinfo($this->user_filename);
        $to_parse = $path_parts['filename'];
        $parts = explode("_", $to_parse);
        $lname = $parts[0];
        $fname = $parts[1];
        $dob = $parts[2];
        $time = strtotime($dob);
        $dob = date('Y-m-d', $time);
        $findPatient = "SELECT fname, lname, pubpid, pid FROM patient_data WHERE fname = ? AND lname = ? AND DOB = ?";
        $result = sqlStatement($findPatient, [$fname, $lname, $dob]);
        if ($result) {
            $count = 0;
            while ($row = sqlFetchArray($result)) {
                $doc = new \Document();
                $file_contents = file_get_contents($this->filename);
                $ret = $doc->createDocument(
                    $row['pid'],
                    10,
                    basename($this->user_filename),
                    mime_content_type($this->filename),
                    $file_contents
                );

                $count++;
            }
            if ($count == 0) {
                return new Response([
                    'result' => Response::FAILURE,
                    'messages' => ["Could not find patient for file"]
                ]);

            } else if ($count == 1) {
                return new Response([
                    'result' => Response::SUCCESS,
                ]);
            } else {
                return new Response([
                    'result' => Response::SUCCESS,
                    'messages' => ["Found `$count` matches for file"]
                ]);
            }
        } else {
            return new Response([
                'result' => Response::FAILURE,
                'messages' => ["Could not find patient for file`"]
            ]);
        }
    }

    public function validateUploadFile($file)
    {
        $path_parts = pathinfo($file['name']);
        $to_parse = $path_parts['filename'];
        $parts = explode("_", $to_parse);
        if (count($parts) != 3) {
            $this->validation_messages[] = "The image has an invalid file name format.";
            return false;
        }

        // Make sure DOB is correct format
        $dob = $parts[2];
        $time = strtotime($dob);
        $formatted_dob = date('Y-m-d', $time);
        if ($time == -1 ||
            strlen($formatted_dob) != 10) {
            $this->validation_messages[] = "The DOB in the image has an invalid format";
            return false;
        }

        return true;
    }

    public function validate()
    {
        return true;
    }

    public function getValidationMessages()
    {
        return $this->validation_messages;
    }
}
