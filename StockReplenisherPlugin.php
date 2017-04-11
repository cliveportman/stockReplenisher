<?php
namespace Craft;

class StockReplenisherPlugin extends BasePlugin
{
    function getName()
    {
         return Craft::t('Stock Replenisher');
    }

    public function getDescription()
    {
        return 'Replenishes stock within Craft Commerce';
    }

    function getVersion()
    {
        return '0.1';
    }

    function getDeveloper()
    {
        return 'Clive Portman';
    }

    function getDeveloperUrl()
    {
        return 'http://cliveportman.co.uk';
    }
    
    
    public function init()
    {
        parent::init();

        craft()->on('commerce_orderHistories.onStatusChange', function (Event $event) {
            $order = $event->params['order'];
            if ($order->orderStatus->id == 3) {
                craft()->stockReplenisher->replenishWholeOrder($order->id);
            }
        });
        
    }
    

}