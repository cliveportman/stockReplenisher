<?php
namespace Craft;

class StockReplenisherService extends BaseApplicationComponent
{

	// UPDATES A PRODUCT'S STOCK
	// MOST OF THIS IS COPIED FROM Commerce_VariantsService.php
	// IT UPDATES THE DATABASE DIRECTLY RATHER THAN USES AN UPDATE STOCK FUNCTION
	// NOTE THE QTY IS ADJUSTED NOT REPLACED (SO USE -n TO INCREASE STOCK)
	public function replenishStock(Commerce_VariantModel $purchasable, $quantityToAdd = 0) {

        $clearCacheOfElementIds = [];
        if ($purchasable instanceof Commerce_VariantModel && !$purchasable->unlimitedStock)
        {

            // UPDATE THE QUANTITY IN THE DATABASE (-1 WILL ADD 1 STOCK)
            craft()->db->createCommand()->update('commerce_variants',
                ['stock' => new \CDbExpression('stock - :qty', [':qty' => -$quantityToAdd])],
                'id = :variantId',
                [':variantId' => $purchasable->id]);

            // UPDATE
            $purchasable->stock = craft()->db->createCommand()
                ->select('stock')
                ->from('commerce_variants')
                ->where('id = :variantId', [':variantId' => $purchasable->id])
                ->queryScalar();

            // CLEAR CACHE (NOT SURE WHETHER THIS IS NEEDED)
            $clearCacheOfElementIds[] = $purchasable->id;
            $clearCacheOfElementIds[] = $purchasable->product->id;
        }

        $clearCacheOfElementIds = array_unique($clearCacheOfElementIds);
        craft()->templateCache->deleteCachesByElementId($clearCacheOfElementIds);

		return $purchasable->stock + $quantityToAdd;
	}











	// REPLENISHES STOCK OF ALL LINE ITEMS WITHIN AN ORDER
	public function replenishWholeOrder($orderId) 
	{

		// GET THE ORDER USING THE ORDER ID
        $order = craft()->commerce_orders->getOrderById($orderId);

        // CHECK THERE IS SUCH AN ORDER
        if (!$order) {
	        $message = 'Could not find order #' . $order->id;
            StockReplenisherPlugin::log($message, LogLevel::Error);
            throw new Exception($message);
        }

        // GET THE ORDER'S LINE ITEMS
        $lineItems = $order->lineItems;

        // AS WE'RE DEALING WITH THE WHOLE ORDER HERE, LOOP THROUGH ALL LINE ITEMS
        foreach ($lineItems as &$lineItem) {

        	$quantityToAdd = $lineItem->qty;

    		// SET THE LINE ITEM QUANTITY TO ZERO
    		$cancelledLineItemId = craft()->stockReplenisher->updateLineItemQty($order, $lineItem, 0);

 			/************************
 			*************************
 			REMOVE THIS LINE IF NOT USING THE REGISTRATION PLUGIN
 			*************************
 			************************/
        	// TEST FOR A PURCHASABLE ID AND DISABLE THE REGISTER
        	if ($lineItem->purchasableId) $cancelledRegister = craft()->registration->disableRegister($order, $lineItem);

        	// REPLENISH THE STOCK
        	if ($lineItem->purchasableId) $updatedStock = craft()->stockReplenisher->replenishStock($lineItem->purchasable, $quantityToAdd);
        }


        // NO NEED TO CHANGE THE ORDER STATUS HERE AS THIS FUNCTION
        // HAS BEEN CALLED IN RESPONSE TO THAT ANYWAY
        return TRUE;

	}











	// REPLENISHES STOCK OF ALL LINE ITEMS WITHIN AN ORDER
	public function replenishLineItem($orderId, $lineItemId, $newQuantity = 0) 
	{

		// GET THE ORDER USING THE ORDER ID
        $order = craft()->commerce_orders->getOrderById($orderId);

        // CHECK THERE IS SUCH AN ORDER
        if (!$order) {
	        $message = 'Could not find order #' . $order->id;
            StockReplenisherPlugin::log($message, LogLevel::Error);
            throw new Exception($message);
        }

        // GET THE ORDER'S LINE ITEMS
        $lineItems = $order->lineItems;

        // LOOP THROUGH THE LINE ITEMS TO FIND THE LINE ITEM WE'RE AFTER
        foreach ($lineItems as &$lineItem) {

        	// COMPARE WITH THE LINE ITEM ID TO CANCEL AND THEN CANCEL
        	if ($lineItem->id == $lineItemId && $lineItem->purchasableId) {

        		$quantityToReplenish = $lineItem->qty - $newQuantity;

                // REPLENISH THE STOCK
                $updatedStock = craft()->stockReplenisher->replenishStock($lineItem->purchasable, $quantityToReplenish);

        		// SET THE LINE ITEM QUANTITY TO ZERO
        		$newLineItemQty = craft()->stockReplenisher->updateLineItemQty($order, $lineItem, $newQuantity);
                $message = "Line item #$lineItem->id could not be updated";
                StockReplenisherPlugin::log($message, LogLevel::Error);
                if (!$newLineItemQty) return FALSE;

                /************************
                *************************
                REMOVE THIS LINE IF NOT USING THE REGISTRATION PLUGIN
                *************************
                ************************/
        		// TEST FOR A PURCHASABLE ID AND DISABLE THE REGISTER
        		if ($lineItem->purchasableId && $newQuantity == 0) {
        			$cancelledRegister = craft()->registration->disableRegister($order, $lineItem);
        		} elseif ($lineItem->purchasableId) {
                    $updatedRegister = craft()->registration->removeRowsFromRegister($order, $lineItem, $quantityToReplenish);
                }
	        }
        }
        

        // UPDATE THE ORDER'S STATUS TO 'UPDATED' WHICH HAS A STATUS ID OF 3
        $message = "";
        $order = craft()->commerce_orders->getOrderById($order->id);
        $orderStatus = craft()->commerce_orderStatuses->getOrderStatusById(5);

        $order->orderStatusId = $orderStatus->id;
        $order->message = $message;

        if (craft()->commerce_orders->saveOrder($order)) return TRUE;

		return TRUE;

	}











	// UPDATES A LINE ITEM AND SETS QUANTITY TO ZERO
	public function updateLineItemQty(Commerce_OrderModel $order, Commerce_LineItemModel $lineItem, $newQuantity = 0, $note = 'Items cancelled') {

		// LOOP THROUGH THE ORDER'S LINE ITEMS LOOKING FOR THE ONE WE'RE AFTER
        foreach ($order->getLineItems() as $item) {
            if ($item->id == $lineItem->id) {
                $lineItem = $item;
                break;
            }
        }

        // IF THE LINE ITEM HAS A VALID PURCHASABLE IN IT
        if ($lineItem->purchasableId) {

        	// SET THE QUANTITY AND NOTE
	        $lineItem->qty = $newQuantity;
	        $lineItem->note = $note;

	        // SAVE THE LINE ITEM
	        if (craft()->commerce_lineItems->saveLineItem($lineItem)) {
	        	$message = 'Line item #' . $lineItem->id . ' updated to ' . $lineItem->qty . ' items within order #' . $order->id;
                StockReplenisherPlugin::log($message, LogLevel::Info);
	        } else {
	        	$message = 'Could not update line item #' . $lineItem->id . ' to ' . $lineItem->qty . ' items within order #' . $order->id;
                StockReplenisherPlugin::log($message, LogLevel::Error);
	        	return FALSE;
	        }
	    }

		return $lineItem->id;

	}

}