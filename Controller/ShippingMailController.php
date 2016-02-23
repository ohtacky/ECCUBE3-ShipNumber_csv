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

namespace Plugin\ShipNumberCsv\Controller;

use Eccube\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Eccube\Exception\CsvImportException;
use Eccube\Service\CsvImportService;
use Eccube\Util\Str;
use Symfony\Component\Filesystem\Filesystem;
use Eccube\Entity\MailHistory;

class ShippingMailController
{

    public function __construct()
    {
    }

    public function mailAll(Application $app, Request $request)
    {

        $builder = $app['form.factory']->createBuilder('mail');

        $form = $builder->getForm();

        $ids = '';

        if ('POST' === $request->getMethod()) {

            $form->handleRequest($request);

            $mode = $request->get('mode');

            $ids = $request->get('ids');

            // テンプレート変更の場合は. バリデーション前に内容差し替え.
            if ($mode == 'change') {
                if ($form->get('template')->isValid()) {
                    /** @var $data \Eccube\Entity\MailTemplate */
                    $MailTemplate = $form->get('template')->getData();
                    $form = $builder->getForm();
                    $form->get('template')->setData($MailTemplate);
                    $form->get('subject')->setData($MailTemplate->getSubject());
                    $form->get('header')->setData($MailTemplate->getHeader());
                    $form->get('footer')->setData($MailTemplate->getFooter());
                }
            } else if ($form->isValid()) {
                switch ($mode) {
                    case 'confirm':
                        // フォームをFreezeして再生成.

                        $builder->setAttribute('freeze', true);
                        $builder->setAttribute('freeze_display_text', true);

                        $data = $form->getData();

                        $tmp = explode(',', $ids);

                        $ShippingNumberContent = $app['eccube.plugin.repository.ship_number']->find($tmp[0]);

                        $new_header = $data['header'];

                        if (isset($ShippingNumberContent)) {
                            $Shippingnumber = $ShippingNumberContent -> getShipNumber();
                            $new_header = str_replace("伝票番号：", "伝票番号：".$Shippingnumber, $new_header);
                        }


                        $Order = $app['eccube.repository.order']->find($tmp[0]);

                        if (is_null($Order)) {
                            throw new NotFoundHttpException('order not found.');
                        }

                        $body = $this->createBody($app, $new_header, $data['footer'], $Order);

                        $MailTemplate = $form->get('template')->getData();

                        $form = $builder->getForm();
                        $form->setData($data);
                        $form->get('template')->setData($MailTemplate);

                        return $app->renderView('ShipNumberCsv/Resource/template/Admin/shipping_mail_all_confirm.twig', array(
                            'form' => $form->createView(),
                            'body' => $body,
                            'ids' => $ids,
                        ));
                        break;

                    case 'complete':

                        $data = $form->getData();

                        $ids = explode(',', $ids);

                        //ヘッダーの初期値
                        $data_header = $data['header'];

                        foreach ($ids as $value) {

                          $ShippingNumberContent = $app['eccube.plugin.repository.ship_number']->find($value);

                          if (isset($ShippingNumberContent)) {
                              $Shippingnumber = $ShippingNumberContent -> getShipNumber();
                              $data['header'] = str_replace("伝票番号：", "伝票番号：".$Shippingnumber, $data['header']);
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

                            //ヘッダーを初期値に戻す
                            $data['header'] = $data_header;

                        }

                        $app['orm.em']->flush($MailHistory);


                        return $app->redirect($app->url('admin_order_mail_complete'));
                        break;

                    default:
                        break;

                }
            }
        } else {
            foreach ($_GET as $key => $value) {
                $ids = str_replace('ids', '', $key) . ',' . $ids;
            }
            $ids = substr($ids, 0, -1);
        }

        return $app->renderView('ShipNumberCsv/Resource/template/Admin/shipping_mail_all.twig', array(
            'form' => $form->createView(),
            'ids' => $ids,
        ));
    }


    private function createBody($app, $header, $footer, $Order)
    {
        return $app->renderView('Mail/order.twig', array(
            'header' => $header,
            'footer' => $footer,
            'Order' => $Order,
        ));
    }




}
