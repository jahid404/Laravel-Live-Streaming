<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StreamController extends Controller
{
    public function createStream()
    {
        $streamId = Str::uuid();  // Generate a unique stream ID
        return view('stream', compact('streamId'));  // Return the streaming page with the stream ID
    }

    public function watchStream($streamId)
    {
        return view('watch', compact('streamId'));  // Return the watch stream page with the stream ID
    }
}

