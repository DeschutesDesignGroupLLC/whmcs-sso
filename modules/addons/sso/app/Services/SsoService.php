<?php

namespace App\Services;

use Illuminate\Support\Arr;
use WHMCS\Database\Capsule;
use WHMCS\User\User;

class SsoService
{
    /**
     * @return mixed
     */
    public function findSsoConnection($sub, $email)
    {
        try {
            $member = Capsule::table('mod_sso_members')->where('sub', $sub)->orderBy('user_id', 'desc')->first();
            $user = User::findOrFail($member->user_id);
        } catch (\Exception $exception) {
            $user = User::where('email', $email)->first();
        }

        return $user;
    }

    /**
     * @return void
     */
    public function addSsoConnection($userId, $sub, $accessToken, $idToken)
    {
        Capsule::table('mod_sso_members')->updateOrInsert([
            'user_id' => $userId,
        ], [
            'sub' => $sub,
            'access_token' => $accessToken,
            'id_token' => $idToken,
        ]);
    }

    /**
     * @return void
     */
    public function removeSsoConnection($userId)
    {
        Capsule::table('mod_sso_members')->whereIn('user_id', Arr::wrap($userId))->delete();
    }
}
