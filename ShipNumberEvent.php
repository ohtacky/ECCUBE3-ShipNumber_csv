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

//========必ずやる========


//========プラスアルファ========
//★★新規登録の際にも配送伝票番号が登録できるように

//========有料プラグイン========
//csvでの一括インポート
//★★配送会社の登録
//★★各配送会社毎の発送メールの文言


namespace Plugin\ShipNumber;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Eccube\Entity\MailHistory;


class ShipNumberEvent
{
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function mailAllShipNumber()
    {
      $app = $this->app;
      $builder = $app['form.factory']->createBuilder('mail');
      $form = $builder->getForm();
      $ids = '';

      if ('POST' === $app['request']->getMethod()) {
          $form->handleRequest($app['request']);
          $mode = $app['request']->get('mode');
          $ids = $app['request']->get('ids');


          switch ($mode) {
            case 'complete':

                $data = $form->getData();

                $ids = explode(',', $ids);

                $ShippingNumberFirst = $app['eccube.plugin.repository.ship_number']->find($ids[0]);

                if (isset($ShippingNumberFirst)) {
                    $GetShipNumberFirst = $ShippingNumberFirst -> getShipNumber();
                }

                foreach ($ids as $value) {

                    $ShippingNumberContent = $app['eccube.plugin.repository.ship_number']->find($value);

                    if (isset($ShippingNumberContent)) {
                        $Shippingnumber = $ShippingNumberContent -> getShipNumber();
                        $data['header'] = str_replace("伝票番号：".$GetShipNumberFirst, "伝票番号：".$Shippingnumber, $data['header']);
                    } else if (isset($Shippingnumber)) {
                        $data['header'] = str_replace("伝票番号：".$Shippingnumber, "伝票番号：", $data['header']);
                    } else {
                        $data['header'] = str_replace("伝票番号：".$GetShipNumberFirst, "伝票番号：", $data['header']);
                    }

                    $Order = $app['eccube.repository.order']->find($value);

                    $body = $this->createBody($app, $data['header'], $data['footer'], $Order);

                    // メール送信
                    $app['eccube.service.mail']->sendAdminOrderMail($Order, $data);

                    // 送信履歴を保存.
                    $MailTemplate = $form->get('template')->getData();
                    $MailHistory = new MailHistory();
                    $MailHistory
                        ->setSubject($data['subject'])
                        ->setMailBody($body)
                        ->setMailTemplate($MailTemplate)
                        ->setSendDate(new \DateTime())
                        ->setOrder($Order);
                    $app['orm.em']->persist($MailHistory);
                }

                $app['orm.em']->flush($MailHistory);

                exit ($app->redirect($app->url('admin_order_mail_complete')));

                break;

            default:
                break;
          }
      }
    }

    private function createBody($app, $header, $footer, $Order)
    {
        return $app->renderView('Mail/order.twig', array(
            'header' => $header,
            'footer' => $footer,
            'Order' => $Order,
        ));
    }


    public function mailAllShipNumberComfirm(FilterResponseEvent $event)
    {

      $app = $this->app;
      $request = $event->getRequest();

      if ('POST' === $app['request']->getMethod()) {

          $mode = $request->get('mode');
          $ids = $request->get('ids');

          switch ($mode) {
            case 'confirm':

              $tmp = explode(',', $ids);

              $ShippingNumberContent = $app['eccube.plugin.repository.ship_number']->find($tmp[0]);

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
              $OrderContent = new \Plugin\ShipNumber\Entity\ShipNumber();
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
              'ShipNumber/Resource/template/Admin/ship_number.twig',
              array('form' => $form->createView())
          );

          $oldElement = $crawler
          ->filter('.accordion')
          ->last();

          if ($oldElement->count() > 0) {
              $oldHtml = $oldElement->html();
              $newHtml = $oldHtml.$twig;

              $html = $crawler->html();
              $html = str_replace($oldHtml, $newHtml, $html);

              $response->setContent($html);
              $event->setResponse($response);
          }

    }

}
