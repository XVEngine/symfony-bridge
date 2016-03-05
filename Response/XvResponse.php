<?php


namespace XVEngine\Bundle\SymfonyBridgeBundle\Response;

use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use XVEngine\Core\Handler\AbstractHandler;


/**
 * Class XvResponse
 * @author Krzysztof Bednarczyk
 * @package Tattool\Bundle\XVBundle\Classes\Response
 */
class XvResponse extends JsonResponse
{


    /**
     * Stack of handlers/events
     * @var AbstractHandler[]
     */
    public $handlers = array();

    /**
     * @var mixed
     */
    protected $jsonLd;


    /**
     *
     * @author Krzysztof Bednarczyk
     * @param callable|AbstractHandler $handler
     * @param null|Object $obj
     * @return $this
     */
    public function addHandler($handler, $obj = null)
    {

        if ($handler instanceof AbstractHandler) {
            return $this->_addRawHandler($handler);
        }


        if ($obj) {
            $handler->bindTo($obj);
        }

        $handler = $handler($this);

        return $this->_addRawHandler($handler);
    }


    /**
     *
     * @deprecated Now u can use only addHandler
     * @author Krzysztof Bednarczyk
     * @param AbstractHandler $handler
     * @return $this
     */
    public function addRawHandler(AbstractHandler $handler)
    {
        return $this->_addRawHandler($handler);
    }


    /**
     * Temporary method function
     * @author Krzysztof Bednarczyk
     * @param AbstractHandler $handler
     * @return $this
     */
    protected function _addRawHandler(AbstractHandler $handler)
    {
        $this->handlers[] = $handler;

        return $this;
    }


    /**
     * Get all events/handlers
     * @author Krzysztof Bednarczyk
     * @return AbstractHandler[]
     */
    public function getHandlers()
    {
        return $this->handlers;
    }


    /**
     * Remove handler from stack with specific ID
     * @author Krzysztof Bednarczyk
     * @param $id
     * @return bool
     */
    public function removeHandler($id)
    {
        foreach ($this->handlers as $index => $handler) {
            if ($handler->getID() == $id) {
                unset($this->handlers[$index]);
            }
        }

        return true;
    }


    /**
     * Method prepare response - add Cache directives and etag
     * @author Krzysztof Bednarczyk
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function prepare(Request $request)
    {

        $this->setPrivate();
        $this->setMaxAge(0);
        $this->setSharedMaxAge(0);
        $this->headers->addCacheControlDirective('no-cache', true);
        $this->headers->addCacheControlDirective('max-age', 0);
        $this->headers->addCacheControlDirective('must-revalidate', true);
        $this->headers->addCacheControlDirective('no-store', true);
        $this->headers->set("X-Powered-By", "XV-Server v1.0");

        $this->setData(array(
            "handlers" => array_values($this->handlers)
        ));

        $this->setEtag(uniqid());
        $this->setMaxAge(0);
        $this->setLastModified(new DateTime());
        return parent::prepare($request);

    }


    /**
     * @author Krzysztof Bednarczyk
     * @param mixed $thing
     */
    public function setJsonLd($thing)
    {
        $this->jsonLd = $thing;

        return $this;
    }


    /**
     * @author Krzysztof Bednarczyk
     * @return mixed
     */
    public function getJsonLd()
    {
        return $this->jsonLd;
    }


    /**
     *
     * Method join handlers/events form other AjaxResponse
     * @author Krzysztof Bednarczyk
     * @param $response
     * @return $this
     */
    public function joinResponse($response)
    {
        if (!($response instanceof XvResponse)) {
            throw new \InvalidArgumentException("Invalid argument in joinResponse. First argument should be instance of AjaxResponse.");
        }


        foreach ($response->getHandlers() as $handler) {

            if (!$handler instanceof AbstractHandler) {
                continue;
            }
            $this->_addRawHandler($handler);
        }
        return $this;
    }
}
