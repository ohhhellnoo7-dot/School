<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HiController extends Controller 
{
    public function greet(string $name) {
        return view('hi', compact('hiName'));
    }
}
