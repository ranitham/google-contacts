<?php

namespace App\Http\Controllers\Auth;

use App\GoogleUser;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use http\Env\Response;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Request;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Redirect the user to the GitHub authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email', Google_Service_People::CONTACTS_READONLY])
            ->with(["access_type" => "offline", "prompt" => "consent select_account"])
            ->redirect();
    }

    /**
     * Obtain the user information from Google.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback(Request $request)
    {
        $user = Socialite::driver('google')->stateless()->user();

        /* Store the email, id, and tokens in the db
        $user->email;
        $user->id;
        $user->token,

        */

        // see if the user exists
        $gu = GoogleUser::find($user->id);
        if(!$gu){
            $gu = GoogleUser::create([
                'id' => $user->id,
                'refresh_token' => $user->refreshToken,
                'expires_at' => Carbon::now()->addSeconds($user->expiresIn),
                'token' => $user->token,
                'email' => strtolower($user->email),
            ]);
        } else {
            $gu->refresh_token = $user->refreshToken;
            $gu->expires_at = Carbon::now()->addSeconds($user->expiresIn);
            $gu->token = $user->token;
            $gu->email = strtolower($user->email);
            $gu->save();
        }

        // Show success screen
        return Response();
    }
}
