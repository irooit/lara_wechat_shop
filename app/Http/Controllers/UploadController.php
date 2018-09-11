<?php

namespace App\Http\Controllers;


use App\Result;
use Intervention\Image\Facades\Image;

class UploadController
{

    public function image()
    {
        $file = request()->file('file');
        $path = $file->store('/image/item', 'public');
        $url = asset('storage/' . $path);

        $image = Image::make($url);

        return Result::success([
            'url' => $url,
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
            'size' => $file->getSize(),
        ]);
    }
}