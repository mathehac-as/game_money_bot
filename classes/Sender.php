<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Sender
 *
 * @author 
 */
class Sender {
    private $telegram_url;
    private $botToken;
    private $telegram_receiver_id;
    
    public function __construct($telegram_url, $botToken, $telegram_receiver_id){
        $this->telegram_url = $telegram_url;
        $this->botToken = $botToken;
        $this->telegram_receiver_id = $telegram_receiver_id;
    }
    
    function sendTelegram($message)
    {
        $result = false; 
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => $this->telegram_url.$this->botToken.'/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => $this->telegram_receiver_id,
                    'text' => $message,
                ),
            )
        ); 
        $response = curl_exec($ch);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
        {
            file_put_contents('error.log', print_r($err, true));
        } 
        else 
        {
            $response = json_decode($response);  
            if (isset($response->ok) && $response->ok) {
                $result = true;
            }
        }
        return $result;
    }
}
