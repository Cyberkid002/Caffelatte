<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiHelper;
use App\Http\Models\Business\UserModel;
use App\Http\Models\Dal\UserCModel;
use App\Http\Models\Dal\UserQModel;
use App\Http\Models\Dal\SuggestCModel;
use App\Http\Models\Dal\SuggestQModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use \Firebase\JWT\JWT;


/**
 * Class UserController
 * @package App\Http\Controllers\Api
 */
class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    public function index() {

    }

    public function profile(Request $request, $id) {
        $user = UserQModel::get_user_by_id($id);

        if (!$user) {
            return ApiHelper::error(
                config('constant.error_type.not_found'), 404,
                'user id not found',
                404
            );
        }

        return ApiHelper::success($user);
    }

    public function update(Request $request) {
        $user_id = $request->input('user_id');
        $data = [];
        if ($request->input('gender')) {
            $data['gender'] = $request->input('gender');
        }

        if ($request->input('chat_id')) {
            $data['chat_id'] = $request->input('chat_id');
        }

        if ($request->input('country')) {
            $data['country'] = $request->input('country');
        }

        if ($request->input('city')) {
            $data['city'] = $request->input('city');
        }

        if ($request->input('language')) {
            $data['language'] = $request->input('language');
        }

        if ($request->input('education')) {
            $data['education'] = $request->input('education');
        }

        if ($request->input('occupation')) {
            $data['occupation'] = $request->input('occupation');
        }

        if ($request->input('sumary')) {
            $data['sumary'] = $request->input('sumary');
        }

        if ($request->input('information')) {
            $data['information'] = $request->input('information');
        }

        if ($request->input('religion')) {
            $data['religion'] = $request->input('religion');
        }

        if ($request->input('fcm_token')) {
            $data['fcm_token'] = $request->input('fcm_token');
        }

        if ($request->input('height')) {
            $data['height'] = intval($request->input('height'));
            if ($data['height'] < 0) {
                return ApiHelper::error(
                    config('constant.error_type.bad_request'),
                    config('constant.error_code.auth.param_wrong'),
                    'param height wrong',
                    400
                );
            } 
        }

        if (empty($data)) {
            return ApiHelper::error(
                config('constant.error_type.bad_request'),
                config('constant.error_code.auth.param_wrong'),
                'param wrong',
                400
            );
        }

        try {
            $user = UserCModel::update_user($user_id, $data);
        } catch (\Exception $e) {
            return ApiHelper::error(
                config('constant.error_type.server_error'),
                config('constant.error_code.common.server_error'),
                'error: ' . $e->getMessage(),
                500
            );
        }

        return ApiHelper::success(['message' => 'success']);
    }

    public function suggest(Request $request) {
        $current_time = time();
        // test
        if ($request->input('time')) {
            $current_time = strtotime($request->input('time'));
        }

        $suggests = [];

        $user_id = $request->input('user_id');
        $user = UserQModel::get_user_by_id($user_id);

        // get friend from table user
        $friends = $user->_friend ? json_decode($user->_friend) : [];
        $friends_temp = $friends;

        // get all user matching from table suggest
        $suggested = SuggestQModel::get_list_matching($user_id);

        if (!empty($user->suggest_at) && $user->suggest_at == date('Y-m-d', $current_time)) {
            // get user in field suggested
            $result = DB::table('users as u')
                ->select('u.*')
                ->join('suggests as s', 's.matching_id', '=', 'u.id')
                ->where('s.user_id', '=', $user_id)
                ->where('s.created_at', '=', $user->suggest_at)
                ->whereIn('s.status', [config('constant.suggest.status.suggested')])
                ->get()
                ->toArray();

            return ApiHelper::success($result);
        } else {
            // remove old suggest (not like, pass)

            // get new matching
            while (count($friends_temp) > 0 && count($suggests) < 3) {
                // get random 1 friend of friends
                $index = array_rand($friends_temp);
                $person_facebook_id = $friends_temp[$index];
                unset($friends_temp[$index]); // remove this friend

                // get list friend of this person
                $person = UserQModel::get_user_by_facebook_id($person_facebook_id);
                if ($person) {
                    $person_friends = $person->_friend ? json_decode($person->_friend) : [];

                    $suggests_id = [];
                    foreach ($suggests as $item) {
                        array_push($suggests_id, $item->id);
                    }

                    $result = DB::table('users')
                        ->select('*')
                        ->where('id', '!=', $user_id)
                        ->whereIn('facebook_id', $person_friends)
                        ->whereNotIn('id', $suggests_id)
                        ->whereNotIn('facebook_id', $friends)
                        ->whereNotIn('id', $suggested)
                        ->limit(3)
                        ->get()
                        ->toArray();

                    foreach ($result as $item) {
                        if (count($suggests) < 3) {
                            array_push($suggests, $item);
                        }
                    }
                }
            }

            if (!empty($suggests)) {
                $data = [];
                $matching_ids = [];
                foreach ($suggests as $item) {
                    array_push($matching_ids, $item->id);
                    array_push($data, [
                        'user_id' => $user_id,
                        'matching_id' => $item->id,
                        'status' => config('constant.suggest.status.suggested'),
                        'created_at' => date('Y-m-d', $current_time)
                    ]);
                }

                // save list suggest table suggest
                SuggestCModel::create_suggest($data);

                // update cache field _suggested table user
                UserCModel::update_user($user_id, [
                    '_suggested' => json_encode($matching_ids),
                    'suggest_at' => date('Y-m-d', $current_time)
                ]);

            }
        }

        return ApiHelper::success($suggests);
    }
}