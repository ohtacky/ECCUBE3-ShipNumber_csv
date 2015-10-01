<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/


namespace Plugin\ShipNumber\Entity;

class ShipNumber extends \Eccube\Entity\AbstractEntity
{

    private $ship_number;

    private $Order;

    private $order_id;

    public function getShipNumber()
    {
        return $this->ship_number;
    }

    public function setShipNumber($ship_number)
    {
        $this->ship_number = $ship_number;

        return $this;
    }

    public function getOrder()
    {
        return $this->Order;
    }

    public function setOrder(\Eccube\Entity\Order $Order)
    {
        $this->Order = $Order;

        return $this;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($order_id)
    {
        $this->order_id = $order_id;

        return $this;
    }

}
