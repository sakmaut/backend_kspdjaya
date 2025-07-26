<?php

namespace App\Http\Controllers\Repositories\Users;

interface UsersRepositoryInterface
{
    function getActiveUsers();
    function findUserByid($id);
    function findUserByUsername($username);
    function create($request);
    function update($request, $userById);
    function delete($request, $userById);
    function resetPassword($request, $userByUsername);
}