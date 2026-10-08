<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class MyCardController extends Controller
{
    public function show(Request $request): View
    {
        abort_if($request->user() === null, 403);

        return view('my-card');
    }
}
