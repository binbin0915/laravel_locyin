<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Requests\Api\User\CaptchaRequest;
use  Illuminate\Support\Str;
use Illuminate\Http\Request;
use Gregwar\Captcha\CaptchaBuilder;

class CaptchasController extends Controller
{
    public function store(CaptchaRequest $request, CaptchaBuilder $captchaBuilder)
    {
        $key = 'captcha-'.Str::random(15);
        $phone = $request->phone;

        $captcha = $captchaBuilder->build();
        $expiredAt = now()->addMinutes(2);
        $code = $captcha->getPhrase();
        \Cache::put($key, ['phone' => $phone, 'code' => $code], $expiredAt);

        $result = [
            'captcha_key' => $key,
            'code' => $code,
            'expired_at' => $expiredAt->toDateTimeString(),
            'captcha_image_content' => $captcha->inline()
        ];

        return response()->json($result)->setStatusCode(201);
    }
}
