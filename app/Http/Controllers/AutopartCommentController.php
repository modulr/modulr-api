<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AutopartComment;

class AutopartCommentController extends Controller
{
    public function index()
    {
        return DB::table('autopart_comments')
            ->select('id', 'comment','autopart_id')
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function show($id)
    {
        return AutopartComment::with($this->relationships)->find($id);
    }

    public function store(Request $request)
    {
        AutopartComment::create([
            'comment' => $request->comment,
            'autopart_id' => $request->autopart_id,
            'created_by' => $request->user()->id,
        ]);

        $comments = AutopartComment::where('autopart_id', $request->autopart_id)
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->get();

        return $comments;
    }

    public function destroy($id)
    {
        return AutopartComment::destroy($id);
    }

}
