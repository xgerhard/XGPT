<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\SettingsHandler;
use Exception;
use Session;

class SettingsController extends Controller
{
    public function index()
    {
        // Set default settings for new users
        if (!Auth::user()->settings()->exists()) {
            $this->setDefaultSettings();
        }

        return view('settings');
    }

    public function save(Request $request)
    {
        if ($request->has('save_settings')) {
            Session::flash('message', 'Successfully saved your settings!');
            Session::flash('alert-class', 'alert-success'); 

            try {
                $user = Auth::user();
                $user->settings()->updateOrCreate([
                    'user_id' => $user->id
                ], [
                    'start_instructions' => $request->string('start_instructions')->replace("\r\n", "\n")->substr(0, 500)->value(),
                    'show_conversation_id' => $request->has('show_conversation_id'),
                    'mention_user' => $request->has('mention_user'),
                    'show_sponsor_heart' => $request->has('show_sponsor_heart')
                ]);
            } catch (Exception $e) {
                Session::flash('message', 'Something went wrong.. please try again later.');
                Session::flash('alert-class', 'alert-error');
                report($e);
            }
        }

        return redirect('/settings');
    }

    private function setDefaultSettings()
    {
        $user = Auth::user();
        $settings = new SettingsHandler;
        $settings->setDefaultSettings($user);
        Auth::setUser($user->fresh());
    }
}
