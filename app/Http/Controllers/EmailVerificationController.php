<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse(null, 'Email sudah terverifikasi.');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->successResponse(null, 'Link verifikasi telah dikirim.');
    }
}