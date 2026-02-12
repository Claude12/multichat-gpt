<?php

class ChatGPTAPI {
    private $apiKey;
    private $apiUrl;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
    }

    public function sendMessage($messages) {
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'max_tokens' => 100,
        );

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Request Error: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true);
    }
}

// Example usage:
// $chatGpt = new ChatGPTAPI('your_api_key');
// $response = $chatGpt->sendMessage([['role' => 'user', 'content' => 'Hello!']]);
// print_r($response);

?>