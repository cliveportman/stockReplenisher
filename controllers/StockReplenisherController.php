<?php
namespace Craft;

class StockReplenisherController extends BaseController
{

    protected $allowAnonymous = true;

    public function actionAdjustLineItemQuantity()
    {

        $this->requirePostRequest();
        
        $vars = [];
        $orderId = craft()->request->getParam('orderId');
        $lineItemId = craft()->request->getParam('lineItemId');
        $newQuantity = craft()->request->getParam('newQuantity');

        if( craft()->stockReplenisher->replenishLineItem($orderId, $lineItemId, $newQuantity) ) {
            $this->redirectToPostedUrl();
        } else {
            return FALSE;
        }

    }

}