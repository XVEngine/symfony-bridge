<?php

namespace XVEngine\Bundle\SymfonyBridgeBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use XVEngine\Core\Handler\HistoryHandler;
use XVEngine\Bundle\SymfonyBridgeBundle\Controller\BootstrapController;
use XVEngine\Bundle\SymfonyBridgeBundle\Controller\Controller;
use XVEngine\Bundle\SymfonyBridgeBundle\Exception\CsrfHttpException;
use XVEngine\Bundle\SymfonyBridgeBundle\Response\XvResponse;

/**
 * Class KernelListener
 * @author Krzysztof Bednarczyk
 * @package XVEngine\Bundle\SymfonyBridgeBundle\EventListener
 */
class KernelListener
{
    use ContainerAwareTrait;

    /**
     * @var string
     */
    protected $uniqueKey;

    /**
     * KernelControllerListener constructor.
     * @author Krzysztof Bednarczyk
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->uniqueKey = uniqid();
        $this->setContainer($container);
    }


    /**
     * @author Krzysztof Bednarczyk
     * @param Request $request
     * @return bool
     */
    public function isBot(Request $request)
    {
        if (preg_match('/facebookexternalhit|twitterbot/i', $request->headers->get("User-Agent", ""))) {
            return true;
        }
        if ($request->get("spider", "") === "yes") {
            return true;
        }

        if (preg_match('/phantom/i', $request->headers->get("User-Agent", ""))) {
            return true;
        }

        return false;
    }


    /**
     * @author Krzysztof Bednarczyk
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $controller = $event->getController()[0];
        if (!($controller instanceof Controller)) { //do nothing when is other controller;
            return;
        }

        $request = $event->getRequest();


        /**
         * Validate CSRF token
         */
        $isXVRequest = (int)$request->headers->get('X-XV-Request', 0);
        if ($isXVRequest) {
            if (!$this->checkCsrfToken($request)) {
                throw new CsrfHttpException("Invalid CSRF token. Please turn on cookies and reload page.");
            }
            $request->headers->set("X-XV-{$this->uniqueKey}", 1);
            return;
        }

        $controller = $event->getController()[0];
        if (!($controller instanceof Controller)) {
            return;
        }

        /**
         * Prevent posting data directly form other post
         */
        if ($request->getMethod() !== Request::METHOD_GET) {
            $event->setController(function () use ($request) {
                return new RedirectResponse($request->getRequestUri());
            });
            return;
        }

        $request->headers->set("X-XV-First-Request", 1);
        $request->headers->set("X-XV-Source", "bootstrap");
    }


    /**
     *
     * @author Krzysztof Bednarczyk
     * @param $request
     * @return bool
     */
    public function checkCsrfToken($request)
    {
        $header = $request->headers->get('X-XV-Csrf', NULL);
        $cookie = $request->cookies->get('xv_csrf', NULL);
        return ($cookie != NULL && ($cookie === $header));
    }


    /**
     *
     * @author Krzysztof Bednarczyk
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!($response instanceof XvResponse)) {
            return;
        }


        /**
         * When is a xvRequest and is back request, remove history handler
         */
        if ($request->headers->get("X-XV-History-Request", 0)) {
            $handlers = $response->getHandlers();
            foreach ($handlers as $item) {
                if (!($item instanceof HistoryHandler) || $item->isProtected()) {
                    continue;
                }
                !($id = $item->getID()) && ($item->setID(uniqid()));
                $response->removeHandler($item->getID());
            }
            return;
        }

        if ($request->headers->get("X-XV-{$this->uniqueKey}", 0)) {
            return;
        }


        $controller = new BootstrapController();
        $controller->setContainer($this->container);
        $controller->setAjaxResponse($response);
        $event->setResponse($controller->indexAction($event->getRequest()));
    }


    /**
     *
     * @author Krzysztof Bednarczyk
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $exception = $event->getException();
        $isXVRequest = (int)$event->getRequest()->headers->get('X-XV-Request', 0);

        if (
            $exception instanceof AccessDeniedHttpException ||
            $exception instanceof AccessDeniedHttpException ||
            $exception instanceof AuthenticationException
        ) {
            $path = parse_url($event->getRequest()->getRequestUri(), PHP_URL_PATH);
            $event->setResponse(new RedirectResponse("/login/?r={$path}"));
            $event->stopPropagation();
            return;
        }

        if ($exception instanceof NotFoundHttpException) {
            $event->setResponse(new RedirectResponse("/page/404/" . ($isXVRequest ? "?dialog=true" : "")));
            $event->stopPropagation();
            return;
        }

        if ($exception instanceof CsrfHttpException) {
            $event->setResponse(new RedirectResponse("/page/csrf/"));
            $event->stopPropagation();
            return;
        }


    }

}
