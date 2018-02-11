<?php

namespace SilbinaryWolf\Ralph;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * NOTE: This will automatically be added to RequestProcessor when Ralph::enable()
 *       is called.
 */
class RequestFilter implements \SilverStripe\Control\RequestFilter {
    public function preRequest(HTTPRequest $request) 
    {
        // no-op
    }

    public function postRequest(HTTPRequest $request, HTTPResponse $response) 
    {
        $body = $response->getBody();
        $html = singleton('SilbinaryWolf\\Ralph\\Ralph')->forTemplate();
        $response->setBody($body.$html);
    }
}
