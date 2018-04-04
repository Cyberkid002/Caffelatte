<?php
namespace app\Http\Controllers\Api;

use Facebook\Facebook;
use App\SocialNetwork;
use App\User;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use \Firebase\JWT\JWT;
use App\Http\Models\Business\UserModel;
use App\Http\Models\Dal\UserCModel;
use App\Http\Models\Dal\UserQModel;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    public function facebook(Request $request) {
        $facebook_token = $request->input('facebook_token');
        $gender = $request->input('gender');
        $chat_id = $request->input('chat_id');
        $country = $request->input('country');
        $city = $request->input('city');

        if (!$facebook_token || !$gender || !$chat_id || !$country || !$city) {
            return ApiHelper::error(
                config('constant.error_type.bad_request'),
                config('constant.error_code.auth.param_wrong'),
                'param wrong',
                400
            );
        }

        $fb = new Facebook([
            'app_id' => config('facebook.id'),
            'app_secret' => config('facebook.secret')
        ]);

        try {
            $response = $fb->get('/me?fields=id,name,email,link,birthday', $facebook_token); // get user facebook
            $profile = $response->getGraphUser();

            // facebook_token error
            if (!$profile || !isset($profile['id'])) {
                return ApiHelper::error(config('constant.error_type.bad_request'),
                    config('constant.error_code.auth.get_profile_error'),
                    'get profile facebook error',
                    400
                );
            }
        } catch (\Exception $e) {
            return ApiHelper::error(
                config('constant.error_type.bad_request'),
                config('constant.error_code.auth.login_facebook_failed'),
                'Login facebook failed: ' . $e->getMessage(),
                400
            );
        }

        $friends = [];

        try {
            $response = $fb->get('/me/friends?limit=4000', $facebook_token);
            $graphEdge = $response->getGraphEdge();
            foreach ($graphEdge as $graphNode) {
                array_push($friends, $graphNode['id']);
            }
        } catch (\Exception $e) {
            return ApiHelper::error(
                config('constant.error_type.bad_request'),
                config('constant.error_code.auth.get_friend_facebook_failed'),
                'Get friends facebook failed: ' . $e->getMessage(),
                400
            );
        }

        // get current user
        $user = UserQModel::get_user_by_facebook_id($profile['id']);

        if ($user) {
            // signin
            $jwt = [
                'id' => $user->id,
                'exp' => time() + config('constant.jwt.token_expire')
            ];
            $token = JWT::encode($jwt, env('JWT_KEY')); // JWT::decode($token, env('JWT_KEY'), ['HS256']);
            UserCModel::update_user($user->id, [
                'token' => $token,
                'login_at' => date('Y-m-d H:i:s')
            ]);

            return ApiHelper::success($user);
        } else {
            // signup
            try {
                $data = [
                    'name' => $profile['name'],
                    'facebook_id' => $profile['id'],
                    'facebook_token' => $facebook_token,
                    'gender' => $gender,
                    'chat_id' => $chat_id,
                    'country' => $country,
                    'city' => $city,
                    '_friend' => json_encode($friends),
                    'login_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $user_id = UserCModel::create_user($data);

                // setup jwt to update token
                $jwt = [
                    'id' => $user_id,
                    'exp' => time() + config('constant.jwt.token_expire')
                ];
                $token = JWT::encode($jwt, env('JWT_KEY')); // JWT::decode($token, env('JWT_KEY'), ['HS256']);
                UserCModel::update_user($user_id, ['token' => $token]);
            } catch (\Exception $e) {
                return ApiHelper::error(
                    config('constant.error_type.server_error'),
                    config('constant.error_code.common.server_error'),
                    'error: ' . $e->getMessage(),
                    500
                );
            }

            $user = UserQModel::get_user_by_facebook_id($profile['id']);

            return ApiHelper::success($user);
        }
    }
}