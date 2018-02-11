<?php

namespace SilbinaryWolf\Ralph;

/**
 * NOTE: This will automatically be added to RequestProcessor when Ralph::enable()
 *       is called.
 */
class RequestFilter implements \RequestFilter {
    /**
     * @param \SS_HTTPRequest $request
     * @param \SS_HTTPResponse $response
     * @param \DataModel $model
     */
    public function postRequest(\SS_HTTPRequest $request, \SS_HTTPResponse $response, \DataModel $model) {
    	$body = $response->getBody();
        $html = singleton('SilbinaryWolf\\Ralph\\Ralph')->forTemplate();
        $body = $body.$html;
        $response->setBody($body);
    }

    public function preRequest(\SS_HTTPRequest $request, \Session $session, \DataModel $model) { 
    }
}
