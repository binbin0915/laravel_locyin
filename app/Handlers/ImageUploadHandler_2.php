<?php

namespace App\Handlers;

use Illuminate\Support\Facades\Storage;
use  Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use OSS\OssClient;
use OSS\Core\OssException;
///////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////// aliyuncs/oss-sdk-php    ///////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////
class ImageUploadHandler_2
{
    // 只允许以下后缀名的图片文件上传
    protected $allowed_ext = ["png", "jpg", "gif", 'jpeg'];

    public function save($file, $folder, $file_prefix)
    {
        // 构建存储的文件夹规则，值如：uploads/images/avatars/201709/21/
        // 文件夹切割能让查找效率更高。
        $folder_name = "uploads/images/$folder/" . date("Ym/d", time());

        // 文件具体存储的物理路径，`public_path()` 获取的是 `public` 文件夹的物理路径。
        // 值如：/home/vagrant/Code/larabbs/public/uploads/images/avatars/201709/21/
        $upload_path = public_path() . '/' . $folder_name;

        // 获取文件的后缀名，因图片从剪贴板里黏贴时后缀名为空，所以此处确保后缀一直存在
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'png';

        // 拼接文件名，加前缀是为了增加辨析度，前缀可以是相关数据模型的 ID
        // 值如：1_1493521050_7BVc9v9ujP.png
        $filename = $file_prefix . '_' . time() . '_' . Str::random(10) . '.' . $extension;

        // 如果上传的不是图片将终止操作
        if ( ! in_array($extension, $this->allowed_ext)) {
            return false;
        }
        // 将图片移动到我们的目标存储路径中
        $file->move($upload_path, $filename);

        return [
            'path' => config('app.url') . "/$folder_name/$filename"
        ];
    }
    public function save_to_qiniu($file, $folder, $file_prefix ,$max_width = false)
    {


        $extension = $file->extension();
        //dd($file);
        // 如果限制了图片宽度，就进行裁剪
        if ($max_width && $extension != 'gif') {
            // open an image file
            $file = Image::make($file->getRealPath());
            // 进行大小调整的操作
            $file->resize($max_width, null, function ($constraint) {
                // 设定宽度是 $max_width，高度等比例缩放
                $constraint->aspectRatio();
                // 防止裁图时图片尺寸变大
                $constraint->upsize();
            });
            $file->save();
            //dd($file);
        }
        //$image_name = 'uploads/images/'. "$folder/".date('YmdHis')."/$file_prefix".'_'.Str::random(random_int(10,20)) . '.'.$extension;
        $image_name = 'uploads/images/'. "$folder/".date("Ym/d", time())."/$file_prefix".'_'.Str::random(random_int(10,20)) . '.'.$extension;
        $disk = Storage::disk('qiniu');
        $fileinfo = $disk->put($image_name,$file);

        // 如果上传的不是图片将终止操作
        if ( ! in_array($extension, $this->allowed_ext)) {
            return false;
        }
        return [
            'path' => $disk->downloadUrl($image_name)
        ];
    }
    public function save_to_aliyun($file, $folder, $file_prefix ,$max_width = false)
    {
        $extension = $file->extension();
        $filePos = $file->getRealPath();
        $realType = $file->getMimeType();
        $size = $file->getSize();
        // 如果限制了图片宽度，就进行裁剪
        if ($max_width && $extension != 'gif') {
            // open an image file
            $file = Image::make($filePos);
            // 进行大小调整的操作
            $file->resize($max_width, null, function ($constraint) {
                // 设定宽度是 $max_width，高度等比例缩放
                $constraint->aspectRatio();
                // 防止裁图时图片尺寸变大
                $constraint->upsize();
            });
            $file->save();
            //dd($file);
        }
        $handle = fopen($filePos, 'r');
        //$image_name = 'uploads/images/'. "$folder/".date('YmdHis')."/$file_prefix".'_'.Str::random(random_int(10,20)) . '.'.$extension;
        $image_name = 'uploads/images/'. "$folder/".date("Ym/d", time())."/$file_prefix".'_'.Str::random(random_int(10,20)) . '.'.$extension;
       /* if (!OSS::publicUpload(config('aliyunoss.BucketName'), $image_name, $filePos,[
            'ContentType' => $realType,
        ])){
            return false;
        }*/
        // 如果上传的不是图片将终止操作
        if ( ! in_array($extension, $this->allowed_ext)) {
            return false;
        }


        // 阿里云主账号AccessKey
        $accessKeyId = config('aliyunoss.AccessKeyId');
        $accessKeySecret = config('aliyunoss.AccessKeySecret');
        // Endpoint以杭州为例，其它Region请按实际情况填写。
        $endpoint = "http://oss-cn-beijing.aliyuncs.com";
        // 设置存储空间名称。
        $bucket= config('aliyunoss.BucketName');
        // 设置文件名称。
        $object = $image_name;
        // <yourLocalFile>由本地文件路径加文件名包括后缀组成，例如/users/local/myfile.txt。
        $filePath = $filePos;
        //dd($filePath);

        try{
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $ossClient->uploadFile($bucket, $object, $filePath);
        } catch(OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            dd($e->getMessage() . "\n");
            return;
        }
        print(__FUNCTION__ . ": OK" . "\n");

        return [
            'path' =>config('aliyunoss.ALIOSS_URL').'/'.$image_name,
        ];
    }
}
