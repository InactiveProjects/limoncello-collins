<?php namespace App\Http\Controllers\JsonApi;

use \Generator;
use \Illuminate\Http\Response;
use \App\Http\Controllers\Controller;
use \Neomerx\Limoncello\Http\JsonApiTrait;
use \Neomerx\Limoncello\Config\Config as C;
use \Illuminate\Database\Eloquent\Collection;
use \Neomerx\Limoncello\Contracts\IntegrationInterface;

abstract class JsonApiController extends Controller
{
    use JsonApiTrait {
        getConfig as traitGetConfig;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->integration = app(IntegrationInterface::class);
        $this->initJsonApiSupport();
    }

    /*
     * If you use Eloquent the following helper method will assist you to
     * encode database collections and keep your code clean.
     */

    /**
     * @param object|array                                                       $data
     * @param int                                                                $statusCode
     * @param array<string,\Neomerx\JsonApi\Contracts\Schema\LinkInterface>|null $links
     * @param mixed                                                              $meta
     *
     * @return Response
     */
    public function getResponse(
        $data,
        $statusCode = Response::HTTP_OK,
        $links = null,
        $meta = null
    ) {
        $data = ($data instanceof Collection ? $this->iterateCollection($data) : $data);
        return $this->getContentResponse($data, $statusCode, $links, $meta);
    }

    /**
     * Add URL prefix to document links.
     */
    protected function getConfig()
    {
        $config = $this->traitGetConfig();
        $config[C::JSON][C::JSON_URL_PREFIX] = \Request::getSchemeAndHttpHost();

        return $config;
   }
   
   /**
    * @param Collection $data
    * 
    * @return Generator
    */
   private function iterateCollection(Collection $data)
   {
       foreach ($data->all() as $item) {
           yield $item;
       }
   }
}
