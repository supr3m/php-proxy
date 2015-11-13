<?php namespace Proxy;

use Proxy\Adapter\AdapterInterface;
use Proxy\Exception\UnexpectedValueException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Relay\RelayBuilder;
use Zend\Diactoros\Response;
use Zend\Diactoros\Uri;

class Proxy {

    /**
     * The Request instance.
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * The adapter instance.
     *
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * Middleware filters.
     *
     * @var callable[]
     */
    protected $filters = [];

    /**
     * Construct a Proxy instance.
     *
     * @param AdapterInterface $adapter
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Prepare the proxy to forward a request instance.
     *
     * @param  RequestInterface $request
     * @return $this
     */
    public function forward(RequestInterface $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Forward the request to the target url and return the response.
     *
     * @param  string $target
     * @throws UnexpectedValueException
     * @return Response
     */
    public function to($target)
    {
        if (is_null($this->request))
        {
            throw new UnexpectedValueException('Missing request instance.');
        }

        $target = new Uri($target);

        // Overwrite target scheme and host.
        $uri = $this->request->getUri()
            ->withScheme($target->getScheme())
            ->withHost($target->getHost());

        // Check for custom port.
        if ( $port = $target->getPort() ) {
            $uri = $uri->withPort($port);
        } else {
            // Default port? no matter, add it!
            $uri = $this->setDefaultPort( $uri, $target );
        }

        // Check for subdirectory.
        if ($path = $target->getPath()) {
            $uri = $uri->withPath(rtrim($path, '/') . '/' . ltrim($uri->getPath(), '/'));
        }

        $request = $this->request->withUri($uri);

        $stack = $this->filters;

        $stack[] = function (RequestInterface $request, ResponseInterface $response, callable $next)
        {
            $response = $this->adapter->send($request);

            return $next($request, $response);
        };

        $relay = (new RelayBuilder)->newInstance($stack);

        return $relay($request, new Response);
    }

    /**
     * Add port to proxy request
     * @param  $uri
     * @param  Uri    $target
     * @return $uri
     */
    private function setDefaultPort( $uri, Uri $target ) {
        switch ( $target->getScheme() ) {
            case 'http':  $uri = $uri->withPort( 80 );  break;
            case 'https': $uri = $uri->withPort( 443 ); break;
        }

        return $uri;
    }

    /**
     * Add filter middleware.
     *
     * @param  callable $callable
     * @return $this
     */
    public function filter(callable $callable)
    {
        $this->filters[] = $callable;

        return $this;
    }

    /**
     * Get the request instance.
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

}
