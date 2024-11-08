<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StreamController extends Controller
{
    public function createStream()
    {
        $streamId = Str::uuid();
        return view('stream', compact('streamId'));
    }

    public function createScreenShareStream()
    {
        $streamId = Str::uuid();
        return view('stream_v2', compact('streamId'));
    }

    public function watchStream($streamId)
    {
        return view('watch', compact('streamId'));
    }
}
