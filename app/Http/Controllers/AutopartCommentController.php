<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AutopartComment;

class AutopartCommentController extends Controller
{
    public function store(Request $request)
    {
        $comment = AutopartComment::create([
            'comment' => $request->comment,
            'autopart_id' => $request->autopart_id,
            'created_by' => $request->user()->id,
        ]);

        $comment->user = $comment->user;

        return $comment;

    }

    public function destroy($id)
    {
        return AutopartComment::destroy($id);
    }

}
