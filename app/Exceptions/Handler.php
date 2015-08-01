<?php namespace App\Exceptions;

use \Exception;
use \Psr\Log\LoggerInterface;
use \Illuminate\Http\Request;
use \Illuminate\Http\Response;
use \Neomerx\JsonApi\Document\Error;
use \Neomerx\JsonApi\Factories\Factory;
use \App\Http\Controllers\JsonApi\LaravelIntegration;
use \Neomerx\Limoncello\Config\Config as C;
use \Neomerx\JsonApi\Encoder\EncoderOptions;
use \Neomerx\Limoncello\Errors\RenderContainer;
use \Neomerx\Limoncello\Contracts\IntegrationInterface;
use \Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use \Neomerx\JsonApi\Contracts\Exceptions\RenderContainerInterface;
use \Neomerx\JsonApi\Contracts\Parameters\SupportedExtensionsInterface;

use \Illuminate\Database\Eloquent\ModelNotFoundException;
use \Illuminate\Database\Eloquent\MassAssignmentException;

use \Symfony\Component\HttpKernel\Exception\GoneHttpException;
use \Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use \Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use \Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use \Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use \Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class Handler extends ExceptionHandler
{
    /**
     * @var RenderContainerInterface
     */
    private $renderContainer;

    /**
     * @var IntegrationInterface
     */
    private $integration;

    /**
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        parent::__construct($log);

        $this->integration = new LaravelIntegration();

        $extensionsClosure = function () {
            /** @var SupportedExtensionsInterface $supportedExtensions */
            $supportedExtensions = app()->resolved(SupportedExtensionsInterface::class) === false ? null :
                app()->make(SupportedExtensionsInterface::class);
            return $supportedExtensions;
        };

        $this->renderContainer = new RenderContainer(new Factory(), $this->integration, $extensionsClosure);

        $this->registerCustomExceptions();
    }

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        GoneHttpException::class,
        ConflictHttpException::class,
        NotFoundHttpException::class,
        BadRequestHttpException::class,
        AccessDeniedHttpException::class,
        UnauthorizedHttpException::class,
        NotAcceptableHttpException::class,
        LengthRequiredHttpException::class,
        TooManyRequestsHttpException::class,
        MethodNotAllowedHttpException::class,
        PreconditionFailedHttpException::class,
        PreconditionRequiredHttpException::class,
        UnsupportedMediaTypeHttpException::class,
    ];

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request   $request
     * @param Exception $exception
     *
     * @return Response
     */
    public function render($request, Exception $exception)
    {
        $render = $this->renderContainer->getRender($exception);
        return $render($request, $exception);
    }

    /**
     * Here you can add 'exception -> HTTP code' mapping or custom exception renders.
     */
    private function registerCustomExceptions()
    {
        $this->renderContainer->registerHttpCodeMapping([

            MassAssignmentException::class => Response::HTTP_FORBIDDEN,

        ]);

        //
        // That's an example of how to create custom response with JSON API Error.
        //
        $custom404render = function (/*Request $request, ModelNotFoundException $exception*/) {

            // This render can convert JSON API Error to Response
            $jsonApiErrorRender = $this->renderContainer->getErrorsRender(Response::HTTP_NOT_FOUND);

            // Prepare Error object (e.g. take info from the exception)
            $title = 'Requested item not found';
            $error = new Error(null, null, null, null, $title);

            // Load JSON formatting options from config
            $opts = array_get($this->integration->getConfig(), C::JSON.'.'. C::JSON_OPTIONS, C::JSON_OPTIONS_DEFAULT);
            $encodeOptions = new EncoderOptions($opts);

            // Convert error (note it accepts array of errors) to HTTP response
            return $jsonApiErrorRender([$error], $encodeOptions);
        };
        $this->renderContainer->registerRender(ModelNotFoundException::class, $custom404render);
    }
}
