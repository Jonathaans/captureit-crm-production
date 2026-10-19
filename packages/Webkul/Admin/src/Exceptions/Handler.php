<?php

namespace Webkul\Admin\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Json error messages.
     *
     * @var array
     */
    protected $jsonErrorMessages = [];

    /**
     * Create handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->jsonErrorMessages = [
            '404' => trans('admin::app.errors.404.title'),
            '403' => trans('admin::app.errors.403.title'),
            '401' => trans('admin::app.errors.401.title'),
            '500' => trans('admin::app.errors.500.title'),
        ];
    }

    /**
     * Customize production errors after Laravel has normalized the exception.
     *
     * @param  Request  $request
     * @return Response
     */
    protected function renderExceptionResponse($request, Throwable $exception)
    {
        if (config('app.debug')) {
            return parent::renderExceptionResponse($request, $exception);
        }

        $errorCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        // Statuses without a branded page retain Laravel's response, including CSRF 419.
        if (! in_array($errorCode, [401, 403, 404, 500, 503], true)) {
            return parent::renderExceptionResponse($request, $exception);
        }

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        if ($this->shouldReturnJson($request, $exception)) {
            return response()->json([
                'message' => $this->jsonErrorMessages[$errorCode]
                    ?? trans('admin::app.common.something-went-wrong'),
            ], $errorCode, $headers);
        }

        return response()->view('admin::errors.index', compact('errorCode'), $errorCode, $headers);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  Request  $request
     * @return Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->shouldReturnJson($request, $exception)) {
            return response()->json(['message' => $this->jsonErrorMessages[401]], 401);
        }

        return redirect()->guest(route('admin.session.create'));
    }
}
