<?php

namespace App\Exceptions;

use App\Exceptions\Custom\DataNotFoundException;
use App\Exceptions\Custom\FilterException;
use App\Exceptions\Custom\InvalidCredentialsException;
use App\Exceptions\Custom\NotAuthorizedException;
use App\Exceptions\Custom\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use ResponseJson\ResponseJson;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        AuthorizationException::class,
        DataNotFoundException::class,
        FilterException::class,
        HttpException::class,
        InvalidCredentialsException::class,
        ModelNotFoundException::class,
        NotAuthorizedException::class,
        ValidationException::class,
    ];

    /**
     * constructor
     * @param ResponseJson $response
     * @return void
     */
    public function __construct(
        ResponseJson $response
    ) {
        $this->response = $response;
    }

    /**
     * report bug
     * @param  Throwable $exception
     * @return void
     * @throws Exception
     */
    public function report(
        Throwable $exception
    ) {
        if ($this->shouldntReport($exception)) {
            return;
        }

        if (app()->bound('sentry') && env('APP_ENV') == 'production') {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * render bug and return with json response
     * @param  Request $request
     * @param  Throwable $exception
     * @return JsonResponse
     * @throws Throwable
     */
    public function render(
        $request,
        Throwable $exception
    ): JsonResponse {
        $requestId = $request->requestId ?? '';
        $startProfile = $request->startProfile ?? 0;

        if ($exception instanceof ValidationException) {
            $result = $this->response->response(
                $requestId,
                $startProfile,
                $request->jwtToken,
                $exception->getMessages(),
                'A validation error occurrs'
            );

            return response()->json($result, 422);
        }

        if ($exception instanceof HttpException) {
            $result = $this->response->response(
                $requestId,
                $startProfile,
                $request->jwtToken,
                [],
                'Route not found'
            );

            return response()->json($result, 404);
        }

        $code = $exception->getCode() ?? 500;
        if (!is_int($code) || $code > 505 || $code < 0) {
            $code = 500;
        }

        $message = $exception->getMessage();
        if ($code == 500) {
            if (ENV('APP_DEBUG') == 'true') {
                dd($exception);
            }

            $message = 'An unexpected error occurred, please try again later';
        }

        $result = $this->response->response(
            $requestId,
            $startProfile,
            $request->jwtToken,
            [],
            $message,
            $code
        );

        return response()->json($result, $code);
    }
}
