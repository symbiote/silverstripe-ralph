<?php

namespace SilbinaryWolf\Ralph;

use SS_HTTPRequest;
use SS_HTTPResponse;
use DataModel;
use Session;

/**
 * NOTE: This will automatically be added to RequestProcessor when Ralph::enable()
 *       is called.
 *
 */
class RequestFilter implements \RequestFilter {
    public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) 
    {
    	$body = $response->getBody();
        $html = singleton('SilbinaryWolf\\Ralph\\Ralph')->forTemplate();
        $response->setBody($body.$html);
    }

    public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) 
    { 
        // no-op
    }
}
