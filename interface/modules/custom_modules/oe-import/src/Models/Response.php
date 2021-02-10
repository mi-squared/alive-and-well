<?php


namespace Mi2\Import\Models;

class Response extends AbstractModel
{
    const SUCCESS = 'success';
    const FAILURE = 'failure';

    protected $messages = [];
    protected $result;


    /**
     * @return mixed
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param mixed $message
     */
    public function setMessages($messages)
    {
        $this->messages = $messages;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }
}
