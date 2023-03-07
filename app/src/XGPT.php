<?php

namespace App\src;

use App\src\OpenAI;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Str;

class XGPT
{
    private $conversation = null;
    private $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant.'
        ]
    ];

    public function __construct()
    {
        $this->openai = new OpenAI;
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
        foreach($conversation->messages as $message)
        {
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
            'max_tokens' => 40,
            'messages' => $this->getMessages()
        ]);

        if(isset($response['choices'][0]['message']['content']))
        {
            // we need max 32 characters for username + info (200 max length)
            $message = $response['choices'][0]['message']['content'];
            $message = trim(preg_replace('/\s+/', ' ', $message));

            if (strlen($message) > 168) {
                $message = substr($message, 0, 165) .'...';
            }
            $this->storeMessage($message, 'assistant');

            return $message .' #'. $this->conversation->id;
        }

        return 'Error, unexpected response..';
    }

    public function generateConversion()
    {
        $length = 3;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';

        do
        {
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
