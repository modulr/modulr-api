<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Image;

use App\Models\AutopartImage;
use App\Models\Autopart;

class AutopartImageController extends Controller
{
    public function uploadTemp (Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png|max:20000',
        ]);

        $url = Storage::putFile('temp/'.$request->user()->id, $request->file('file'));
        $img = pathinfo($url);

        $thumb = Image::make($request->file('file'));
        $thumb->resize(400, 400, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $thumb->resizeCanvas(400, 400);
        $thumb->encode('jpg');
    
        Storage::put($img['dirname'].'/thumbnail_'.$img['basename'], (string) $thumb);

        return ['url' => Storage::url($url), 'url_thumbnail' => Storage::url($img['dirname'].'/thumbnail_'.$img['basename'])];
    }

    public function upload (Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png|max:20000',
        ]);

        $url = Storage::putFile('autoparts/'.$request->id.'/images', $request->file('file'));
        $img = pathinfo($url);

        $thumb = Image::make($request->file('file'));
        $thumb->resize(400, 400, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $thumb->resizeCanvas(400, 400);
        $thumb->encode('jpg');
    
        Storage::put($img['dirname'].'/thumbnail_'.$img['basename'], (string) $thumb);

        $lastImg = AutopartImage::where('autopart_id', $request->id)->orderBy('order', 'desc')->first();

        if (isset($lastImg)) {
            $order = $lastImg->order + 1;
        } else {
            $order = 0;
        }

        return AutopartImage::create([
            'basename' => $img['basename'],
            'order' => $order,
            'autopart_id' => $request->id
        ]);
    }

    public function destroyTemp (Request $request)
    {
        $img = pathinfo($request->url);

        if (Storage::exists('temp/'.$request->user()->id.'/'.$img['basename'])){
            Storage::delete('temp/'.$request->user()->id.'/'.$img['basename']);
        }
        
        if (Storage::exists('temp/'.$request->user()->id.'/thumbnail_'.$img['basename'])){
            Storage::delete('temp/'.$request->user()->id.'/thumbnail_'.$img['basename']);
        }

        return true;
    }

    public function destroy (Request $request)
    {
        $image = AutopartImage::find($request->id);
        $autopart = Autopart::find($image->autopart_id);

        if (Storage::exists('autoparts/'.$image->autopart_id.'/images/'.$image->basename)){
            Storage::delete('autoparts/'.$image->autopart_id.'/images/'.$image->basename);
        }
        if (Storage::exists('autoparts/'.$image->autopart_id.'/images/thumbnail_'.$image->basename)){
            Storage::delete('autoparts/'.$image->autopart_id.'/images/thumbnail_'.$image->basename);
        }

        $image->delete();
        foreach ($autopart->images as $key => $value) {
            AutopartImage::where('id', $value['id'])
                        ->where('autopart_id', $value['autopart_id'])
                        ->update(['order' => $key]);
        }

        return true;
    }

    public function sort (Request $request)
    {
        foreach ($request->images as $key => $value) {
            AutopartImage::where('id', $value['id'])
                        ->where('autopart_id', $value['autopart_id'])
                        ->update(['order' => $key]);
        }

        return true;
    }

}
