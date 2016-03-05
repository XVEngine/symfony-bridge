<?php

namespace XVweb\Bundle\SymfonyBridgeBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as ControllerAbstract;
use XVEngine\Core\Handler\ServiceHandler;
use XVweb\Bundle\SymfonyBridgeBundle\Response\XvResponse;


/**
 * Class Controller
 * @author Krzysztof Bednarczyk
 * @package XVweb\Bundle\SymfonyBridgeBundle\Controller
 */
class Controller extends ControllerAbstract
{

    /**
     *
     * Stack of handlers/events
     *
     * @var XvResponse
     */
    public $response;


    /**
     *
     */
    public function __construct()
    {
        $this->response = new XvResponse();
    }


    /**
     * Shorthand handler to set page name in request service
     * @author Krzysztof Bednarczyk
     * @param $pageName
     * @param array $params
     * @return $this
     */
    public function setPageName($pageName, $params = [])
    {
        $this->response->addHandler(function () use ($pageName, $params) {
            $service = new ServiceHandler("request");
            $service->clearHeader("^X-XV-Page");
            $service->setHeader("X-XV-Page", $pageName);

            foreach ($params as $key => $param) {
                $service->setHeader("X-XV-Page-{$key}", $param);
            }

            return $service;
        }, $this);

        return $this;
    }

}
