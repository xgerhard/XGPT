<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use App\src\NightbotAPI;
use App\src\SettingsHandler;
use Log;
use Exception;
use Session;

class DashboardController extends Controller
{

    public function __construct()
    {
        
    }

    public function index()
    {
        // Set default settings for new users
        if (!Auth::user()->settings()->exists()) {
            $this->setDefaultSettings();
        }

        // Logout if no Nightbot session is found
        $token = session('nightbot_api_token');
        if (!$token) {
            return redirect('/logout');
        }

        // $user = Auth::user();
        $nightbotApi = new NightbotAPI;
        $nightbotApi->setAccessToken($token);
        $commands = $nightbotApi->getCustomCommands();
        $installedCommands = [];

        if (isset($commands['commands']) and !empty($commands['commands'])) {
            foreach ($commands['commands'] as $command) {
                if (str_contains($command['message'], '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)')) {
                    $installedCommands[] = $command;
                }
            }
        }

        return view('dashboard', [
            'commands' => $installedCommands
        ]);
    }

    public function save(Request $request)
    {
        if ($request->has('action')) {
            $token = session('nightbot_api_token');
            if (!$token) {
                return redirect('/logout');
            }

            switch ($request->post('action')) {
                case 'install_command':
                    if ($request->has('command_code')) {
                        $commandCode = $request->post('command_code');
                        if (trim($commandCode) != '') {
                            $this->installNightbotCommand($commandCode);
                        }
                    }
                break;
            }
        } elseif ($request->has('save_settings')) {
            Session::flash('message', 'Succesfully saved your settings!');
            Session::flash('alert-class', 'callout-block-success'); 

            try {
                $user = Auth::user();
                $user->settings()->updateOrCreate([
                    'user_id' => $user->id
                ], [
                    'api_key' => Crypt::encryptString($request->post('api_key')),
                    'start_instructions' => $request->post('start_instructions'),
                    'end_instructions' => $request->post('end_instructions'),
                    'show_conversation_id' => $request->has('show_conversation_id'),
                    'mention_user' => $request->has('mention_user'),
                    'show_sponsor_heart' => $request->has('show_sponsor_heart')
                ]);
            } catch (Exception $e) {
                Session::flash('message', 'Something went wrong.. please try again later.');
                Session::flash('alert-class', 'callout-block-danger'); 
            }
        }

        return redirect('/dashboard');
    }

    private function setDefaultSettings()
    {
        $user = Auth::user();
        $settings = new SettingsHandler;
        $settings->setDefaultSettings($user);
        Auth::setUser($user->fresh());
    }

    private function installNightbotCommand($commandCode)
    {
        $token = session('nightbot_api_token');
        if (!$token) {
            return redirect('/logout');
        }

        $nightbotApi = new NightbotAPI;
        $nightbotApi->setAccessToken($token);
        $commands = $nightbotApi->getCustomCommands();

        $commandData = [
            'coolDown' => 5,
            'message' => '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))',
            'name' => $commandCode,
            'userLevel' => 'everyone'
        ];

        if (isset($commands['commands']) and !empty($commands['commands'])) {
            foreach ($commands['commands'] as $command) {
                if ($command['name'] == $commandCode) {
                    // update
                    $nightbotApi->editCustomCommand($command['_id'], $commandData);
                    return;
                }
            }

            // create new
            $nightbotApi->addCustomCommand($commandData);
        }
    }
}