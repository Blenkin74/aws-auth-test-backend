<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRegisterRequest;
use App\Http\Requests\UserUpdateInfoRequest;
use App\Http\Requests\UserUpdatePasswordRequest;
use App\Models\User;
use Auth;
use Cookie;
use Hash;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * @param UserRegisterRequest $request
     * @return Application|ResponseFactory|\Illuminate\Http\Response
     */
    public function register(UserRegisterRequest $request): \Illuminate\Http\Response|Application|ResponseFactory
    {
        $user = User::create(
            $request->only('first_name', 'last_name', 'email', 'username')
            + [
                'password' => Hash::make($request->input('password')),
                'is_admin' => 1
            ]
        );
        return response($user, Response::HTTP_CREATED);
    }

    /**
     * @param Request $request
     * @return Application|ResponseFactory|\Illuminate\Http\Response
     * @throws ValidationException
     */
    public function login(Request $request): \Illuminate\Http\Response|Application|ResponseFactory
    {
        $this->validate($request, [
           'login' => 'required',
           'password' => 'required'
        ]);

        $login_type = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $request->merge([
            $login_type => $request->input('login')
        ]);

        if (! Auth::attempt($request->only([$login_type, 'password']))) {
            return response([
                'error' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();

        $jwt = $user->createToken('token', ['admin'])->plainTextToken;

        $cookie = cookie('jwt', $jwt, 60*24);   // 1 day

        return response([
            'message' => 'success'
        ])->withCookie($cookie);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function user(Request $request): mixed
    {
        return $request->user();
    }

    /**
     * @return Application|ResponseFactory|\Illuminate\Http\Response
     */
    public function logout(): \Illuminate\Http\Response|Application|ResponseFactory
    {
        $cookie = Cookie::forget('jwt');

        return response([
            'message' => 'success'
        ])->withCookie($cookie);
    }

    /**
     * @param UserUpdateInfoRequest $request
     * @return Application|ResponseFactory|\Illuminate\Http\Response
     */
    public function updateInfo(UserUpdateInfoRequest $request): \Illuminate\Http\Response|Application|ResponseFactory
    {
        $user = $request->user();

        if ($request->input('email') !== $user->email) {
            if (!$this->safeUpdateFieldForUser('email', $request->input('email'), $user->id)) {
                return response([
                    "errors" => [
                        "email" => "Provided email is already used by another user"
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($request->input('username') !== $user->username) {
            if (!$this->safeUpdateFieldForUser('username', $request->input('username'), $user->id)) {
                return response([
                    "errors" => [
                        "username" => "Provided username is already used by another user"
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $user->update($request->only('first_name', 'last_name', 'email', 'username'));

        return response($user, Response::HTTP_ACCEPTED);
    }

    /**
     * @param string $input
     * @param mixed $value
     * @param int $userid
     * @return bool
     */
    private function safeUpdateFieldForUser(string $input, mixed $value, int $userid): bool
    {
        if (is_null($value) || DB::table('users')
            ->where($input, $value)
            ->where('id', "!=", $userid)
            ->doesntExist()) {
            return true;
        }

        return false;
    }

    /**
     * @param UserUpdatePasswordRequest $request
     * @return \Illuminate\Http\Response|Application|ResponseFactory
     */
    public function updatePassword(UserUpdatePasswordRequest $request): \Illuminate\Http\Response|Application|ResponseFactory
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->input('password'))
        ]);

        return response($user, Response::HTTP_ACCEPTED);
    }
}
