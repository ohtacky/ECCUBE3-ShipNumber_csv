<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2015 Takashi Otaki All Rights Reserved.
*
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\ShipNumberCsv;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Eccube\Entity\MailHistory;


class ShipNumberCsvEvent
{
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }



    public function mailShipNumber(FilterResponseEvent $event)
    {

      $app = $this->app;
      $request = $event->getRequest();

      if ('POST' === $app['request']->getMethod()) {

          $mode = $request->get('mode');

          switch ($mode) {
            case 'confirm':

              $order_id = $request->attributes->get('id');
              $ShippingNumberContent = $app['eccube.plugin.repository.ship_number']->find($order_id);

              if (isset($ShippingNumberContent)) {
                  $Shippingnumber = $ShippingNumberContent -> getShipNumber();
                  $response = $event->getResponse();
                  $addShippingNumber = str_replace("伝票番号：", "伝票番号：".$Shippingnumber, $response);
                  $response->setContent($addShippingNumber);
                  $event->setResponse($response);
              }
              break;
              default:
              break;
          }
      }
    }



    public function registerShipNumber(FilterResponseEvent $event)
    {
      $app = $this->app;

      if ('POST' === $app['request']->getMethod()) {

          $form = $app['form.factory']
              ->createBuilder('order')
              ->getForm();

          $form->handleRequest($app['request']);

          $ship_number = $form->get('content')->getData();

          $order_id = $app['request']->attributes->get('id');

          $OrderContent = $app['eccube.plugin.repository.ship_number']->find($order_id);

          if (is_null($OrderContent)) {
              $OrderContent = new \Plugin\ShipNumberCsv\Entity\ShipNumberCsv();
          }

          $Order = $app['eccube.repository.order']->find($order_id);

          $OrderContent
              ->setShipNumber($ship_number)
              ->setOrder($Order)
              ->setOrderId($Order->getId());

          $app['orm.em']->persist($OrderContent);
          $app['orm.em']->flush();

      }
    }



    public function onRenderAdminOrderEditBefore(FilterResponseEvent $event)
    {
          $app = $this->app;
          $request = $event->getRequest();
          $response = $event->getResponse();

          $html = $response->getContent();
          $crawler = new Crawler($html);

          $order_id = $app['request']->attributes->get('id');
          $OrderContent = $app['eccube.plugin.repository.ship_number']->find($order_id);

          $form = $app['form.factory']
              ->createBuilder('order')
              ->getForm();

          if (isset($OrderContent)) {
              $form->get('content')->setData($OrderContent->getShipNumber());
          }

          $form->handleRequest($request);

          $twig = $app->renderView(
              'ShipNumberCsv/Resource/template/Admin/ship_number_csv.twig',
              array('form' => $form->createView())
          );

          $oldElement = $crawler
          ->filter('.box')
          ->last();

          if ($oldElement->count() > 0) {
              $oldHtml = $oldElement->html();
              $newHtml = $oldHtml.$twig;

              $html = $crawler->html();
              $html = str_replace($oldHtml, $newHtml, $html);

              $html = html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');

              $first = array("<head>", "</body>");
              $last = array("<html lang=\"ja\"><head>", "</body></html>");
              $html = str_replace($first, $last, $html);

              $response->setContent($html);
              $event->setResponse($response);
          }

    }



    //発送メールプルダウン追加
    public function shippingMailAll(FilterResponseEvent $event)
    {
          $app = $this->app;
          $request = $event->getRequest();
          $response = $event->getResponse();

          $html = $response->getContent();
          $crawler = new Crawler($html);

          $twig = $app->renderView('ShipNumberCsv/Resource/template/Admin/shipping_mail_all_list.twig');

          $oldElement = $crawler
          ->filter('.dropdown-menu > li')
          ->eq(13);

          $html = $crawler->html();

          $oldHtml = '';
          $newHtml = '';
          if (count($oldElement) > 0) {
            $oldHtml = $oldElement->html();
            $newHtml = $oldHtml.$twig;
          }

          $html = str_replace($oldHtml, $newHtml, $html);

          $html = html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');

          $response->setContent($html);
          $event->setResponse($response);

    }


}
