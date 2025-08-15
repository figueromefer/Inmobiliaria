<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InquilinoController extends Controller
{
    public function index()
    {
        return view('inquilinos.index');
    }
}
