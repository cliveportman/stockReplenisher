<?php

namespace Craft;
use Commerce\Helpers\CommerceDbHelper;
use Commerce\Interfaces\Purchasable;

class StockReplenisherVariable
{

    public function replenishWholeOrder($orderId = '')
    {        
        return craft()->stockReplenisher->replenishWholeOrder($orderId);
    }

    public function replenishLineItem($orderId = '', $lineItemId = '', $newQuantity = 0)
    {        
        return craft()->stockReplenisher->replenishLineItem($orderId, $lineItemId, $newQuantity);
    }

}