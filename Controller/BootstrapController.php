<?php
/**
 * @author Krzysztof Bednarczyk
 * User: devno
 * Date: 3/5/2016
 * Time: 9:50 AM
 */

namespace XVEngine\Bundle\SymfonyBridgeBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use XVEngine\Core\Handler\AbstractHandler;
use XVEngine\Core\Handler\ActionHandler;
use XVEngine\Core\Handler\MultiHandler;
use XVEngine\Core\Handler\RequestHandler;
use XVEngine\Core\Handler\ServiceHandler;

class BootstrapController extends Controller
{

    protected $csrf;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var XvResponse
     */
    protected $xvResponse = null;


    /**
     *
     */
    public function indexAction(Request $request)
    {
        $this->request = $request;

        $this->csrf = $request->cookies->get("xv_csrf", null);
        if (!$this->csrf) {
            $this->csrf = substr(md5(rand() . uniqid()), 0, 15);
        }

        $params = array(
            'bootstrapHandlers' => $this->getBootstrapHandlers(),
            'js' => $this->getJavasScripts(),
            'css' => $this->getCSS(),
            'scripts' => $this->getScripts(),
        );

        $response = $this->render('@XVEngineSymfonyBridge/Default/bootstrap.html.twig', $params);


        $response->setPrivate();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('max-age', 0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->setCookie(new Cookie('xv_csrf', $this->csrf));

        $prefetch = [];
        foreach ($params['js'] as $item) {
            $prefetch[] = "<{$item}>; rel=prefetch;";
        }
        foreach ($params['css'] as $item) {
            $prefetch[] = "<{$item}>; rel=prefetch;";
        }

        $response->headers->set("Link", $prefetch);


        return $response;
    }


    /**
     *
     * @param string $file
     * @return string
     */
    public function getStaticFileURL($file)
    {
        $webRoot = $this->get('kernel')->getRootDir() . '/../web';
        return $file . '?_=' . (filemtime($webRoot . $file));
    }

    /**
     *
     * @return string[]
     */
    public function getJavasScripts()
    {
        $result = [];

        if ($this->get("kernel")->getEnvironment() == "prod") {
            $result[] = $this->getStaticFileURL('/js/application.min.js');
            return $result;
        }

        $result[] = $this->getStaticFileURL('/js/vendor.js');
        $result[] = $this->getStaticFileURL('/js/application.js');

        return $result;
    }

    /**
     * @author Krzysztof Bednarczyk
     * @return string[]
     */
    public function getScripts()
    {
        $result = [];
        return $result;
    }


    /**
     *
     * @return string[]
     */
    public function getBootstrapHandlers()
    {
        $handlers = $this->onLoadHandlers();
        array_unshift($handlers, $this->getServicesConfigurationHandler());

        return $handlers;
    }

    /**
     *
     * @author Krzysztof Bednarczyk
     * @return string[]
     */
    public function getCSS()
    {
        $result = [];
        $result[] = $this->getStaticFileURL('/css/style.css');
        return $result;
    }


    /**
     * Configuration for services
     *
     * @author Krzysztof Bordeux Bednarczyk
     * @return MultiHandler
     */
    public function getServicesConfigurationHandler()
    {
        $multi = new MultiHandler();

        $multi->addHandler(function () {
            $service = new ServiceHandler("request");
            $service->setHeader("X-XV-Csrf", $this->csrf);
            return $service;
        }, $this);

        return $multi;
    }


    /**
     *
     * @author Krzysztof Bednarczyk
     * @return AbstractHandler[]
     */
    public function onLoadHandlers()
    {

        if ($this->xvResponse) {
            return $this->xvResponse->getHandlers();
        }

        $multi = new MultiHandler();

        $multi->addHandler(function () {
            $request = new RequestHandler($this->request->getUri());
            $request->addHeader("X-XV-First-Request", 1);
            $request->addHeader("X-XV-Source", "bootstrap");
            return $request;
        }, $this);

        $multi->addHandler(function () {
            $service = new ServiceHandler("php.phantom");
            $service->setLoaded();
            return $service;
        }, $this);

        return [$multi];
    }


}