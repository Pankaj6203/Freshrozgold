<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiConsoleController extends Controller
{
    public function index()
    {
        // You can pass dynamic data here if needed, e.g., list of users to impersonate
        return view('admin.api_console');
    }
}
