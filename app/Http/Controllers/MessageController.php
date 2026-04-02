<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\Message;

class MessageController extends Controller
{
    public function store(Request $request) {
        $message = Message::create(['user_id' => auth()->id(), 
            'receiver_id' => $request->receiver_id, 'body' => $request->body]);
        broadcast(new MessageSent($message->load('user')))->toOthers();
        return response()->json($message->load('user'));
    }

    public function index() { 
        return view('chat'); 
    }

    public function fetchUsers() {
        return \App\Models\User::where('id', '!=', auth()->id())->get();
    }

    public function fetchMessages($receiver_id = null) {
        $query = Message::with('user');

        if ($receiver_id) {
            $query->where(function($q) use ($receiver_id) {
                $q->where('user_id', auth()->id())->where('receiver_id', $receiver_id);
            })->orWhere(function($q) use ($receiver_id) {
                $q->where('user_id', $receiver_id)->where('receiver_id', auth()->id());
            });
        } else {
            $query->whereNull('receiver_id');
        }

        return $query->orderBy('created_at', 'asc')->get();
    }
}
