<?php

namespace App\src;

use App\src\OpenAI;
use App\src\NightbotAPI;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Str;

class XGPT
{
    private $nbheaders = null;
    private $conversation = null;
    private $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant.'
        ]
    ];

    public function __construct($nbheaders)
    {
        $this->openai = new OpenAI;
        $this->nbheaders = $nbheaders;
    }

    public function setConversionId($id)
    {
        $conversation = Conversation::firstOrCreate([
            'id' => $id
        ]);

        $this->conversation = $conversation;
    }

    public function setMessage($message, $role = 'user')
    {
        if (!$this->conversation) {
            $this->generateConversion();
        }

        $this->storeMessage($message, $role);
    }

    public function getMessages()
    {
        return array_merge($this->messages, $this->getConversationMessages());
    }

    public function getConversationMessages()
    {
        $messages = [];
        $conversation = Conversation::with('messages')->find($this->conversation->id);
        foreach ($conversation->messages as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content
            ];
        }

        return $messages;
    }

    public function storeMessage($message, $role)
    {
        ConversationMessage::create([
            'role' => $role,
            'content' => $message,
            'conversation_id' => $this->conversation->id
        ]);
    }

    public function getResponse()
    {
        $response = $this->openai->getChatCompletion([
            'model' => 'gpt-3.5-turbo',
            //'max_tokens' => 100,
            'messages' => $this->getMessages()
        ]);

        // test data
        // $response['choices'][0]['message']['content'] = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla suscipit leo eget ante cursus dignissim. Donec sit amet metus eget felis mollis porta. Aliquam erat volutpat. Sed at vulputate enim, sit amet posuere massa. Morbi sodales laoreet odio, non iaculis magna faucibus dignissim. Pellentesque fringilla ante lorem, vel ultricies ante ullamcorper a.";

        if (isset($response['choices'][0]['message']['content'])) {
            
            $message = $response['choices'][0]['message']['content'];
            $message = trim(preg_replace('/\s+/', ' ', $message));

            $username = false;
            if ($this->nbheaders->getUser()) {
                $username = $this->nbheaders->getUser()->displayName;
            }

            $messageLength = 399;
            if ($username) {
                $messageLength -= strlen($username) + 2;
            }

            if (strlen($message) > $messageLength) {
                if ($this->nbheaders->getResponseUrl()) {
                    $secondMessageLength = 394;
                    if (strlen($message) > ($messageLength - 7 + $secondMessageLength)) {
                        $secondMessage = substr($message, $messageLength - 7, ($messageLength - 7 + $secondMessageLength) - 7) .'...';
                        $storeMessage = substr($message, 0, ($messageLength - 7 + $secondMessageLength) - 7);
                    } else {
                        $secondMessage =  substr($message, $messageLength - 7, ($messageLength - 7 + $secondMessageLength) - 4);
                        $storeMessage = substr($message, 0, ($messageLength - 7 + $secondMessageLength) - 4);
                    }

                    $this->storeMessage($storeMessage, 'assistant');
                    $secondMessage .= ' #'. $this->conversation->id;

                    session([$this->conversation->id => [
                        'responseUrl' => $this->nbheaders->getResponseUrl(),
                        'message' => $secondMessage
                    ]]);
                }
                $message = substr($message, 0, $messageLength - 7) .'...';
            } else {
                $message = substr($message, 0, $messageLength - 4);
                $this->storeMessage($message, 'assistant');
            }

            $message = ($username ? $username .': ' : '') . $message .' #'. $this->conversation->id;
            return $message;
        }

        return 'Error, unexpected response..';
    }

    public function generateConversion()
    {
        $length = 3;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';

        do {
            $string = '';
            for ($i = 0; $i < $length; $i++) {
                $index = rand(0, strlen($characters) - 1);
                $string .= $characters[$index];
            }

            $conversation = Conversation::find($string);
        }
        while ($conversation);

        $this->conversation = Conversation::create([
            'id' => $string
        ]);
    }
}
