<?php

class Response {
    public $message;
    public $code;
    public $success;
    public $content;

    public function __construct($message='An unknown error has occurred',$code=500,$success=false,$content=[]){
        $this->message = $message;
        $this->code = $code;
        $this->success = $success;
        $this->content = $content;
    }
//    public function makeResponse(){
//        $response = new stdClass();
//        $response->message = 'An unknown error has occurred';
//        $response->code = 500;
//        $response->success = false;
//        $response->content = '';
//        return $response;
//    }

    public function killWithMessage(){
        die(json_encode($this));
    }
    public function kill($message){
        $this->message = $message;
        die(json_encode($this));
    }
}