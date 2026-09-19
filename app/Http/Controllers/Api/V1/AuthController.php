<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\SetPinRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\VerifyPinRequest;
use App\Http\Resources\UserResource;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\OtpService;
use App\Services\Auth\PinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthService $auth,
        private readonly PinService $pins,
    ) {}

    /** POST /auth/otp/send */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $purpose = $request->purpose();

        if ($purpose === OtpCode::PURPOSE_RESET_PIN && ! User::where('phone', $request->phone)->exists()) {
            throw new ApiException(__('auth.user_not_found'), 404, 'user_not_found');
        }

        $result = $this->otp->send($request->phone, $purpose, $request->ip());

        return $this->success($result, __('auth.otp.sent'));
    }

    /** POST /auth/otp/verify */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $purpose = $request->purpose();

        $this->otp->verify($request->phone, $request->code, $purpose);

        [$user, $token, $isNew] = $this->auth->loginWithVerifiedOtp(
            $request->phone,
            $purpose,
            $request->deviceName(),
        );

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'is_new' => $isNew,
            'user' => new UserResource($user),
        ], __('auth.otp.verified'));
    }

    /** GET /auth/me */
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    /** PUT /auth/profile */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return $this->success(new UserResource($user->fresh()), __('auth.profile_updated'));
    }

    /** PUT /auth/pin */
    public function setPin(SetPinRequest $request): JsonResponse
    {
        $this->pins->set($request->user(), $request->pin, $request->input('current_pin'));

        return $this->success(new UserResource($request->user()->fresh()), __('auth.pin.set'));
    }

    /** POST /auth/pin/verify */
    public function verifyPin(VerifyPinRequest $request): JsonResponse
    {
        $this->pins->verify($request->user(), $request->pin);

        return $this->success(message: __('auth.pin.verified'));
    }

    /** POST /auth/logout — joriy qurilma */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(message: __('auth.logged_out'));
    }

    /** POST /auth/logout-all — barcha qurilmalar */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(message: __('auth.logged_out'));
    }
}
