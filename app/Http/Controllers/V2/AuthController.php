<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\SendOtpRequest;
use App\Http\Requests\V2\VerifyOtpRequest;
use App\Models\User;
use App\Services\JWTService;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private JWTService $jwtService,
        private OtpService $otpService,
        private SmsService $smsService,
    ) {}

    /**
     * Step 1 of registration — send OTP to phone
     */
    public function register(SendOtpRequest $request): JsonResponse
    {
        $phone = $request->validated()['phone'];

        if (User::where('phone', $phone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already registered.',
            ], 422);
        }

        if (!$this->otpService->canRequest($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please wait before requesting a new code.',
            ], 429);
        }

        $code = $this->otpService->generate($phone, 'register');
        $this->smsService->send($phone, "Your verification code is: {$code}");

        return response()->json([
            'success' => true,
            'message' => 'OTP sent. Please verify your phone number.',
        ]);
    }

    /**
     * Step 2 of registration — verify OTP and create account
     */
    public function registerVerify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->validated()['phone'];
        $otp   = $request->validated()['otp'];
        $name  = $request->input('name', '');

        if (!$this->otpService->verify($phone, $otp, 'register')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 401);
        }

        $user = User::create([
            'phone' => $phone,
            'name'  => $name,
            'role'  => 'user',
        ]);

        $token = $this->jwtService->generateToken([
            'phone' => $user->phone,
            'name'  => $user->name,
            'role'  => $user->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user'       => [
                    'phone' => $user->phone,
                    'name'  => $user->name,
                    'role'  => $user->role,
                ],
            ],
        ], 201);
    }

    /**
     * Step 1 of login — send OTP to phone
     */
    public function login(SendOtpRequest $request): JsonResponse
    {
        $phone = $request->validated()['phone'];

        if (!$this->otpService->canRequest($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please wait before requesting a new code.',
            ], 429);
        }

        // Always generate and attempt to send — don't reveal if phone is registered
        $code = $this->otpService->generate($phone, 'login');

        if (User::where('phone', $phone)->exists()) {
            $this->smsService->send($phone, "Your login code is: {$code}");
        }

        // Same response whether phone exists or not (prevents enumeration)
        return response()->json([
            'success' => true,
            'message' => 'If this number is registered, an OTP has been sent.',
        ]);
    }

    /**
     * Step 2 of login — verify OTP and return JWT
     */
    public function loginVerify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->validated()['phone'];
        $otp   = $request->validated()['otp'];

        if (!$this->otpService->verify($phone, $otp, 'login')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 401);
        }

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 401);
        }

        $token = $this->jwtService->generateToken([
            'phone' => $user->phone,
            'name'  => $user->name,
            'role'  => $user->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user'       => [
                    'phone' => $user->phone,
                    'name'  => $user->name,
                    'role'  => $user->role,
                ],
            ],
        ]);
    }
}
