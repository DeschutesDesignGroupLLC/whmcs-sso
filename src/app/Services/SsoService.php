<?php

namespace DeschutesDesignGroupLLC\App\Services;

use Illuminate\Support\Arr;
use WHMCS\Database\Capsule;
use WHMCS\User\User;

class SsoService
{
    public function findSsoConnection($sub, $email): mixed
    {
        try {
            $member = Capsule::table('mod_sso_members')->where('sub', $sub)->orderBy('user_id', 'desc')->first();
            $user = User::findOrFail($member->user_id);
        } catch (\Exception $exception) {
            $user = User::where('email', $email)->first();
        }

        return $user;
    }

    public function addSsoConnection($userId, $sub, $accessToken, $idToken): void
    {
        Capsule::table('mod_sso_members')->updateOrInsert([
            'user_id' => $userId,
        ], [
            'sub' => $sub,
            'access_token' => $accessToken,
            'id_token' => $idToken,
        ]);
    }

    public function removeSsoConnection($userId): void
    {
        Capsule::table('mod_sso_members')->whereIn('user_id', Arr::wrap($userId))->delete();
    }
}
