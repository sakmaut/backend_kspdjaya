<?php

namespace App\Http\Controllers\Repositories\Users;

use App\Models\User;
use Carbon\Carbon;

class UserRepositories implements UsersRepositoryInterface
{
    protected $userEntity;

    function __construct(User $userEntity)
    {
        $this->userEntity = $userEntity;
    }

    function getActiveUsers()
    {
        return $this->userEntity::where('status', 'active')->with('branch')->get();
    }

    function findUserByid($id)
    {
        return $this->userEntity::where('id', $id)->first();
    }

    function findUserByUsername($username)
    {
        return $this->userEntity::where('username', $username)->first();
    }

    function create($request)
    {
        $data = [
            'username' => $request->username ?? '',
            'email' => $request->username . '@gmail.com',
            'password' => !empty($request->password) ? bcrypt($request->password) : bcrypt($request->username),
            'fullname' => $request->nama ?? '',
            'branch_id' => $request->cabang_id ?? '',
            'position' => $request->jabatan ?? '',
            'no_ktp' => $request->no_ktp ?? '',
            'alamat' => $request->alamat ?? '',
            'gender' => $request->gender ?? '',
            'mobile_number' => $request->no_hp ?? '',
            'status' => $request->status == '' ? 'active' : strtolower($request->status),
            'created_by' => $request->user()->id
        ];

        return $this->userEntity::create($data);
    }

    function update($request, $userById)
    {
        $data = [
            'username' => $request->username ?? '',
            'fullname' => $request->nama ?? '',
            'branch_id' => $request->cabang_id ?? '',
            'position' => $request->jabatan ?? '',
            'no_ktp' => $request->no_ktp ?? '',
            'alamat' => $request->alamat ?? '',
            'gender' => $request->gender ?? '',
            'mobile_number' => $request->no_hp ?? '',
            'status' => strtolower($request->status) ?? '',
            'updated_by' => $request->user()->id ?? '',
            'updated_at' => Carbon::now() ?? null
        ];

        if (isset($request->password) && !empty($request->password)) {
            $data['password'] = bcrypt($request->password);
        }

        return $userById->update($data);
    }

    function delete($request, $userById)
    {
        $data = [
            'deleted_by' => $request->user()->id,
            'deleted_at' => Carbon::now() ?? null
        ];

        return $userById->update($data);
    }

    function resetPassword($request, $userByUsername)
    {
        $data = [
            'password' => bcrypt($request->username),
            'updated_by' => $request->user()->id ?? '',
            'updated_at' => Carbon::now() ?? null
        ];

        return $userByUsername->update($data);
    }
}
